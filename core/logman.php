<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [logman.php].
 *     Copyright (C) 2015  Alexei Muzarov
 * 
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 * 
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 * 
 *     You should have received a copy of the GNU General Public License along
 *     with this program; if not, write to the Free Software Foundation, Inc.,
 *     51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 *     e-mail: azexmail@gmail.com
 *     e-mail: azexmail@mail.ru
 */

namespace core;

require_once 'swconst.php';
require_once 'policy.php';

interface intLogMan {
    function __construct(\mysqli $dbLink, Policy $policyObject);
    public function errId();
    public function errExp();
    public function applyPolicy($flag);
    public function installEvents(\DOMDocument $events, $validate = TRUE);
    public function delEvents($plugin);
    public function newRecord($plugin, $event, $insertion = '');
    public function clearLog();
    public function sumLogAllPlugins($rpp = 20);
    public function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function sumLogByPlugin($plugin, $rpp = 20);
    public function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
}

class LogMan implements intLogMan {
    private $errid = 0; // error's id
    private $errexp = ''; // error's explanation
    private $dbLink; // database link
    private $policyObject; // policy objectobject
    private $usePolicy = TRUE; // flag of the policy application
    
    public function __construct(\mysqli $dbLink, Policy $policyObject) {
        $this->dbLink = $dbLink;
        $this->policyObject = $policyObject;
    }
    
    private function setError($id, $exp) {
        $this->errid = $id;
        $this->errexp = $exp;
    }
    
    private function zeroizeError() {
        $this->errid = 0;        $this->errexp = '';
    }
    
    public function errId() {
        return $this->errid;
    }
    
    public function errExp() {
        return $this->errexp;
    }
    
    public function applyPolicy($flag) {
        if ($flag) {
            $this->usePolicy = TRUE;
        }
        else {
            $this->usePolicy = FALSE;
        }
    }

