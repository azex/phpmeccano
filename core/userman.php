<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logging.php';
require_once 'policy.php';

class UserMan {
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
    
    public static function createGroup($groupname, $description, $log = TRUE) {
        if (!pregGName($groupname) || !is_string($description)) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('createGroup: incorect type of incoming parameters');
            return FALSE;
        }
        $description = self::$dblink->real_escape_string($description);
        self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_groups` (`groupname`, `description`) "
                . "VALUES ('$groupname', '$description') ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('createGroup: group wasn\'t created | '.self::$dblink->error);
            return FALSE;
        }
        $groupId = self::$dblink->insert_id;
        if (!Policy::addGroup($groupId)) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp(Policy::errExp());
            return FALSE;
        }
        if ($log && !Logging::newRecord('core_newGroup', $groupname)) {
            self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
        }
        return $groupId;
    }
}
