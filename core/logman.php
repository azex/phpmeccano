<?php

namespace core;

require_once 'swconst.php';

interface intLogMan {
    function __construct(\mysqli $dbLink);
    public static function setDbLink(\mysqli $dbLink);
    public static function errId();
    public static function errExp();
    public static function installEvents(\DOMDocument $events);
    public static function delEvents($plugin);
    public static function newRecord($plugin, $event, $insertion = '');
    public static function clearLog();
    public static function sumLogAllPlugins($rpp = 20);
    public static function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function sumLogByPlugin($plugin, $rpp = 20);
    public static function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array(), $ascent = FALSE);
}

class LogMan implements intLogMan {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    
    public function __construct(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setDbLink(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
    }
    
    private static function setErrId($id) {
        self::$errid = $id;
    }
    
    private static function setErrExp($exp) {
        self::$errexp = $exp;
    }
    
    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }
    
    public static function installEvents(\DOMDocument $events) {
        self::$errid = 0;        self::$errexp = '';
        if (!@$events->relaxNGValidate(MECCANO_CORE_DIR.'/logman/install-events-schema-v01.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('installEvents: incorrect structure of the events');
            return FALSE;
        }
        $pluginName = $events->getElementsByTagName('log')->item(0)->getAttribute("plugin");
        // check whether plugin is installed
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$pluginName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp("installEvents: cannot check whether the plugin is installed | ".self::$dbLink->errno);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("installEvents: required plugin [$pluginName] is not installed");
            return FALSE;
        }
        // plugin identifier
        list($pluginId) = $qPlugin->fetch_row();
        // get list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `code`, `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installEvents: cannot get list of available languages: '.self::$dbLink->error);
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
        $qEvents = self::$dbLink->query("SELECT `keyword`, `id` "
                . "FROM `".MECCANO_TPREF."_core_logman_events` "
                . "WHERE `plugid`=$pluginId");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installEvents: unable to get installed events | '.self::$dbLink->error);
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
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("installEvents: unable to delete outdated event | ".self::$dbLink->error);
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
                    $updateDesc = self::$dbLink->real_escape_string($desc);
                    $eventId = $installedEvents[$keyword];
                    self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_logman_descriptions` "
                            . "SET `description`='$updateDesc' "
                            . "WHERE `eventid`=$eventId "
                            . "AND `codeid`=$codeId ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('installEvents: unable to update event description | '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
            // install event
            else {
                self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_events` "
                        . "(`keyword`, `plugid`) "
                        . "VALUES ('$keyword', $pluginId) ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installEvents: unable add new event | '.self::$dbLink->error);
                    return FALSE;
                }
                $eventId = self::$dbLink->insert_id;
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $newDesc = self::$dbLink->real_escape_string($desc);
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_descriptions` "
                            . "(`description`, `eventid`, `codeid`) "
                            . "VALUES ('$newDesc', $eventId, $codeId) ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('installEvents: unable to add new event description | '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function delEvents($plugin) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp("delEvents: incorrect plugin name");
            return FALSE;
        }
        if ($plugin == "core") {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp("delEvents: unable to delete core events");
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delEvents: unable remove events | ".self::$dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }

        public static function newRecord($plugin, $keyword, $insertion = '') {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregPlugin($keyword) || !is_string($insertion)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('newRecord: check arguments');
            return FALSE;
        }
        $keyword = self::$dbLink->real_escape_string($keyword);
        $insertion = self::$dbLink->real_escape_string($insertion);
        // get event identifier
        $qEvent = self::$dbLink->query("SELECT `e`.`id` "
                . "FROM `".MECCANO_TPREF."_core_logman_events` `e` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "WHERE `e`.`keyword`='$keyword' "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newRecord: unable to get event identifier | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('newRecord: plugin or event not found');
            return FALSE;
        }
        list($eventId) = $qEvent->fetch_row();
        // make new record
        if (isset($_SESSION[AUTH_LIMITED])) {
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`, `user`) "
                    . "VALUES ($eventId, '$insertion', '".$_SESSION[AUTH_USERNAME]."') ;");
        }
        else {
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`) "
                    . "VALUES ($eventId, '$insertion') ;");
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newRecord: unable to make new record | '.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
//    
    public static function clearLog() {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('clearLog: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        self::$dbLink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_logman_records` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('clearLog: unable to clear log | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::newRecord('core', 'clearLog')) {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function sumLogAllPlugins($rpp = 20) { // rpp - records per page
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumLog: rpp must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_logman_records` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumLog: unable to counted total records | '.self::$dbLink->error);
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
    
    public static function getPageAllPlugins($pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check order parameters');
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
        $qResult = self::$dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: unable to get log page | '.self::$dbLink->error);
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
    
    public static function sumLogByPlugin($plugin, $rpp = 20) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumLog: check arguments');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dbLink->query("SELECT COUNT(`r`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r`"
                . "JOIN `".MECCANO_TPREF."_core_logman_events` `e` "
                . "ON `e`.`id`=`r`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumLog: unable to counted total records | '.self::$dbLink->error);
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
    
    public static function getPageByPlugin($plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array(), $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregLang($code) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check order parameters');
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
        $qResult = self::$dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
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
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: unable to get log page | '.self::$dbLink->error);
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
    
    public static function getLogAllPlugins($code = MECCANO_DEF_LANG, $orderBy = array(), $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregLang($code)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check order parameters');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
                . "FROM `".MECCANO_TPREF."_core_logman_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_logman_descriptions` `d` "
                . "ON `r`.`eventid` = `d`.`eventid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `d`.`codeid` = `l`.`id` "
                . "WHERE `l`.`code` = '$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: unable to get log | '.self::$dbLink->error);
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
    
    public static function getLogByPlugin($plugin, $code = MECCANO_DEF_LANG, $orderBy = array(), $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregLang($code)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check arguments');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: check order parameters');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dbLink->query("SELECT `r`.`id` `id`, `r`.`time` `time`, REPLACE(`d`.`description`, '%d', `r`.`insertion`) `event`, `r`.`user` `user` "
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
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: unable to get log | '.self::$dbLink->error);
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
