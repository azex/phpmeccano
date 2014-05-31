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
    
    //group methods
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
        if ($log && !Logging::newRecord('core_newGroup', $groupname." | id: $groupId")) {
            self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
        }
        return $groupId;
    }
    
    public static function groupStatus($id, $active, $log = TRUE) {
        if (!is_integer($id) || $id<1) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('groupStatus: group id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $id>1) {
            $active = 0;
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('groupStatus: system group can\'t be disabled');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `active`=$active "
                . "WHERE `id`=$id ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('groupStatus: status wasn\'t changed | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('groupStatus: incorrect group status or group doesn\'t exist');
            return FALSE;
        }
        if ($log) {
            if ($active) {
                $l = Logging::newRecord('core_enGroup', "$id");
            }
            else {
                $l = Logging::newRecord('core_disGroup', "$id");
            }
            if (!$l) {
                self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
            }
        }
        return TRUE;
    }
    
    public static function groupExists($groupname) {
        if (!pregGName($groupname)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('groupExists: incorrect group name');
            return FALSE;
        }
        $qId = self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `groupname`='$groupname' ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('groupExists: can\'t check group existence | '.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function moveGroupTo($id, $destId) {
        if (!is_integer($id) || !is_integer($destId) || $destId<1 || $destId == $id) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('moveGroupTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($id == 1) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('moveGroupTo: can\'t move system group');
            return FALSE;
        }
        self::$dblink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveGroupTo: can\'t check destination group existence |'.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('moveGroupTo: destination group doesn\'t exist');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `groupid`=$id ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveGroupTo: can\'t move users to another group |'.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            return TRUE;
        }
        return FALSE;
    }
    
    //user methods
    public static function createUser($username, $password, $email, $groupId, $active = TRUE, $log = TRUE) {
        if (!pregUName($username) || !pregPassw($password) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_integer($groupId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('createUser: incorrect incoming parameters');
            return FALSE;
        }
        self::$dblink->query("SELECT `u`.`id`, `i`.`id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u`, `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "WHERE `u`.`username`='$username' "
                . "OR `i`.`email`='$email' "
                . "LIMIT 1;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('createUser: can\'t check username and email | '.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('createUser: username or email are already in use');
            return FALSE;
        }
        self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('createUser: can\'t check group | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('createUser: defined group doesn\'t exist');
            return FALSE;
        }
        $salt = makeSalt($username);
        $passw = passwHash($password, $salt);
        $usi = makeIdent($username);
        if ($active) { $active = 1; }
        else { $active = 0; }
        $sql = array(
            'userid' => "INSERT INTO `".MECCANO_TPREF."_core_userman_users` (`username`, `groupid`, `salt`, `active`) "
            . "VALUES ('$username', '$groupId', '$salt', $active) ;",
            'mail' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userinfo` (`id`, `email`) "
            . "VALUES (LAST_INSERT_ID(), '$email') ;",
            'passw' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`userid`, `password`) "
            . "VALUES (LAST_INSERT_ID(), '$passw') ;",
            'usi' => "INSERT INTO `".MECCANO_TPREF."_core_auth_usi` (`id`, `usi`) "
            . "VALUES (LAST_INSERT_ID(), '$usi') ;"
            );
        foreach ($sql as $key => $value) {
            self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('createUser: something went wrong | '.self::$dblink->error);
                return FALSE;
            }
            if ($key == 'userid') {
                $userid = self::$dblink->insert_id;
            }
        }
        if ($log && !Logging::newRecord('core_newUser', $username." | id: $userid")) {
            self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
        }
        return $userid;
    }
    
    public static function userExists($username) {
        if (!pregUName($username)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userExists: incorrect username');
            return FALSE;
        }
        $qId = self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `username`='$username' ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userExists: can\'t check user existence | '.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function mailExists($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userExists: incorrect email');
            return FALSE;
        }
        self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` "
                . "WHERE `email`='$email' ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userExists: can\'t check email existence | '.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            return TRUE;
        }
        return FALSE;
    }
    
    public static function userStatus($id, $active, $log = TRUE) {
        if (!is_integer($id) || $id<1) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userStatus: user id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $id>1) {
            $active = 0;
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('userStatus: system user can\'t be disabled');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `active`=$active "
                . "WHERE `id`=$id ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userStatus: status wasn\'t changed | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userStatus: incorrect user status or group doesn\'t exist');
            return FALSE;
        }
        if ($log) {
            if ($active) {
                $l = Logging::newRecord('core_enUser', "$id");
            }
            else {
                $l = Logging::newRecord('core_disUser', "$id");
            }
            if (!$l) {
                self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
            }
        }
        return TRUE;
    }
}