        public function installEvents(\DOMDocument $events, $validate = TRUE) {
        $this->zeroizeError();
        if ($validate && !@$events->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/logman-events-v01.rng')) {
            $this->setError(ERROR_INCORRECT_DATA, 'installEvents: incorrect structure of the events');
            return FALSE;
        }
        $pluginName = $events->getElementsByTagName('log')->item(0)->getAttribute("plugin");
        // check whether plugin is installed
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$pluginName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "installEvents: cannot check whether the plugin is installed -> ".$this->dbLink->errno);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "installEvents: required plugin [$pluginName] is not installed");
            return FALSE;
        }
        // plugin identifier
        list($pluginId) = $qPlugin->fetch_row();
        // get list of available languages
        $qAvaiLang = $this->dbLink->query("SELECT `code`, `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installEvents: cannot get list of available languages: '.$this->dbLink->error);
            return FALSE;
        }
        // avaiable languages
        $avLangIds = array();
        $avLangCodes = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avLangIds[$row[0]] = $row[1];
            $avLangCodes[] = $row[0];
        }
        // parse DOM tree
        $incomingEvents = array();
        $eventNodes = $events->getElementsByTagName('event');
        foreach ($eventNodes as $eventNode) {
            $keyword = $eventNode->getAttribute('keyword');
            $incomingEvents[$keyword] = array();
            $descNodes = $eventNode->getElementsByTagName('desc');
            foreach ($descNodes as $descNode){
                $code = $descNode->getAttribute('code');
                if (isset($avLangIds[$code])) {
                    $incomingEvents[$keyword][$code] = $descNode->nodeValue;
                }
            }
        }
        // get installed events of the plugin
        $qEvents = $this->dbLink->query("SELECT `keyword`, `id` "
                . "FROM `".MECCANO_TPREF."_core_logman_events` "
                . "WHERE `plugid`=$pluginId");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installEvents: unable to get installed events -> '.$this->dbLink->error);
            return FALSE;
        }
        $installedEvents = array();
        while ($row = $qEvents->fetch_row()) {
            $installedEvents[$row[0]] = $row[1];
        }
        // delete outdated events
        $outdatedEvents = array_diff(array_keys($installedEvents), array_keys($incomingEvents));
        foreach ($outdatedEvents as $keyword) {
            $eventId = $installedEvents[$keyword];
            $sql = array(
                "DELETE `r` FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `e`.`id`=`r`.`eventid` "
                . "WHERE `e`.`id`=$eventId ;",
                "DELETE `d` FROM `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `e`.`id`=`d`.`eventid`"
                . "WHERE `e`.`id`=$eventId ;",
                "DELETE  FROM `".MECCANO_TPREF."_core_logman_events` "
                . "WHERE `id`=$eventId ;",
            );
            foreach ($sql as $dQuery) {
                $this->dbLink->query($dQuery);
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, "installEvents: unable to delete outdated event -> ".$this->dbLink->error);
                    return FALSE;
                }
            }
        }
        // install/update events
        foreach ($incomingEvents as $keyword => $descriptions) {
            $missingCodes = array_diff($avLangCodes, array_keys($descriptions));
            if ($missingCodes) {
                foreach ($missingCodes as $code) {
                    $descriptions[$code] = "$keyword: [%d]";
                }
            }
            // update event
            if (isset($installedEvents[$keyword])) {
                $eventId = $installedEvents[$keyword];
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $updateDesc = $this->dbLink->real_escape_string($desc);
                    $eventId = $installedEvents[$keyword];
                    $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_logman_descriptions` "
                            . "SET `description`='$updateDesc' "
                            . "WHERE `eventid`=$eventId "
                            . "AND `codeid`=$codeId ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installEvents: unable to update event description -> '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
            // install event
            else {
                $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_events` "
                        . "(`keyword`, `plugid`) "
                        . "VALUES ('$keyword', $pluginId) ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'installEvents: unable add new event -> '.$this->dbLink->error);
                    return FALSE;
                }
                $eventId = $this->dbLink->insert_id;
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $newDesc = $this->dbLink->real_escape_string($desc);
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_descriptions` "
                            . "(`description`, `eventid`, `codeid`) "
                            . "VALUES ('$newDesc', $eventId, $codeId) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installEvents: unable to add new event description -> '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public function delEvents($plugin) {
        $this->zeroizeError();
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, "delEvents: incorrect plugin name");
            return FALSE;
        }
        if ($plugin == "core") {
            $this->setError(ERROR_SYSTEM_INTERVENTION, "delEvents: unable to delete core events");
            return FALSE;
        }
        $sql = array(
            "DELETE `r` "
            . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
            . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
            . "ON `e`.`id`=`r`.`eventid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`e`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;",
            "DELETE `d` "
            . "FROM `".MECCANO_TPREF."_core_logman_descriptions` `d` "
            . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
            . "ON `e`.`id`=`d`.`eventid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`e`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;",
            "DELETE `e` "
            . "FROM `".MECCANO_TPREF."_core_logman_events` `e` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`e`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;"
        );
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delEvents: unable remove events -> ".$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }

        public function newRecord($plugin, $keyword, $insertion = '') {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregPlugin($keyword) || !is_string($insertion)) {
            $this->setError(ERROR_INCORRECT_DATA, 'newRecord: check arguments');
            return FALSE;
        }
        $keyword = $this->dbLink->real_escape_string($keyword);
        $insertion = $this->dbLink->real_escape_string($insertion);
        // get event identifier
        $qEvent = $this->dbLink->query("SELECT `e`.`id` "
                . "FROM `".MECCANO_TPREF."_core_logman_events` `e` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "WHERE `e`.`keyword`='$keyword' "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'newRecord: unable to get event identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'newRecord: plugin or event not found');
            return FALSE;
        }
        list($eventId) = $qEvent->fetch_row();
        // make new record
        if (isset($_SESSION[AUTH_LIMITED])) {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`, `user`) "
                    . "VALUES ($eventId, '$insertion', '".$_SESSION[AUTH_USERNAME]."') ;");
        }
        else {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`) "
                    . "VALUES ($eventId, '$insertion') ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'newRecord: unable to make new record -> '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
