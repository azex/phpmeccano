<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [auth.php].
 *     Copyright (C) 2015  Alexei Muzarov
 * 
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 * 
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 * 
 *     You should have received a copy of the GNU General Public License along
 *     with this program; if not, write to the Free Software Foundation, Inc.,
 *     51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 *     e-mail: azexmail@gmail.com
 *     e-mail: azexmail@mail.ru
 *     https://bitbucket.org/azexmail/phpmeccano
 */

namespace core;

require_once 'logman.php';

interface intAuth {
    public function __construct(LogMan $logObject);
    public function userLogin($username, $password, $useCookie = TRUE, $cookieTime = 'month', $log = TRUE, $blockBrute = FALSE);
    public function isSession();
    public function userLogout();
    public function getSession($log = TRUE);
}

class Auth extends ServiceMethods implements intAuth {
    private $dbLink; // database link
    private $logObject; // log object
    
    public function __construct(LogMan $logObject) {
        if (!session_id()) {
            session_start();
        }
        $this->dbLink = $logObject->dbLink;
        $this->logObject = $logObject;
    }
    
    public function userLogin($username, $password, $useCookie = TRUE, $cookieTime = 'month', $log = TRUE, $blockBrute = FALSE) {
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
        list($userId, $salt, $lang, $direction) = $qResult->fetch_row();
        $passwEncoded = passwHash($password, $salt);
        // check whether password is valid
        $qResult = $this->dbLink->query("SELECT `u`.`username`, `p`.`id`, `p`.`limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `u`.`id`=`p`.`userid` "
                . "WHERE `u`.`id`=$userId "
                . "AND `p`.`password`='$passwEncoded' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to confirm password -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            if ($blockBrute) {
                // check whether authentication is not blocked
                $qResult = $this->dbLink->query("SELECT `b`.`counter`, TO_SECONDS(`b`.`tempblock`), TO_SECONDS(CURRENT_TIMESTAMP) "
                        . "FROM `".MECCANO_TPREF."_core_userman_temp_block` `b` "
                        . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u`"
                        . "ON `b`.`id`=`u`.`id` "
                        . "WHERE `u`.`id`=$userId "
                        . "AND `b`.`tempblock` < CURRENT_TIMESTAMP ;");
                // authentication is blocked
                if (!$this->dbLink->affected_rows) {
                    $this->setError(ERROR_RESTRICTED_ACCESS, 'userLogin: user authentication is blocked temporarily');
                    return FALSE;
                }
                else {
                    list($counter, $tempblock, $now) = $qResult->fetch_row();
                    $blockperiod = strtotime(MECCANO_AUTH_BLOCK_PERIOD) - strtotime('TODAY');
                    $difference = $now - $tempblock;
                    // reset counter if incorrect password has not been put more than MECCANO_AUTH_BLOCK_PERIOD
                    if ($difference > $blockperiod) {
                        $counter = 1;
                    }
                    // block authentication
                    if ($counter == MECCANO_AUTH_LIMIT) {
                        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_temp_block` "
                                . "SET `tempblock`=ADDTIME(CURRENT_TIMESTAMP, '".MECCANO_AUTH_BLOCK_PERIOD."'), "
                                . "`counter`=1 "
                                . "WHERE `id`=$userId;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to block user authentication -> '.$this->dbLink->error);
                            return FALSE;
                        }
                        $this->setError(ERROR_RESTRICTED_ACCESS, 'userLogin: user authentication is blocked temporarily');
                        return FALSE;
                    }
                    // raise counter
                    else {
                        $counter += 1;
                        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_temp_block` "
                                . "SET `tempblock`=SUBTIME(CURRENT_TIMESTAMP, '00:00:01'), "
                                . "`counter`=$counter "
                                . "WHERE `id`=$userId;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to block user authentication -> '.$this->dbLink->error);
                            return FALSE;
                        }
                    }
                }
            }
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: invalid password');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_temp_block` "
                . "SET `counter`=1 "
                . "WHERE `id`=$userId;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to reset blocking counter -> '.$this->dbLink->error);
            return FALSE;
        }
        list($username, $passId, $limited) = $qResult->fetch_row();
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
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if ($log && !$this->logObject->newRecord('core', 'auth_session', "name: $username; ID: $userId; IP: $ipAddress; User-agent: $userAgent")) {
            $this->setError(ERROR_NOT_CRITICAL, "userLogin: -> ".$this->logObject->errExp());
        }
        $_SESSION[AUTH_USERNAME] = $username;
        $_SESSION[AUTH_USER_ID] = (int) $userId;
        $_SESSION[AUTH_LIMITED] = (int) $limited;
        $_SESSION[AUTH_LANGUAGE] = $lang;
        $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
        // control parameters
        $_SESSION[AUTH_UNIQUE_SESSION_ID] = $usi;
        $_SESSION[AUTH_PASSWORD_ID] = (int) $passId;
        $_SESSION[AUTH_IP] = $ipAddress;
        $_SESSION[AUTH_USER_AGENT] = $userAgent;
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
            $qResult = $this->dbLink->query("SELECT `l`.`code`, `l`.`dir`, `u`.`username`, `g`.`groupname`, `u`.`id`, `p`.`password` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`userid`=`u`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "ON `s`.`id`=`p`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l`"
                    . "ON `l`.`id`=`u`.`langid` "
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
            $userData = $qResult->fetch_row();
            $_SESSION[AUTH_LANGUAGE] = $userData[0];
            $_SESSION[AUTH_LANGUAGE_DIR] = $userData[1];
            $_SESSION[AUTH_USERNAME] = $userData[2];
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
    
    public function getSession($log = TRUE) {
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
            list($passId, $limited, $userId, $username, $lang, $direction) = $qResult->fetch_row();
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            if ($log && !$this->logObject->newRecord('core', 'auth_session', "name: $username; ID: $userId; IP: $ipAddress; User-agent: $userAgent")) {
                $this->setError(ERROR_NOT_CRITICAL, "getSession: -> ".$this->logObject->errExp());
            }
            $_SESSION[AUTH_USERNAME] = $username;
            $_SESSION[AUTH_USER_ID] = (int) $userId;
            $_SESSION[AUTH_LIMITED] = (int) $limited;
            $_SESSION[AUTH_LANGUAGE] = $lang;
            $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
            // control parameters
            $_SESSION[AUTH_UNIQUE_SESSION_ID] = $_COOKIE[AUTH_UNIQUE_SESSION_ID];
            $_SESSION[AUTH_PASSWORD_ID] = (int) $passId;
            $_SESSION[AUTH_IP] = $ipAddress;
            $_SESSION[AUTH_USER_AGENT] = $userAgent;
            $_SESSION[AUTH_TOKEN] = makeIdent($username);
            return TRUE;
        }
        return FALSE;
    }
}
