<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logman.php';
require_once 'policy.php';

interface intUserMan {
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject);
    public static function setDbLink(\mysqli $dbLink);
    public static function setLogObject(LogMan $logObject);
    public static function setPolicyObject(Policy $policyObject);
    public static function errId();
    public static function errExp();
    public static function createGroup($groupName, $description, $log = TRUE);
    public static function groupStatus($groupId, $active, $log = TRUE);
    public static function groupExists($groupName);
    public static function moveGroupTo($groupId, $destId);
    public static function aboutGroup($groupId);
    public static function setGroupName($groupId, $groupName);
    public static function setGroupDesc($groupId, $description);
    public static function delGroup($groupId, $log = TRUE);
    public static function sumGroups($rpp = 20);
    public static function getGroups($pageNumber, $totalGroups, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public static function getAllGroups($orderBy = array('id'), $ascent = FALSE);
    public static function createUser($username, $password, $email, $groupId, $active = TRUE, $langCode = MECCANO_DEF_LANG, $log = TRUE);
    public static function userExists($username);
    public static function mailExists($email);
    public static function userStatus($userId, $active, $log = TRUE);
    public static function moveUserTo($userId, $destId);
    public static function delUser($userId, $log = TRUE);
    public static function aboutUser($userId);
    public static function userPasswords($userId);
    public static function addPassword($userId, $password, $description='');
    public static function delPassword($passwId, $userId);
    public static function setPassword($passwId, $userId, $password);
    public static function setUserName($userId, $username, $log = TRUE);
    public static function setUserMail($userId, $email);
    public static function setFullName($userId, $name);
    public static function changePassword($passwId, $userId, $oldPassw, $newPassw);
    public static function sumUsers($rpp = 20);
    public static function getUsers($pageNumber, $totalUsers, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public static function getAllUsers($orderBy = array('id'), $ascent = FALSE);
    public static function setUserLang($userId, $code = MECCANO_DEF_LANG);
}

class UserMan implements intUserMan{
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $logObject; // log object
    private static $policyObject; // policy object
    
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject) {
        self::$dbLink = $dbLink;
        self::$logObject = $logObject;
        self::$policyObject = $policyObject;
    }
    
    public static function setDbLink(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setLogObject(LogMan $logObject) {
        self::$logObject = $logObject;
    }
    
    public static function setPolicyObject(Policy $policyObject) {
        self::$policyObject = $policyObject;
    }
    
    private static function setError($id, $exp) {
        self::$errid = $id;
        self::$errexp = $exp;
    }
    
    private static function zeroizeError() {
        self::$errid = 0;        self::$errexp = '';
    }
    
    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }
    
    //group methods
    public static function createGroup($groupName, $description, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'createGroup: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!pregGName($groupName) || !is_string($description)) {
            self::setError(ERROR_NOT_EXECUTED, 'createGroup: incorect type of incoming parameters');
            return FALSE;
        }
        $description = self::$dbLink->real_escape_string($description);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_groups` (`groupname`, `description`) "
                . "VALUES ('$groupName', '$description') ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'createGroup: group wasn\'t created -> '.self::$dbLink->error);
            return FALSE;
        }
        $groupId = self::$dbLink->insert_id;
        if (!self::$policyObject->addGroup($groupId)) {
            self::setError(ERROR_NOT_EXECUTED, self::$policyObject->errExp());
            return FALSE;
        }
        if ($log && !self::$logObject->newRecord('core', 'createGroup', "$groupName; ID: $groupId")) {
            self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
        }
        return (int) $groupId;
    }
    
    public static function groupStatus($groupId, $active, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'groupStatus: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || $groupId<1) {
            self::setError(ERROR_INCORRECT_DATA, 'groupStatus: group id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $groupId>1) {
            $active = 0;
        }
        else {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'groupStatus: system group cannot be disabled');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `active`=$active "
                . "WHERE `id`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'groupStatus: status was not changed -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'groupStatus: incorrect group status or group does not exist');
            return FALSE;
        }
        if ($log) {
            if ($active) {
                $l = self::$logObject->newRecord('core', 'enGroup', "ID: $groupId");
            }
            else {
                $l = self::$logObject->newRecord('core', 'disGroup', "ID: $groupId");
            }
            if (!$l) {
                self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
            }
        }
        return TRUE;
    }
    
    public static function groupExists($groupName) {
        self::zeroizeError();
        if (!pregGName($groupName)) {
            self::setError(ERROR_INCORRECT_DATA, 'groupExists: incorrect group name');
            return FALSE;
        }
        $qId = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `groupname`='$groupName' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'groupExists: unable to check group existence -> '.self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function moveGroupTo($groupId, $destId) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'moveGroupTo: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_integer($destId) || $destId<1 || $destId == $groupId) {
            self::setError(ERROR_INCORRECT_DATA, 'moveGroupTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($groupId == 1) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'moveGroupTo: unable to move system group');
            return FALSE;
        }
        self::$dbLink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'moveGroupTo: unable to check destination group existence |'.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'moveGroupTo: no one user of the group was not moved');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `groupid`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'moveGroupTo: unable to move users into another group |'.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->affected_rows;
    }
    
    public static function aboutGroup($groupId) {
        self::zeroizeError();
        if (!is_integer($groupId)) {
            self::setError(ERROR_INCORRECT_DATA, 'aboutGroup: identifier must be integer');
            return FALSE;
        }
        $qAbout = self::$dbLink->query("SELECT `groupname`, `description`, `creationtime`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'aboutGroup: something went wrong -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'aboutGroup: defined group not found');
            return FALSE;
        }
        $qSum = self::$dbLink->query("SELECT COUNT(`id`) "
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
    
    public static function setGroupName($groupId, $groupName) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'setGroupName: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !pregGName($groupName)) {
            self::setError(ERROR_INCORRECT_DATA, 'setGroupName: incorrect incoming parameters');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `groupname`='$groupName' "
                . "WHERE `id`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setGroupName: unable to set groupname -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setGroupName: defined group not found or groupname was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setGroupDesc($groupId, $description) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'setGroupDesc: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_string($description)) {
            self::setError(ERROR_INCORRECT_DATA, 'setGroupDesc: incorrect incoming parameters');
            return FALSE;
        }
        $description = self::$dbLink->real_escape_string($description);
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `description`='$description' "
                . "WHERE `id`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setGroupDesc: unable to set description -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setGroupDesc: defined group not found or description was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delGroup($groupId, $log = TRUE) {
        self::zeroizeError();
        if (!is_integer($groupId)) {
            self::setError(ERROR_INCORRECT_DATA, 'delGroup: identifier must be integer');
            return FALSE;
        }
        $qUsers = self::$dbLink->query("SELECT COUNT(`id`) "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `groupid`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delGroup: unable to check existence of users in the group -> '.self::$dbLink->error);
            return FALSE;
        }
        $users = $qUsers->fetch_row();
        if ($users[0]) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'delGroup: the group contains users');
            return FALSE;
        }
        if (!self::$policyObject->delGroup($groupId) && !in_array(self::$policyObject->errId(), array(ERROR_NOT_FOUND, ''))) {
            self::setError(ERROR_INCORRECT_DATA, self::$policyObject->errExp());
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
            $qGroup = self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, 'delGroup: '.self::$dbLink->error);
                return FALSE;
            }
            if (!self::$dbLink->affected_rows) {
                self::setError(ERROR_NOT_FOUND, 'delGroup: defined group not found');
                return FALSE;
            }
            if ($key == 0) {
                list($groupname) = $qGroup->fetch_row();
            }
        }
        if ($log && !self::$logObject->newRecord('core', 'delGroup', "$groupname; ID: $groupId")) {
            self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
        }
        return TRUE;
    }
    
    public static function sumGroups($rpp = 20) { // gpp - groups per page
        self::zeroizeError();
        if (!is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumGroups: value of groups per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumGroups: total users couldn\'t be counted -> '.self::$dbLink->error);
            return FALSE;
        }
        list($totalGroups) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalGroups/$rpp;
        $remainer = fmod($totalGroups, $rpp);
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
    
    public static function getGroups($pageNumber, $totalGroups, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!is_integer($pageNumber) || !is_integer($totalGroups) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getGroups: values of $pageNumber, $totalGroups, $gpp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'time', 'active');
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
            self::setError(ERROR_INCORRECT_DATA, 'getGroups: orderBy must be array');
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
        $qResult = self::$dbLink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getGroups: group info page couldn\'t be gotten -> '.self::$dbLink->error);
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
    
    public static function getAllGroups($orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        $rightEntry = array('id', 'name', 'time', 'active');
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
            self::setError(ERROR_INCORRECT_DATA, 'getGroups: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dbLink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getGroups: group info page couldn\'t be gotten -> '.self::$dbLink->error);
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
    public static function createUser($username, $password, $email, $groupId, $active = TRUE, $langCode = MECCANO_DEF_LANG, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'createUser: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!pregUName($username) || !pregPassw($password) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_integer($groupId) || !pregLang($langCode)) {
            self::setError(ERROR_INCORRECT_DATA, 'createUser: incorrect incoming parameters');
            return FALSE;
        }
        self::$dbLink->query("SELECT `u`.`id`, `i`.`id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u`, `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "WHERE `u`.`username`='$username' "
                . "OR `i`.`email`='$email' "
                . "LIMIT 1;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'createUser: unable to check username and email -> '.self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'createUser: username or email are already in use');
            return FALSE;
        }
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$langCode' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'createUser: unable to check defined language -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'createUser: defined language not found');
            return FALSE;
        }
        list($langId) = $qLang->fetch_row();
        self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'createUser: unable to check group -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'createUser: defined group not found');
            return FALSE;
        }
        $salt = makeSalt($username);
        $passw = passwHash($password, $salt);
        $usi = makeIdent($username);
        if ($active) { $active = 1; }
        else { $active = 0; }
        $sql = array(
            'userid' => "INSERT INTO `".MECCANO_TPREF."_core_userman_users` (`username`, `groupid`, `salt`, `active`, `langid`) "
            . "VALUES ('$username', '$groupId', '$salt', $active, $langId) ;",
            'mail' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userinfo` (`id`, `email`) "
            . "VALUES (LAST_INSERT_ID(), '$email') ;",
            'passw' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`userid`, `password`, `limited`) "
            . "VALUES (LAST_INSERT_ID(), '$passw', 0) ;",
            'usi' => "INSERT INTO `".MECCANO_TPREF."_core_auth_usi` (`id`, `usi`) "
            . "VALUES (LAST_INSERT_ID(), '$usi') ;"
            );
        foreach ($sql as $key => $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, 'createUser: something went wrong -> '.self::$dbLink->error);
                return FALSE;
            }
            if ($key == 'userid') {
                $userid = self::$dbLink->insert_id;
            }
        }
        if ($log && !self::$logObject->newRecord('core', 'createUser', "$username; ID: $userid")) {
            self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
        }
        return (int) $userid;
    }
    
    public static function userExists($username) {
        self::zeroizeError();
        if (!pregUName($username)) {
            self::setError(ERROR_INCORRECT_DATA, 'userExists: incorrect username');
            return FALSE;
        }
        $qId = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `username`='$username' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'userExists: unable to check user existence -> '.self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function mailExists($email) {
        self::zeroizeError();
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::setError(ERROR_INCORRECT_DATA, 'userExists: incorrect email');
            return FALSE;
        }
        $qId = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` "
                . "WHERE `email`='$email' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'userExists: unable to check email existence -> '.self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public static function userStatus($userId, $active, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'userStatus: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || $userId<1) {
            self::setError(ERROR_INCORRECT_DATA, 'userStatus: user id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $userId>1) {
            $active = 0;
        }
        else {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'userStatus: system user can\'t be disabled');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `active`=$active "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'userStatus: status wasn\'t changed -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'userStatus: incorrect user status or group not found');
            return FALSE;
        }
        if ($log) {
            if ($active) {
                $l = self::$logObject->newRecord('core', 'enUser', "ID: $userId");
            }
            else {
                $l = self::$logObject->newRecord('core', 'disUser', "ID: $userId");
            }
            if (!$l) {
                self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
            }
        }
        return TRUE;
    }
    
    public static function moveUserTo($userId, $destId) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'moveUserTo: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !is_integer($destId) || $destId<1 || $destId == $userId) {
            self::setError(ERROR_INCORRECT_DATA, 'moveUserTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($userId == 1) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'moveUserTo: unable to move system user');
            return FALSE;
        }
        self::$dbLink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'moveUserTo: unable to check destination group existence |'.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'moveUserTo: destination group not found');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'moveUserTo: unable to move user into another group |'.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'moveUserTo: user not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delUser($userId, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'delUser: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId)) {
            self::setError(ERROR_INCORRECT_DATA, 'delUser: identifier must be integer');
            return FALSE;
        }
        if ($userId == 1) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'delUser: unable to delete system user');
            return FALSE;
        }
        $qName = self::$dbLink->query("SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delUser: defined user not found');
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
            "DELETE FROM `".MECCANO_TPREF."_core_userman_users` "
            . "WHERE `id`=$userId ;"
        );
        foreach ($sql as $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, 'delUser: something went wrong -> '.self::$dbLink->error);
                return FALSE;
            }
        }
        list($username) = $qName->fetch_row();
        if ($log && !self::$logObject->newRecord('core', 'delUser', "$username; ID: $userId")) {
            self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
        }
        return TRUE;
    }
    
    public static function aboutUser($userId) {
        self::zeroizeError();
        if (!is_integer($userId)) {
            self::setError(ERROR_INCORRECT_DATA, 'aboutUser: id must be integer');
            return FALSE;
        }
        $qAbout = self::$dbLink->query("SELECT `u`.`username`, `i`.`fullname`, `i`.`email`, `u`.`creationtime`, `u`.`active`, `g`.`id`, `g`.`groupname` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` `i`, `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid`=`g`.`id` "
                . "WHERE `i`.`id`=$userId "
                . "AND `u`.`id`=$userId "
                . "LIMIT 1 ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'aboutUser: something went wrong -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'aboutUser: defined user not found');
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
        self::zeroizeError();
        if (!is_integer($userId)) {
            self::setError(ERROR_INCORRECT_DATA, 'userPasswords: id must be integer');
            return FALSE;
        }
        $qPassw = self::$dbLink->query("SELECT `id`, `description`, `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid` = $userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'userPasswords: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'userPasswords: defined user not found');
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
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'addPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !pregPassw($password) || !is_string($description)) {
            self::setError(ERROR_INCORRECT_DATA, 'addPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qHash = self::$dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addPassword: unable to check defined user -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addPassword: defined user not found');
            return FALSE;
        }
        list($salt) = $qHash->fetch_row();
        $passwHash = passwHash($password, $salt);
        // check whether the new password repeates existing password
        self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid`=$userId "
                . "AND `password`='$passwHash' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "addPassword: unable to check uniqueness of the password -> ".self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, "changePassword: password already in use");
            return FALSE;
        }
        $description = self::$dbLink->real_escape_string($description);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`userid`, `password`, `description`, `limited`) "
                . "VALUES($userId, '$passwHash', '$description', 1) ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addPassword: unable to add password -> '.self::$dbLink->error);
            return FALSE;
        }
        $insertId = (int) self::$dbLink->insert_id;
        $usi = makeIdent("$insertId");
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_auth_usi` (`id`, `usi`) "
                . "VALUES($insertId, '$usi') ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addPassword: unable to create unique session identifier -> '.self::$dbLink->error);
            return FALSE;
        }
        return (int) $insertId;
    }
    
    public static function delPassword($passwId, $userId) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'delPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($passwId) || !is_integer($userId)) {
            self::setError(ERROR_INCORRECT_DATA, 'delPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qLimited = self::$dbLink->query("SELECT `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `id`=$passwId "
                . "AND `userid`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_INCORRECT_DATA, 'delPassword: unable to check limitation status of the password -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delPassword: check incoming parameters');
            return FALSE;
        }
        list($limited) = $qLimited->fetch_row();
        if (!$limited) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'delPassword: impossible to delete primary password');
            return FALSE;
        }
        $sql = array("DELETE FROM `".MECCANO_TPREF."_core_auth_usi` "
            . "WHERE `id`=$passwId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `id`=$passwId ;");
        foreach ($sql as $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, 'delPassword: '.self::$dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public static function setPassword($passwId, $userId, $password) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'setPassword: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($passwId) || !is_integer($userId) || !pregPassw($password)) {
            self::setError(ERROR_INCORRECT_DATA, 'setPassword: incorrect incoming parameters');
            return FALSE;
        }
        $qSalt = self::$dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setPassword: unable to check defined user');
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'setPassword: defined user not found');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $passwHash = passwHash($password, $salt);
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                . "SET `password`='$passwHash' "
                . "WHERE `id`=$passwId "
                . "AND `userid`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setPassword: unable to update password -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setPassword: defined password not found or password was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setUserName($userId, $username, $log = TRUE) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'setUserName: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !pregUName($username)) {
            self::setError(ERROR_INCORRECT_DATA, 'setUserName: incorrect incoming parameters');
            return FALSE;
        }
        $qNewName = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `username`='$username' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setUserName: unable to check new name -> '.self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setUserName: new name already in use');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `username`='$username' "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setUserName: unable to set username -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'setUserName: unable to find defined user');
            return FALSE;
        }
        if ($log && !self::$logObject->newRecord('core', 'setUserName', "$username; ID: $userId")) {
            self::setError(ERROR_NOT_CRITICAL, self::$logObject->errExp());
        }
        return TRUE;
    }
    
    public static function setUserMail($userId, $email) {
        self::zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'setUserMail: function execution was terminated because of using of limited authentication');
            return FALSE;
        }
        if (!is_integer($userId) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::setError(ERROR_INCORRECT_DATA, 'setUserMail: incorrect incoming parameters');
            return FALSE;
        }
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `email`='$email' "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setUserMail: unable to set email -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setUserMail: defined user does not exist or email was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setFullName($userId, $name) {
        self::zeroizeError();
        if (!is_integer($userId) || !is_string($name)) {
            self::setError(ERROR_INCORRECT_DATA, 'setFullName: incorrect incoming parameters');
            return FALSE;
        }
        $name = self::$dbLink->real_escape_string($name);
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `fullname`='$name' "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'setFullName: unable to set name -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, 'setFullName: defined user not found or name was repeated');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function changePassword($passwId, $userId, $oldPassw, $newPassw) {
        self::zeroizeError();
        if (!is_integer($passwId) || !is_integer($userId) || !pregPassw($oldPassw) || !pregPassw($newPassw)) {
            self::setError(ERROR_INCORRECT_DATA, 'changePassword: incorrect incoming parameters');
            return FALSE;
        }
        $qSalt = self::$dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'changePassword: unable to check defined user -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'changePassword: defined user not found');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $oldPasswHash = passwHash($oldPassw, $salt);
        $newPasswHash = passwHash($newPassw, $salt);
        // check whether the new password repeates existing password
        self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid`=$userId "
                . "AND `password`='$newPasswHash' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "changePassword: unable to check uniqueness of the new password -> ".self::$dbLink->error);
            return FALSE;
        }
        if (self::$dbLink->affected_rows) {
            self::setError(ERROR_ALREADY_EXISTS, "changePassword: new password already in use");
            return FALSE;
        }
        // change password
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`=$passwId "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' "
                    . "AND `limited`=1 ;");
        }
        else {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`=$passwId "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' ;");
        }
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'changePassword: unable to update password -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'changePassword: defined password not found, it has been received invalid old password, or maybe your authentication is limited');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function sumUsers($rpp = 20) { // rpp - records per page
        self::zeroizeError();
        if (!is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumUsers: value of users per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = self::$dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_users` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumUsers: total users couldn\'t be counted -> '.self::$dbLink->error);
            return FALSE;
        }
        list($totalUsers) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalUsers/$rpp;
        $remainer = fmod($totalUsers, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalUsers, 'pages' => (int) $totalPages);
    }
    
    public static function getUsers($pageNumber, $totalUsers, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!is_integer($pageNumber) || !is_integer($totalUsers) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getUsers: values of $pageNumber, $totalUsers, $upp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'username', 'time', 'fullname', 'email', 'group', 'gid', 'active');
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
            self::setError(ERROR_INCORRECT_DATA, 'getUsers: orderBy must be array');
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
        $qResult = self::$dbLink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `fullname`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getUsers: unable to get user info page -> '.self::$dbLink->error);
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
    
    public static function getAllUsers($orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        $rightEntry = array('id', 'username', 'time', 'fullname', 'email', 'group', 'gid', 'active');
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
            self::setError(ERROR_INCORRECT_DATA, 'getAllUsers: orderBy must be array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = self::$dbLink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `fullname`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getAllUsers: unable to get user info page -> '.self::$dbLink->error);
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
    
    public static function setUserLang($userId, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!is_integer($userId) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, "setUserLang: incorrect argument(s)");
            return FALSE;
        }
        $sql = array(
            "user" => "SELECT `username` "
            . "FROM `".MECCANO_TPREF."_core_userman_users` "
            . "WHERE `id` = $userId ;",
            "language" => "SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_langman_languages` "
            . "WHERE `code` = '$code' ;"
        );
        foreach ($sql as $key => $value) {
            $qCheck = self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, "setUserLang: ".self::$dbLink->error);
                return FALSE;
            }
            if (!self::$dbLink->affected_rows) {
                self::setError(ERROR_NOT_FOUND, "setUserLang: $key not found");
                return FALSE;
            }
        }
        list($codeId) = $qCheck->fetch_row();
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `langid` = $codeId "
                . "WHERE `id`=$userId ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "setUserLang: unable to set user language -> ".self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
}
