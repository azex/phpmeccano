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
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject, LangMan $langmanObject);
    public static function setDbLink(\mysqli $dbLink);
    public static function setLogObject(LogMan $logObject);
    public static function setPolicyObject(Policy $policyObject);
    public static function errId();
    public static function errExp();
    public static function unpack($package);
    public static function delUnpacked($id);
    public static function listUnpacked();
    public static function aboutUnpacked($id);
    public static function pluginData($plugin);
    public static function install($id);
}

class Plugins implements intPlugins {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $logObject; // log object
    private static $policyObject; // policy object
    private static $langmanObject; // policy object
    
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject, LangMan $langmanObject) {
        self::$dbLink = $dbLink;
        self::$logObject = $logObject;
        self::$policyObject = $policyObject;
        self::$langmanObject = $langmanObject;
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
            // validate xml components
            $serviceData = new \DOMDocument();
            $xmlComponents = array(
                "policy.xml" => "policy-v01.rng",
                "log.xml" => "logman-events-v01.rng",
                "texts.xml" => "langman-text-v01.rng",
                "titles.xml" => "langman-title-v01.rng",
                "depends.xml" => "plugins-package-depends-v01.rng",
                "metainfo.xml" => "plugins-package-metainfo-v01.rng"
                );
            foreach ($xmlComponents as $valComponent=> $valSchema) {
                $xmlComponent = openRead($tmpPath."/$valComponent");
                if (!$xmlComponent) {
                    Files::remove($tmpPath);
                    self::setError(ERROR_NOT_EXECUTED, "unpack: unable to read [$valComponent]");
                    return FALSE;
                }
                if (mime_content_type($tmpPath."/$valComponent") != "application/xml") {
                    Files::remove($tmpPath);
                    self::setError(ERROR_NOT_EXECUTED, "unpack: [$valComponent] is not XML-structured");
                    return FALSE;
                }
                $serviceData->loadXML($xmlComponent);
                if (!@$serviceData->relaxNGValidate(MECCANO_CORE_DIR."/validation-schemas/$valSchema")) {
                    Files::remove($tmpPath);
                    self::setError(ERROR_INCORRECT_DATA, "unpack: invalid [$valComponent] structure");
                    return FALSE;
                }
            }
            // get data from metainfo.xml
            $packVersion = $serviceData->getElementsByTagName('metainfo')->item(0)->getAttribute('version');
            if ($packVersion != '0.1') {
                Files::remove($tmpPath);
                self::setError(ERROR_INCORRECT_DATA, "unpack: installer is incompatible with the package specification [$packVersion]");
                return FALSE;
            }
            $shortName = $serviceData->getElementsByTagName('shortname')->item(0)->nodeValue;
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
                self::setError(ERROR_ALREADY_EXISTS, "unpack: plugin [$shortName] was already unpacked");
                return FALSE;
            }
            $fullName = self::$dbLink->real_escape_string(htmlspecialchars($serviceData->getElementsByTagName('fullname')->item(0)->nodeValue));
            $version = $serviceData->getElementsByTagName('version')->item(0)->nodeValue;
            $insertColumns = "`short`, `full`, `version`, `spec`, `dirname`";
            $insertValues = "'$shortName', '$fullName', '$version', '$packVersion', '$tmpName'";
            // get optional data
            $optionalData = array('about', 'credits', 'url', 'email', 'license');
            foreach ($optionalData as $optNode) {
                if ($optional = $serviceData->getElementsByTagName("$optNode")->item(0)->nodeValue) {
                    $optional = self::$dbLink->real_escape_string($optional);
                    $insertColumns = $insertColumns.", `$optNode`";
                    $insertValues = $insertValues.", '$optional'";
                }
            }
            // get list of the needed dependences
            $serviceData->load($tmpPath."/depends.xml");
            $depends = "";
            $dependsNodes = $serviceData->getElementsByTagName("plugin");
            foreach ($dependsNodes as $dependsNode) {
                $depends = $depends.$dependsNode->getAttribute('name')." (".$dependsNode->getAttribute('operator')." ".$dependsNode->getAttribute('version')."), ";
            }
            $depends = htmlspecialchars(substr($depends, 0, -2));
            $insertColumns = $insertColumns.", `depends`";
            $insertValues = $insertValues.", '$depends'";
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
            if ($curVersion = self::pluginData($row[1])) {
                $curSumVersion = calcSumVersion($curVersion["version"]);
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
        $qUncpacked = self::$dbLink->query("SELECT `short`, `full`, `version`, `about`, `credits`, `url`, `email`, `license`, `depends` "
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
        list($shortName, $fullName, $version, $about, $credits, $url, $email, $license, $depends) = $qUncpacked->fetch_row();
        if ($curVersion = self::pluginData($shortName)) {
            $curSumVersion = calcSumVersion($curVersion["version"]);
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
        $unpackedNode->appendChild($xml->createElement('depends', $depends));
        $unpackedNode->appendChild($xml->createElement('action', $action));
        return $xml;
    }
    
    public static function pluginData($plugin) {
        self::zeroizeError();
        if (!pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, "pluginData: incorrect name");
            return FALSE;
        }
        $qPlugin = self::$dbLink->query("SELECT `id`, `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin'");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "pluginData: unable to get plugin version | ".self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "pluginData: plugin not found");
            return FALSE;
        }
        list($id, $version) = $qPlugin->fetch_row();
        return array("id" => (int) $id, "version" => $version);
    }
    
    public static function install($id, $reset = FALSE) {
        if (!is_integer($id) || !is_bool($reset)) {
            self::setError(ERROR_INCORRECT_DATA, "install: incorrect argument(s)");
            return FALSE;
        }
        $qPlugin = self::$dbLink->query("SELECT `short`, `full`, `version`, `spec`, `dirname`, `about`, `credits`, `url`, `email`, `license` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, "install: ".self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "install: unpacked plugin with id [$id] not found");
            return FALSE;
        }
        list($shortName, $fullName, $version, $packVersion, $plugDir, $about, $credits, $url, $email, $license) = $qPlugin->fetch_row();
        if ($packVersion != '0.1') {
                self::setError(ERROR_INCORRECT_DATA, "install: installer is incompatible with the package specification [$packVersion]");
                return FALSE;
            }
        // revalidate xml components
        $plugPath = MECCANO_UNPACKED_PLUGINS."/$plugDir";
        $serviceData = new \DOMDocument();
        $xmlComponents = array(
            "policy.xml" => "policy-v01.rng",
            "log.xml" => "logman-events-v01.rng",
            "texts.xml" => "langman-text-v01.rng",
            "titles.xml" => "langman-title-v01.rng",
            "depends.xml" => "plugins-package-depends-v01.rng"
            );
        foreach ($xmlComponents as $valComponent=> $valSchema) {
            $xmlComponent = openRead($plugPath."/$valComponent");
            if (!$xmlComponent) {
                self::setError(ERROR_NOT_EXECUTED, "unpack: unable to read [$valComponent]");
                return FALSE;
            }
            if (mime_content_type($plugPath."/$valComponent") != "application/xml") {
                self::setError(ERROR_NOT_EXECUTED, "unpack: [$valComponent] is not XML-structured");
                return FALSE;
            }
            $serviceData->loadXML($xmlComponent);
            if (!@$serviceData->relaxNGValidate(MECCANO_CORE_DIR."/validation-schemas/$valSchema")) {
                self::setError(ERROR_INCORRECT_DATA, "unpack: invalid [$valComponent] structure");
                return FALSE;
            }
        }
        // check for plugin dependences
        $dependsNodes = $serviceData->getElementsByTagName("plugin");
        foreach ($dependsNodes as $dependsNode) {
            $depPlugin = $depends.$dependsNode->getAttribute('name');
            $depVersion = $dependsNode->getAttribute('version');
            $operator = $dependsNode->getAttribute('operator');
            $existDep = self::pluginData($depPlugin);
            if (!$existDep || !compareVersions($existDep["version"], $depVersion, $operator)) {
                self::setError(ERROR_NOT_FOUND, "install: required $depPlugin ($operator $depVersion)");
                return FALSE;
            }
        }
        // check existence of the required files and directories
        $requiredFiles = array("inst.php", "rm.php");
        foreach ($requiredFiles as $fileName) {
            if (!is_file($plugPath."/$fileName")) {
                self::setError(ERROR_NOT_FOUND, "install: file [$fileName] is required");
                return FALSE;
            }
        }
        $requiredDirs = array("documents","js","php");
        foreach ($requiredDirs as $dirName) {
            if (!is_dir($plugPath."/$dirName")) {
                self::setError(ERROR_NOT_FOUND, "install: directory [$dirName] is required");
                return FALSE;
            }
        }
        // get identifier and version of the being installed plugin
        if ($idAndVersion = self::pluginData($shortName)) {
            $existId = (int) $idAndVersion["id"]; // identifier of the being reinstalled/upgraded/downgraded plugin
            $existVersion = $idAndVersion["version"]; // version of the being reinstalled/upgraded/downgraded plugin
        }
        else {
            self::$dbLink->query(
                    "INSERT INTO `".MECCANO_TPREF."_core_plugins_installed` "
                    . "(`name`, `version`) "
                    . "VALUES ('$shortName', '$version') ;"
                    );
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, "install: ".self::$dbLink->error);
                return FALSE;
            }
            $existId = (int) self::$dbLink->insert_id; // identifier if the being installed plugin
            $existVersion = ""; // empty version of the being installed plugin
        }
        // insert or update information about plugin
        if ($existVersion) {
            $sql = array(
                "UPDATE `".MECCANO_TPREF."_core_plugins_installed` "
                . "SET `version`='$version' "
                . "WHERE `id`=$existId ;",
                "UPDATE `".MECCANO_TPREF."_core_plugins_installed_about` "
                . "SET `full`='$fullName', "
                . "`about`='$about', "
                . "`credits`='$credits', "
                . "`url`='$url', "
                . "`email`='$email', "
                . "`license`='$license' "
                . "WHERE `id`=$existId"
            );
        }
        else {
            $sql = array(
                "INSERT INTO `".MECCANO_TPREF."_core_plugins_installed_about` "
                . "(`id`, `full`, `about`, `credits`, `url`, `email`, `license`) "
                . "VALUES ($existId, '$fullName', '$about', '$credits', '$url', '$email', '$license') ;"
            );
        }
        foreach ($sql as $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, "install: ".self::$dbLink->error);
                return FALSE;
            }
        }
        // run preinstallation
        require_once $plugPath.'/inst.php';
        $instObject = new Install(self::$dbLink, $existId, $existVersion, $reset);
        if (!$instObject->preinst()) {
            self::setError($instObject->errId(), "install -> ".$instObject->errExp());
            return FALSE;
        }
        // install policy access rules
        $serviceData->load($plugPath.'/policy.xml');
        if (!self::$policyObject->install($serviceData, FALSE)) {
            self::setError(self::$policyObject->errId(), "install -> ".self::$policyObject->errExp());
            return FALSE;
        }
        // install log events
        $serviceData->load($plugPath.'/log.xml');
        if (!self::$logObject->installEvents($serviceData, FALSE)) {
            self::setError(self::$logObject->errId(), "install -> ".self::$logObject->errExp());
            return FALSE;
        }
        // install texts
        $serviceData->load($plugPath.'/texts.xml');
        if (!self::$langmanObject->installTexts($serviceData, FALSE)) {
            self::setError(self::$langmanObject->errId(), "install -> ".self::$langmanObject->errExp());
            return FALSE;
        }
        // install titles
        $serviceData->load($plugPath.'/titles.xml');
        if (!self::$langmanObject->installTitles($serviceData, FALSE)) {
            self::setError(self::$langmanObject->errId(), "install -> ".self::$langmanObject->errExp());
            return FALSE;
        }
        // copy files and directories to their destinations
        if ($shortName == "core") {
            $docDest = MECCANO_CORE_DIR;
        }
        else {
            $docDest = MECCANO_PHP_DIR."/$shortName";
        }
        $beingCopied = array(
            "documents" => MECCANO_DOCUMENTS_DIR."/$shortName",
            "php" => $docDest,
            "js" => MECCANO_JS_DIR."/$shortName",
            "rm.php" => MECCANO_UNINSTALL."/$shortName.php"
        );
        foreach ($beingCopied as $source => $dest) {
            if (!Files::copy($plugPath."/$source", $dest, TRUE, TRUE)) {
                self::setError(Files::errId(), "install -> ".Files::errExp());
                return FALSE;
            }
        }
        // run postinstallation
        if (!$instObject->postinst()) {
            self::setError($instObject->errId(), "install -> ".$instObject->errExp());
            return FALSE;
        }
        return $existId;
    }
}
