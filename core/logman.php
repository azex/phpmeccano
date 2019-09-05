<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Core module [logman.php].
 *     Copyright (C) 2015-2019  Alexei Muzarov
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
 *     https://bitbucket.org/azexmail/phpmeccano
 */

namespace core;

require_once MECCANO_CORE_DIR.'/extclass.php';

interface intLogMan {
    function __construct(\mysqli $dbLink);
    public function installEvents(\DOMDocument $events, $validate = true);
    public function delLogEvents($plugin); // old name [delEvents]
    public function clearLog();
    public function sumLogAllPlugins($rpp = 20);
    public function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false);
    public function sumLogByPlugin($plugin, $rpp = 20);
    public function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false);
    public function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false);
    public function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false);
}

class LogMan extends ServiceMethods implements intLogMan {
    
    public function __construct(\mysqli $dbLink) {
        $this->dbLink = $dbLink;
    }

    public function installEvents(\DOMDocument $events, $validate = true) {
        $this->zeroizeError();
        if ($validate && !@$events->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/logman-events-v01.rng')) {
            $this->setError(ERROR_INCORRECT_DATA, 'installEvents: incorrect structure of the events');
            return false;
        }
        $pluginName = $events->getElementsByTagName('log')->item(0)->getAttribute("plugin");
        // check whether plugin is installed
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$pluginName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "installEvents: cannot check whether the plugin is installed -> ".$this->dbLink->errno);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "installEvents: required plugin [$pluginName] is not installed");
            return false;
        }
        // plugin identifier
        list($pluginId) = $qPlugin->fetch_row();
        // get list of available languages
        $qAvaiLang = $this->dbLink->query("SELECT `code`, `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installEvents: cannot get list of available languages: '.$this->dbLink->error);
            return false;
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
            return false;
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
                    return false;
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
                        return false;
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
                    return false;
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
                        return false;
                    }
                }
            }
        }
        return true;
    }
    
    public function delLogEvents($plugin) {
        $this->zeroizeError();
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, "delLogEvents: incorrect plugin name");
            return false;
        }
        if ($plugin == "core") {
            $this->setError(ERROR_SYSTEM_INTERVENTION, "delLogEvents: unable to delete core events");
            return false;
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
                $this->setError(ERROR_NOT_EXECUTED, "delLogEvents: unable remove events -> ".$this->dbLink->error);
                return false;
            }
        }
        return true;
    }
    
    public function clearLog() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'logman_clear_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "clearLog: restricted by the policy");
            return false;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'clearLog: function execution was terminated because of using of limited authentication');
            return false;
        }
        $this->dbLink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_logman_records` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'clearLog: unable to clear log -> '.$this->dbLink->error);
            return false;
        }
        $this->newLogRecord('core', 'logman_clear_log');
        return true;
    }
    
    public function sumLogAllPlugins($rpp = 20) { // rpp - records per page
        $this->zeroizeError();
        if (!is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumLog: rpp must be integer');
            return false;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_logman_records` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumLog: unable to counted total records -> '.$this->dbLink->error);
            return false;
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
    
    public function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getPageAllPlugins: restricted by the policy");
            return false;
        }
        if (!pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPageAllPlugins: check arguments');
            return false;
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
            $this->setError(ERROR_INCORRECT_DATA, 'getPageAllPlugins: check order parameters');
            return false;
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
        if ($ascent == true) {
            $direct = '';
        }
        elseif ($ascent == false) {
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
            $this->setError(ERROR_NOT_EXECUTED, 'getPageAllPlugins: unable to get log page -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $logNode = $xml->createElement('log');
            $xml->appendChild($logNode);
            while ($row = $qResult->fetch_row()) {
                $recordNode = $xml->createElement('record');
                $logNode->appendChild($recordNode);
                $recordNode->appendChild($xml->createElement('id', $row[0]));
                $recordNode->appendChild($xml->createElement('time', $row[1]));
                $recordNode->appendChild($xml->createElement('event', $row[2]));
                $recordNode->appendChild($xml->createElement('user', $row[3]));
            }
            return $xml;
        }
        else {
            $log = array();
            while ($row = $qResult->fetch_row()) {
                $log[] = array(
                    'id' => (int) $row[0],
                    'time' => $row[1],
                    'event' => $row[2],
                    'user' => $row[3]
                );
            }
            if ($this->outputType == 'json') {
                return json_encode($log);
            }
            else {
                return $log;
            }
        }
    }
    
    public function sumLogByPlugin($plugin, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumLog: check arguments');
            return false;
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
            return false;
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
    
    public function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getPageByPlugin: restricted by the policy");
            return false;
        }
        if (!pregPlugin($plugin) || !pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPageByPlugin: check arguments');
            return false;
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
            $this->setError(ERROR_INCORRECT_DATA, 'getPageByPlugin: check order parameters');
            return false;
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
        if ($ascent == true) {
            $direct = '';
        }
        elseif ($ascent == false) {
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
            $this->setError(ERROR_NOT_EXECUTED, 'getPageByPlugin: unable to get log page -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $logNode = $xml->createElement('log');
            $attr_plugin = $xml->createAttribute('plugin');
            $attr_plugin->value = $plugin;
            $logNode->appendChild($attr_plugin);
            $xml->appendChild($logNode);
            while ($row = $qResult->fetch_row()) {
                $recordNode = $xml->createElement('record');
                $logNode->appendChild($recordNode);
                $recordNode->appendChild($xml->createElement('id', $row[0]));
                $recordNode->appendChild($xml->createElement('time', $row[1]));
                $recordNode->appendChild($xml->createElement('event', $row[2]));
                $recordNode->appendChild($xml->createElement('user', $row[3]));
            }
            return $xml;
        }
        else {
            $log = array();
            $log['plugin'] = $plugin;
            while ($row = $qResult->fetch_row()) {
                $log['records'][] = array(
                    'id' => (int) $row[0],
                    'time' => $row[1],
                    'event' => $row[2],
                    'user' => $row[3]
                );
            }
            if ($this->outputType == 'json') {
                return json_encode($log);
            }
            else {
                return $log;
            }
        }
    }
    
    public function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getLogAllPlugins: restricted by the policy");
            return false;
        }
        if (!pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getLogAllPlugins: check arguments');
            return false;
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
            $this->setError(ERROR_INCORRECT_DATA, 'getLogAllPlugins: check order parameters');
            return false;
        }
        if ($ascent == true) {
            $direct = '';
        }
        elseif ($ascent == false) {
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
            $this->setError(ERROR_NOT_EXECUTED, 'getLogAllPlugins: unable to get log -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $logNode = $xml->createElement('log');
            $xml->appendChild($logNode);
            while ($row = $qResult->fetch_row()) {
                $recordNode = $xml->createElement('record');
                $logNode->appendChild($recordNode);
                $recordNode->appendChild($xml->createElement('id', $row[0]));
                $recordNode->appendChild($xml->createElement('time', $row[1]));
                $recordNode->appendChild($xml->createElement('event', $row[2]));
                $recordNode->appendChild($xml->createElement('user', $row[3]));
            }
            return $xml;
        }
        else {
            $log = array();
            while ($row = $qResult->fetch_row()) {
                $log[] = array(
                    'id' => (int) $row[0],
                    'time' => $row[1],
                    'event' => $row[2],
                    'user' => $row[3]
                );
            }
            if ($this->outputType == 'json') {
                return json_encode($log);
            }
            else {
                return $log;
            }
        }
    }
    
    public function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = false) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'logman_get_log')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getLogByPlugin: restricted by the policy");
            return false;
        }
        if (!pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getLogByPlugin: check arguments');
            return false;
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
            $this->setError(ERROR_INCORRECT_DATA, 'getLogByPlugin: check order parameters');
            return false;
        }
        if ($ascent == true) {
            $direct = '';
        }
        elseif ($ascent == false) {
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
            $this->setError(ERROR_NOT_EXECUTED, 'getLogByPlugin: unable to get log -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $logNode = $xml->createElement('log');
            $attr_plugin = $xml->createAttribute('plugin');
            $attr_plugin->value = $plugin;
            $logNode->appendChild($attr_plugin);
            $xml->appendChild($logNode);
            while ($row = $qResult->fetch_row()) {
                $recordNode = $xml->createElement('record');
                $logNode->appendChild($recordNode);
                $recordNode->appendChild($xml->createElement('id', $row[0]));
                $recordNode->appendChild($xml->createElement('time', $row[1]));
                $recordNode->appendChild($xml->createElement('event', $row[2]));
                $recordNode->appendChild($xml->createElement('user', $row[3]));
            }
            return $xml;
        }
        else {
            $log = array();
            $log['plugin'] = $plugin;
            while ($row = $qResult->fetch_row()) {
                $log['records'][] = array(
                    'id' => (int) $row[0],
                    'time' => $row[1],
                    'event' => $row[2],
                    'user' => $row[3]
                );
            }
            if ($this->outputType == 'json') {
                return json_encode($log);
            }
            else {
                return $log;
            }
        }
    }
    
}
