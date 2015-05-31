<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [plugins.php].
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

require_once 'logman.php';
require_once 'files.php';

interface intPlugins {
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject, LangMan $langmanObject);
    public function unpack($package);
    public function delUnpacked($plugin);
    public function listUnpacked();
    public function aboutUnpacked($plugin);
    public function pluginData($plugin);
    public function install($plugin, $reset = FALSE);
    public function delInstalled($plugin, $keepData = TRUE);
    public function listInstalled();
    public function aboutInstalled($plugin);
}

class Plugins extends ServiceMethods implements intPlugins {
    private $dbLink; // database link
    private $logObject; // log object
    private $policyObject; // policy object
    private $langmanObject; // policy object
    
    public function __construct(\mysqli $dbLink, LogMan $logObject, Policy $policyObject, LangMan $langmanObject) {
        $this->dbLink = $dbLink;
        $this->logObject = $logObject;
        $this->policyObject = $policyObject;
        $this->langmanObject = $langmanObject;
    }
    
    public function unpack($package) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_install')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "unpack: restricted by the policy");
            return FALSE;
        }
        $zip = new \ZipArchive();
        $zipOpen = $zip->open($package);
        if ($zipOpen === TRUE) {
            $tmpName = makeIdent();
            $unpackPath = MECCANO_UNPACKED_PLUGINS."/$tmpName";
            $tmpPath = MECCANO_TMP_DIR."/$tmpName";
            if (!@$zip->extractTo($tmpPath)) {
                $this->setError(ERROR_NOT_EXECUTED, "unpack: unable to extract package to $tmpPath");
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
                    $this->setError(ERROR_NOT_EXECUTED, "unpack: unable to read [$valComponent]");
                    return FALSE;
                }
                if (mime_content_type($tmpPath."/$valComponent") != "application/xml") {
                    Files::remove($tmpPath);
                    $this->setError(ERROR_NOT_EXECUTED, "unpack: [$valComponent] is not XML-structured");
                    return FALSE;
                }
                $serviceData->loadXML($xmlComponent);
                if (!@$serviceData->relaxNGValidate(MECCANO_CORE_DIR."/validation-schemas/$valSchema")) {
                    Files::remove($tmpPath);
                    $this->setError(ERROR_INCORRECT_DATA, "unpack: invalid [$valComponent] structure");
                    return FALSE;
                }
            }
            // get data from metainfo.xml
            $packVersion = $serviceData->getElementsByTagName('metainfo')->item(0)->getAttribute('version');
            if ($packVersion != '0.1') {
                Files::remove($tmpPath);
                $this->setError(ERROR_INCORRECT_DATA, "unpack: installer is incompatible with the package specification [$packVersion]");
                return FALSE;
            }
            $shortName = $serviceData->getElementsByTagName('shortname')->item(0)->nodeValue;
            $qIsUnpacked = $this->dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                    . "WHERE `short`='$shortName' ;");
            if ($this->dbLink->errno) {
                Files::remove($tmpPath);
                $this->setError(ERROR_NOT_EXECUTED, 'unpack: unable to check whether the plugin is unpacked -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                Files::remove($tmpPath);
                $this->setError(ERROR_ALREADY_EXISTS, "unpack: plugin [$shortName] was already unpacked");
                return FALSE;
            }
            $fullName = $this->dbLink->real_escape_string(htmlspecialchars($serviceData->getElementsByTagName('fullname')->item(0)->nodeValue));
            $version = $serviceData->getElementsByTagName('version')->item(0)->nodeValue;
            $insertColumns = "`short`, `full`, `version`, `spec`, `dirname`";
            $insertValues = "'$shortName', '$fullName', '$version', '$packVersion', '$tmpName'";
            // get optional data
            $optionalData = array('about', 'credits', 'url', 'email', 'license');
            foreach ($optionalData as $optNode) {
                if ($optional = $serviceData->getElementsByTagName("$optNode")->item(0)->nodeValue) {
                    $optional = $this->dbLink->real_escape_string($optional);
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
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_plugins_unpacked` ($insertColumns)"
                    . "VALUES ($insertValues) ;");
            if ($this->dbLink->errno) {
                Files::remove($tmpPath);
                $this->setError(ERROR_NOT_EXECUTED, 'unpack: '.$this->dbLink->error);
                return FALSE;
            }
            if (!Files::move($tmpPath, $unpackPath)) {
                $this->setError(Files::errId(), 'unpack: -> '.Files::errExp());
                return FALSE;
            }
        }
        else {
            $this->setError(ERROR_NOT_EXECUTED, "unpack: unable to open package. ZipArchive error: $zipOpen");
            return FALSE;
        }
        return $shortName;
    }
    
    public function delUnpacked($plugin) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_install')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delUnpacked: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delUnpacked: incorrect plugin name');
            return FALSE;
        }
        $qUnpacked = $this->dbLink->query("SELECT `dirname` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `short`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delUnpacked: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "delUnpacked: cannot find defined plugin");
            return FALSE;
        }
        list($dirName) = $qUnpacked->fetch_row();
        if (!Files::remove(MECCANO_UNPACKED_PLUGINS."/$dirName")) {
            $this->setError(Files::errId(), 'delUnpacked: -> '.Files::errExp());
            return FALSE;
        }
        $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `short`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delUnpacked: unable to delete unpacked plugin ->'.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function listUnpacked() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_install')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "listUnpacked: restricted by the policy");
            return FALSE;
        }
        $qUncpacked = $this->dbLink->query("SELECT `short`, `full`, `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "listUnpacked: ".$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $unpackedNode = $xml->createElement('unpacked');
        $xml->appendChild($unpackedNode);
        while ($row = $qUncpacked->fetch_row()) {
            if ($curVersion = $this->pluginData($row[0])) {
                $curSumVersion = calcSumVersion($curVersion["version"]);
                $newSumVersion = calcSumVersion($row[2]);
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
            $pluginNode->appendChild($xml->createElement('short', $row[0]));
            $pluginNode->appendChild($xml->createElement('full', $row[1]));
            $pluginNode->appendChild($xml->createElement('version', $row[2]));
            $pluginNode->appendChild($xml->createElement('action', $action));
        }
        return $xml;
    }
    
    public function aboutUnpacked($plugin) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_install')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "aboutUnpacked: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'aboutUnpacked: incorrect plugin name');
            return FALSE;
        }
        $qUncpacked = $this->dbLink->query("SELECT `short`, `full`, `version`, `about`, `credits`, `url`, `email`, `license`, `depends` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `short`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "aboutUnpacked: ".$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "aboutUnpacked: plugin not found");
            return FALSE;
        }
        list($shortName, $fullName, $version, $about, $credits, $url, $email, $license, $depends) = $qUncpacked->fetch_row();
        if ($curVersion = $this->pluginData($shortName)) {
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
        $xml = new \DOMDocument('1.0', 'utf-8');
        $unpackedNode = $xml->createElement('unpacked');
        $xml->appendChild($unpackedNode);
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
    
    public function pluginData($plugin) {
        $this->zeroizeError();
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, "pluginData: incorrect name");
            return FALSE;
        }
        $qPlugin = $this->dbLink->query("SELECT `id`, `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin'");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "pluginData: unable to get plugin version -> ".$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "pluginData: plugin not found");
            return FALSE;
        }
        list($id, $version) = $qPlugin->fetch_row();
        return array("id" => (int) $id, "version" => $version);
    }
    
    public function install($plugin, $reset = FALSE, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_install')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "install: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin) || !is_bool($reset)) {
            $this->setError(ERROR_INCORRECT_DATA, "install: incorrect argument(s)");
            return FALSE;
        }
        $qPlugin = $this->dbLink->query("SELECT `short`, `full`, `version`, `spec`, `dirname`, `about`, `credits`, `url`, `email`, `license` "
                . "FROM `".MECCANO_TPREF."_core_plugins_unpacked` "
                . "WHERE `short`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "install: ".$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "install: unpacked plugin [$plugin] not found");
            return FALSE;
        }
        list($shortName, $fullName, $version, $packVersion, $plugDir, $about, $credits, $url, $email, $license) = $qPlugin->fetch_row();
        if ($packVersion != '0.1') {
                $this->setError(ERROR_INCORRECT_DATA, "install: installer is incompatible with the package specification [$packVersion]");
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
                $this->setError(ERROR_NOT_EXECUTED, "unpack: unable to read [$valComponent]");
                return FALSE;
            }
            if (mime_content_type($plugPath."/$valComponent") != "application/xml") {
                $this->setError(ERROR_NOT_EXECUTED, "unpack: [$valComponent] is not XML-structured");
                return FALSE;
            }
            $serviceData->loadXML($xmlComponent);
            if (!@$serviceData->relaxNGValidate(MECCANO_CORE_DIR."/validation-schemas/$valSchema")) {
                $this->setError(ERROR_INCORRECT_DATA, "unpack: invalid [$valComponent] structure");
                return FALSE;
            }
        }
        // check for plugin dependences
        $dependsNodes = $serviceData->getElementsByTagName("plugin");
        foreach ($dependsNodes as $dependsNode) {
            $depPlugin = $depends.$dependsNode->getAttribute('name');
            $depVersion = $dependsNode->getAttribute('version');
            $operator = $dependsNode->getAttribute('operator');
            $existDep = $this->pluginData($depPlugin);
            if (!$existDep || !compareVersions($existDep["version"], $depVersion, $operator)) {
                $this->setError(ERROR_NOT_FOUND, "install: required $depPlugin ($operator $depVersion)");
                return FALSE;
            }
        }
        // check existence of the required files and directories
        $requiredFiles = array("inst.php", "rm.php");
        foreach ($requiredFiles as $fileName) {
            if (!is_file($plugPath."/$fileName")) {
                $this->setError(ERROR_NOT_FOUND, "install: file [$fileName] is required");
                return FALSE;
            }
        }
        $requiredDirs = array("documents","js","php");
        foreach ($requiredDirs as $dirName) {
            if (!is_dir($plugPath."/$dirName")) {
                $this->setError(ERROR_NOT_FOUND, "install: directory [$dirName] is required");
                return FALSE;
            }
        }
        // get identifier and version of the being installed plugin
        if ($idAndVersion = $this->pluginData($shortName)) {
            $existId = (int) $idAndVersion["id"]; // identifier of the being reinstalled/upgraded/downgraded plugin
            $existVersion = $idAndVersion["version"]; // version of the being reinstalled/upgraded/downgraded plugin
        }
        else {
            $this->dbLink->query(
                    "INSERT INTO `".MECCANO_TPREF."_core_plugins_installed` "
                    . "(`name`, `version`) "
                    . "VALUES ('$shortName', '$version') ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "install: ".$this->dbLink->error);
                return FALSE;
            }
            $existId = (int) $this->dbLink->insert_id; // identifier if the being installed plugin
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
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "install: ".$this->dbLink->error);
                return FALSE;
            }
        }
        // run preinstallation
        require_once $plugPath.'/inst.php';
        $instObject = new Install($this->dbLink, $existId, $existVersion, $reset);
        if (!$instObject->preinst()) {
            $this->setError($instObject->errId(), "install -> ".$instObject->errExp());
            return FALSE;
        }
        // install policy access rules
        $serviceData->load($plugPath.'/policy.xml');
        if (!$this->policyObject->install($serviceData, FALSE)) {
            $this->setError($this->policyObject->errId(), "install -> ".$this->policyObject->errExp());
            return FALSE;
        }
        // install log events
        $serviceData->load($plugPath.'/log.xml');
        if (!$this->logObject->installEvents($serviceData, FALSE)) {
            $this->setError($this->logObject->errId(), "install -> ".$this->logObject->errExp());
            return FALSE;
        }
        // install texts
        $serviceData->load($plugPath.'/texts.xml');
        if (!$this->langmanObject->installTexts($serviceData, FALSE)) {
            $this->setError($this->langmanObject->errId(), "install -> ".$this->langmanObject->errExp());
            return FALSE;
        }
        // install titles
        $serviceData->load($plugPath.'/titles.xml');
        if (!$this->langmanObject->installTitles($serviceData, FALSE)) {
            $this->setError($this->langmanObject->errId(), "install -> ".$this->langmanObject->errExp());
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
            if (!Files::copy($plugPath."/$source", $dest, TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, TRUE)) {
                $this->setError(Files::errId(), "install -> ".Files::errExp());
                return FALSE;
            }
        }
        // run postinstallation
        if (!$instObject->postinst()) {
            $this->setError($instObject->errId(), "install -> ".$instObject->errExp());
            return FALSE;
        }
        //
        if ($log && !$this->logObject->newRecord('core', 'plugins_install', "$shortName; v$version; ID: $existId")) {
            $this->setError(ERROR_NOT_CRITICAL, "install -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function delInstalled($plugin, $keepData = TRUE, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_del_installed')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delInstalled: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin) || !is_bool($keepData)) {
            $this->setError(ERROR_INCORRECT_DATA, "delInstalled: incorrect argument(s)");
            return FALSE;
        }
        // check whether the plugin installed
        $qPlugin = $this->dbLink->query("SELECT `id`, `name`, `version` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "delInstalled: plugin not found");
            return FALSE;
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "delInstalled: ".$this->dbLink->error);
            return FALSE;
        }
        list($id, $shortName, $version) = $qPlugin->fetch_row();
        if (strtolower($shortName) == "core") {
            $this->setError(ERROR_SYSTEM_INTERVENTION, "delInstalled: unable to remove [core]");
            return FALSE;
        }
        // check whether the removement script exists
        if (!is_file(MECCANO_UNINSTALL."/$shortName.php")) {
            $this->setError(ERROR_NOT_FOUND, "delInstalled: removement script [".MECCANO_UNINSTALL."/$shortName.php] not found");
            return FALSE;
        }
        //run preremovement
        require_once MECCANO_UNINSTALL."/$shortName.php";
        $rmObject = new Remove($this->dbLink, $id, $keepData);
        if (!$rmObject->prerm()) {
            $this->setError($rmObject->errId(), "delInstalled -> ".$rmObject->errExp());
            return FALSE;
        }
        // remove policy access rules
        if (!$this->policyObject->delPolicy($shortName)) {
            $this->setError($this->policyObject->errId(), "delInstalled -> ".$this->policyObject->errExp());
            return FALSE;
        }
        // remove log events
        if (!$this->logObject->delEvents($shortName)) {
            $this->setError($this->logObject->errId(), "delInstalled -> ".$this->logObject->errExp());
            return FALSE;
        }
        // remove texts and titles
        if (!$this->langmanObject->delPlugin($shortName)) {
            $this->setError($this->langmanObject->errId(), "delInstalled -> ".$this->langmanObject->errExp());
            return FALSE;
        }
        // run postremovement
        if (!$rmObject->postrm()) {
            $this->setError($rmObject->errId(), "delInstalled -> ".$rmObject->errExp());
            return FALSE;
        }
        // remove files and directories of the plugin
        $beingRemoved = array(
            "php" => MECCANO_PHP_DIR."/$shortName",
            "js" => MECCANO_JS_DIR."/$shortName",
            "rm.php" => MECCANO_UNINSTALL."/$shortName.php"
        );
        if (!$keepData) {
            $beingRemoved["documents"] = MECCANO_DOCUMENTS_DIR."/$shortName";
        }
        foreach ($beingRemoved as $source) {
            if (!Files::remove($source)) {
                $this->setError(Files::errId(), "delInstalled -> ".Files::errExp());
                return FALSE;
            }
        }
        // delete information about plugin
        $sql = array(
            "DELETE FROM `".MECCANO_TPREF."_core_plugins_installed_about` "
            . "WHERE `id`=$id",
            "DELETE FROM `".MECCANO_TPREF."_core_plugins_installed` "
            . "WHERE `id`=$id",
        );
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delInstalled: ".$this->dbLink->error);
                return FALSE;
            }
        }
        //
        if ($log && !$this->logObject->newRecord('core', 'plugins_del_installed', "$shortName; v$version; ID: $id")) {
            $this->setError(ERROR_NOT_CRITICAL, "install -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function listInstalled() {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_installed')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "listInstalled: restricted by the policy");
            return FALSE;
        }
        $qInstalled = $this->dbLink->query("SELECT `i`.`name`, `a`.`full`, `i`.`version`, `i`.`time` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` `i` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed_about` `a` "
                . "ON `a`.`id`=`i`.`id` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "listInstalled: ".$this->dbLink->error);
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $installedNode = $xml->createElement("installed");
        $xml->appendChild($installedNode);
        while ($row = $qInstalled->fetch_row()) {
            $pluginNode = $xml->createElement("plugin");
            $installedNode->appendChild($pluginNode);
            $pluginNode->appendChild($xml->createElement("short", $row[0]));
            $pluginNode->appendChild($xml->createElement("full", $row[1]));
            $pluginNode->appendChild($xml->createElement("version", $row[2]));
            $pluginNode->appendChild($xml->createElement("time", $row[3]));
        }
        return $xml;
    }
    
    public function aboutInstalled($plugin) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'plugins_installed')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "aboutInstalled: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, "aboutInstalled: incorrect plugin name");
            return FALSE;
        }
        $qPlugin = $this->dbLink->query("SELECT `i`.`name`, `a`.`full`, `i`.`version`, `i`.`time`, `a`.`about`, `a`.`credits`, `a`.`url`, `a`.`email`, `a`.`license` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` `i` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed_about` `a` "
                . "ON `a`.`id`=`i`.`id` "
                . "WHERE `i`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "aboutInstalled: ".$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "aboutInstalled: plugin not found");
            return FALSE;
        }
        list($shortName, $fullName, $version, $instTime, $about, $credits, $url, $email, $license) = $qPlugin->fetch_row();
        $xml = new \DOMDocument('1.0', 'utf-8');
        $installedNode = $xml->createElement("installed");
        $xml->appendChild($installedNode);
        $installedNode->appendChild($xml->createElement("short", $shortName));
        $installedNode->appendChild($xml->createElement("full", $fullName));
        $installedNode->appendChild($xml->createElement("version", $version));
        $installedNode->appendChild($xml->createElement("time", $instTime));
        $installedNode->appendChild($xml->createElement("about", $about));
        $installedNode->appendChild($xml->createElement("credits", $credits));
        $installedNode->appendChild($xml->createElement("url", $url));
        $installedNode->appendChild($xml->createElement("email", $email));
        $installedNode->appendChild($xml->createElement("license", $license));
        return $xml;
    }
}
