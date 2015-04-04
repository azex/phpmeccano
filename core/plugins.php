<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';
require_once 'logman.php';
require_once 'files.php';

/**
 * Description of Plugins
 *
 * @author azex
 */

interface intPlugins {
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject);
    public static function setDbLink(\mysqli $dbLink);
    public static function setLogObject(LogMan $logObject);
    public static function setPolicyObject(Policy $policyObject);
    public static function errId();
    public static function errExp();
    public static function unpack($package);
    public static function delUnpacked($id);
    public static function listUnpacked();
    public static function aboutUnpacked($id);
    public static function getVersion($plugin);
}

class Plugins implements intPlugins {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $logObject; // log object
    private static $policyObject; // policy object
    
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject) {
        self::$dbLink = $dbLink;
        self::$logObject = $logObject;
        self::$policyObject = $policyObject;
    }
    
    public static function setDbLink(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setLogObject(LogMan $logObject) {
        self::$logObject = $logObject;
    }
    
    public static function setPolicyObject(Policy $policyObject) {
        self::$policyObject = $policyObject;
    }
    
    private static function setError($id, $exp) {
        self::$errid = $id;
        self::$errexp = $exp;
    }
    
    private static function zeroizeError() {
        self::$errid = 0;        self::$errexp = '';
    }
    
    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }
    
    public static function unpack($package) {
        self::zeroizeError();
        $zip = new \ZipArchive();
        $zipOpen = $zip->open($package);
        if ($zipOpen === TRUE) {
            $tmpName = makeIdent();
            $unpackPath = MECCANO_UNPACKED_PLUGINS."/$tmpName";
            $tmpPath = MECCANO_TMP_DIR."/$tmpName";
            if (!@$zip->extractTo($tmpPath)) {
                self::setError(ERROR_NOT_EXECUTED, "unpack: unable to extract package to $tmpPath");
                return FALSE;
            }
            $zip->close();
            $metaInfo = openRead($tmpPath."/metainfo.xml");
            if (!$metaInfo) {
                Files::remove($tmpPath);
                self::setError(ERROR_NOT_EXECUTED, "unpack: unable to read meta-info");
                return FALSE;
            }
            if (mime_content_type($tmpPath."/metainfo.xml") != "application/xml") {
                Files::remove($tmpPath);
                self::setError(ERROR_NOT_EXECUTED, "unpack: meta-info is not XML-structured");
                return FALSE;
            }
            $metaDOM = new \DOMDocument();
            $metaDOM->loadXML($metaInfo);
            if (!@$metaDOM->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/plugins-package-metainfo-v01.rng')) {
                Files::remove($tmpPath);
                self::setError(ERROR_INCORRECT_DATA, 'unpack: invalid meta-info structure');
                return FALSE;
            }
            $shortName = $metaDOM->getElementsByTagName('shortname')->item(0)->nodeValue;
            $qIsUnpacked = self::$dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                    . "WHERE `short`='$shortName' ;");
            if (self::$dbLink->errno) {
                Files::remove($tmpPath);
                self::setError(ERROR_NOT_EXECUTED, 'unpack: cannot check whether the plugin is unpacked | '.self::$dbLink->error);
                return FALSE;
            }
            if (self::$dbLink->affected_rows) {
                Files::remove($tmpPath);
                self::setError(ERROR_ALREADY_EXISTS, "unpack: plugin [$shortName] is already unpacked");
                return FALSE;
            }
            $fullName = self::$dbLink->real_escape_string(htmlspecialchars($metaDOM->getElementsByTagName('fullname')->item(0)->nodeValue));
            $version = $metaDOM->getElementsByTagName('version')->item(0)->nodeValue;
            $insertColumns = "`short`, `full`, `version`, `dirname`";
            $insertValues = "'$shortName', '$fullName', '$version', '$tmpName'";
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
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_plugins_unpacked` ($insertColumns)"
                    . "VALUES ($insertValues) ;");
            if (self::$dbLink->errno) {
                Files::remove($tmpPath);
                self::setError(ERROR_NOT_EXECUTED, 'unpack: '.self::$dbLink->error);
                return FALSE;
            }
            if (!Files::move($tmpPath, $unpackPath)) {
                self::setError(Files::errId(), 'unpack: -> '.Files::errExp());
                return FALSE;
            }
        }
        else {
            self::setError(ERROR_NOT_EXECUTED, "unpack: unable to open package. ZipArchive error: $zipOpen");
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delUnpacked($id) {
        self::zeroizeError();
        if (!is_integer($id)) {
            self::setError(ERROR_INCORRECT_DATA, 'delUnpacked: id must be integer');
            return FALSE;
        }
        $qUnpacked = self::$dbLink->query("SELECT `dirname` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delUnpacked: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "delUnpacked: cannot find defined plugin");
            return FALSE;
        }
        list($dirName) = $qUnpacked->fetch_row();
        if (!Files::remove(MECCANO_UNPACKED_PLUGINS."/$dirName")) {
            self::setError(Files::errId(), 'delUnpacked: -> '.Files::errExp());
            return FALSE;
        }
        self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delUnpacked: unable to delete unpacked plugin'.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function listUnpacked() {
        self::zeroizeError();
        $qUncpacked = self::$dbLink->query("SELECT `id`, `short`, `full`, `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "listUnpacked: ".self::$dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument();
        $unpackedNode = $xml->createElement('unpacked');
        $xml->appendChild($unpackedNode);
        while ($row = $qUncpacked->fetch_row()) {
            if ($curVersion = self::getVersion($row[1])) {
                $curSumVersion = calcSumVersion($curVersion);
                $newSumVersion = calcSumVersion($row[3]);
                if ($curSumVersion < $newSumVersion) {
                    $action = "upgrade";
                }
                elseif ($curSumVersion == $newSumVersion) {
                    $action = "reinstall";
                }
                elseif ($curSumVersion > $newSumVersion) {
                    $action = "downgrade";
                }
            }
            else {
                $action = "install";
            }
            $pluginNode = $xml->createElement('plugin');
            $unpackedNode->appendChild($pluginNode);
            $pluginNode->appendChild($xml->createElement('id', $row[0]));
            $pluginNode->appendChild($xml->createElement('short', $row[1]));
            $pluginNode->appendChild($xml->createElement('full', $row[2]));
            $pluginNode->appendChild($xml->createElement('version', $row[3]));
            $pluginNode->appendChild($xml->createElement('action', $action));
        }
        return $xml;
    }
    
    public static function aboutUnpacked($id) {
        self::zeroizeError();
        if (!is_integer($id)) {
            self::setError(ERROR_INCORRECT_DATA, 'aboutUnpacked: id must be integer');
            return FALSE;
        }
        $qUncpacked = self::$dbLink->query("SELECT `short`, `full`, `version`, `about`, `credits`, `url`, `email`, `license` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "aboutUnpacked: ".self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "aboutUnpacked: cannot find defined plugin");
            return FALSE;
        }
        list($shortName, $fullName, $version, $about, $credits, $url, $email, $license) = $qUncpacked->fetch_row();
        if ($curVersion = self::getVersion($shortName)) {
            $curSumVersion = calcSumVersion($curVersion);
            $newSumVersion = calcSumVersion($version);
            if ($curSumVersion < $newSumVersion) {
                $action = "upgrade";
            }
            elseif ($curSumVersion == $newSumVersion) {
                $action = "reinstall";
            }
            elseif ($curSumVersion > $newSumVersion) {
                $action = "downgrade";
            }
        }
        else {
            $action = "install";
        }
        $xml = new \DOMDocument();
        $unpackedNode = $xml->createElement('unpacked');
        $xml->appendChild($unpackedNode);
        $unpackedNode->appendChild($xml->createElement('id', $id));
        $unpackedNode->appendChild($xml->createElement('short', $shortName));
        $unpackedNode->appendChild($xml->createElement('full', $fullName));
        $unpackedNode->appendChild($xml->createElement('version', $version));
        $unpackedNode->appendChild($xml->createElement('about', $about));
        $unpackedNode->appendChild($xml->createElement('credits', $credits));
        $unpackedNode->appendChild($xml->createElement('url', $url));
        $unpackedNode->appendChild($xml->createElement('email', $email));
        $unpackedNode->appendChild($xml->createElement('license', $license));
        $unpackedNode->appendChild($xml->createElement('action', $action));
        return $xml;
    }
    
    public static function getVersion($plugin) {
        self::zeroizeError();
        if (!pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, "getVersion: incorrect name");
            return FALSE;
        }
        $qPlugin = self::$dbLink->query("SELECT `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin'");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "getVersion: unable to get plugin version | ".self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "getVersion: plugin not found");
            return FALSE;
        }
        list($version) = $qPlugin->fetch_row();
        return $version;
    }
}
