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
    
    public static function unpack($package) {
        self::$errid = 0;        self::$errexp = '';
        $zip = new \ZipArchive();
        $zipOpen = $zip->open($package);
        if ($zipOpen === TRUE) {
            $tmpName = makeIdent();
            $unpackPath = MECCANO_UNPACKED_PLUGINS."/$tmpName";
            $tmpPath = MECCANO_TMP_DIR."/$tmpName";
            if (!@$zip->extractTo($tmpPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("unpack: unable to extract package to $tmpPath");
                return FALSE;
            }
            $zip->close();
            $metaInfo = openRead($tmpPath."/metainfo.xml");
            if (!$metaInfo) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("unpack: unable to read meta-info");
                return FALSE;
            }
            if (mime_content_type($tmpPath."/metainfo.xml") != "application/xml") {
                Files::remove($tmpPath);
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("unpack: meta-info is not XML-structured");
                return FALSE;
            }
            $metaDOM = new \DOMDocument();
            $metaDOM->loadXML($metaInfo);
            if (!@$metaDOM->relaxNGValidate(MECCANO_CORE_DIR.'/plugins/metainfo-schema-v01.rng')) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('unpack: invalid meta-info structure');
                return FALSE;
            }
            $shortName = $metaDOM->getElementsByTagName('shortname')->item(0)->nodeValue;
            $qIsUnpacked = self::$dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                    . "WHERE `short`='$shortName' ;");
            if (self::$dbLink->errno) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('unpack: cannot check whether the plugin is unpacked | '.self::$dbLink->error);
                return FALSE;
            }
            if (self::$dbLink->affected_rows) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("unpack: plugin [$shortName] is already unpacked");
                return FALSE;
            }
            $fullName = self::$dbLink->real_escape_string(htmlspecialchars($metaDOM->getElementsByTagName('fullname')->item(0)->nodeValue));
            $uV = (int) $metaDOM->getElementsByTagName('version')->item(0)->getAttribute('upper');
            $mV = (int) $metaDOM->getElementsByTagName('version')->item(0)->getAttribute('middle');
            $lV = (int) $metaDOM->getElementsByTagName('version')->item(0)->getAttribute('lower');
            $insertColumns = "`short`, `full`, `uv`, `mv`, `lv`, `dirname`";
            $insertValues = "'$shortName', '$fullName', $uV, $mV, $lV, '$tmpName'";
            if ($optional = $metaDOM->getElementsByTagName('about')->item(0)->nodeValue) {
                $optional = self::$dbLink->real_escape_string($optional);
                $insertColumns = $insertColumns.", `about`";
                $insertValues = $insertValues.", '$optional'";
            }
            if ($optional = $metaDOM->getElementsByTagName('credits')->item(0)->nodeValue) {
                $optional = self::$dbLink->real_escape_string($optional);
                $insertColumns = $insertColumns.", `credits`";
                $insertValues = $insertValues.", '$optional'";
            }
            if ($optional = $metaDOM->getElementsByTagName('url')->item(0)->nodeValue) {
                $optional = self::$dbLink->real_escape_string($optional);
                $insertColumns = $insertColumns.", `url`";
                $insertValues = $insertValues.", '$optional'";
            }
            if ($optional = $metaDOM->getElementsByTagName('email')->item(0)->nodeValue) {
                $optional = self::$dbLink->real_escape_string($optional);
                $insertColumns = $insertColumns.", `email`";
                $insertValues = $insertValues.", '$optional'";
            }
            if ($optional = $metaDOM->getElementsByTagName('license')->item(0)->nodeValue) {
                $optional = self::$dbLink->real_escape_string($optional);
                $insertColumns = $insertColumns.", `license`";
                $insertValues = $insertValues.", '$optional'";
            }
            $pretSumVersion = 10000*$uV + 100*$mV + $lV;
            $qIsPlugin = self::$dbLink->query("SELECT `uv`, `mv`, `lv` "
                    . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                    . "WHERE `name`='$shortName' ;");
            if (self::$dbLink->errno) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('unpack: cannot check whether the plugin is installed | '.self::$dbLink->error);
                return FALSE;
            }
            if (self::$dbLink->affected_rows) {
                list($uv, $mv, $lv) = $qIsPlugin->fetch_row();
                $existSumVersion = 10000*$uv + 100*$mv + $lv;
                if ($pretSumVersion == $existSumVersion) {
                    $action = "reinstall";
                }
                elseif ($pretSumVersion > $existSumVersion) {
                    $action = "upgrade";
                }
                else {
                    $action = "downgrade";
                }
            }
            else {
                $action = "install";
            }
            $insertColumns = $insertColumns.", `action`";
            $insertValues = $insertValues.", '$action'";
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_plugins_unpacked` ($insertColumns)"
                    . "VALUES ($insertValues) ;");
            if (self::$dbLink->errno) {
                Files::remove($tmpPath);
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('unpack: '.self::$dbLink->error);
                return FALSE;
            }
            if (!Files::move($tmpPath, $unpackPath)) {
                self::setErrId(Files::errId());                self::setErrExp('unpack: -> '.Files::errExp());
                return FALSE;
            }
        }
        else {
            self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("unpack: unable to open package. ZipArchive error: $zipOpen");
            return FALSE;
        }
        return self::$dbLink->insert_id;
    }
    
    public static function delUnpacked($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delUnpacked: id must be integer');
            return FALSE;
        }
        $qUnpacked = self::$dbLink->query("SELECT `dirname` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delUnpacked: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("delUnpacked: cannot find defined plugin");
            return FALSE;
        }
        list($dirName) = $qUnpacked->fetch_row();
        if (!Files::remove(MECCANO_UNPACKED_PLUGINS."/$dirName")) {
            self::setErrId(Files::errId());                self::setErrExp('delUnpacked: -> '.Files::errExp());
            return FALSE;
        }
        self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delUnpacked: unable to delete unpacked plugin'.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function listUnpacked() {
        self::$errid = 0;        self::$errexp = '';
        $qUncpacked = self::$dbLink->query("SELECT `id`, `short`, `full`, CONCAT(`uv`, '.', `mv`, '.', `lv`) `version`, `action` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp("listUnpacked: ".self::$dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument();
        $unpackedNode = $xml->createElement('unpacked');
        $xml->appendChild($unpackedNode);
        if (!self::$dbLink->affected_rows) {
            return $xml;
        }
        while ($row = $qUncpacked->fetch_row()) {
            $pluginNode = $xml->createElement('plugin');
            $unpackedNode->appendChild($pluginNode);
            $pluginNode->appendChild($xml->createElement('id', $row[0]));
            $pluginNode->appendChild($xml->createElement('short', $row[1]));
            $pluginNode->appendChild($xml->createElement('full', $row[2]));
            $pluginNode->appendChild($xml->createElement('version', $row[3]));
            $pluginNode->appendChild($xml->createElement('action', $row[4]));
        }
        return $xml;
    }
}
