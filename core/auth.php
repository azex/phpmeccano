<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logman.php';

interface intAuth {
    public function __construct(\mysqli $dbLink, LogMan $logObject);
    public function errId();
    public function errExp();
    public function userLogin($username, $password, $log = FALSE, $useCookie = TRUE, $cookieTime = 'month');
    public function isSession();
    public function userLogout();
    public function getSession($log = FALSE);
}

class Auth implements intAuth {
    private $errid = 0; // error's id
    private $errexp = ''; // error's explanation
    private $dbLink; // database link
    private $logObject; // log object
    
    public function __construct(\mysqli $dbLink, LogMan $logObject) {
        if (!session_id()) {
            session_start();
        }
        $this->dbLink = $dbLink;
        $this->logObject = $logObject;
    }
    
    private function setError($id, $exp) {
        $this->errid = $id;
        $this->errexp = $exp;
    }
    
    private function zeroizeError() {
        $this->errid = 0;        $this->errexp = '';
    }
    
    public function errId() {
        return $this->errid;
    }
    
    public function errExp() {
        return $this->errexp;
    }
    
    public function userLogin($username, $password, $log = FALSE, $useCookie = TRUE, $cookieTime = 'month') {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: finish current session before starting new');
            return FALSE;
        }
        if (!pregUName($username)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: username can contain only letters and numbers and has length from 3 to 20');
            return FALSE;
        }
        if (!pregPassw($password)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: password can contain only letters, numbers and common symbols and has length from 8 to 50');
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
        $qResult = $this->dbLink->query("SELECT `u`.`id`, `u`.`salt`, `l`.`code`, `l`.`dir` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `g`.`id`=`u`.`groupid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `u`.`langid`=`l`.`id` "
                . "WHERE `u`.`username`='$username' "
                . "AND `u`.`active`=1 "
                . "AND `g`.`active`=1 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to confirm username -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'userLogin: invalid username or user (group) is disabled');
            return FALSE;
        }
        list($userId, $salt, $lang, $direction) = $qResult->fetch_array(MYSQL_NUM);
        $passwEncoded = passwHash($password, $salt);
        $qResult = $this->dbLink->query("SELECT `u`.`username`, `p`.`id`, `p`.`limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `u`.`id`=`p`.`userid` "
                . "WHERE `u`.`id`=$userId AND `p`.`password`='$passwEncoded' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to confirm password -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'userLogin: invalid password');
            return FALSE;
        }
        list($username, $passId, $limited) = $qResult->fetch_array(MYSQLI_NUM);
        $usi = makeIdent($username);
        if ($useCookie) {
            $term = $terms[$cookieTime];
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                    . "SET `usi`='$usi', `endtime`=FROM_UNIXTIME($term) "
                    . "WHERE `id`=$passId ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to set unique session identifier -> '.$this->dbLink->error);
                return FALSE;
            }
            setcookie(COOKIE_UNIQUE_SESSION_ID, $usi, $term, '/');
        }
        if ($log) {
            $this->logObject->newRecord('core', 'authLogin', $username);
        }
        $_SESSION[AUTH_USERNAME] = $username;
        $_SESSION[AUTH_USER_ID] = (int) $userId;
        $_SESSION[AUTH_LIMITED] = (int) $limited;
        $_SESSION[AUTH_LANGUAGE] = $lang;
        $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
        // control parameters
        $_SESSION[AUTH_UNIQUE_SESSION_ID] = $usi;
        $_SESSION[AUTH_PASSWORD_ID] = (int) $passId;
        $_SESSION[AUTH_IP] = $_SERVER['REMOTE_ADDR'];
        $_SESSION[AUTH_USER_AGENT] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION[AUTH_TOKEN] = makeIdent($username);
        return TRUE;
    }
    
    public function isSession() {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            if ($_SESSION[AUTH_IP] != $_SERVER['REMOTE_ADDR'] || $_SESSION[AUTH_USER_AGENT] != $_SERVER['HTTP_USER_AGENT']) {
                $this->userLogout();
                $this->setError(ERROR_NOT_EXECUTED, 'isSession: session probably is stolen');
                return FALSE;
            }
            $qResult = $this->dbLink->query("SELECT `g`.`groupname`, `u`.`id`, `p`.`password` "
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
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'isSession: unable to check user availability -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->userLogout();
                return FALSE;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    public function userLogout() {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            $qResult = $this->dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` "
                    . "WHERE `usi`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userLogout: unable to check unique session identifier -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $usi = makeIdent($_SESSION[AUTH_USERNAME]);
                $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_auth_usi` "
                        . "SET `usi`='$usi' "
                        . "WHERE `id`=".$_SESSION[AUTH_PASSWORD_ID]." ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'userLogout: unable to reset unique session identifier -> '.$this->dbLink->error);
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
    
    public function getSession($log = FALSE) {
        $this->zeroizeError();
        if (!isset($_SESSION[AUTH_USER_ID]) && isset($_COOKIE[AUTH_UNIQUE_SESSION_ID]) && pregIdent($_COOKIE[AUTH_UNIQUE_SESSION_ID])) {
            $qResult = $this->dbLink->query("SELECT `p`.`id`, `p`.`limited`, `u`.`id`, `u`.`username`, `l`.`code`, `l`.`dir` "
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
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getSession: unable to get user data -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                return FALSE;
            }
            list($passId, $limited, $userId, $username, $lang, $direction) = $qResult->fetch_array(MYSQLI_NUM);
            if ($log) {
                $this->logObject->newRecord('core', 'authLogin', $username);
            }
            $_SESSION[AUTH_USERNAME] = $username;
            $_SESSION[AUTH_USER_ID] = (int) $userId;
            $_SESSION[AUTH_LIMITED] = (int) $limited;
            $_SESSION[AUTH_LANGUAGE] = $lang;
            $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
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
