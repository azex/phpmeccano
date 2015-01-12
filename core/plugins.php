<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logging.php';
require_once 'files.php';

/**
 * Description of Plugins
 *
 * @author azex
 */
class Plugins {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $logObject; // log object
    private static $policyObject; // policy object
    
    public function __construct($dbLink, $logObject, $policyObject) {
        self::$dbLink = $dbLink;
        self::$logObject = $logObject;
        self::$policyObject = $policyObject;
    }
    
    public static function setDbLink($dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setLogObject($logObject) {
        self::$logObject = $logObject;
    }
    
    public static function setPolicyObject($policyObject) {
        self::$policyObject = $policyObject;
    }
    
    private static function setErrId($id) {
        self::$errid = $id;
    }
    
    private static function setErrExp($exp) {
        self::$errexp = $exp;
    }
    
    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }
}
