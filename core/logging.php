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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('one or more of received arguments isn\'t strings');
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
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('your query for new record was not executed | '.self::$dblink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function clearLog() {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        self::$dblink->query("TRUNCATE TABLE `".MECCANO_TPREF."_core_log_records` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('log was not cleared | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::newRecord('core_clearlog')) {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function sumLog($rpp = 20) { // rpp - records per page
        if (!is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('value of records per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dblink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_log_records` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('total records couldn\'t be counted | '.self::$dblink->error);
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
}
