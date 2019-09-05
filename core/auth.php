<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Core module [auth.php].
 *     Copyright (C) 2015-2019  Alexei Muzarov
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

loadPHP('extclass');

interface intAuth {
    public function __construct(\mysqli $dbLink);
    public function userLogin($username, $password, $useCookie = true, $cookieTime = 'month', $log = true, $blockBrute = false, $cleanSessions = true);
    public function login2FA($code);
    public function isSession();
    public function userLogout();
    public function getSession($log = true);
    public function userSessions($userId);
    public function destroyAllSessions($userId);
    public function destroySession($userId, $sesId);
}

class Auth extends ServiceMethods implements intAuth {
    
    public function __construct(\mysqli $dbLink) {
        if (!session_id()) {
            session_start();
        }
        $this->dbLink = $dbLink;
    }
    
    public function userLogin($username, $password, $useCookie = true, $cookieTime = 'month', $log = true, $blockBrute = false, $cleanSessions = true) {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: close current session before to start new');
            return false;
        }
        if (!pregUName($username) && !pregMail($username)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: username can contain only letters and numbers and has length from 3 to 20 or you should use e-mail instead of username');
            return false;
        }
        if (!pregPassw($password)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: password can contain only letters, numbers and common symbols and has length from 8 to 50');
            return false;
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
            $useCookie = false;
        }
        if (pregUName($username)) {
            $qResult = $this->dbLink->query("SELECT `u`.`id`, `u`.`salt`, `l`.`code`, `l`.`dir` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `u`.`langid`=`l`.`id` "
                    . "WHERE `u`.`username`='$username' "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 ;");
        }
        else {
            $qResult = $this->dbLink->query("SELECT `u`.`id`, `u`.`salt`, `l`.`code`, `l`.`dir` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `u`.`langid`=`l`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                    . "ON `i`.`id`=`u`.`id` "
                    . "WHERE `i`.`email`='$username' "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to confirm username -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'userLogin: invalid username or user (group) is disabled');
            return false;
        }
        list($userId, $salt, $lang, $direction) = $qResult->fetch_row();
        $passwEncoded = passwHash($password, $salt);
        // check whether password is valid
        if ($blockBrute) {
            $checkPassw = "SELECT `u`.`username`, `p`.`id`, `p`.`limited`, `p`.`doubleauth` "
                    . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `u`.`id`=`p`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_temp_block` `b` "
                    . "ON `u`.`id`=`b`.`id` "
                    . "WHERE `u`.`id`=$userId "
                    . "AND `p`.`password`='$passwEncoded' "
                    . "AND `b`.`tempblock` < CURRENT_TIMESTAMP ;";
        }
        else {
            $checkPassw = "SELECT `u`.`username`, `p`.`id`, `p`.`limited`, `p`.`doubleauth` "
                    . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `u`.`id`=`p`.`userid` "
                    . "WHERE `u`.`id`=$userId "
                    . "AND `p`.`password`='$passwEncoded' ;";
        }
        $qResult = $this->dbLink->query($checkPassw);
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to confirm password -> '.$this->dbLink->error);
            return false;
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
                    return false;
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
                            return false;
                        }
                        $this->setError(ERROR_RESTRICTED_ACCESS, 'userLogin: user authentication is blocked temporarily');
                        return false;
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
                            return false;
                        }
                    }
                }
            }
            $this->setError(ERROR_INCORRECT_DATA, 'userLogin: invalid password');
            return false;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_temp_block` "
                . "SET `counter`=1 "
                . "WHERE `id`=$userId;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to reset blocking counter -> '.$this->dbLink->error);
            return false;
        }
        list($username, $passId, $limited, $doubleauth) = $qResult->fetch_row();
        // new unique session id
        $usi = guid();
        // IP and user-agent of the user
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (!$useCookie) {
            $term = $curTime;
        }
        else {
            $term = $terms[$cookieTime];
            setcookie(COOKIE_UNIQUE_SESSION_ID, $usi, $term, '/');
        }
        // record data about the session term
        $sql = array(
            "INSERT INTO `".MECCANO_TPREF."_core_auth_usi` (`id`, `pid`, `endtime`) "
            . "VALUES('$usi', '$passId', FROM_UNIXTIME($term)) ;",
            "INSERT INTO `".MECCANO_TPREF."_core_auth_session_info` (`id`, `ip`, `useragent`, `created`) "
            . "VALUES('$usi', '$ipAddress', '$userAgent', CURRENT_TIMESTAMP) ;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to set unique session identifier -> '.$this->dbLink->error);
                return false;
            }
        }
        if ($cleanSessions) {
            // delete expired sessions of the user
            $sql = array(
                "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
                . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                . "ON `si`.`id`=`s`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `s`.`pid`=`p`.`id`"
                . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `p`.`userid`=`u`.`id` "
                . "WHERE `u`.`id`=$userId "
                . "AND `s`.`endtime`<NOW() ;",
                "DELETE `s` FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `s`.`pid`=`p`.`id`"
                . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `p`.`userid`=`u`.`id` "
                . "WHERE `u`.`id`=$userId "
                . "AND `s`.`endtime`<NOW() ;"
            );
            foreach ($sql as $key => $value) {
                $this->dbLink->query($value);
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'userLogin: unable to delete expired sessions of the user -> '.$this->dbLink->error);
                    return false;
                }
            }
        }
        if ($log && !$this->newLogRecord('core', 'auth_session', "name: $username; ID: $userId; IP: $ipAddress; User-agent: $userAgent")) {
            $this->setError(ERROR_NOT_CRITICAL, "userLogin: -> ".$this->errExp());
        }
        
        // if 2-factor authentication is enabled
        if (((int) $doubleauth)) {
            $code = genPassword(8, false, true, true, false, false, false);
            $_SESSION[AUTH_2FA_SWAP] = array(
                $code => array(
                    AUTH_USERNAME => $username,
                    AUTH_USER_ID => (int) $userId,
                    AUTH_LIMITED => (int) $limited,
                    AUTH_LANGUAGE => $lang,
                    AUTH_LANGUAGE_DIR => $direction,
                    AUTH_UNIQUE_SESSION_ID => $usi,
                    AUTH_PASSWORD_ID => $passId,
                    AUTH_IP => $ipAddress,
                    AUTH_USER_AGENT => $userAgent,
                    AUTH_TOKEN => makeIdent($username),
                )
            );
            return $code;
        }
        // if 2-factor authentication is disabled
        // record the session valiables //
        $_SESSION[AUTH_USERNAME] = $username;
        $_SESSION[AUTH_USER_ID] = (int) $userId;
        $_SESSION[AUTH_LIMITED] = (int) $limited;
        $_SESSION[AUTH_LANGUAGE] = $lang;
        $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
        // control parameters
        $_SESSION[AUTH_UNIQUE_SESSION_ID] = $usi;
        $_SESSION[AUTH_PASSWORD_ID] = $passId;
        $_SESSION[AUTH_IP] = $ipAddress;
        $_SESSION[AUTH_USER_AGENT] = $userAgent;
        $_SESSION[AUTH_TOKEN] = makeIdent($username);
        return true;
    }
    
    public function login2FA($code) {
        $this->zeroizeError();
        if (is_string($code) && preg_match('/^[A-Z\d]{8}$/', $code)) {
            if (isset($_SESSION[AUTH_2FA_SWAP]) && isset($_SESSION[AUTH_2FA_SWAP][$code])) {
                // record the session valiables //
                $_SESSION[AUTH_USERNAME] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_USERNAME];
                $_SESSION[AUTH_USER_ID] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_USER_ID];
                $_SESSION[AUTH_LIMITED] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_LIMITED];
                $_SESSION[AUTH_LANGUAGE] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_LANGUAGE];
                $_SESSION[AUTH_LANGUAGE_DIR] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_LANGUAGE_DIR];
                // control parameters
                $_SESSION[AUTH_UNIQUE_SESSION_ID] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_UNIQUE_SESSION_ID];
                $_SESSION[AUTH_PASSWORD_ID] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_PASSWORD_ID];
                $_SESSION[AUTH_IP] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_IP];
                $_SESSION[AUTH_USER_AGENT] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_USER_AGENT];
                $_SESSION[AUTH_TOKEN] = $_SESSION[AUTH_2FA_SWAP][$code][AUTH_TOKEN];
                unset($_SESSION[AUTH_2FA_SWAP]);
                return true;
            }
            else {
                $this->setError(ERROR_NOT_FOUND, 'login2FA: the code is not found');
                return false;
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'login2FA: incorrect code format');
            return false;
        }
    }
    
    public function isSession() {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            if ($_SESSION[AUTH_IP] != $_SERVER['REMOTE_ADDR'] || $_SESSION[AUTH_USER_AGENT] != $_SERVER['HTTP_USER_AGENT']) {
                $this->userLogout();
                $this->setError(ERROR_NOT_EXECUTED, 'isSession: probably the session is stolen');
                return false;
            }
            $qResult = $this->dbLink->query("SELECT `l`.`code`, `l`.`dir`, `u`.`username`, `g`.`groupname`, `u`.`id`, `p`.`password` "
                    . "FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`userid`=`u`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "ON `s`.`pid`=`p`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l`"
                    . "ON `l`.`id`=`u`.`langid` "
                    . "WHERE `u`.`id`=".$_SESSION[AUTH_USER_ID]." "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 "
                    . "AND `p`.`id`='".$_SESSION[AUTH_PASSWORD_ID]."' "
                    . "AND `s`.`id`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'isSession: unable to check user availability -> '.$this->dbLink->error);
                return false;
            }
            if (!$this->dbLink->affected_rows) {
                $this->userLogout();
                return false;
            }
            $userData = $qResult->fetch_row();
            $_SESSION[AUTH_LANGUAGE] = $userData[0];
            $_SESSION[AUTH_LANGUAGE_DIR] = $userData[1];
            $_SESSION[AUTH_USERNAME] = $userData[2];
            return true;
        }
        return false;
    }
    
    public function userLogout() {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_USER_ID])) {
            $qResult = $this->dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` "
                    . "WHERE `id`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userLogout: unable to check unique session identifier -> '.$this->dbLink->error);
                return false;
            }
            if ($this->dbLink->affected_rows) {
                $sql = array(
                    "DELETE FROM `".MECCANO_TPREF."_core_auth_session_info` "
                    . "WHERE `id`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;", 
                    "DELETE FROM `".MECCANO_TPREF."_core_auth_usi` "
                    . "WHERE `id`='".$_SESSION[AUTH_UNIQUE_SESSION_ID]."' ;"
                );
                foreach ($sql as $key => $value) {
                    $this->dbLink->query($value);
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'userLogout: unable to delete session identifier -> '.$this->dbLink->error);
                        return false;
                    }
                    if (!$this->dbLink->affected_rows) {
                        $this->setError(ERROR_NOT_FOUND, 'userLogout: session is not found');
                        return false;
                    }
                }
            }
            if (isset($_COOKIE)) {
                foreach ($_COOKIE as $key => $value) {
                    setcookie($key, '', (time() - 3600), '/');
                }
            }
            session_unset(); session_destroy();
            return true;
        }
        return false;
    }
    
    public function getSession($log = true) {
        $this->zeroizeError();
        if (!isset($_SESSION[AUTH_USER_ID]) && isset($_COOKIE[AUTH_UNIQUE_SESSION_ID]) && pregGuid($_COOKIE[AUTH_UNIQUE_SESSION_ID])) {
            $qResult = $this->dbLink->query("SELECT `p`.`id`, `p`.`limited`, `u`.`id`, `u`.`username`, `l`.`code`, `l`.`dir` "
                    . "FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                    . "ON `p`.`id`=`s`.`pid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `u`.`id`=`p`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `u`.`langid`=`l`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "WHERE `s`.`id`='".$_COOKIE[AUTH_UNIQUE_SESSION_ID]."' "
                    . "AND `s`.`endtime`>NOW() "
                    . "AND `u`.`active`=1 "
                    . "AND `g`.`active`=1 ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getSession: unable to get user data -> '.$this->dbLink->error);
                return false;
            }
            if (!$this->dbLink->affected_rows) {
                return false;
            }
            list($passId, $limited, $userId, $username, $lang, $direction) = $qResult->fetch_row();
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            if ($log && !$this->newLogRecord('core', 'auth_session', "name: $username; ID: $userId; IP: $ipAddress; User-agent: $userAgent")) {
                $this->setError(ERROR_NOT_CRITICAL, "getSession: -> ".$this->errExp());
            }
            $_SESSION[AUTH_USERNAME] = $username;
            $_SESSION[AUTH_USER_ID] = (int) $userId;
            $_SESSION[AUTH_LIMITED] = (int) $limited;
            $_SESSION[AUTH_LANGUAGE] = $lang;
            $_SESSION[AUTH_LANGUAGE_DIR] = $direction;
            // control parameters
            $_SESSION[AUTH_UNIQUE_SESSION_ID] = $_COOKIE[AUTH_UNIQUE_SESSION_ID];
            $_SESSION[AUTH_PASSWORD_ID] = $passId;
            $_SESSION[AUTH_IP] = $ipAddress;
            $_SESSION[AUTH_USER_AGENT] = $userAgent;
            $_SESSION[AUTH_TOKEN] = makeIdent($username);
            return true;
        }
        return false;
    }
    
    public function userSessions($userId) {
        $this->zeroizeError();
        if (!is_integer($userId) || $userId<1) {
            $this->setError(ERROR_INCORRECT_DATA, 'userSessions: user id must be integer and greater than zero');
            return false;
        }
        if (!$this->checkFuncAccess('core', 'auth_user_sessions', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "userSessions: restricted by the policy");
            return false;
        }
        // delete expired sessions of the user
        $sql = array(
            "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
            . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "ON `si`.`id`=`s`.`id` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId "
            . "AND `s`.`endtime`<NOW() ;",
            "DELETE `s` FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId "
            . "AND `s`.`endtime`<NOW() ;"
            );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userSessions: unable to delete expired sessions of the user -> '.$this->dbLink->error);
                return false;
            }
        }
        // get user sessions
        $qResult = $this->dbLink->query(
                "SELECT `si`.`id` `usi`, `si`.`ip` `ip`, `si`.`useragent` `useragent`, `si`.`created` `created`, `s`.`endtime` `endtime`, `p`.`description` `info`  FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
                . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
                . "ON `si`.`id`=`s`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
                . "ON `s`.`pid`=`p`.`id`"
                . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `p`.`userid`=`u`.`id` "
                . "WHERE `u`.`id`=$userId "
                . "ORDER BY `si`.`created` DESC ;"
                );
        if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'userSessions: unable to get list of the user sessions -> '.$this->dbLink->error);
                return false;
            }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $listNode = $xml->createElement('list');
            $xml->appendChild($listNode);
            $uidAttribute = $xml->createAttribute('uid');
            $uidAttribute->value = $userId;
            $listNode->appendChild($uidAttribute);
            while ($row = $qResult->fetch_row()) {
                $sessionNode = $xml->createElement('session');
                $listNode->appendChild($sessionNode);
                $sessionNode->appendChild($xml->createElement('usi', $row[0]));
                $sessionNode->appendChild($xml->createElement('ip', $row[1]));
                $sessionNode->appendChild($xml->createElement('useragent', $row[2]));
                $sessionNode->appendChild($xml->createElement('created', $row[3]));
                $sessionNode->appendChild($xml->createElement('endtime', $row[4]));
                $sessionNode->appendChild($xml->createElement('info', $row[5]));
            }
            return $xml;
        }
        else {
            $listNode = array();
            $listNode['uid'] = $userId;
            $listNode['sessions'] = array();
            while ($row = $qResult->fetch_row()) {
                $listNode['sessions'][] = array(
                    'usi' => $row[0],
                    'ip' => $row[1],
                    'useragent' => $row[2],
                    'created' => $row[3],
                    'endtime' => $row[4],
                    'info' => $row[5]
                );
            }
            if ($this->outputType == 'array') {
                return $listNode;
            }
            else {
                return json_encode($listNode);
            }
        }
    }
    
    public function destroyAllSessions($userId) {
        $this->zeroizeError();
        if (!is_integer($userId) || $userId<1) {
            $this->setError(ERROR_INCORRECT_DATA, 'destroyAllSessions: user id must be integer and greater than zero');
            return false;
        }
        if (!$this->checkFuncAccess('core', 'auth_destroy_sessions', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "destroyAllSessions: restricted by the policy");
            return false;
        }
        // delete expired sessions of the user
        $sql = array(
            "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
            . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "ON `si`.`id`=`s`.`id` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId ;",
            "DELETE `s` FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId ;"
            );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'destroyAllSessions: unable to delete sessions of the user -> '.$this->dbLink->error);
                return false;
            }
        }
        return true;
    }
    
    public function destroySession($userId, $sesId) {
        $this->zeroizeError();
        if (!is_integer($userId) || $userId<1) {
            $this->setError(ERROR_INCORRECT_DATA, 'destroyAllSessions: user id must be integer and greater than zero');
            return false;
        }
        if (!pregGuid($sesId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'destroySession: incorrect unique session identifier ');
            return false;
        }
        if (!$this->checkFuncAccess('core', 'auth_destroy_sessions', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "destroySession: restricted by the policy");
            return false;
        }
        // delete expired sessions of the user
        $sql = array(
            "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
            . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "ON `si`.`id`=`s`.`id` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id` "
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `si`.`id` = '$sesId' "
            . "AND `u`.`id`=$userId ;",
            "DELETE `s` FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id` "
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `s`.`id` = '$sesId' "
            . "AND `u`.`id`=$userId ;"
            );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'destroySession: unable to delete session of the user -> '.$this->dbLink->error);
                return false;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'destroySession: session is not found');
                return false;
            }
        }
        return true;
    }
}
