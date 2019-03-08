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
    public function readConfig();
    public function writeConfig($conf);
    public function state();
}

class Maintenance extends ServiceMethods implements intMaintenance {
    
    public function readConfig() {
        $confPath = MECCANO_SERVICE_PAGES.'/maintenance.json';
        if (!is_file($confPath)) {
            $this->setError(ERROR_NOT_FOUND, "readConf: configurational file [$confPath] is not found");
            return false;
        }
        if (!is_readable($confPath)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "readConf: configurational file [$confPath] is not readable");
            return false;
        }
        $conf = file_get_contents(MECCANO_SERVICE_PAGES.'/maintenance.json');
        // checking of recieved data
        $decoded = json_decode($conf);
        if (!isset($decoded->enabled) || gettype($decoded->enabled) != 'boolean') {
            $this->setError(ERROR_INCORRECT_DATA, 'readConf: parameter [enabled] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->timeout) || gettype($decoded->timeout) != 'integer' || $decoded->timeout<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'readConf: parameter [timeout] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->prmsg) || gettype($decoded->prmsg) != 'string') {
            $this->setError(ERROR_INCORRECT_DATA, 'readConf: parameter [prmsg] is incorrect or not exist');
            return false;
        }
        if (!isset($decoded->secmsg) || gettype($decoded->secmsg) != 'string') {
            $this->setError(ERROR_INCORRECT_DATA, 'readConf: parameter [secmsg] is incorrect or not exist');
            return false;
        }
        return $conf;
    }
    
    public function writeConfig($conf) {
        if (gettype($conf) != 'object') {
            $this->setError(ERROR_INCORRECT_DATA, 'writeConfig: invalid type of got parameters');
            return false;
        }
        if (!isset($conf->enabled) || gettype($conf->enabled) != 'boolean') {
            $this->setError(ERROR_INCORRECT_DATA, 'writeConfig: parameter [enabled] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->timeout) || gettype($conf->timeout) != 'integer' || $decoded->timeout<0) {
            $this->setError(ERROR_INCORRECT_DATA, 'writeConfig: parameter [timeout] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->prmsg) || gettype($conf->prmsg) != 'string') {
            $this->setError(ERROR_INCORRECT_DATA, 'writeConfig: parameter [prmsg] is incorrect or not exist');
            return false;
        }
        if (!isset($conf->secmsg) || gettype($conf->secmsg) != 'string') {
            $this->setError(ERROR_INCORRECT_DATA, 'writeConfig: parameter [secmsg] is incorrect or not exist');
            return false;
        }
        $confPath = MECCANO_SERVICE_PAGES.'/maintenance.json';
        if (!is_file($confPath)) {
            $this->setError(ERROR_NOT_FOUND, "writeConfig: configurational file [$confPath] is not found");
            return false;
        }
        if (!is_writable($confPath)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "writeConfig: configurational file [$confPath] is not writable");
            return false;
        }
        file_put_contents($confPath, json_encode($conf));
        return true;
    }
    
    public function state() {
        $conf = $this->readConfig();
        if (!$conf) {
            $this->setError($this->errid, 'state -> '.$this->errexp);
            return false;
        }
        $decoded = json_decode($conf);
        return array('enabled' => $decoded->enabled);
    }
}
