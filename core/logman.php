<?php

namespace core;

require_once 'swconst.php';

interface intLogMan {
    function __construct(\mysqli $dbLink);
    public static function setDbLink(\mysqli $dbLink);
    public static function errId();
    public static function errExp();
    public static function installEvents(\DOMDocument $events);
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
                . "FROM `".MECCANO_TPREF."_core_log_events` "
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
                "DELETE `r` FROM `".MECCANO_TPREF."_core_log_records` `r` "
                . "JOIN `".MECCANO_TPREF."_core_log_events` `e` "
                . "ON `e`.`id`=`r`.`eventid` "
                . "WHERE `e`.`id`=$eventId ;",
                "DELETE `d` FROM `".MECCANO_TPREF."_core_log_descriptions` `d` "
                . "JOIN `".MECCANO_TPREF."_core_log_events` `e` "
                . "ON `e`.`id`=`d`.`eventid`"
                . "WHERE `e`.`id`=$eventId ;",
                "DELETE  FROM `".MECCANO_TPREF."_core_log_events` "
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
                    self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_log_descriptions` "
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
                self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_log_events` "
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
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_log_descriptions` "
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

//        public static function newRecord($event, $insertion = '') {
//        self::$errid = 0;        self::$errexp = '';
//        if (!is_string($event) || !is_string($insertion)) {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('newRecord: one or more of received arguments aren\'t strings');
//            return FALSE;
//        }
//        $event = self::$dbLink->real_escape_string($event);
//        $insertion = self::$dbLink->real_escape_string($insertion);
//        if (isset($_SESSION[AUTH_LIMITED])) {
//            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_log_records` (`did`, `insertion`, `user`) "
//                    . "VALUES ((SELECT `id` FROM `".MECCANO_TPREF."_core_log_description`"
//                    . " WHERE `event`='$event'), '$insertion', '".$_SESSION[AUTH_USERNAME]."') ;");
//        }
//        else {
//            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_log_records` (`did`, `insertion`) "
//                    . "VALUES ((SELECT `id` FROM `".MECCANO_TPREF."_core_log_description`"
//                    . " WHERE `event`='$event'), '$insertion') ;");
//        }
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newRecord: your query for new record was not executed | '.self::$dbLink->error);
//            return FALSE;
//        }
//        return TRUE;
//    }
//    
//    public static function clearLog() {
//        self::$errid = 0;        self::$errexp = '';
//        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
//            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('clearLog: function execution was terminated because of using of limited authentication');
//            return FALSE;
//        }
//        self::$dbLink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_log_records` ;");
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('clearLog: log was not cleared | '.self::$dbLink->error);
//            return FALSE;
//        }
//        if (!self::newRecord('core_clearLog')) {
//            return FALSE;
//        }
//        return TRUE;
//    }
//    
//    public static function sumLog($rpp = 20) { // rpp - records per page
//        self::$errid = 0;        self::$errexp = '';
//        if (!is_integer($rpp)) {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumLog: value of records per page must be integer');
//            return FALSE;
//        }
//        if ($rpp < 1) {
//            $rpp = 1;
//        }
//        $qResult = self::$dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_log_records` ;");
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumLog: total records couldn\'t be counted | '.self::$dbLink->error);
//            return FALSE;
//        }
//        list($totalRecs) = $qResult->fetch_array(MYSQLI_NUM);
//        $totalPages = $totalRecs/$rpp;
//        $remainer = fmod($totalRecs, $rpp);
//        if ($totalPages<1 && $totalPages>0) {
//            $totalPages = 1;
//        }
//        elseif ($totalPages>1 && $remainer != 0) {
//            $totalPages += 1;
//        }
//        elseif ($totalPages == 0) {
//            $totalPages = 1;
//        }
//        return array((int) $totalRecs, (int) $totalPages);
//    }
//    
//    public static function getPage($pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
//        self::$errid = 0;        self::$errexp = '';
//        if (!is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: values of $pageNumber, $totalPages, $rpp must be integers');
//            return FALSE;
//        }
//        $rightEntry = array('id', 'user', 'event', 'time');
//        if (is_string($orderBy)) {
//            if (!in_array($orderBy, $rightEntry, TRUE)) {
//            $orderBy = 'id';
//            }
//        }
//        elseif (is_array($orderBy)) {
//            $arrayLen = count($orderBy);
//            if (count(array_intersect($orderBy, $rightEntry))) {
//                $orderList = '';
//                foreach ($orderBy as $value) {
//                    $orderList = $orderList.$value.'`, `';
//                }
//                $orderBy = substr($orderList, 0, -4);
//            }
//            else {
//                $orderBy = 'id';
//            }
//        }
//        else {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: value of $orderBy must be string or array');
//            return FALSE;
//        }
//        if ($pageNumber < 1) {
//            $pageNumber = 1;
//        }
//        elseif ($pageNumber>$totalPages && $totalPages) {
//            $pageNumber = $totalPages;
//        }
//        if ($totalPages < 1) {
//            $totalPages = 1;
//        }
//        if ($rpp < 1) {
//            $rpp = 1;
//        }
//        if ($ascent == TRUE) {
//            $direct = '';
//        }
//        elseif ($ascent == FALSE) {
//            $direct = 'DESC';
//        }
//        $start = ($pageNumber - 1) * $rpp;
//        $qResult = self::$dbLink->query("SELECT `L`.`id` `id`, `time`, REPLACE(`description`, '%d', `insertion`) `event`, `user` "
//                . "FROM `".MECCANO_TPREF."_core_log_records` `L` "
//                . "INNER JOIN `".MECCANO_TPREF."_core_log_description` `LD` ON `L`.`did` = `LD`.`id` "
//                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: log page couldn\'t be gotten | '.self::$dbLink->error);
//            return FALSE;
//        }
//        $xml = new \DOMDocument('1.0', 'utf-8');
//        $logNode = $xml->createElement('log');
//        $xml->appendChild($logNode);
//        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
//            $recordNode = $xml->createElement('record');
//            $logNode->appendChild($recordNode);
//            $recordNode->appendChild($xml->createElement('id', $row[0]));
//            $recordNode->appendChild($xml->createElement('time', $row[1]));
//            $recordNode->appendChild($xml->createElement('event', $row[2]));
//            $recordNode->appendChild($xml->createElement('user', $row[3]));
//        }
//        return $xml;
//    }
//    
//    public static function newEvent($event, $description) {
//        self::$errid = 0;        self::$errexp = '';
//        if (!is_string($event) || !is_string($description)) {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('newEvent: one or more of received arguments aren\'t strings');
//            return FALSE;
//        }
//        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
//            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('newEvent: function execution was terminated because of using of limited authentication');
//            return FALSE;
//        }
//        $event = self::$dbLink->real_escape_string($event);
//        $description = self::$dbLink->real_escape_string($description);
//        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_log_description` (`event`, `description`) "
//                . "VALUES ('$event', '$description');");
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newEvent: new event wasn\'t added | '.self::$dbLink->error);
//            return FALSE;
//        }
//        if (!self::newRecord('core_newEvent', $event)) {
//            return FALSE;
//        }
//        return TRUE;
//    }
//    
//    public static function delEvent($event) {
//        self::$errid = 0;        self::$errexp = '';
//        if (!is_string($event)) {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delEvent: received argument isn\'t string');
//            return FALSE;
//        }
//        $sysEvents = array("core_misc",
//            "core_newGroup",
//            "core_delGroup",
//            "core_enGroup",
//            "core_disGroup",
//            "core_newUser",
//            "core_delUser",
//            "core_enUser",
//            "core_disUser",
//            "core_authLogin",
//            "core_clearLog",
//            "core_newEvent",
//            "core_delEvent");
//        if (in_array($event, $sysEvents)) {
//            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delEvent: it is impossible to delete system event');
//            return FALSE;
//        }
//        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
//            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('delEvent: function execution was terminated because of using of limited authentication');
//            return FALSE;
//        }
//        $event = self::$dbLink->real_escape_string($event);
//        $queries = array(
//            "DELETE FROM `".MECCANO_TPREF."_core_log_records`"
//                . "WHERE `did`=(SELECT `id` FROM `".MECCANO_TPREF."_core_log_description` WHERE `event`='$event');",
//            "DELETE FROM `".MECCANO_TPREF."_core_log_description` WHERE `event`='$event';"
//        );
//        foreach ($queries as $value) {
//            self::$dbLink->query($value);
//            if (self::$dbLink->errno) {
//                self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delEvent: event couldn\'t be deleted | '.self::$dbLink->error);
//                return FALSE;
//            }
//        }
//        if (!self::$dbLink->affected_rows) {
//            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delEvent: defined event doesn\'t exist');
//            return FALSE;
//        }
//        if (!self::newRecord('core_delEvent', $event)) {
//            return FALSE;
//        }
//        return TRUE;
//    }
//    
//    public static function getAll($orderBy = 'id', $ascent = FALSE) {
//        self::$errid = 0;        self::$errexp = '';
//        $rightEntry = array('id', 'user', 'event', 'time');
//        if (is_string($orderBy)) {
//            if (!in_array($orderBy, $rightEntry, TRUE)) {
//            $orderBy = 'id';
//            }
//        }
//        elseif (is_array($orderBy)) {
//            if (count(array_intersect($orderBy, $rightEntry))) {
//                $orderList = '';
//                foreach ($orderBy as $value) {
//                    $orderList = $orderList.$value.'`, `';
//                }
//                $orderBy = substr($orderList, 0, -4);
//            }
//            else {
//                $orderBy = 'id';
//            }
//        }
//        else {
//            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAll: value of $orderBy must be string or array');
//            return FALSE;
//        }
//        if ($ascent == TRUE) {
//            $direct = '';
//        }
//        elseif ($ascent == FALSE) {
//            $direct = 'DESC';
//        }
//        $qResult = self::$dbLink->query("SELECT `L`.`id` `id`, `time`, REPLACE(`description`, '%d', `insertion`) `event`, `user` "
//                . "FROM `".MECCANO_TPREF."_core_log_records` `L` "
//                . "INNER JOIN `".MECCANO_TPREF."_core_log_description` `LD` ON `L`.`did` = `LD`.`id` "
//                . "ORDER BY `$orderBy` $direct ;");
//        if (self::$dbLink->errno) {
//            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getAll: log records couldn\'t be gotten | '.self::$dbLink->error);
//            return FALSE;
//        }
//        $xml = new \DOMDocument('1.0', 'utf-8');
//        $logNode = $xml->createElement('log');
//        $xml->appendChild($logNode);
//        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
//            $recordNode = $xml->createElement('record');
//            $logNode->appendChild($recordNode);
//            $recordNode->appendChild($xml->createElement('id', $row[0]));
//            $recordNode->appendChild($xml->createElement('time', $row[1]));
//            $recordNode->appendChild($xml->createElement('event', $row[2]));
//            $recordNode->appendChild($xml->createElement('user', $row[3]));
//        }
//        return $xml;
//    }
}
