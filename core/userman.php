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
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('createGroup: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
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
    
    public static function groupStatus($groupId, $active, $log = TRUE) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('groupStatus: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || $groupId<1) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('groupStatus: group id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $groupId>1) {
            $active = 0;
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('groupStatus: system group can\'t be disabled');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `active`=$active "
                . "WHERE `id`=$groupId ;");
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
                $l = Logging::newRecord('core_enGroup', "$groupId");
            }
            else {
                $l = Logging::newRecord('core_disGroup', "$groupId");
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
    
    public static function moveGroupTo($groupId, $destId) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('moveGroupTo: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_integer($destId) || $destId<1 || $destId == $groupId) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('moveGroupTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($groupId == 1) {
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
                . "WHERE `groupid`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveGroupTo: can\'t move users to another group |'.self::$dblink->error);
            return FALSE;
        }
        return self::$dblink->affected_rows;
    }
    
    //user methods
    public static function createUser($username, $password, $email, $groupId, $active = TRUE, $log = TRUE) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('createUser: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
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
            'iptime' => "INSERT INTO `".MECCANO_TPREF."_core_auth_iptime` (`id`, `ip`) "
            . "VALUES (LAST_INSERT_ID(), '0.0.0.0') ;",
            'mail' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userinfo` (`id`, `email`) "
            . "VALUES (LAST_INSERT_ID(), '$email') ;",
            'passw' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`userid`, `password`, `limited`) "
            . "VALUES (LAST_INSERT_ID(), '$passw', 0) ;",
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
    
    public static function userStatus($userId, $active, $log = TRUE) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('userStatus: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || $userId<1) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userStatus: user id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $userId>1) {
            $active = 0;
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('userStatus: system user can\'t be disabled');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `active`=$active "
                . "WHERE `id`=$userId ;");
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
                $l = Logging::newRecord('core_enUser', "$userId");
            }
            else {
                $l = Logging::newRecord('core_disUser', "$userId");
            }
            if (!$l) {
                self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
            }
        }
        return TRUE;
    }
    
    public static function moveUserTo($userId, $destId) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('moveUserTo: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !is_integer($destId) || $destId<1 || $destId == $userId) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('moveUserTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($userId == 1) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('moveUserTo: can\'t move system user');
            return FALSE;
        }
        self::$dblink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveUserTo: can\'t check destination group existence |'.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('moveUserTo: destination group doesn\'t exist');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveUserTo: can\'t move user to another group |'.self::$dblink->error);
            return FALSE;
        }
        return self::$dblink->affected_rows;
    }
    
    public static function delUser($userId, $log = TRUE) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('delUser: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delUser: identifier must be integer');
            return FALSE;
        }
        if ($userId == 1) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delUser: can\'t delete system user');
            return FALSE;
        }
        $qName = self::$dblink->query("SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delUser: defined user doesn\'t exist');
            return FALSE;
        }
        $sql = array(
            "DELETE FROM `".MECCANO_TPREF."_core_auth_usi` "
            . "WHERE `id` IN "
            . "(SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `userid`=$userId) ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `userid`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userinfo` "
            . "WHERE `id`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_auth_iptime` "
            . "WHERE `id`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_users` "
            . "WHERE `id`=$userId ;"
        );
        foreach ($sql as $value) {
            self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delUser: something went wrong | '.self::$dblink->error);
                return FALSE;
            }
        }
        list($username) = $qName->fetch_row();
        if ($log && !Logging::newRecord('core_delUser', $username." | id: $userId")) {
            self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
        }
        return TRUE;
    }
    
    public static function aboutUser($userId) {
        if (!is_integer($userId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('aboutUser: id must be integer');
            return FALSE;
        }
        $qAbout = self::$dblink->query("SELECT `i`.`fullname`, `i`.`email`, `g`.`id`, `g`.`groupname` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` `i`, `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid`=`g`.`id` "
                . "WHERE `i`.`id`=$userId "
                . "AND `u`.`id`=$userId "
                . "LIMIT 1 ;");
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('aboutUser: defined user doesn\'t exist');
            return FALSE;
        }
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('aboutUser: something went wrong | '.self::$dblink->error);
            return FALSE;
        }
        $row = $qAbout->fetch_row();
        $xml = new \DOMDocument('1.0', 'utf-8');
        $aboutNode = $xml->createElement('about');
        $xml->appendChild($aboutNode);
        $userNode = $xml->createElement('user');
        $aboutNode->appendChild($userNode);
        $userNode->appendChild($xml->createElement('fullname', $row[0]));
        $userNode->appendChild($xml->createElement('email', $row[1]));
        $groupNode = $xml->createElement('group');
        $aboutNode->appendChild($groupNode);
        $groupNode->appendChild($xml->createElement('id', $row[2]));
        $groupNode->appendChild($xml->createElement('name', $row[3]));
        return $xml;
    }
    
    public static function userPasswords($userId) {
        if (!is_integer($userId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userPasswords: id must be integer');
            return FALSE;
        }
        $qPassw = self::$dblink->query("SELECT `id`, `description`, `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid` = $userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userPasswords: '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userPasswords: defined user doesn\'t exist');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $securityNode = $xml->createElement('security');
        $xml->appendChild($securityNode);
        while ($row = $qPassw->fetch_row()) {
            $passwNode = $xml->createElement('password');
            $securityNode->appendChild($passwNode);
            $passwNode->appendChild($xml->createElement('id', $row[0]));
            $passwNode->appendChild($xml->createElement('description', $row[1]));
            $passwNode->appendChild($xml->createElement('limited', $row[2]));
        }
        return $xml;
    }
    
    public static function addPassword($userId, $password, $description='') {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('addPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !pregPassw($password) || !is_string($description)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qHash = self::$dblink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addPassword: can\'t check defined user');
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addPassword: defined user doesn\'t exist');
            return FALSE;
        }
        list($salt) = $qHash->fetch_row();
        $passwHash = passwHash($password, $salt);
        $description = self::$dblink->real_escape_string($description);
        self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`userid`, `password`, `description`, `limited`) "
                . "VALUES($userId, '$passwHash', '$description', 1) ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addPassword: can\'t add password | '.self::$dblink->error);
            return FALSE;
        }
        $insertId = (int) self::$dblink->insert_id;
        $usi = makeIdent("$insertId");
        self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_auth_usi` (`id`, `usi`) "
                . "VALUES($insertId, '$usi') ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addPassword: can\'t create unique session identifier | '.self::$dblink->error);
            return FALSE;
        }
        return $insertId;
    }
    
    public static function delPassword($passwId, $userId) {
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('delPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($passwId) || !is_integer($userId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qLimited = self::$dblink->query("SELECT `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `id`=$passwId "
                . "AND `userid`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPassword: can\'t check limitation status of the password | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPassword: check incoming parageters');
            return FALSE;
        }
        list($limited) = $qLimited->fetch_row();
        if (!$limited) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delPassword: impossible to delete primary password');
            return FALSE;
        }
        $sql = array("DELETE FROM `".MECCANO_TPREF."_core_auth_usi` "
            . "WHERE `id`=$passwId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `id`=$passwId ;");
        foreach ($sql as $value) {
            self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delPassword: '.self::$dblink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
}
