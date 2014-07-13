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
        self::$errid = 0;        self::$errexp = '';
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
        return (int) $groupId;
    }
    
    public static function groupStatus($groupId, $active, $log = TRUE) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('groupStatus: incorrect group status or group doesn\'t exist');
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
        self::$errid = 0;        self::$errexp = '';
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
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('moveGroupTo: destination group doesn\'t exist');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `groupid`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveGroupTo: can\'t move users to another group |'.self::$dblink->error);
            return FALSE;
        }
        return (int) self::$dblink->affected_rows;
    }
    
    public static function aboutGroup($groupId) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($groupId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('aboutGroup: identifier must be integer');
            return FALSE;
        }
        $qAbout = self::$dblink->query("SELECT `groupname`, `description`, `creationtime`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('aboutGroup: something went wrong | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('aboutGroup: defined group doesn\'t exist');
            return FALSE;
        }
        $qSum = self::$dblink->query("SELECT COUNT(`id`) "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `groupid`=$groupId ;");
        $about = $qAbout->fetch_row();
        $sum = $qSum->fetch_row();
        $xml = new \DOMDocument('1.0', 'utf-8');
        $aboutNode = $xml->createElement('group');
        $xml->appendChild($aboutNode);
        $aboutNode->appendChild($xml->createElement('name', $about[0]));
        $aboutNode->appendChild($xml->createElement('description', $about[1]));
        $aboutNode->appendChild($xml->createElement('time', $about[2]));
        $aboutNode->appendChild($xml->createElement('active', $about[3]));
        $aboutNode->appendChild($xml->createElement('usum', $sum[0]));
        return $xml;
    }
    
    public static function setGroupName($groupId, $groupname) {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('setGroupName: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !pregGName($groupname)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setGroupName: incorrect incoming parameters');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `groupname`='$groupname' "
                . "WHERE `id`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setGroupName: can\'t set groupname | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setGroupName: defined group doesn\'t exist or groupname was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setGroupDesc($groupId, $description) {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('setGroupDesc: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_string($description)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setGroupDesc: incorrect incoming parameters');
            return FALSE;
        }
        $description = self::$dblink->real_escape_string($description);
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `description`='$description' "
                . "WHERE `id`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setGroupDesc: can\'t set description | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setGroupDesc: defined group doesn\'t exist or description was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delGroup($groupId, $log = TRUE) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($groupId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delGroup: identifier must be integer');
            return FALSE;
        }
        $qUsers = self::$dblink->query("SELECT COUNT(`id`) "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `groupid`=$groupId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delGroup: can\'t check existence of users in the group | '.self::$dblink->error);
            return FALSE;
        }
        $users = $qUsers->fetch_row();
        if ($users[0]) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delGroup: the group contains users');
            return FALSE;
        }
        if (!Policy::delGroup($groupId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp(Policy::errExp());
            return FALSE;
        }
        $sql = array(
            "SELECT `groupname` "
            . "FROM `".MECCANO_TPREF."_core_userman_groups` "
            . "WHERE `id`=$groupId ;", 
            "DELETE FROM `".MECCANO_TPREF."_core_userman_groups` "
            . "WHERE `id`=$groupId ;"
        );
        foreach ($sql as $key => $value) {
            $qGroup = self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delGroup: '.self::$dblink->error);
                return FALSE;
            }
            if (!self::$dblink->affected_rows) {
                self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delGroup: defined group doesn\'t exist');
                return FALSE;
            }
            if ($key == 0) {
                list($groupname) = $qGroup->fetch_row();
            }
        }
        if ($log && !Logging::newRecord('core_delGroup', $groupname." | id: $groupId")) {
            self::setErrId(ERROR_NOT_CRITICAL);            self::setErrExp(Logging::errExp());
        }
        return TRUE;
    }
    
    public static function sumGroups($gpp = 20) { // gpp - groups per page
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($gpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumGroups: value of groups per page must be integer');
            return FALSE;
        }
        if ($gpp < 1) {
            $gpp = 1;
        }
        $qResult = self::$dblink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumGroups: total users couldn\'t be counted | '.self::$dblink->error);
            return FALSE;
        }
        list($totalGroups) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalGroups/$gpp;
        $remainer = fmod($totalGroups, $gpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array((int) $totalGroups, (int) $totalPages);
    }
    
    public static function getGroups($pageNumber, $totalGroups, $gpp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($pageNumber) || !is_integer($totalGroups) || !is_integer($gpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getGroups: values of $pageNumber, $totalGroups, $gpp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'time', 'active');
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getGroups: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalGroups && $totalGroups) {
            $pageNumber = $totalGroups;
        }
        if ($totalGroups < 1) {
            $totalGroups = 1;
        }
        if ($gpp < 1) {
            $gpp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $start = ($pageNumber - 1) * $gpp;
        $qResult = self::$dblink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $gpp;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getGroups: group info page couldn\'t be gotten | '.self::$dblink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $groupsNode = $xml->createElement('groups');
        $xml->appendChild($groupsNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $groupNode = $xml->createElement('group');
            $groupsNode->appendChild($groupNode);
            $groupNode->appendChild($xml->createElement('id', $row[0]));
            $groupNode->appendChild($xml->createElement('name', $row[1]));
            $groupNode->appendChild($xml->createElement('time', $row[2]));
            $groupNode->appendChild($xml->createElement('active', $row[3]));
        }
        return $xml;
    }
    
    public static function getAllGroups($orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $rightEntry = array('id', 'group', 'time');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getGroups: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dblink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getGroups: group info page couldn\'t be gotten | '.self::$dblink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $groupsNode = $xml->createElement('groups');
        $xml->appendChild($groupsNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $groupNode = $xml->createElement('group');
            $groupsNode->appendChild($groupNode);
            $groupNode->appendChild($xml->createElement('id', $row[0]));
            $groupNode->appendChild($xml->createElement('name', $row[1]));
            $groupNode->appendChild($xml->createElement('time', $row[2]));
            $groupNode->appendChild($xml->createElement('active', $row[3]));
        }
        return $xml;
    }

    //user methods
    public static function createUser($username, $password, $email, $groupId, $active = TRUE, $log = TRUE) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('createUser: username or email are already in use');
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('createUser: defined group doesn\'t exist');
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
        return (int) $userid;
    }
    
    public static function userExists($username) {
        self::$errid = 0;        self::$errexp = '';
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
        self::$errid = 0;        self::$errexp = '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userExists: incorrect email');
            return FALSE;
        }
        $qId = self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` "
                . "WHERE `email`='$email' ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userExists: can\'t check email existence | '.self::$dblink->error);
            return FALSE;
        }
        if (self::$dblink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function userStatus($userId, $active, $log = TRUE) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userStatus: incorrect user status or group doesn\'t exist');
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
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('moveUserTo: destination group doesn\'t exist');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('moveUserTo: can\'t move user to another group |'.self::$dblink->error);
            return FALSE;
        }
        return (int) self::$dblink->affected_rows;
    }
    
    public static function delUser($userId, $log = TRUE) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delUser: defined user doesn\'t exist');
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
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($userId)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('aboutUser: id must be integer');
            return FALSE;
        }
        $qAbout = self::$dblink->query("SELECT `u`.`username`, `i`.`fullname`, `i`.`email`, `u`.`creationtime`, `u`.`active`, `g`.`id`, `g`.`groupname` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` `i`, `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid`=`g`.`id` "
                . "WHERE `i`.`id`=$userId "
                . "AND `u`.`id`=$userId "
                . "LIMIT 1 ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('aboutUser: something went wrong | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('aboutUser: defined user doesn\'t exist');
            return FALSE;
        }
        $about = $qAbout->fetch_row();
        $xml = new \DOMDocument('1.0', 'utf-8');
        $aboutNode = $xml->createElement('user');
        $xml->appendChild($aboutNode);
        $aboutNode->appendChild($xml->createElement('username', $about[0]));
        $aboutNode->appendChild($xml->createElement('fullname', $about[1]));
        $aboutNode->appendChild($xml->createElement('email', $about[2]));
        $aboutNode->appendChild($xml->createElement('time', $about[3]));
        $aboutNode->appendChild($xml->createElement('active', $about[4]));
        $aboutNode->appendChild($xml->createElement('gid', $about[5]));
        $aboutNode->appendChild($xml->createElement('group', $about[6]));
        return $xml;
    }
    
    public static function userPasswords($userId) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userPasswords: defined user doesn\'t exist');
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
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addPassword: can\'t check defined user | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addPassword: defined user doesn\'t exist');
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
        return (int) $insertId;
    }
    
    public static function delPassword($passwId, $userId) {
        self::$errid = 0;        self::$errexp = '';
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
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delPassword: check incoming parameters');
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
    
    public static function setPassword($passwId, $userId, $password){
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('setPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($passwId) || !is_integer($userId) || !pregPassw($password)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qSalt = self::$dblink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setPassword: can\'t check defined user');
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('setPassword: defined user doesn\'t exist');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $passwHash = passwHash($password, $salt);
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                . "SET `password`='$passwHash' "
                . "WHERE `id`=$passwId "
                . "AND `userid`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setPassword: can\'t update password | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setPassword: defined password doesn\'t exist or password was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setUserName($userId, $username) {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('setUserName: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !pregUName($username)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setUserName: incorrect incoming parameters');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `username`='$username' "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setUserName: can\'t set username | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setUserName: defined user doesn\'t exist or username was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setUserMail($userId, $email) {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);            self::setErrExp('setUserMail: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setUserMail: incorrect incoming parameters');
            return FALSE;
        }
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `email`='$email' "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setUserMail: can\'t set email | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setUserMail: defined user doesn\'t exist or email was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setFullName($userId, $name) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($userId) || !is_string($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setFullName: incorrect incoming parameters');
            return FALSE;
        }
        $name = self::$dblink->real_escape_string($name);
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `fullname`='$name' "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setFullName: can\'t set name | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('setFullName: defined user doesn\'t exist or name was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function changePassword($passwId, $userId, $oldPassw, $newPassw){
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($passwId) || !is_integer($userId) || !pregPassw($oldPassw) || !pregPassw($newPassw)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('changePassword: incorrect incoming parameters');
            return FALSE;
        }
        $qSalt = self::$dblink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('changePassword: can\'t check defined user | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('changePassword: defined user doesn\'t exist');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $oldPasswHash = passwHash($oldPassw, $salt);
        $newPasswHash = passwHash($newPassw, $salt);
        if (isset($_SESSION['core_auth_limited']) && $_SESSION['core_auth_limited']) {
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`=$passwId "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' "
                    . "AND `limited`=1 ;");
        }
        else {
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`=$passwId "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' ;");
        }
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('changePassword: can\'t update password | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);            self::setErrExp('changePassword: defined password doesn\'t exist, new password repeats existing, was received invalid old password or usage of limited authentication');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function sumUsers($upp = 20) { // upp - users per page
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($upp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumUsers: value of users per page must be integer');
            return FALSE;
        }
        if ($upp < 1) {
            $upp = 1;
        }
        $qResult = self::$dblink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_users` ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumUsers: total users couldn\'t be counted | '.self::$dblink->error);
            return FALSE;
        }
        list($totalUsers) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalUsers/$upp;
        $remainer = fmod($totalUsers, $upp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array((int) $totalUsers, (int) $totalPages);
    }
    
    public static function getUsers($pageNumber, $totalUsers, $upp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($pageNumber) || !is_integer($totalUsers) || !is_integer($upp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getUsers: values of $pageNumber, $totalUsers, $upp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'username', 'time', 'name', 'email', 'group', 'gid', 'active');
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getUsers: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalUsers && $totalUsers) {
            $pageNumber = $totalUsers;
        }
        if ($totalUsers < 1) {
            $totalUsers = 1;
        }
        if ($upp < 1) {
            $upp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $start = ($pageNumber - 1) * $upp;
        $qResult = self::$dblink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `name`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $upp;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getUsers: user info page couldn\'t be gotten | '.self::$dblink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $usersNode = $xml->createElement('users');
        $xml->appendChild($usersNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $userNode = $xml->createElement('user');
            $usersNode->appendChild($userNode);
            $userNode->appendChild($xml->createElement('id', $row[0]));
            $userNode->appendChild($xml->createElement('username', $row[1]));
            $userNode->appendChild($xml->createElement('fullname', $row[2]));
            $userNode->appendChild($xml->createElement('email', $row[3]));
            $userNode->appendChild($xml->createElement('group', $row[4]));
            $userNode->appendChild($xml->createElement('gid', $row[5]));
            $userNode->appendChild($xml->createElement('time', $row[6]));
            $userNode->appendChild($xml->createElement('active', $row[7]));
        }
        return $xml;
    }
    
    public static function getAllUsers($orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $rightEntry = array('id', 'username', 'time', 'name', 'email', 'group', 'gid', 'active');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllUsers: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dblink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `name`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getUsers: user info page couldn\'t be gotten | '.self::$dblink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $usersNode = $xml->createElement('users');
        $xml->appendChild($usersNode);
        while ($row = $qResult->fetch_array(MYSQL_NUM)) {
            $userNode = $xml->createElement('user');
            $usersNode->appendChild($userNode);
            $userNode->appendChild($xml->createElement('id', $row[0]));
            $userNode->appendChild($xml->createElement('username', $row[1]));
            $userNode->appendChild($xml->createElement('fullname', $row[2]));
            $userNode->appendChild($xml->createElement('email', $row[3]));
            $userNode->appendChild($xml->createElement('group', $row[4]));
            $userNode->appendChild($xml->createElement('gid', $row[5]));
            $userNode->appendChild($xml->createElement('time', $row[6]));
            $userNode->appendChild($xml->createElement('active', $row[7]));
        }
        return $xml;
    }
}
