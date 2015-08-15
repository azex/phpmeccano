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

interface intRemove {
    public function __construct(\mysqli $dblink, $id, $keepData = TRUE);
    public function errId();
    public function errExp();
    public function prerm();
    public function postrm();
}

class Remove implements intRemove {
    
    private $errid = 0; // error code
    private $errexp = ''; // error description
    private $dbLink; // mysqli object
    private $id; // identifier of the installed plugin
    private $keepData; // 

    public function __construct(\mysqli $dblink, $id, $keepData = TRUE) {
        $this->dbLink = $dblink;
        $this->id = $id;
        $this->keepData = $keepData;
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
    
    public function prerm() {
        // put your code here
        return TRUE;
    }
    
    public function postrm() {
        // put your code here
        return TRUE;
    }

}
