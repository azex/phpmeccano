<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [unifunctions.php].
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
 */

namespace core;

require_once 'swconst.php';

// it generates and returns unique password salt
function makeSalt($prefix = '') {
    return substr(base64_encode(sha1(uniqid().microtime(TRUE).rand(1, 10e9).uniqid($prefix, TRUE))), 0, 22);
}

// it generates and returns unique identifier for user, session etc.
function makeIdent($prefix = '') {
    return sha1(uniqid().microtime(TRUE).rand(1, 10e9).uniqid($prefix, TRUE));
}

// it calculates password hash
function passwHash($password, $salt) {
    if (is_string($password) && strlen($password)<51 && strlen($password)>7 && is_string($salt) && strlen($salt)==22) {
        return sha1(crypt($password, '$2y$10$'.$salt));
    }
    else {
        return FALSE;
    }
}

// it checks database prefix
function pregPref($prefix) {
    if (is_string($prefix) && preg_match('/^[a-zA-Z\d]{1,20}$/', $prefix)) {
        return TRUE;
    }
    return FALSE;
}

// it checks user name
function pregUName($username) {
    if (is_string($username) && preg_match('/^[a-zA-Z\d]{3,20}$/', $username)) {
        return TRUE;
    }
    return FALSE;
}

// it checks group name
function pregGName($groupname) {
    if (is_string($groupname) && preg_match('/^.{1,50}$/', $groupname)) {
        return TRUE;
    }
    return FALSE;
}

// it checks entered password
function pregPassw($password) {
    if (is_string($password) && preg_match('/^[-+=_a-zA-Z\d@.,?!;:"\'~`|#№*$%&^\][(){}<>\/\\\]{8,50}$/', $password)) {
        return TRUE;
    }
    return FALSE;
}

// it checks entered identifier
function pregIdent($ident) {
    if (is_string($ident) && preg_match('/^[a-zA-Z\d]{40}$/', $ident)) {
        return TRUE;
    }
    return FALSE;
}

// it checks entered language code
function pregLang($code) {
    if (is_string($code) && preg_match('/^[a-z]{2}-[A-Z]{2}$/', $code)) {
        return TRUE;
    }
    return FALSE;
}

// it checks entered plugin name
function pregPlugin($name) {
    if (is_string($name) && preg_match('/^[a-zA-Z\d_]{3,30}$/', $name)) {
        return TRUE;
    }
    return FALSE;
}

// it checks name identifiers with length from 3 to 40 characters
function pregName40($name) {
    if (is_string($name) && preg_match('/^[a-zA-Z\d_]{3,40}$/', $name)) {
        return TRUE;
    }
    return FALSE;
}

// it reads and returns content of defined file
function openRead($path) {
    if (is_file($path) && is_readable($path)) {
        $handle = fopen($path, 'r');
        $fileSize = filesize($path);
        if (!$fileSize) {
            fclose($handle);
            return FALSE;
        }
        $data = fread($handle, $fileSize);
        fclose($handle);
        return $data;
    }
    return FALSE;
}

// transform XML with XSLT
function xmlTransform($xml, $xsl) {
    $xmlDOM = new \DOMDocument();
    $xslDOM = new \DOMDocument();
    if ($xmlDOM->loadXML($xml) && $xslDOM->loadXML($xsl)) {
        $xslt = new \XSLTProcessor();
        $xslt->importStylesheet($xslDOM);
        if ($transformed = $xslt->transformToDoc($xmlDOM)) {
            return $transformed;
        }
    }
    return FALSE;
}

// general information about session
function authUserId() {
    if (isset($_SESSION[AUTH_USER_ID])) {
        return $_SESSION[AUTH_USER_ID];
    }
    return FALSE;
}

// returns username if there is active session
function authUName() {
    if (isset($_SESSION[AUTH_USERNAME])) {
        return $_SESSION[AUTH_USERNAME];
    }
    return FALSE;
}

// returns session type if there is active session
function authLimited() {
    if (isset($_SESSION[AUTH_LIMITED])) {
        return $_SESSION[AUTH_LIMITED];
    }
    return FALSE;
}

// returns language code if there is active session
function authLang() {
    if (isset($_SESSION[AUTH_LANGUAGE])) {
        return $_SESSION[AUTH_LANGUAGE];
    }
    return FALSE;
}

// returns language code if there is active session
function authLangDir() {
    if (isset($_SESSION[AUTH_LANGUAGE_DIR])) {
        return $_SESSION[AUTH_LANGUAGE_DIR];
    }
    return FALSE;
}

// returns session token if there is active session
function authToken() {
    if (isset($_SESSION[AUTH_TOKEN])) {
        return $_SESSION[AUTH_TOKEN];
    }
    return FALSE;
}

// compares two versions
function compareVersions($version1, $version2, $operator = ">=") {
    if (!is_string($version1) || !preg_match('/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}$/', $version1) || !is_string($version2) || !preg_match('/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}$/', $version2) || !in_array($operator, array(">=", "<=", "==", ">", "<", "!="))) {
        return FALSE;
    }
    // calculate first summary version
    list($uv1, $mv1, $lv1) = explode(".", $version1);
    $sumVersion1 = 10000*$uv1 + 100*$mv1 + $lv1;
    // calculate second summary version
    list($uv2, $mv2, $lv2) = explode(".", $version2);
    $sumVersion2 = 10000*$uv2 + 100*$mv2 + $lv2;
    // compare versions
    if ($operator == ">=" && $version1 >= $version2) {
        return TRUE;
    }
    elseif ($operator == "<=" && $version1 <= $version2) {
        return TRUE;
    }
    elseif ($operator == "==" && $version1 == $version2) {
        return TRUE;
    }
    elseif ($operator == ">" && $version1 > $version2) {
        return TRUE;
    }
    elseif ($operator == "<" && $version1 < $version2) {
        return TRUE;
    }
    elseif ($operator == "!=" && $version1 != $version2) {
        return TRUE;
    }
    return FALSE;
}

// calculates summary version
function calcSumVersion($version) {
    if (!is_string($version) || !preg_match('/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}$/', $version)) {
        return FALSE;
    }
    list($uv, $mv, $lv) = explode(".", $version);
    $sumVersion = 10000*$uv + 100*$mv + $lv;
    return (int) $sumVersion;
}
