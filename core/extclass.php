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
}
