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
}
