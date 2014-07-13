<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logging.php';

class Auth {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dblink; // database link
    
    public function __construct($dblink = FALSE) {
        if (!session_id()) {
            session_start();
        }
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
    
    public static function userLogin($username, $password, $log = FALSE, $useCookie = TRUE, $cookieTime = 'month') {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_userid'])) {
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
        $qResult = self::$dblink->query("SELECT `u`.`id`, `u`.`salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `g`.`id`=`u`.`groupid` "
                . "WHERE `u`.`username`='$username' "
                . "AND `u`.`active`=1 "
                . "AND `g`.`active`=1 ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userLogin: can\'t confirm username | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userLogin: invalid username or user (group) is disabled');
            return FALSE;
        }
        list($userId, $salt) = $qResult->fetch_array(MYSQL_NUM);
        $passwEncoded = passwHash($password, $salt);
        $qResult = self::$dblink->query("SELECT `u`.`username`, `p`.`id`, `p`.`limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `u`.`id`=`p`.`userid` "
                . "WHERE `u`.`id`=$userId AND `p`.`password`='$passwEncoded' ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('userLogin: can\'t confirm password | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('userLogin: invalid password');
            return FALSE;
        }
        list($username, $passId, $limited) = $qResult->fetch_array(MYSQLI_NUM);
        $usi = makeIdent($username);
        if ($useCookie) {
            $term = $terms[$cookieTime];
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                    . "SET `usi`='$usi', `endtime`=FROM_UNIXTIME($term) "
                    . "WHERE `id`=$passId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogin: can\'t set unique session identifier | '.self::$dblink->error);
                return FALSE;
            }
            setcookie('core_auth_usi', $usi, $term, '/');
        }
        if ($log) {
            Logging::newRecord('core_authLogin', $username);
        }
        $_SESSION['core_auth_uname'] = $username;
        $_SESSION['core_auth_userid'] = (int) $userId;
        $_SESSION['core_auth_limited'] = (int) $limited;
        // control parameters
        $_SESSION['core_auth_usi'] = $usi;
        $_SESSION['core_auth_password'] = (int) $passId;
        $_SESSION['core_auth_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['core_auth_uagent'] = $_SERVER['HTTP_USER_AGENT'];
        return TRUE;
    }
    
    public static function isSession() {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_userid'])) {
            if ($_SESSION['core_auth_ip'] != $_SERVER['REMOTE_ADDR'] || $_SESSION['core_auth_uagent'] != $_SERVER['HTTP_USER_AGENT']) {
                self::userLogout();
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('isSession: session probably is stolen');
                return FALSE;
            }
            $qResult = self::$dblink->query("SELECT `g`.`groupname`, `u`.`id`, `p`.`password` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`userid`=`u`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "ON `s`.`id`=`p`.`id` "
                    . "WHERE `u`.`id`=".$_SESSION['core_auth_userid']." "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 "
                    . "AND `p`.`id`=".$_SESSION['core_auth_password']." "
                    . "AND `s`.`usi`='".$_SESSION['core_auth_usi']."' ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('isSession: can\'t check user availability | '.self::$dblink->error);
                return FALSE;
            }
            if (!self::$dblink->affected_rows) {
                self::userLogout();
                return FALSE;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    public static function userLogout() {
        self::$errid = 0;        self::$errexp = '';
        if (isset($_SESSION['core_auth_userid'])) {
            $qResult = self::$dblink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` "
                    . "WHERE `usi`='".$_SESSION['core_auth_usi']."' ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogout: can\'t check unique session identifier | '.self::$dblink->error);
                return FALSE;
            }
            if (self::$dblink->affected_rows) {
                $usi = makeIdent($_SESSION['core_auth_uname']);
                self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                        . "SET `usi`='$usi' "
                        . "WHERE `id`=".$_SESSION['core_auth_password']." ;");
                if (self::$dblink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('userLogout: can\'t reset unique session identifier | '.self::$dblink->error);
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
        if (!isset($_SESSION['core_auth_userid']) && isset($_COOKIE['core_auth_usi']) && pregIdent($_COOKIE['core_auth_usi'])) {
            $qResult = self::$dblink->query("SELECT `p`.`id`, `p`.`limited`, `u`.`id`, `u`.`username` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`id`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `u`.`id`=`p`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "WHERE `s`.`usi`='".$_COOKIE['core_auth_usi']."' "
                    . "AND `s`.`endtime`>NOW() "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('getSession: can\'t get user data | '.self::$dblink->error);
                return FALSE;
            }
            if (!self::$dblink->affected_rows) {
                return FALSE;
            }
            list($passId, $limited, $userId, $username) = $qResult->fetch_array(MYSQLI_NUM);
            if ($log) {
                Logging::newRecord('core_authLogin', $username);
            }
            $_SESSION['core_auth_uname'] = $username;
            $_SESSION['core_auth_userid'] = (int) $userId;
            $_SESSION['core_auth_limited'] = (int) $limited;
            // control parameters
            $_SESSION['core_auth_usi'] = $_COOKIE['core_auth_usi'];
            $_SESSION['core_auth_password'] = (int) $passId;
            $_SESSION['core_auth_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['core_auth_uagent'] = $_SERVER['HTTP_USER_AGENT'];
            return TRUE;
        }
        return FALSE;
    }
}
