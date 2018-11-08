<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Core module [extclass.php].
 *     Copyright (C) 2015-2016  Alexei Muzarov
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

require_once MECCANO_CORE_DIR.'/swconst.php';
require_once MECCANO_CORE_DIR.'/unifunctions.php';

interface intServiceMethods {
    public function errId();
    public function errExp();
    public function applyPolicy($flag);
    public function outputFormat($output = 'xml');
    public function checkFuncAccess($plugin, $func); // old name [checkAccess]
    public function checkUserAccess($plugin, $func, $userId); // old name [checkAccess]
    public function newLogRecord($plugin, $event, $insertion = ''); // old name [newRecord]
}

class ServiceMethods implements intServiceMethods {
    protected $errid = 0; // error's id
    protected $errexp = ''; // error's explanation
    protected $usePolicy = TRUE; // flag of the policy application
    protected $outputType = 'json'; // format of the output data
    
    protected function setError($id, $exp, $errtype = E_USER_NOTICE) {
        $this->errid = $id;
        $this->errexp = $exp;
        if (MECCANO_SHOW_ERRORS) {
            trigger_error("ERROR $id. $exp", $errtype);
        }
    }
    
    protected function zeroizeError() {
        $this->errid = 0;        $this->errexp = '';
    }
    
    public function errId() {
        return $this->errid;
    }
    
    public function errExp() {
        return $this->errexp;
    }
    
    public function applyPolicy($flag = FALSE) {
        if ($flag) {
            $this->usePolicy = TRUE;
        }
        else {
            $this->usePolicy = FALSE;
        }
    }
    
    public function outputFormat($output = 'xml') {
        if ($output == 'xml') {
            $this->outputType = 'xml';
        }
        elseif ($output == 'json') {
            $this->outputType = 'json';
        }
        elseif ($output == 'array') {
            $this->outputType = 'array';
        }
        else {
            $this->outputType = 'json';
        }
    }
    
    // method came from core module policy.php
    public function checkFuncAccess($plugin, $func) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregPlugin($func)) {
            $this->setError(ERROR_INCORRECT_DATA, 'checkFuncAccess: check incoming parameters');
            return FALSE;
        }
        // allow if policy is disabled
        if (!$this->usePolicy) {
            return 1;
        }
        if (isset($_SESSION[AUTH_USER_ID])) {
            $qAccess = $this->dbLink->query("SELECT `a`.`access` "
                . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                . "ON `a`.`funcid`=`s`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `a`.`groupid`=`g`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `g`.`id`=`u`.`groupid` "
                . "WHERE `u`.`id`=".$_SESSION[AUTH_USER_ID]." "
                . "AND `s`.`name`='$plugin' "
                . "AND `s`.`func`='$func' "
                . "LIMIT 1 ;");
        }
        else {
            $qAccess = $this->dbLink->query("SELECT `n`.`access` "
                . "FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
                . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                . "ON `n`.`funcid`=`s`.`id` "
                . "WHERE `s`.`name`='$plugin' "
                . "AND `s`.`func`='$func' "
                . "LIMIT 1 ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkFuncAccess: something went wrong -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'checkFuncAccess: policy is not found');
            return FALSE;
        }
        list($access) = $qAccess->fetch_row();
        return (int) $access;
    }
    
    // Method [checkUserAccess] grants access  to the user independently from the access policy, if argument $userId is equal to ID of the authenticated user. For example, it may be useful, if the user going to get it's own data. Otherwise, method [checkUserAccess] works as [checkFuncAccess].
    public function checkUserAccess($plugin, $func, $userId) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregPlugin($func)) {
            $this->setError(ERROR_INCORRECT_DATA, 'checkFuncAccess: check incoming parameters');
            return FALSE;
        }
        // allow if policy is disabled
        if (!$this->usePolicy) {
            return 1;
        }
        if (isset($_SESSION[AUTH_USER_ID])) {
            if ($_SESSION[AUTH_USER_ID] == $userId) {
                return 1;
            }
            else {
                $qAccess = $this->dbLink->query("SELECT `a`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "ON `a`.`groupid`=`g`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "WHERE `u`.`id`=".$_SESSION[AUTH_USER_ID]." "
                    . "AND `s`.`name`='$plugin' "
                    . "AND `s`.`func`='$func' "
                    . "LIMIT 1 ;");
            }
        }
        else {
            $qAccess = $this->dbLink->query("SELECT `n`.`access` "
                . "FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
                . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                . "ON `n`.`funcid`=`s`.`id` "
                . "WHERE `s`.`name`='$plugin' "
                . "AND `s`.`func`='$func' "
                . "LIMIT 1 ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkFuncAccess: something went wrong -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'checkFuncAccess: policy is not found');
            return FALSE;
        }
        list($access) = $qAccess->fetch_row();
        return (int) $access;
    }
    
    // method came from core module logman.php
    public function newLogRecord($plugin, $keyword, $insertion = '') {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregPlugin($keyword) || !is_string($insertion)) {
            $this->setError(ERROR_INCORRECT_DATA, 'newLogRecord: check arguments');
            return FALSE;
        }
        $keyword = $this->dbLink->real_escape_string($keyword);
        $insertion = $this->dbLink->real_escape_string($insertion);
        // get event identifier
        $qEvent = $this->dbLink->query("SELECT `e`.`id` "
                . "FROM `".MECCANO_TPREF."_core_logman_events` `e` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`e`.`plugid` "
                . "WHERE `e`.`keyword`='$keyword' "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'newLogRecord: unable to get event identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'newLogRecord: plugin or event not found');
            return FALSE;
        }
        list($eventId) = $qEvent->fetch_row();
        // make new record
        if (isset($_SESSION[AUTH_LIMITED])) {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`, `user`) "
                    . "VALUES ($eventId, '$insertion', '".$_SESSION[AUTH_USERNAME]."') ;");
        }
        else {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_logman_records` (`eventid`, `insertion`) "
                    . "VALUES ($eventId, '$insertion') ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'newLogRecord: unable to make new record -> '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
}
