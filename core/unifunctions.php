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
    if (is_string($username) && preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
        return TRUE;
    }
    return FALSE;
}