//    
    public function clearLog() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'logman_clear_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "clearLog: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'clearLog: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        $this->dbLink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_logman_records` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'clearLog: unable to clear log -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->newRecord('core', 'logman_clear_log')) {
            return FALSE;
        }
        return TRUE;
    }
    
    public function sumLogAllPlugins($rpp = 20) { // rpp - records per page
        $this->zeroizeError();
        if (!is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumLog: rpp must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_logman_records` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumLog: unable to counted total records -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_row();
        $totalPages = $totalRecs/$rpp;
        $remainer = fmod($totalRecs, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalRecs, 'pages' => (int) $totalPages);
    }
    
    public function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getPageAllPlugins: restricted by the policy");
            return FALSE;
        }
        if (!pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check order parameters');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalPages && $totalPages) {
            $pageNumber = $totalPages;
        }
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $start = ($pageNumber - 1) * $rpp;
        $qResult = $this->dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getPage: unable to get log page -> '.$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $logNode = $xml->createElement('log');
        $xml->appendChild($logNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $recordNode = $xml->createElement('record');
            $logNode->appendChild($recordNode);
            $recordNode->appendChild($xml->createElement('id', $row[0]));
            $recordNode->appendChild($xml->createElement('time', $row[1]));
            $recordNode->appendChild($xml->createElement('event', $row[2]));
            $recordNode->appendChild($xml->createElement('user', $row[3]));
        }
        return $xml;
    }
    
    public function sumLogByPlugin($plugin, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumLog: check arguments');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query("SELECT COUNT(`r`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r`"
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `e`.`id`=`r`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumLog: unable to counted total records -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_row();
        $totalPages = $totalRecs/$rpp;
        $remainer = fmod($totalRecs, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalRecs, 'pages' => (int) $totalPages);
    }
    
    public function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getPageByPlugin: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin) || !pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check order parameters');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalPages && $totalPages) {
            $pageNumber = $totalPages;
        }
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $start = ($pageNumber - 1) * $rpp;
        $qResult = $this->dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `r`.`eventid`=`e`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "AND `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getPage: unable to get log page -> '.$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $logNode = $xml->createElement('log');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $logNode->appendChild($attr_plugin);
        $xml->appendChild($logNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $recordNode = $xml->createElement('record');
            $logNode->appendChild($recordNode);
            $recordNode->appendChild($xml->createElement('id', $row[0]));
            $recordNode->appendChild($xml->createElement('time', $row[1]));
            $recordNode->appendChild($xml->createElement('event', $row[2]));
            $recordNode->appendChild($xml->createElement('user', $row[3]));
        }
        return $xml;
    }
    
    public function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getLogAllPlugins: restricted by the policy");
            return FALSE;
        }
        if (!pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check order parameters');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = $this->dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getPage: unable to get log -> '.$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $logNode = $xml->createElement('log');
        $xml->appendChild($logNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $recordNode = $xml->createElement('record');
            $logNode->appendChild($recordNode);
            $recordNode->appendChild($xml->createElement('id', $row[0]));
            $recordNode->appendChild($xml->createElement('time', $row[1]));
            $recordNode->appendChild($xml->createElement('event', $row[2]));
            $recordNode->appendChild($xml->createElement('user', $row[3]));
        }
        return $xml;
    }
    
    public function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getLogByPlugin: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getPage: check order parameters');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = $this->dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `r`.`eventid`=`e`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "AND `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getPage: unable to get log -> '.$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $logNode = $xml->createElement('log');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $logNode->appendChild($attr_plugin);
        $xml->appendChild($logNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $recordNode = $xml->createElement('record');
            $logNode->appendChild($recordNode);
            $recordNode->appendChild($xml->createElement('id', $row[0]));
            $recordNode->appendChild($xml->createElement('time', $row[1]));
            $recordNode->appendChild($xml->createElement('event', $row[2]));
            $recordNode->appendChild($xml->createElement('user', $row[3]));
        }
        return $xml;
    }
    
}
