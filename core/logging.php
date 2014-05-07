<?php

namespace core;

require_once 'swconst.php';

class Logging {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dblink; // database link
    
    public function __construct($dblink = FALSE) {
        self::$dblink = $dblink;
    }
    
    public static function setDbLink($dblink) {
        self::$dblink = $dblink;
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
    
    public static function newRecord($event, $insertion = '') {
        if (!is_string($event) || !is_string($insertion)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('newRecord: one or more of received arguments aren\'t strings');
            return FALSE;
        }
        if (isset($_SESSION['core_auth_limited'])) {
            self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_log_records` (`did`, `insertion`, `user`) "
                    . "VALUES ((SELECT `id` FROM `".MECCANO_TPREF."_core_log_description`"
                    . " WHERE `event`='$event'), '$insertion', '".$_SESSION['core_auth_uname']."') ;");
        }
        else {
            self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_log_records` (`did`, `insertion`) "
                    . "VALUES ((SELECT `id` FROM `".MECCANO_TPREF."_core_log_description`"
                    . " WHERE `event`='$event'), '$insertion') ;");
        }
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newRecord: your query for new record was not executed | '.self::$dblink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function clearLog() {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('clearLog: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        self::$dblink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_log_records` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('clearLog: log was not cleared | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::newRecord('core_clearLog')) {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function sumLog($rpp = 20) { // rpp - records per page
        if (!is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumLog: value of records per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dblink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_log_records` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumLog: total records couldn\'t be counted | '.self::$dblink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_array(MYSQLI_NUM);
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
        return array((int) $totalRecs, (int) $totalPages);
    }
    
    public static function getPage($pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
        if (!is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: values of $pageNumber, $totalPages, $rpp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            $arrayLen = count($orderBy);
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPage: value of $orderBy must be string or array');
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
        $qResult = self::$dblink->query("SELECT `L`.`id` `id`, `time`, REPLACE(`description`, '%d', `insertion`) `event`, `user` "
                . "FROM `".MECCANO_TPREF."_core_log_records` `L` "
                . "INNER JOIN `".MECCANO_TPREF."_core_log_description` `LD` ON `L`.`did` = `LD`.`id` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPage: log page couldn\'t be gotten | '.self::$dblink->error);
            return FALSE;
        }
        return $qResult;
    }
    
    public static function newEvent($event, $description) {
        if (!is_string($event) || !is_string($description)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('newEvent: one or more of received arguments aren\'t strings');
            return FALSE;
        }
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('newEvent: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        $event = self::$dblink->real_escape_string($event);
        $description = self::$dblink->real_escape_string($description);
        self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_log_description` (`event`, `description`) "
                . "VALUES ('$event', '$description');");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('newEvent: new event wasn\'t added | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::newRecord('core_newEvent', $event)) {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delEvent($event) {
        if (!is_string($event)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delEvent: received argument isn\'t string');
            return FALSE;
        }
        $sysEvents = array("core_misc",
            "core_newGroup",
            "core_delGroup",
            "core_newUser",
            "core_delUser",
            "core_authLogin",
            "core_clearLog",
            "core_newEvent",
            "core_delEvent");
        if (in_array($event, $sysEvents)) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delEvent: it is impossible to delete system event');
            return FALSE;
        }
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('delEvent: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        $event = self::$dblink->real_escape_string($event);
        $queries = array(
            "DELETE FROM `".MECCANO_TPREF."_core_log_records`"
                . "WHERE `did`=(SELECT `id` FROM `".MECCANO_TPREF."_core_log_description` WHERE `event`='$event');",
            "DELETE FROM `".MECCANO_TPREF."_core_log_description` WHERE `event`='$event';"
        );
        foreach ($queries as $value) {
            self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delEvent: event couldn\'t be deleted | '.self::$dblink->error);
                return FALSE;
            }
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delEvent: defined event doesn\'t exist');
            return FALSE;
        }
        if (!self::newRecord('core_delEvent', $event)) {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function getAllAsXML($orderBy = 'id', $ascent = FALSE) {
        $rightEntry = array('id', 'user', 'event', 'time');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            $arrayLen = count($orderBy);
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllAsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dblink->query("SELECT `L`.`id` `id`, `time`, REPLACE(`description`, '%d', `insertion`) `event`, `user` "
                . "FROM `".MECCANO_TPREF."_core_log_records` `L` "
                . "INNER JOIN `".MECCANO_TPREF."_core_log_description` `LD` ON `L`.`did` = `LD`.`id` "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getAllAsXML: log records couldn\'t be gotten | '.self::$dblink->error);
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
}
