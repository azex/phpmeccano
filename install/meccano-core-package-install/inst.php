<?php

/*
 *     This file is part of phpMeccano project.
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

interface intInstall {
    public function __construct(\mysqli $dblink, $id, $version = '', $reset = FALSE);
    public function errId();
    public function errExp();
    public function preinst();
    public function postinst();
}

class Install implements intInstall {
    
    private $errid = 0; // error code
    private $errexp = ''; // error description
    private $dbLink; // mysqli object
    private $id; // identifier of the installing plugin
    private $version; // version of the existing plugin
    private $reset; // 

    public function __construct(\mysqli $dblink, $id, $version = '', $reset = FALSE) {
        $this->dbLink = $dblink;
        $this->id = $id;
        $this->version = $version;
        $this->reset = $reset;
    }
    
    private function setError($id, $exp) {
        $this->errid = $id;
        $this->errexp = $exp;
    }
    
    public function errId() {
        return $this->errid;
    }
    
    public function errExp() {
        return $this->errexp;
    }
    
    public function preinst() {
        if (!$this->version) {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_languages` (`code`, `name`, `dir`) VALUES"
                    . "('en-US', 'English (USA)', 'ltr'),"
                    . "('ru-RU', 'Русский (Россия)', 'ltr') ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'prerinst: '.$this->dbLink->error);
                return FALSE;
            }
        }
        else {
            $this->setError(ERROR_NOT_EXECUTED, 'preinst: package is intended only for installation');
            return FALSE;
        }
        return TRUE;
    }
    
    public function postinst() {
        // put your code here
        return TRUE;
    }
    
}
