<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Core module [maintenance.php].
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

interface intMaintenance {
    public function __construct(\mysqli $dbLink);
    public function state();
    public function write($conf);
    public function enable();
    public function disable();
    public function timeout($sec = 1800);
    public function startpoint($sec = 0);
    public function prmsg($msg = 'The site is under maintenance');
    public function secmsg($msg = 'We will be back soon');
    public function reset();
}

class Maintenance extends ServiceMethods implements intMaintenance {
    
    public function __construct(\mysqli $dbLink) {
        $this->dbLink = $dbLink;
    }
    
    public function state() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "state: restricted by the policy");
            return false;
        }
        $confPath = MECCANO_SERVICE_PAGES.'/maintenance.json';
        if (!is_file($confPath)) {
            $this->setError(ERROR_NOT_FOUND, "state: configuration file is not found");
            return false;
        }
        if (!is_readable($confPath)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "state: configuration file is not readable");
            return false;
        }
        $conf = file_get_contents($confPath);
        // checking of recieved data
        $decoded = json_decode($conf);
        if (is_null($decoded)) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: configuration file contains error');
            return false;
        }
        if (!isset($decoded->enabled) || !is_bool($decoded->enabled)) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: parameter [enabled] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->timeout) || !is_integer($decoded->timeout) || $decoded->timeout<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: parameter [timeout] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->startpoint) || !is_integer($decoded->startpoint) || $decoded->startpoint<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: parameter [startpoint] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->prmsg) || !is_string($decoded->prmsg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: parameter [prmsg] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->secmsg) || !is_string($decoded->secmsg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'state: parameter [secmsg] is incorrect or not exist');
            return false;
        }
        if ($decoded->enabled && ($decoded->timeout + $decoded->startpoint)< time()) {
            $expired = true;
        }
        else {
            $expired = false;
        }
        return array(
                    'enabled' => $decoded->enabled,
                    'prmsg' => $decoded->prmsg,
                    'secmsg' => $decoded->secmsg,
                    'timeout' => $decoded->timeout,
                    'startpoint' => $decoded->startpoint,
                    'expired' => $expired
                    );
    }
    
    public function write($conf) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "write: restricted by the policy");
            return false;
        }
        if (!is_object($conf)) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [conf] must be object');
            return false;
        }
        if (!isset($conf->enabled) || !is_bool($conf->enabled)) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [enabled] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->prmsg) || !is_string($conf->prmsg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [prmsg] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->secmsg) || !is_string($conf->secmsg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [secmsg] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->timeout) || !is_integer($conf->timeout) || $conf->timeout<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [timeout] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->startpoint) || !is_integer($conf->startpoint) || $conf->startpoint<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'write: parameter [startpoint] is incorrect or not exist');
            return false;
        }
        $confPath = MECCANO_SERVICE_PAGES.'/maintenance.json';
        if (!is_file($confPath)) {
            $this->setError(ERROR_NOT_FOUND, "write: configuration file is not found");
            return false;
        }
        if (!is_writable($confPath)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "write: configuration file is not writable");
            return false;
        }
        file_put_contents(
                $confPath, json_encode(
                        array(
                            'enabled' => $conf->enabled,
                            'prmsg' => $conf->prmsg,
                            'secmsg' => $conf->secmsg,
                            'timeout' => $conf->timeout,
                            'startpoint' => $conf->startpoint
                            )
                        )
                );
        return true;
    }
    
    public function enable() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "enable: restricted by the policy");
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'enable -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if (!$decoded->enabled) {
            $decoded->enabled = true;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'enable -> '.$this->errexp);
                return false;
            }
        }
        return array('enabled' => $decoded->enabled);
    }
    
    public function disable() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "disable: restricted by the policy");
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'disable -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if ($decoded->enabled) {
            $decoded->enabled = false;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'disable -> '.$this->errexp);
                return false;
            }
        }
        return array('enabled' => $decoded->enabled);
    }
    
    public function timeout($sec = 1800) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "timeout: restricted by the policy");
            return false;
        }
        if (!is_integer($sec) || $sec < 0) {
            $this->setError(ERROR_INCORRECT_DATA, 'timeout: invalid type of got parameters');
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'timeout -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if ($decoded->timeout != $sec) {
            $decoded->timeout = $sec;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'timeout -> '.$this->errexp);
                return false;
            }
        }
        return array('timeout' => $decoded->timeout);
    }
    
    public function startpoint($sec = 0) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "startpoint: restricted by the policy");
            return false;
        }
        if (!is_integer($sec) || $sec < 0) {
            $this->setError(ERROR_INCORRECT_DATA, 'startpoint: invalid type of got parameters');
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'startpoint -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if ($decoded->startpoint != $sec) {
            $decoded->startpoint = $sec;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'startpoint -> '.$this->errexp);
                return false;
            }
        }
        return array('startpoint' => $decoded->startpoint);
    }
    
    public function prmsg($msg = 'The site is under maintenance') {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "prmsg: restricted by the policy");
            return false;
        }
        if (!is_string($msg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'prmsg: invalid type of got parameters');
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'prmsg -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if ($decoded->prmsg != $msg) {
            $decoded->prmsg = $msg;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'prmsg -> '.$this->errexp);
                return false;
            }
        }
        return array('prmsg' => $decoded->prmsg);
    }
    
    public function secmsg($msg = 'We will be back soon') {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "secmsg: restricted by the policy");
            return false;
        }
        if (!is_string($msg)) {
            $this->setError(ERROR_INCORRECT_DATA, 'secmsg: invalid type of got parameters');
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'secmsg -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        if ($decoded->secmsg != $msg) {
            $decoded->secmsg = $msg;
            if (!$this->write($decoded)) {
                $this->setError($this->errid, 'secmsg -> '.$this->errexp);
                return false;
            }
        }
        return array('secmsg' => $decoded->secmsg);
    }
    
    public function reset() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'maintenance_configure')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "reset: restricted by the policy");
            return false;
        }
        $conf = $this->state();
        if (!$conf) {
            $this->setError($this->errid, 'reset -> '.$this->errexp);
            return false;
        }
        $decoded = (object) $conf;
        $decoded->prmsg = 'The site is under maintenance';
        $decoded->secmsg = 'We will be back soon';
        $decoded->timeout = 1800;
        $decoded->startpoint = 0;
        if (!$this->write($decoded)) {
            $this->setError($this->errid, 'reset -> '.$this->errexp);
                return false;
        }
        return array(
                    'enabled' => $decoded->enabled,
                    'prmsg' => $decoded->prmsg,
                    'secmsg' => $decoded->secmsg,
                    'timeout' => $decoded->timeout,
                    'startpoint' => $decoded->startpoint,
                    );
    }
}
