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
    
    public static function userLogin($username, $password, $useLog = FALSE, $useCookie = TRUE, $cookieTime = 'mounth') {
        if (!pregUName($username)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('username can contain only letters and numbers and has length from 3 to 20');
            return FALSE;
        }
        if (!pregPassw($password)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('password can contain only letters, numbers and common symbols and has length from 8 to 50');
            return FALSE;
        }
        $curTime = time();
        $terms = array('hour' => $curTime+3600,
            'day' => $curTime+86400,
            'week' => $curTime+604800,
            'two-weeks' => $curTime+1209600,
            'mounth' => $curTime+2592000,
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
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('can\'t confirm username | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('invalid username');
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
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('can\'t confirm password | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('invalid password');
            return FALSE;
        }
        list($username, $passId, $limited) = $qResult->fetch_array(MYSQLI_NUM);
        $qResult = self::$dblink->query("SELECT `ip`, `time` "
                . "FROM `".MECCANO_TPREF."_core_auth_iptime` "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('can\'t get ip and time of the last authentication | '.self::$dblink->error);
            return FALSE;
        }
        list($ip, $authTime) = $qResult->fetch_array();
        self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_auth_iptime` "
                . "SET `ip`='".$_SERVER['REMOTE_ADDR']."', `time`=CURRENT_TIMESTAMP "
                . "WHERE `id`=$userId ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('can\'t update authentication record | '.self::$dblink->error);
            return FALSE;
        }
        $usi = makeIdent($username);
        if ($useCookie) {
            $term = $terms[$cookieTime];
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                    . "SET `usi`='$usi', `endtime`=FROM_UNIXTIME($term) "
                    . "WHERE `id`=$passId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('can\'t set unique session identifier | '.self::$dblink->error);
                return FALSE;
            }
            setcookie('core_auth_usi', $usi, $term, '/');
        }
        $_SESSION['core_auth_last_time'] = $authTime;
        $_SESSION['core_auth_last_ip'] = $ip;
        //
        $_SESSION['core_auth_uname'] = $username;
        $_SESSION['core_auth_userid'] = (int) $userId;
        $_SESSION['core_auth_limited'] = $limited;
        // control parameters
        $_SESSION['core_auth_password'] = (int) $passId;
        $_SESSION['core_auth_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['core_auth_uagent'] = $_SERVER['HTTP_USER_AGENT'];
        if ($useLog) {
            Logging::newRecord('core_authLogin', $username);
        }
        return TRUE;
    }
}
