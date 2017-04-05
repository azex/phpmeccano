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

interface intServiceMethods {
    public function errId();
    public function errExp();
    public function applyPolicy($flag);
    public function outputFormat($output = 'xml');
    public function checkFuncAccess($plugin, $func); // old name [checkAccess]
}

class ServiceMethods implements intServiceMethods {
    protected $errid = 0; // error's id
    protected $errexp = ''; // error's explanation
    protected $usePolicy = TRUE; // flag of the policy application
    protected $outputType = 'json'; // format of the output data
    
    protected function setError($id, $exp) {
        $this->errid = $id;
        $this->errexp = $exp;
        if (MECCANO_SHOW_ERRORS) {
            echo "<br/><span style='font-style: large; padding: 10px; background: yellow; display: inline-block; color: red'>ERROR $id<br/>$exp</span><br/>";
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
}
