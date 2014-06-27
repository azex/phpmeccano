<?php

namespace core;

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
    if (is_string($groupname) && preg_match('/^[-a-zA-Z\d\s_]{1,50}$/', $groupname)) {
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

function pregIdent($ident) {
    if (is_string($ident) && preg_match('/^[a-zA-Z\d]{40}$/', $ident)) {
        return TRUE;
    }
    return FALSE;
}

function openRead($path) {
    if (is_file($path) && is_readable($path)) {
        $handle = fopen($path, 'r');
        $data = fread($handle, filesize($path));
        fclose($handle);
        return $data;
    }
    return FALSE;
}

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
    if (isset($_SESSION['core_auth_userid'])) {
        return $_SESSION['core_auth_userid'];
    }
    return FALSE;
}

function authUName() {
    if (isset($_SESSION['core_auth_uname'])) {
        return $_SESSION['core_auth_uname'];
    }
    return FALSE;
}

function authLimited() {
    if (isset($_SESSION['core_auth_limited'])) {
        return $_SESSION['core_auth_limited'];
    }
    return FALSE;
}

function authLastTime() {
    if (isset($_SESSION['core_auth_last_time'])) {
        return $_SESSION['core_auth_last_time'];
    }
    return FALSE;
}

function authLastIp() {
    if (isset($_SESSION['core_auth_last_ip'])) {
        return $_SESSION['core_auth_last_ip'];
    }
    return FALSE;
}
