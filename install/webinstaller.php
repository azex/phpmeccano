<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Web installer.
 *     Copyright (C) 2015-2019  Alexei Muzarov
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

loadPHP('plugins');

interface intWebInstaller {
    public function __construct();
    public function validateConstants();
    public function revalidateAll($post);
    public function createDbTables();
    public function installPackage();
    public function groupUsers($userParam);
}

class WebInstaller extends ServiceMethods implements intWebInstaller {
    
    public function __construct() {
        if (!session_id()) {
            session_start();
        }
    }
    
    public function validateConstants() {
        $_checkConst = [
            "MECCANO_DBSTORAGE_ENGINE",
            "MECCANO_DBANAME",
            "MECCANO_DBAPASS",
            "MECCANO_DBHOST",
            "MECCANO_DBPORT",
            "MECCANO_DBNAME",
            "MECCANO_TPREF",
            "MECCANO_CONF_FILE",
            "MECCANO_ROOT_DIR",
            "MECCANO_CORE_DIR",
            "MECCANO_TMP_DIR",
            "MECCANO_PHP_DIR",
            "MECCANO_JS_DIR",
            "MECCANO_CSS_DIR",
            "MECCANO_DOCUMENTS_DIR",
            "MECCANO_UNPACKED_PLUGINS",
            "MECCANO_UNINSTALL",
            "MECCANO_SERVICE_PAGES",
            "MECCANO_SHARED_FILES",
            "MECCANO_SHARED_STDIR",
            "MECCANO_DEF_LANG",
            "MECCANO_AUTH_LIMIT",
            "MECCANO_AUTH_BLOCK_PERIOD",
            "MECCANO_SHOW_ERRORS",
            "MECCANO_MNTC_IP"
        ];
        foreach ($_checkConst as $value) {
            if (!defined($value)) {
                define($value, "N/A");
            }
        }
        $constStatus = [];
        // validate type of the database storage engine
        if (in_array(MECCANO_DBSTORAGE_ENGINE, ['MyISAM', 'InnoDB'])) {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = [true, MECCANO_DBSTORAGE_ENGINE];
        }
        elseif (!is_string(MECCANO_DBSTORAGE_ENGINE) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_DBSTORAGE_ENGINE)) {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = [false, "N/A"];
        }
        else {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = [false, htmlspecialchars(MECCANO_DBSTORAGE_ENGINE)];
        }
        // validate database settings
        $dbConf = [
            'MECCANO_DBANAME' => MECCANO_DBANAME,
            'MECCANO_DBAPASS' => MECCANO_DBAPASS,
            'MECCANO_DBHOST' => MECCANO_DBHOST,
            'MECCANO_DBPORT' => MECCANO_DBPORT
            ];
        $mysqlTest = new \mysqli();
        $isValid = @$mysqlTest->real_connect(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, '', MECCANO_DBPORT);
        foreach ($dbConf as $key => $value) {
            if (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                if ($key == 'MECCANO_DBAPASS') {
                    $constStatus[$key] = [true, "N/A"];
                }
                else {
                    $constStatus[$key] = [false, "N/A"];
                }
            }
            else {
                if ($key == 'MECCANO_DBAPASS') {
                    $pass = preg_replace("/./", "*", $value);
                    $constStatus[$key] = [$isValid, $pass];
                }
                else {
                    $constStatus[$key] = [$isValid, htmlspecialchars($value)];
                }
            }
        }
        // validate database name and table prefix
        $dbConf2 = [
            'MECCANO_DBNAME' => MECCANO_DBNAME,
            'MECCANO_TPREF' => MECCANO_TPREF
        ];
        foreach ($dbConf2 as $key => $value) {
            if ($key == 'MECCANO_DBNAME' && pregDbName($value)) {
                $constStatus[$key] = [true, $value];
            }
            elseif ($key == 'MECCANO_TPREF' && pregPref($value)) {
                $constStatus[$key] = [true, $value];
            }
            elseif (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                $constStatus[$key] = [false, "N/A"];
            }
            else {
                $constStatus[$key] = [false, htmlspecialchars($value)];
            }
        }
        // validate system pathes and path to folder of shared files
        $sysPathes = [
            'MECCANO_CONF_FILE' => MECCANO_CONF_FILE,
            'MECCANO_ROOT_DIR' => MECCANO_ROOT_DIR,
            'MECCANO_CORE_DIR' => MECCANO_CORE_DIR,
            'MECCANO_TMP_DIR' => MECCANO_TMP_DIR,
            'MECCANO_PHP_DIR' => MECCANO_PHP_DIR,
            'MECCANO_JS_DIR' => MECCANO_JS_DIR,
            'MECCANO_CSS_DIR' => MECCANO_CSS_DIR,
            'MECCANO_DOCUMENTS_DIR' => MECCANO_DOCUMENTS_DIR,
            'MECCANO_UNPACKED_PLUGINS' => MECCANO_UNPACKED_PLUGINS,
            'MECCANO_UNINSTALL' => MECCANO_UNINSTALL,
            'MECCANO_SERVICE_PAGES' => MECCANO_SERVICE_PAGES,
            'MECCANO_SHARED_FILES' => MECCANO_SHARED_FILES
        ];
        foreach ($sysPathes as $key => $value) {
            if ($key == 'MECCANO_CONF_FILE' && is_file($value) && is_readable($value)) {
                $constStatus[$key] = [true, $value];
            }
            elseif (is_dir($value) && is_writable($value)) {
                $constStatus[$key] = [true, $value];
            }
            elseif (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                $constStatus[$key] = [false, "N/A"];
            }
            else {
                $constStatus[$key] = [false, htmlspecialchars($value)];
            }
        }
        //  validate sub-folder of shared files
        if (is_string(MECCANO_SHARED_STDIR) && preg_match("/^[-a-zA-Z0-9_]{5,40}$/", MECCANO_SHARED_STDIR)) {
            $constStatus['MECCANO_SHARED_STDIR'] = [true, MECCANO_SHARED_STDIR];
        }
        elseif (!is_string(MECCANO_SHARED_STDIR) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_SHARED_STDIR)) {
            $constStatus['MECCANO_SHARED_STDIR'] = [false, "N/A"];
        }
        else {
            $constStatus['MECCANO_SHARED_STDIR'] = [false, htmlspecialchars(MECCANO_SHARED_STDIR)];
        }
        //validate default language
        if (in_array(MECCANO_DEF_LANG, ["en-US", "ru-RU"])) {
            $constStatus['MECCANO_DEF_LANG'] = [true, MECCANO_DEF_LANG];
        }
        elseif (!is_string(MECCANO_DEF_LANG) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_DEF_LANG)) {
            $constStatus['MECCANO_DEF_LANG'] = [false, "N/A"];
        }
        else {
            $constStatus['MECCANO_DEF_LANG'] = [false, htmlspecialchars(MECCANO_DEF_LANG)];
        }
        // validate temporary blocking data
        // authentication limit
        if (is_integer(MECCANO_AUTH_LIMIT) && MECCANO_AUTH_LIMIT > -1) {
            $constStatus['MECCANO_AUTH_LIMIT'] = [true, MECCANO_AUTH_LIMIT];
        }
        elseif (is_numeric(MECCANO_AUTH_LIMIT)) {
                $constStatus['MECCANO_AUTH_LIMIT'] = [false, MECCANO_AUTH_LIMIT];
        }
        elseif (!is_string(MECCANO_AUTH_LIMIT) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_AUTH_LIMIT)) {
                $constStatus['MECCANO_AUTH_LIMIT'] = [false, "N/A"];
        }
        else {
            $constStatus['MECCANO_AUTH_LIMIT'] = [false, htmlspecialchars(MECCANO_AUTH_LIMIT)];
        }
        // authentication blocking period
        if (is_string(MECCANO_AUTH_BLOCK_PERIOD) && preg_match('/^([0-1]{1}[0-9]{1}|[2]{1}[0-3]{1}):[0-5]{1}[0-9]{1}:[0-5]{1}[0-9]{1}$/', MECCANO_AUTH_BLOCK_PERIOD)) {
            $constStatus['MECCANO_AUTH_BLOCK_PERIOD'] = [true, MECCANO_AUTH_BLOCK_PERIOD];
        }
        elseif (!is_string(MECCANO_AUTH_BLOCK_PERIOD) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_AUTH_BLOCK_PERIOD)) {
                $constStatus['MECCANO_AUTH_BLOCK_PERIOD'] = [false, "N/A"];
        }
        else {
            $constStatus['MECCANO_AUTH_BLOCK_PERIOD'] = [false, htmlspecialchars(MECCANO_AUTH_BLOCK_PERIOD)];
        }
        // displaying of errors
        if (is_bool(MECCANO_SHOW_ERRORS)) {
            if (MECCANO_SHOW_ERRORS) {
                $constStatus['MECCANO_SHOW_ERRORS'] = [false, "true"];
            }
            else {
                $constStatus['MECCANO_SHOW_ERRORS'] = [true, "false"];
            }
        }
        else {
            $constStatus['MECCANO_SHOW_ERRORS'] = [false, "N/A"];
        }
        // IP addresses that ignore maintenance mode
        if (is_array(MECCANO_MNTC_IP) && count(MECCANO_MNTC_IP)) {
            $ipList = "";
            $i = 0;
            $isError = false;
            foreach (MECCANO_MNTC_IP as $value) {
                // validate element as IP address
                if (!is_string($value) || !preg_match('/^((^\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$))$/', $value)) {
                    $isError = true;
                    break;
                }
                // add element into output string
                if ($i) { $ipList .= ", ".$value; }
                else { $ipList = $value; }
                $i += 1;
            }
            // if error is raised
            if ($isError) {
                $constStatus['MECCANO_MNTC_IP'] = [false, "false: element #$i"];
            }
            // if everything is alright
            else {
                $constStatus['MECCANO_MNTC_IP'] = [true, $ipList];
            }
        }
        elseif (is_array(MECCANO_MNTC_IP) && !count(MECCANO_MNTC_IP)) {
            $constStatus['MECCANO_MNTC_IP'] = [true, "null"];
        }
        else {
            $constStatus['MECCANO_MNTC_IP'] = [false, "N/A"];
        }
        // return results of the validation
        return $constStatus;
    }
    
    // step #0 - revalidate all data
    public function revalidateAll($post) {
        $this->zeroizeError();
        // validate post data
        if (!is_array($post)) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: incorrect type of the argument");
            return false;
        }
        $required_keys = [
            'groupname',
            'groupdesc',
            'username',
            'passw',
            'repassw',
            'email'
        ];
        if (array_diff($required_keys, array_keys($post))) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: incomplete user parameters");
            return false;
        }
        if (!pregGName($post['groupname'])) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: invalid group name");
            return false;
        }
        if (!pregUName($post['username'])) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: invalid username");
            return false;
        }
        if (!pregPassw($post['passw']) || ($post['passw'] != $post['repassw'])) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: invalid password or passwords mismatch");
            return false;
        }
        if (!pregMail($post['email'])) {
            $this->setError(ERROR_INCORRECT_DATA, "step #0: incorrect e-mail address");
            return false;
        }
        // validate configurations
        $constStatus = $this->validateConstants();
        foreach ($constStatus as $value) {
            if (!$value[0]) {
                $this->setError(ERROR_INCORRECT_DATA, "step #0: incorrect system parameters");
                return false;
            }
        }
        $_SESSION['webinstaller_step'] = 1;
        return true;
    }
    
    // step #1
    public function createDbTables() {
        $this->zeroizeError();
        $dbName = MECCANO_DBNAME;
        $tabPrefix = MECCANO_TPREF;
        $sEngine = MECCANO_DBSTORAGE_ENGINE;
        $sql = new \mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, '', MECCANO_DBPORT);
        $sql->set_charset('utf8');
        $sql->query("DROP DATABASE `$dbName` ;");
        $queries = [
            // disable errors while installation on newer MySQL versions
            "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';",

            // create database
            "CREATE DATABASE `$dbName` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ;",
            
            // select database
            "USE `$dbName` ;",
            
            // installed plugin
            "CREATE TABLE `{$tabPrefix}_core_plugins_installed` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `name` char(30) NOT null DEFAULT '',
                `version` char(8) NOT null DEFAULT '' COMMENT 'Version of the plugin',
                `time` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Installed plug-ins' AUTO_INCREMENT=1 ;",
            
            // about installed plugin
            "CREATE TABLE `{$tabPrefix}_core_plugins_installed_about` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `full` varchar(50) NOT null DEFAULT '' COMMENT 'Full name of the plugin',
                `about` text NOT null DEFAULT '' COMMENT 'Some words about the plugin',
                `credits` text NOT null DEFAULT '' COMMENT 'Information about developer(s)',
                `url` varchar(100) NOT null DEFAULT '' COMMENT 'Address of the project\'s homepage',
                `email` varchar(100) NOT null DEFAULT '' COMMENT 'E-mail of the developer(s)',
                `license` text NOT null DEFAULT '' COMMENT 'License agreement',
                FOREIGN KEY (`id`) REFERENCES `{$tabPrefix}_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Information about installed plug-ins' AUTO_INCREMENT=1 ;",
            
            // about unpacked plugins
            "CREATE TABLE `{$tabPrefix}_core_plugins_unpacked` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `short` varchar(30) NOT null DEFAULT '' COMMENT 'Short name of the plugin',
                `full` varchar(50) NOT null DEFAULT '' COMMENT 'Full name of the plugin',
                `version` varchar(8) NOT null DEFAULT '' COMMENT 'Version of the plugin',
                `spec` varchar(5) NOT null DEFAULT '' COMMENT 'Version of package specification',
                `dirname` char(40) NOT null DEFAULT '',
                `about` text NOT null DEFAULT '' COMMENT 'Some words about the plugin',
                `credits` text NOT null DEFAULT '' COMMENT 'Information about developer(s)',
                `url` varchar(100) NOT null DEFAULT '' COMMENT 'Address of the project\'s homepage',
                `email` varchar(100) NOT null DEFAULT '' COMMENT 'E-mail of the developer(s)',
                `license` text NOT null DEFAULT '' COMMENT 'License agreement',
                `depends` text NOT null DEFAULT '' COMMENT 'Dependences needed for the plugin',
                PRIMARY KEY (`id`),
                UNIQUE KEY `short` (`short`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Information about unpacked but not installed plug-ins' AUTO_INCREMENT=1 ;",
            
            // system languages
            "CREATE TABLE `{$tabPrefix}_core_langman_languages` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `code` char(5) NOT null DEFAULT '' COMMENT 'Language code',
                `name` varchar(50) NOT null DEFAULT '' COMMENT 'Language name',
                `dir` enum('ltr', 'rtl') NOT null DEFAULT 'ltr' COMMENT 'Text direction',
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of language codes' AUTO_INCREMENT=1 ;",
            
            // title sections
            "CREATE TABLE `{$tabPrefix}_core_langman_title_sections` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `plugid` int(11) UNSIGNED NOT null COMMENT 'Plugin identifier',
                `section` varchar(40) NOT null DEFAULT '' COMMENT 'Section of title',
                `static` tinyint(1) UNSIGNED NOT null DEFAULT 1 COMMENT 'Points if the section data can be changed',
                FOREIGN KEY (`plugid`) REFERENCES `{$tabPrefix}_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `section` (`plugid`, `section`),
                KEY `plugin` (`plugid`),
                KEY `static` (`static`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of title sections' AUTO_INCREMENT=1 ;",
            
            // title names
            "CREATE TABLE `{$tabPrefix}_core_langman_title_names` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `sid` int(11) UNSIGNED NOT null COMMENT 'Section identifier',
                `name` varchar(40) NOT null DEFAULT '' COMMENT 'Short name of title',
                FOREIGN KEY (`sid`) REFERENCES `{$tabPrefix}_core_langman_title_sections` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `title` (`sid`, `name`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of title names' AUTO_INCREMENT=1 ;",
            
            // titles
            "CREATE TABLE `{$tabPrefix}_core_langman_titles` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `codeid` int(11) UNSIGNED NOT null COMMENT 'Language code identifier',
                `nameid` int(11) UNSIGNED NOT null COMMENT 'Title name identifier',
                `title` varchar(128) NOT null DEFAULT '' COMMENT 'Title in some language',
                FOREIGN KEY (`codeid`) REFERENCES `{$tabPrefix}_core_langman_languages` (`id`),
                FOREIGN KEY (`nameid`) REFERENCES `{$tabPrefix}_core_langman_title_names` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `index` (`codeid`, `nameid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Titles written in different languages' AUTO_INCREMENT=1 ;",
            
            // text sections
            "CREATE TABLE `{$tabPrefix}_core_langman_text_sections` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `plugid` int(11) UNSIGNED NOT null COMMENT 'Plugin identifier',
                `section` varchar(40) DEFAULT '' NOT null COMMENT 'Section of text',
                `static` tinyint(1) UNSIGNED NOT null DEFAULT 1 COMMENT 'Points if the section data can be changed',
                FOREIGN KEY (`plugid`) REFERENCES `{$tabPrefix}_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `section` (`plugid`, `section`),
                KEY `plugin` (`plugid`),
                KEY `static` (`static`)
            ) ENGINE='$sEngine' DEFAULT CHARSET=utf8 COMMENT 'List of text sections' AUTO_INCREMENT=1 ;",
            
            // text names
            "CREATE TABLE `{$tabPrefix}_core_langman_text_names` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `sid` int(11) UNSIGNED NOT null COMMENT 'Section identifier',
                `name` varchar(40) DEFAULT '' NOT null COMMENT 'Short name of text',
                FOREIGN KEY (`sid`) REFERENCES `{$tabPrefix}_core_langman_text_sections` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `title` (`sid`, `name`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of text names' AUTO_INCREMENT=1 ;",
            
            // texts
            "CREATE TABLE `{$tabPrefix}_core_langman_texts` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `codeid` int(11) UNSIGNED NOT null COMMENT 'Language code identifier',
                `nameid` int(11) UNSIGNED NOT null COMMENT 'Title name identifier',
                `title` varchar(128) NOT null DEFAULT '' COMMENT 'Title of the text',
                `document` text NOT null DEFAULT '' COMMENT 'Document text',
                `created` TIMESTAMP NOT null DEFAULT 0 COMMENT 'Time of creation',
                `edited` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Time of editing',
                FOREIGN KEY (`codeid`) REFERENCES `{$tabPrefix}_core_langman_languages` (`id`),
                FOREIGN KEY (`nameid`) REFERENCES `{$tabPrefix}_core_langman_text_names` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `index` (`codeid`, `nameid`),
                KEY `created` (`created`),
                KEY `edited` (`edited`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Text written in different languages' AUTO_INCREMENT=1 ;",
            
            // groups
            "CREATE TABLE `{$tabPrefix}_core_userman_groups` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `groupname` varchar(50) NOT null DEFAULT '' COMMENT 'Name of the user group',
                `description` varchar(256) NOT null DEFAULT '' COMMENT 'Description of the group destination',
                `creationtime` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the group creation',
                `active` tinyint(1) UNSIGNED NOT null DEFAULT 1 COMMENT 'Flag that shows a group status (active/not active)',
                PRIMARY KEY (`id`),
                UNIQUE KEY `groupname` (`groupname`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of user groups' AUTO_INCREMENT=1 ;",
            
            // users
            "CREATE TABLE `{$tabPrefix}_core_userman_users` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `username` char(20) NOT null DEFAULT '' COMMENT 'Nickname of the user',
                `creationtime` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the user creation',
                `groupid` int(11) UNSIGNED NOT null COMMENT 'Identifier that points to which group the user belongs',
                `salt` char(22) NOT null COMMENT 'Salt for password',
                `active` tinyint(1) UNSIGNED NOT null DEFAULT 1 COMMENT 'Flag that shows a group status (active/not active)',
                `langid` int(11) UNSIGNED NOT null COMMENT 'Preferred language',
                FOREIGN KEY (`groupid`) REFERENCES `{$tabPrefix}_core_userman_groups` (`id`),
                FOREIGN KEY (`langid`) REFERENCES `{$tabPrefix}_core_langman_languages` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`),
                KEY `salt` (`salt`),
                KEY `groupid` (`groupid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of the users' AUTO_INCREMENT=1 ;",
            
            // user passwords
            "CREATE TABLE `{$tabPrefix}_core_userman_userpass` (
                `id` char(36) NOT null,
                `userid` int(11) UNSIGNED NOT null COMMENT 'Identifier that points to user the password belongs',
                `password` char(40) NOT null DEFAULT '' DEFAULT 0 COMMENT 'Encrypted password',
                `description` char(30) NOT null DEFAULT '' COMMENT 'Password description',
                `limited` tinyint(1) UNSIGNED NOT null DEFAULT 0 COMMENT 'Password type',
                `doubleauth` tinyint(1) UNSIGNED NOT null DEFAULT 0 COMMENT '2-factor authentication',
                FOREIGN KEY (`userid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                PRIMARY KEY (`id`),
                KEY `userid` (`userid`),
                UNIQUE KEY `password` (`userid`, `password`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of passwords' AUTO_INCREMENT=1 ;",
            
            // user info
            "CREATE TABLE `{$tabPrefix}_core_userman_userinfo` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `fullname` varchar(100) NOT null DEFAULT '' COMMENT 'Full name of the user',
                `email` varchar(100) NOT null DEFAULT '' COMMENT 'E-mail of the user',
                FOREIGN KEY (`id`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                PRIMARY KEY (`id`),
                KEY `fullname` (`fullname`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Information about users' AUTO_INCREMENT=1 ;",
            
            // unique session identifier
            "CREATE TABLE `{$tabPrefix}_core_auth_usi` (
                `id` char(36) NOT null COMMENT 'Unique session identifier',
                `pid` char(36) NOT null COMMENT 'Password identifier',
                `endtime` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the session expiration',
                FOREIGN KEY (`pid`) REFERENCES `{$tabPrefix}_core_userman_userpass` (`id`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Data of the sessions' AUTO_INCREMENT=1 ;",

            // information about sessions
            "CREATE TABLE `{$tabPrefix}_core_auth_session_info` (
                `id` char(36) NOT null COMMENT 'Match and related to session identifier',
                `ip` varchar(45) NOT null COMMENT 'IP from which session was requested',
                `useragent`  varchar(512) NOT null COMMENT 'User-agent from which session was requested',
                `created` TIMESTAMP NOT null DEFAULT 0 COMMENT 'Time of creation',
                FOREIGN KEY (`id`) REFERENCES `{$tabPrefix}_core_auth_usi` (`id`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Information about the existing sessions' AUTO_INCREMENT=1 ;",
            
            // temporary blocking of the user authentication
            "CREATE TABLE `{$tabPrefix}_core_userman_temp_block` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `tempblock` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestemp to define temporary blocking of the user authentication to prevent brute force of the password',
                `counter` tinyint(2) UNSIGNED NOT null DEFAULT 1 COMMENT 'Counter of the incorrect inputs of the password',
                FOREIGN KEY (`id`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                KEY `tempblock` (`tempblock`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Information about users' AUTO_INCREMENT=1 ;",
            
            // summary list of the policies
            "CREATE TABLE `{$tabPrefix}_core_policy_summary_list` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `name` char(30) NOT null DEFAULT '' COMMENT 'Name of the plugin',
                `func` char(30) NOT null DEFAULT '' COMMENT 'Name of the function',
                FOREIGN KEY (`name`) REFERENCES `{$tabPrefix}_core_plugins_installed` (`name`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `function` (`name`, `func`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Summary of the policies' AUTO_INCREMENT=1 ;",
            
            // descriptions of the policies
            "CREATE TABLE `{$tabPrefix}_core_policy_descriptions` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `codeid` int(11) UNSIGNED NOT null COMMENT 'Language code identifier',
                `policyid` int(11) UNSIGNED NOT null COMMENT 'Policy identifier',
                `short` varchar(128) NOT null DEFAULT '' COMMENT 'Short policy description',
                `detailed` varchar(1024) NOT null DEFAULT '' COMMENT 'Detailed policy description',
                FOREIGN KEY (`codeid`) REFERENCES `{$tabPrefix}_core_langman_languages` (`id`),
                FOREIGN KEY (`policyid`) REFERENCES `{$tabPrefix}_core_policy_summary_list` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`codeid`, `policyid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Description of policies' AUTO_INCREMENT=1 ;",
            
            // group policies
            "CREATE TABLE `{$tabPrefix}_core_policy_access` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `groupid` int(11) UNSIGNED NOT null COMMENT 'Identifier that point to which group the policy belongs',
                `funcid` int(11) UNSIGNED NOT null COMMENT 'Identifier that point to which function the policy belongs',
                `access` tinyint(1) UNSIGNED NOT null DEFAULT 0 COMMENT 'Flag that shows a policy status (active/not active)',
                FOREIGN KEY (`funcid`) REFERENCES `{$tabPrefix}_core_policy_summary_list` (`id`),
                FOREIGN KEY (`groupid`) REFERENCES `{$tabPrefix}_core_userman_groups` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `policy` (`groupid`, `funcid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Access policies' AUTO_INCREMENT=1 ;",
            
            // policies for non-authorized users
            "CREATE TABLE `{$tabPrefix}_core_policy_nosession` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `funcid` int(11) UNSIGNED NOT null COMMENT 'Identifier that point to which function the policy belongs',
                `access` tinyint(1) UNSIGNED NOT null DEFAULT 0 COMMENT 'Flag that shows a policy status (active/not active)',
                FOREIGN KEY (`funcid`) REFERENCES `{$tabPrefix}_core_policy_summary_list` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `policy` (`funcid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Access policies for inactive session' AUTO_INCREMENT=1 ;",
            
            // keywords of the log events
            "CREATE TABLE `{$tabPrefix}_core_logman_events` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `keyword` varchar(30) NOT null DEFAULT '' COMMENT 'Key word of the event',
                `plugid` int(11) UNSIGNED NOT null COMMENT 'Plugin identifier',
                FOREIGN KEY (`plugid`) REFERENCES `{$tabPrefix}_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `event` (`keyword`, `plugid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of events' AUTO_INCREMENT=1 ;",
            
            // event descriptions
            "CREATE TABLE `{$tabPrefix}_core_logman_descriptions` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `description` varchar(128) NOT null DEFAULT '' COMMENT 'Pattern description of the event',
                `eventid` int(11) UNSIGNED NOT null COMMENT 'Event identifier',
                `codeid` int(11) UNSIGNED NOT null COMMENT 'Language code identifier',
                FOREIGN KEY (`eventid`) REFERENCES `{$tabPrefix}_core_logman_events` (`id`),
                FOREIGN KEY (`codeid`) REFERENCES `{$tabPrefix}_core_langman_languages` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `event` (`eventid`, `codeid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of events' AUTO_INCREMENT=1 ;",
            
            // log records
            "CREATE TABLE `{$tabPrefix}_core_logman_records` (
                `id` int(11) UNSIGNED NOT null AUTO_INCREMENT,
                `eventid` int(11) UNSIGNED NOT null COMMENT 'Identifier that point to which event the record belongs',
                `time` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the record creation',
                `insertion` varchar(1024) NOT null DEFAULT '' COMMENT 'Record content',
                `user` varchar(20) NOT null DEFAULT 'M' COMMENT 'A user who made the record',
                FOREIGN KEY (`eventid`) REFERENCES `{$tabPrefix}_core_logman_events` (`id`),
                PRIMARY KEY (`id`),
                KEY `event` (`eventid`),
                KEY `time` (`time`),
                KEY `user` (`user`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Log records' AUTO_INCREMENT=1 ;",
            
            // topics for discussions
            "CREATE TABLE `{$tabPrefix}_core_discuss_topics` (
                `id` char(36) NOT null,
                `topic` varchar(100) NOT null DEFAULT '' COMMENT 'Name of topic',
                PRIMARY KEY (`id`),
                KEY `topic` (`topic`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of topics for discussions' ;",
            
            // comments to topics
            "CREATE TABLE `{$tabPrefix}_core_discuss_comments` (
                `id` char(36) NOT null,
                `tid` char(36) COMMENT 'Topic identifier',
                `pcid` char(36) DEFAULT null COMMENT 'Identifier of parent comment',
                `userid` int(11) UNSIGNED COMMENT 'User identifier',
                `comment` varchar(1024) COMMENT 'Text of comment',
                `time` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of comment creation',
                `microtime` DOUBLE NOT null COMMENT 'Microtime mark',
                FOREIGN KEY (`tid`) REFERENCES `{$tabPrefix}_core_discuss_topics` (`id`) ON DELETE SET null,
                FOREIGN KEY (`pcid`) REFERENCES `{$tabPrefix}_core_discuss_comments` (`id`) ON DELETE SET null,
                FOREIGN KEY (`userid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`) ON DELETE SET null,
                PRIMARY KEY (`id`),
                KEY `pcid` (`pcid`),
                KEY `userid` (`userid`),
                KEY `comment` (`comment`),
                KEY `microtime` (`microtime`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'List of topic comments' ;",
            
            // social circles
            "CREATE TABLE `{$tabPrefix}_core_share_circles` (
                `id` char(36) NOT null COMMENT 'Circle identifier',
                `userid` int(11) UNSIGNED NOT null COMMENT 'User identifier',
                `cname` varchar(50) NOT null COMMENT 'Circle name',
                FOREIGN KEY (`userid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `ucircle` (`userid`, `cname`),
                KEY `userid` (`userid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'User circles for sharing of records' ;",
            
            // buddy lists of social circles
            "CREATE TABLE `{$tabPrefix}_core_share_buddy_list` (
                `id` char(36) NOT null,
                `cid` char(36) NOT null COMMENT 'Circle identifiers',
                `bid` int(11) UNSIGNED COMMENT 'Buddy (other user) identifier',
                FOREIGN KEY (`cid`) REFERENCES `{$tabPrefix}_core_share_circles` (`id`),
                FOREIGN KEY (`bid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`) ON DELETE SET null,
                PRIMARY KEY (`id`),
                UNIQUE KEY `cbuddy` (`cid`, `bid`),
                KEY `cid` (`cid`),
                KEY `bid` (`bid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Buddy list which is associated with circles' ;",
            
            // user messages
            "CREATE TABLE `{$tabPrefix}_core_share_msgs` (
                `id` char(36) NOT null COMMENT 'Message identifier',
                `source` char(36) NOT null DEFAULT '' COMMENT 'Source message identifier',
                `userid` int(11) UNSIGNED NOT null COMMENT 'User identifier',
                `title` varchar(250) NOT null COMMENT 'Title of the message',
                `text` text NOT null COMMENT 'Text (comment) of the message',
                `msgtime` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the message creation',
                `microtime` DOUBLE NOT null COMMENT 'Microtime mark',
                FOREIGN KEY (`userid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                KEY `userid` (`userid`),
                KEY `microtime` (`microtime`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'User messages' ;",
            
            // user files
            "CREATE TABLE `{$tabPrefix}_core_share_files` (
                `id` char(36) NOT null,
                `userid` int(11) UNSIGNED NOT null COMMENT 'User identifier',
                `title` varchar(255) NOT null COMMENT 'Title of the file',
                `name` varchar(255) NOT null COMMENT 'Name of the file',
                `comment` varchar(1024) NOT null COMMENT 'Comment of the file',
                `stdir` varchar(10) NOT null COMMENT 'Storage directory of the file',
                `mime` varchar(50) NOT null DEFAULT 'file' COMMENT 'Mime type of the file',
                `size` int(11) UNSIGNED NOT null COMMENT 'File size in bytes (up to 4 GB)',
                `filetime` TIMESTAMP NOT null DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of the file creation',
                `microtime` DOUBLE NOT null COMMENT 'Microtime mark',
                FOREIGN KEY (`userid`) REFERENCES `{$tabPrefix}_core_userman_users` (`id`),
                KEY `mime` (`mime`),
                KEY `microtime` (`microtime`),
                KEY `userid` (`userid`),
                PRIMARY KEY (`id`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'User files' ;",
            
            // message-file relations
            "CREATE TABLE `{$tabPrefix}_core_share_msgfile_relations` (
                `id` char(36) NOT null,
                `mid` char(36) NOT null COMMENT 'Message identifier',
                `fid` char(36) NOT null COMMENT 'File identifier',
                `userid` int(11) UNSIGNED NOT null COMMENT 'User identifier',
                FOREIGN KEY (`mid`) REFERENCES `{$tabPrefix}_core_share_msgs` (`id`),
                FOREIGN KEY (`fid`) REFERENCES `{$tabPrefix}_core_share_files` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `relation` (`mid`, `fid`),
                KEY `mid` (`mid`),
                KEY `fid` (`fid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Relations between files and messages' ;",
            
            // accessibility to messages
            "CREATE TABLE `{$tabPrefix}_core_share_msg_accessibility` (
                `id` char(36) NOT null,
                `mid` char(36) NOT null COMMENT 'Message identifier',
                `cid` char(36) COMMENT 'Identifier of the circle which has access to the message. If empty string - public access',
                FOREIGN KEY (`mid`) REFERENCES `{$tabPrefix}_core_share_msgs` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `access` (`mid`, `cid`),
                KEY `cid` (`cid`),
                KEY `mid` (`mid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Accessibility of messages' ;",
            
            // accessibility to files
            "CREATE TABLE `{$tabPrefix}_core_share_files_accessibility` (
                `id` char(36) NOT null,
                `fid` char(36) NOT null COMMENT 'File identifier',
                `cid` char(36) NOT null COMMENT 'Identifier of the circle which has access to the message. If empty string - public access',
                FOREIGN KEY (`fid`) REFERENCES `{$tabPrefix}_core_share_files` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `access` (`fid`, `cid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Accessibility of the shared files' ;",
            
            // messages-comments relations
            "CREATE TABLE `{$tabPrefix}_core_share_msg_topic_rel` (
                `id` char(36) NOT null COMMENT 'Identifier of message',
                `tid` char(36) NOT null COMMENT 'Identifier of topic associated with message',
                FOREIGN KEY (`id`) REFERENCES `{$tabPrefix}_core_share_msgs` (`id`),
                FOREIGN KEY (`tid`) REFERENCES `{$tabPrefix}_core_discuss_topics` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `tid` (`tid`)
            ) ENGINE=$sEngine DEFAULT CHARSET=utf8 COMMENT 'Relations between messages and commented topics' ;"
        ];
        foreach ($queries as $query) {
            $sql->query($query);
            if ($sql->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "step #1: ".$sql->error);
                return false;
            }
        }
        return true;
    }
    
    // step #2
    public function installPackage() {
        $this->zeroizeError();
        $packPath = "meccano_core.zip";
        if (!is_file($packPath)) {
            $this->setError(ERROR_NOT_FOUND, 'step #2: installation package not found');
            return false;
        }
        $sql = new \mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
        $sql->set_charset('utf8');
        $plugin = new Plugins($sql);
        $plugin->applyPolicy(false);
        if(!$plugin->unpack($packPath)) {
            $this->setError($plugin->errId(), "step #2 -> ".$plugin->errExp());
            return false;
        }
        if (!$plugin->install("core") || !$plugin->delUnpacked("core")) {
            $this->setError($plugin->errId(), "step #2 -> ".$plugin->errExp());
            return false;
        }
        return true;
    }
    
    // step #3
    public function groupUsers($userParam) {
        $this->zeroizeError();
        require_once MECCANO_CORE_DIR . '/userman.php';
        $sql = new \mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
        $sql->set_charset("utf8");
        $userman = new UserMan($sql);
        $userman->applyPolicy(false);
        if (!$groupId = $userman->createGroup($userParam['groupname'], $userParam['groupdesc'])) {
            $this->setError($userman->errId(), "step #3 -> ".$userman->errExp());
            return false;
        }
        if (!$userman->createUser($userParam['username'], $userParam['passw'], $userParam['email'], $groupId)) {
            $this->setError($userman->errId(), "step #3 -> ".$userman->errExp());
            return false;
        }
        return true;
    }
    
}
