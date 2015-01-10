<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logging.php';

class Auth {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $logObject; // log object
    
    public function __construct($dbLink, $logObject) {
        if (!session_id()) {
            session_start();
        }
        self::$dbLink = $dbLink;
        self::$logObject = $logObject;
    }
    
    public static function setDbLink($dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setLogObject($logObject) {
        self::$logObject = $logObject;
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
    
    public static function userLogin($username, $password, $log = FALSE, $useCookie = TRUE, $cookieTime = 'month') {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION[AUTH_USER_ID])) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userLogin: finish current session before starting new');
            return FALSE;
        }
        if (!pregUName($username)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userLogin: username can contain only letters and numbers and has length from 3 to 20');
            return FALSE;
        }
        if (!pregPassw($password)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('userLogin: password can contain only letters, numbers and common symbols and has length from 8 to 50');
            return FALSE;
        }
        $curTime = time();
        $terms = array('hour' => $curTime+3600,
            'day' => $curTime+86400,
            'week' => $curTime+604800,
            'two-weeks' => $curTime+1209600,
            'month' => $curTime+2592000,
            'half-year' => $curTime+15552000,
            'year' => $curTime+31536000);
        if (!isset($terms[$cookieTime])) {
            $useCookie = FALSE;
        }
        $qResult = self::$dbLink->query("SELECT `u`.`id`, `u`.`salt`, `l`.`code` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `g`.`id`=`u`.`groupid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `u`.`langid`=`l`.`id` "
                . "WHERE `u`.`username`='$username' "
                . "AND `u`.`active`=1 "
                . "AND `g`.`active`=1 ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userLogin: can\'t confirm username | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userLogin: invalid username or user (group) is disabled');
            return FALSE;
        }
        list($userId, $salt, $lang) = $qResult->fetch_array(MYSQL_NUM);
        $passwEncoded = passwHash($password, $salt);
        $qResult = self::$dbLink->query("SELECT `u`.`username`, `p`.`id`, `p`.`limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `u`.`id`=`p`.`userid` "
                . "WHERE `u`.`id`=$userId AND `p`.`password`='$passwEncoded' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userLogin: can\'t confirm password | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userLogin: invalid password');
            return FALSE;
        }
        list($username, $passId, $limited) = $qResult->fetch_array(MYSQLI_NUM);
        $usi = makeIdent($username);
        if ($useCookie) {
            $term = $terms[$cookieTime];
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                    . "SET `usi`='$usi', `endtime`=FROM_UNIXTIME($term) "
                    . "WHERE `id`=$passId ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogin: can\'t set unique session identifier | '.self::$dbLink->error);
                return FALSE;
            }
            setcookie(COOKIE_UNIQUE_SESSION_ID, $usi, $term, '/');
        }
        if ($log) {
            self::$logObject->newRecord('core_authLogin', $username);
        }
        $_SESSION[AUTH_USERNAME] = $username;
        $_SESSION[AUTH_USER_ID] = (int) $userId;
        $_SESSION[AUTH_LIMITED] = (int) $limited;
        $_SESSION[AUTH_LANGUAGE] = $lang;
        // control parameters
        $_SESSION[AUTH_UNIQUE_SESSION_ID] = $usi;
        $_SESSION[AUTH_PASSWORD_ID] = (int) $passId;
        $_SESSION[AUTH_IP] = $_SERVER['REMOTE_ADDR'];
        $_SESSION[AUTH_USER_AGENT] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION[AUTH_TOKEN] = makeIdent($username);
        return TRUE;
    }
    
    public static function isSession() {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION[AUTH_USER_ID])) {
            if ($_SESSION[AUTH_IP] != $_SERVER['REMOTE_ADDR'] || $_SESSION[AUTH_USER_AGENT] != $_SERVER['HTTP_USER_AGENT']) {
                self::userLogout();
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('isSession: session probably is stolen');
                return FALSE;
            }
            $qResult = self::$dbLink->query("SELECT `g`.`groupname`, `u`.`id`, `p`.`password` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`userid`=`u`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "ON `s`.`id`=`p`.`id` "
                    . "WHERE `u`.`id`=".$_SESSION[AUTH_USER_ID]." "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 "
                    . "AND `p`.`id`=".$_SESSION[AUTH_PASSWORD_ID]." "
                    . "AND `s`.`usi`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('isSession: can\'t check user availability | '.self::$dbLink->error);
                return FALSE;
            }
            if (!self::$dbLink->affected_rows) {
                self::userLogout();
                return FALSE;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    public static function userLogout() {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION[AUTH_USER_ID])) {
            $qResult = self::$dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` "
                    . "WHERE `usi`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogout: can\'t check unique session identifier | '.self::$dbLink->error);
                return FALSE;
            }
            if (self::$dbLink->affected_rows) {
                $usi = makeIdent($_SESSION[AUTH_USERNAME]);
                self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                        . "SET `usi`='$usi' "
                        . "WHERE `id`=".$_SESSION[AUTH_PASSWORD_ID]." ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogout: can\'t reset unique session identifier | '.self::$dbLink->error);
                    return FALSE;
                }
            }
            if (isset($_COOKIE)) {
                foreach ($_COOKIE as $key => $value) {
                    setcookie($key, '', (time() - 3600), '/');
                }
            }
            session_unset(); session_destroy();
            return TRUE;
        }
        return FALSE;
    }
    
    public static function getSession($log = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!isset($_SESSION[AUTH_USER_ID]) && isset($_COOKIE[AUTH_UNIQUE_SESSION_ID]) && pregIdent($_COOKIE[AUTH_UNIQUE_SESSION_ID])) {
            $qResult = self::$dbLink->query("SELECT `p`.`id`, `p`.`limited`, `u`.`id`, `u`.`username`, `l`.`code` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`id`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `u`.`id`=`p`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `u`.`langid`=`l`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "WHERE `s`.`usi`='".$_COOKIE[AUTH_UNIQUE_SESSION_ID]."' "
                    . "AND `s`.`endtime`>NOW() "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('getSession: can\'t get user data | '.self::$dbLink->error);
                return FALSE;
            }
            if (!self::$dbLink->affected_rows) {
                return FALSE;
            }
            list($passId, $limited, $userId, $username, $lang) = $qResult->fetch_array(MYSQLI_NUM);
            if ($log) {
                self::$logObject->newRecord('core_authLogin', $username);
            }
            $_SESSION[AUTH_USERNAME] = $username;
            $_SESSION[AUTH_USER_ID] = (int) $userId;
            $_SESSION[AUTH_LIMITED] = (int) $limited;
            $_SESSION[AUTH_LANGUAGE] = $lang;
            // control parameters
            $_SESSION[AUTH_UNIQUE_SESSION_ID] = $_COOKIE[AUTH_UNIQUE_SESSION_ID];
            $_SESSION[AUTH_PASSWORD_ID] = (int) $passId;
            $_SESSION[AUTH_IP] = $_SERVER['REMOTE_ADDR'];
            $_SESSION[AUTH_USER_AGENT] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION[AUTH_TOKEN] = makeIdent($username);
            return TRUE;
        }
        return FALSE;
    }
}
