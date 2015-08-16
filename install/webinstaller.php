<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Web installer.
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
 *     https://bitbucket.org/azexmail/phpmeccano
 */

namespace core;

require_once MECCANO_CORE_DIR.'/plugins.php';

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
        $_checkConst = array(
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
            "MECCANO_DOCUMENTS_DIR",
            "MECCANO_UNPACKED_PLUGINS",
            "MECCANO_UNINSTALL",
            "MECCANO_DEF_LANG"
        );
        foreach ($_checkConst as $value) {
            if (!defined($value)) {
                define($value, "N/A");
            }
        }
        $constStatus = array();
        // validate type of the database storage engine
        if (in_array(MECCANO_DBSTORAGE_ENGINE, array('MyISAM', 'InnoDB'))) {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = array(TRUE, MECCANO_DBSTORAGE_ENGINE);
        }
        elseif (!is_string(MECCANO_DBSTORAGE_ENGINE) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_DBSTORAGE_ENGINE)) {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = array(FALSE, "N/A");
        }
        else {
            $constStatus['MECCANO_DBSTORAGE_ENGINE'] = array(FALSE, htmlspecialchars(MECCANO_DBSTORAGE_ENGINE));
        }
        // validate database settings
        $dbConf = array(
            'MECCANO_DBANAME' => MECCANO_DBANAME,
            'MECCANO_DBAPASS' => MECCANO_DBAPASS,
            'MECCANO_DBHOST' => MECCANO_DBHOST,
            'MECCANO_DBPORT' => MECCANO_DBPORT
            );
        $mysqlTest = new \mysqli();
        $isValid = @$mysqlTest->real_connect(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, '', MECCANO_DBPORT);
        foreach ($dbConf as $key => $value) {
            if (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                $constStatus[$key] = array(FALSE, "N/A");
            }
            else {
                if ($key == 'MECCANO_DBAPASS') {
                    $pass = preg_replace("/./", "*", $value);
                    $constStatus[$key] = array($isValid, $pass);
                }
                else {
                    $constStatus[$key] = array($isValid, htmlspecialchars($value));
                }
            }
        }
        // validate database name and table prefix
        $dbConf2 = array(
            'MECCANO_DBNAME' => MECCANO_DBNAME,
            'MECCANO_TPREF' => MECCANO_TPREF
        );
        foreach ($dbConf2 as $key => $value) {
            if ($key == 'MECCANO_DBNAME' && pregDbName($value)) {
                $constStatus[$key] = array(TRUE, $value);
            }
            elseif ($key == 'MECCANO_TPREF' && pregPref($value)) {
                $constStatus[$key] = array(TRUE, $value);
            }
            elseif (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                $constStatus[$key] = array(FALSE, "N/A");
            }
            else {
                $constStatus[$key] = array(FALSE, htmlspecialchars($value));
            }
        }
        // validate system pathes
        $sysPathes = array(
            'MECCANO_CONF_FILE' => MECCANO_CONF_FILE,
            'MECCANO_ROOT_DIR' => MECCANO_ROOT_DIR,
            'MECCANO_CORE_DIR' => MECCANO_CORE_DIR,
            'MECCANO_TMP_DIR' => MECCANO_TMP_DIR,
            'MECCANO_PHP_DIR' => MECCANO_PHP_DIR,
            'MECCANO_JS_DIR' => MECCANO_JS_DIR,
            'MECCANO_DOCUMENTS_DIR' => MECCANO_DOCUMENTS_DIR,
            'MECCANO_UNPACKED_PLUGINS' => MECCANO_UNPACKED_PLUGINS,
            'MECCANO_UNINSTALL' => MECCANO_UNINSTALL,
        );
        foreach ($sysPathes as $key => $value) {
            if ($key == 'MECCANO_CONF_FILE' && is_file($value) && is_readable($value)) {
                $constStatus[$key] = array(TRUE, $value);
            }
            elseif (is_dir($value) && is_writable($value)) {
                $constStatus[$key] = array(TRUE, $value);
            }
            elseif (!is_string($value) || !preg_replace("/[\s\n\r\t]+/", "", $value)) {
                $constStatus[$key] = array(FALSE, "N/A");
            }
            else {
                $constStatus[$key] = array(FALSE, htmlspecialchars($value));
            }
        }
        //validate default language
        if (in_array(MECCANO_DEF_LANG, array("en-US", "ru-RU"))) {
            $constStatus['MECCANO_DEF_LANG'] = array(TRUE, MECCANO_DEF_LANG);
        }
        elseif (!is_string(MECCANO_DEF_LANG) || !preg_replace("/[\s\n\r\t]+/", "", MECCANO_DEF_LANG)) {
            $constStatus['MECCANO_DEF_LANG'] = array(FALSE, "N/A");
        }
        else {
            $constStatus['MECCANO_DEF_LANG'] = array(FALSE, htmlspecialchars(MECCANO_DEF_LANG));
        }
        // return results the validation
        return $constStatus;
    }
    
    // step #0 - revalidate all data
    public function revalidateAll($post) {
        $this->zeroizeError();
        // validate post data
        if (!is_array($post)) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: incorrect type of the argument");
            return FALSE;
        }
        $required_keys = array(
            'groupname',
            'groupdesc',
            'username',
            'passw',
            'repassw',
            'email'
        );
        if (array_diff($required_keys, array_keys($post))) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: incomplete user parameters");
            return FALSE;
        }
        if (!pregGName($post['groupname'])) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: invalid group name");
            return FALSE;
        }
        if (!pregUName($post['username'])) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: invalid username");
            return FALSE;
        }
        if (!pregPassw($post['passw']) || ($post['passw'] != $post['repassw'])) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: invalid password or passwords mismatch");
            return FALSE;
        }
        if (!pregMail($post['email'])) {
            $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: incorrect e-mail address");
            return FALSE;
        }
        // validate configurations
        $constStatus = $this->validateConstants();
        foreach ($constStatus as $value) {
            if (!$value[0]) {
                $this->setError(ERROR_INCORRECT_DATA, "revalidateAll: incorrect system parameters");
                return FALSE;
            }
        }
        $_SESSION['webinstaller_step'] = 1;
        return TRUE;
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
        $queries = array(
            // create database
            "CREATE DATABASE `$dbName` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ;",
            
            // select database
            "USE `$dbName` ;",
            
            // installed plugin
            'CREATE TABLE `'.$tabPrefix.'_core_plugins_installed` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` char(30) NOT NULL DEFAULT "",
                `version` char(8) NOT NULL DEFAULT "" COMMENT "Version of the plugin",
                `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;',
            
            // about installed plugin
            'CREATE TABLE `'.$tabPrefix.'_core_plugins_installed_about` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `full` varchar(50) NOT NULL DEFAULT "" COMMENT "Full name of the plugin",
                `about` text NOT NULL DEFAULT "" COMMENT "Some words about the plugin",
                `credits` text NOT NULL DEFAULT "" COMMENT "Information about developer(s)",
                `url` varchar(100) NOT NULL DEFAULT "" COMMENT "Address of the project homepage",
                `email` varchar(100) NOT NULL DEFAULT "" COMMENT "E-mail of the developer(s)",
                `license` text NOT NULL DEFAULT "" COMMENT "License agreement",
                FOREIGN KEY (`id`) REFERENCES `'.$tabPrefix.'_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;',
            
            // about unpacked plugins
            'CREATE TABLE `'.$tabPrefix.'_core_plugins_unpacked` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `short` varchar(30) NOT NULL DEFAULT "" COMMENT "Short name of the plugin",
                `full` varchar(50) NOT NULL DEFAULT "" COMMENT "Full name of the plugin",
                `version` varchar(8) NOT NULL DEFAULT "" COMMENT "Version of the plugin",
                `spec` varchar(5) NOT NULL DEFAULT "" COMMENT "Version of package specification",
                `dirname` char(40) NOT NULL DEFAULT "",
                `about` text NOT NULL DEFAULT "" COMMENT "Some words about the plugin",
                `credits` text NOT NULL DEFAULT "" COMMENT "Information about developer(s)",
                `url` varchar(100) NOT NULL DEFAULT "" COMMENT "Address of the project homepage",
                `email` varchar(100) NOT NULL DEFAULT "" COMMENT "E-mail of the developer(s)",
                `license` text NOT NULL DEFAULT "" COMMENT "License agreement",
                `depends` text NOT NULL DEFAULT "" COMMENT "Dependences needed for the plugin",
                PRIMARY KEY (`id`),
                UNIQUE KEY `short` (`short`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;',
            
            // system languages
            'CREATE TABLE `'.$tabPrefix.'_core_langman_languages` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `code` char(5) NOT NULL DEFAULT "" COMMENT "Language code",
                `name` varchar(50) NOT NULL DEFAULT "" COMMENT "Language name",
                `dir` enum("ltr", "rtl") NOT NULL DEFAULT "ltr" COMMENT "Text direction",
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of language codes" AUTO_INCREMENT=1 ;',
            
            // title sections
            'CREATE TABLE `'.$tabPrefix.'_core_langman_title_sections` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `plugid` int(11) unsigned NOT NULL COMMENT "Plugin identifier",
                `section` varchar(40) NOT NULL DEFAULT "" COMMENT "Section of title",
                `static` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT "Points if the section data can be changed",
                FOREIGN KEY (`plugid`) REFERENCES `'.$tabPrefix.'_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `section` (`plugid`, `section`),
                KEY `plugin` (`plugid`),
                KEY `static` (`static`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of title sections" AUTO_INCREMENT=1 ;',
            
            // title names
            'CREATE TABLE `'.$tabPrefix.'_core_langman_title_names` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `sid` int(11) unsigned NOT NULL COMMENT "Section identifier",
                `name` varchar(40) NOT NULL DEFAULT "" COMMENT "Short name of title",
                FOREIGN KEY (`sid`) REFERENCES `'.$tabPrefix.'_core_langman_title_sections` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `title` (`sid`, `name`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of title names" AUTO_INCREMENT=1 ;',
            
            // titles
            'CREATE TABLE `'.$tabPrefix.'_core_langman_titles` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `codeid` int(11) unsigned NOT NULL COMMENT "Language code identifier",
                `nameid` int(11) unsigned NOT NULL COMMENT "Title name identifier",
                `title` varchar(128) NOT NULL DEFAULT "" COMMENT "Title in some language",
                FOREIGN KEY (`codeid`) REFERENCES `'.$tabPrefix.'_core_langman_languages` (`id`),
                FOREIGN KEY (`nameid`) REFERENCES `'.$tabPrefix.'_core_langman_title_names` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `index` (`codeid`, `nameid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Titles written in different languages" AUTO_INCREMENT=1 ;',
            
            // text sections
            'CREATE TABLE `'.$tabPrefix.'_core_langman_text_sections` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `plugid` int(11) unsigned NOT NULL COMMENT "Plugin identifier",
                `section` varchar(40) DEFAULT "" NOT NULL COMMENT "Section of text",
                `static` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT "Points if the section data can be changed",
                FOREIGN KEY (`plugid`) REFERENCES `'.$tabPrefix.'_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `section` (`plugid`, `section`),
                KEY `plugin` (`plugid`),
                KEY `static` (`static`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of text sections" AUTO_INCREMENT=1 ;',
            
            // text names
            'CREATE TABLE `'.$tabPrefix.'_core_langman_text_names` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `sid` int(11) unsigned NOT NULL COMMENT "Section identifier",
                `name` varchar(40) DEFAULT "" NOT NULL COMMENT "Short name of text",
                FOREIGN KEY (`sid`) REFERENCES `'.$tabPrefix.'_core_langman_text_sections` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `title` (`sid`, `name`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of text names" AUTO_INCREMENT=1 ;',
            
            // texts
            'CREATE TABLE `'.$tabPrefix.'_core_langman_texts` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `codeid` int(11) unsigned NOT NULL COMMENT "Language code identifier",
                `nameid` int(11) unsigned NOT NULL COMMENT "Title name identifier",
                `title` varchar(128) NOT NULL DEFAULT "" COMMENT "Title of the text",
                `document` text NOT NULL DEFAULT "" COMMENT "Document text",
                `created` TIMESTAMP NOT NULL DEFAULT 0 COMMENT "Time of creation",
                `edited` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT "Time of editing",
                FOREIGN KEY (`codeid`) REFERENCES `'.$tabPrefix.'_core_langman_languages` (`id`),
                FOREIGN KEY (`nameid`) REFERENCES `'.$tabPrefix.'_core_langman_text_names` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `index` (`codeid`, `nameid`),
                KEY `created` (`created`),
                KEY `edited` (`edited`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Text written in different languages" AUTO_INCREMENT=1 ;',
            
            // groups
            'CREATE TABLE `'.$tabPrefix.'_core_userman_groups` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `groupname` varchar(50) NOT NULL DEFAULT "" COMMENT "Name of the user group",
                `description` varchar(256) NOT NULL DEFAULT "" COMMENT "Description of the group destination",
                `creationtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "Time of the group creation",
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT "Flag that shows a group status (active/not active)",
                PRIMARY KEY (`id`),
                UNIQUE KEY `groupname` (`groupname`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of user groups" AUTO_INCREMENT=1 ;',
            
            // users
            'CREATE TABLE `'.$tabPrefix.'_core_userman_users` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `username` char(20) NOT NULL DEFAULT "" COMMENT "Nickname of the user",
                `creationtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "Time of the user creation",
                `groupid` int(11) unsigned NOT NULL COMMENT "Identifier that points to which group the user belongs",
                `salt` char(22) NOT NULL COMMENT "Salt for password",
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT "Flag that shows a group status (active/not active)",
                `langid` int(11) unsigned NOT NULL COMMENT "Prefered language",
                FOREIGN KEY (`groupid`) REFERENCES `'.$tabPrefix.'_core_userman_groups` (`id`),
                FOREIGN KEY (`langid`) REFERENCES `'.$tabPrefix.'_core_langman_languages` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`),
                KEY `salt` (`salt`),
                KEY `groupid` (`groupid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of the users" AUTO_INCREMENT=1 ;',
            
            // user passwords
            'CREATE TABLE `'.$tabPrefix.'_core_userman_userpass` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `userid` int(11) unsigned NOT NULL COMMENT "Identifier that points to user the password belongs",
                `password` char(40) NOT NULL DEFAULT "" DEFAULT 0 COMMENT "Encrypted password",
                `description` char(30) NOT NULL DEFAULT "" COMMENT "Password description",
                `limited` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT "Password type",
                FOREIGN KEY (`userid`) REFERENCES `'.$tabPrefix.'_core_userman_users` (`id`),
                PRIMARY KEY (`id`),
                KEY `userid` (`userid`),
                UNIQUE KEY `password` (`userid`, `password`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "List of passwords" AUTO_INCREMENT=1 ;',
            
            // user info
            'CREATE TABLE `'.$tabPrefix.'_core_userman_userinfo` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `fullname` varchar(100) NOT NULL DEFAULT "" COMMENT "Full name of the user",
                `email` varchar(100) NOT NULL DEFAULT "" COMMENT "E-mail of the user",
                FOREIGN KEY (`id`) REFERENCES `'.$tabPrefix.'_core_userman_users` (`id`),
                PRIMARY KEY (`id`),
                KEY `fullname` (`fullname`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Information about users" AUTO_INCREMENT=1 ;',
            
            // temporary blocking of the user authentication
            'CREATE TABLE `'.$tabPrefix.'_core_userman_temp_block` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tempblock` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'Timestamp to define temporary blocking of the user authentication to prevent brute force of the password\',
                `counter` tinyint(2) UNSIGNED NOT NULL DEFAULT 1 COMMENT \'Counter of the incorrect inputs of the password\',
                FOREIGN KEY (`id`) REFERENCES `'.$tabPrefix.'_core_userman_users` (`id`),
                KEY `tempblock` (`tempblock`),
                PRIMARY KEY (`id`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT \'Information about users\' AUTO_INCREMENT=1 ;',
            
            // unique session identifier
            'CREATE TABLE `'.$tabPrefix.'_core_auth_usi` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `usi` char(40) NOT NULL DEFAULT "" COMMENT "Unique session identifier",
                `endtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "Time of the session expiration",
                FOREIGN KEY (`id`) REFERENCES `'.$tabPrefix.'_core_userman_userpass` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `usi` (`usi`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Data of the sessions" AUTO_INCREMENT=1 ;',
            
            // summary list of the policies
            'CREATE TABLE `'.$tabPrefix.'_core_policy_summary_list` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` char(30) NOT NULL DEFAULT "" COMMENT "Name of the plugin",
                `func` char(30) NOT NULL DEFAULT "" COMMENT "Name of the function",
                FOREIGN KEY (`name`) REFERENCES `'.$tabPrefix.'_core_plugins_installed` (`name`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `function` (`name`, `func`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Summary of the policies" AUTO_INCREMENT=1 ;',
            
            // descriptions of the policies
            'CREATE TABLE `'.$tabPrefix.'_core_policy_descriptions` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `codeid` int(11) unsigned NOT NULL COMMENT "Language code identifier",
                `policyid` int(11) unsigned NOT NULL COMMENT "Policy identifier",
                `short` varchar(128) NOT NULL DEFAULT "" COMMENT "Short policy description",
                `detailed` varchar(1024) NOT NULL DEFAULT "" COMMENT "Detailed policy description",
                FOREIGN KEY (`codeid`) REFERENCES `'.$tabPrefix.'_core_langman_languages` (`id`),
                FOREIGN KEY (`policyid`) REFERENCES `'.$tabPrefix.'_core_policy_summary_list` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`codeid`, `policyid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Description of policies" AUTO_INCREMENT=1 ;',
            
            // group policies
            'CREATE TABLE `'.$tabPrefix.'_core_policy_access` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `groupid` int(11) unsigned NOT NULL COMMENT "Identifier that point to which group the policy belongs",
                `funcid` int(11) unsigned NOT NULL COMMENT "Identifier that point to which function the policy belongs",
                `access` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT "Flag that shows a policy status (active/not active)",
                FOREIGN KEY (`funcid`) REFERENCES `'.$tabPrefix.'_core_policy_summary_list` (`id`),
                FOREIGN KEY (`groupid`) REFERENCES `'.$tabPrefix.'_core_userman_groups` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `policy` (`groupid`, `funcid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Access policies" AUTO_INCREMENT=1 ;',
            
            // policies for non-authorized users
            'CREATE TABLE `'.$tabPrefix.'_core_policy_nosession` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `funcid` int(11) unsigned NOT NULL COMMENT "Identifier that point to which function the policy belongs",
                `access` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT "Flag that shows a policy status (active/not active)",
                FOREIGN KEY (`funcid`) REFERENCES `'.$tabPrefix.'_core_policy_summary_list` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `policy` (`funcid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Access policies for inactive session" AUTO_INCREMENT=1 ;',
            
            // keywords of the log events
            'CREATE TABLE `'.$tabPrefix.'_core_logman_events` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `keyword` varchar(30) NOT NULL DEFAULT "" COMMENT "Key word of the event",
                `plugid` int(11) unsigned NOT NULL COMMENT "Plugin identifier",
                FOREIGN KEY (`plugid`) REFERENCES `'.$tabPrefix.'_core_plugins_installed` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `event` (`keyword`, `plugid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Keywords of the log events" AUTO_INCREMENT=1 ;',
            
            // event descriptions
            'CREATE TABLE `'.$tabPrefix.'_core_logman_descriptions` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `description` varchar(128) NOT NULL DEFAULT "" COMMENT "Pattern description of the event",
                `eventid` int(11) unsigned NOT NULL COMMENT "Plugin identifier",
                `codeid` int(11) unsigned NOT NULL COMMENT "Language code identifier",
                FOREIGN KEY (`eventid`) REFERENCES `'.$tabPrefix.'_core_logman_events` (`id`),
                FOREIGN KEY (`codeid`) REFERENCES `'.$tabPrefix.'_core_langman_languages` (`id`),
                PRIMARY KEY (`id`),
                UNIQUE KEY `event` (`eventid`, `codeid`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Event descriptions" AUTO_INCREMENT=1 ;',
            
            // log records
            'CREATE TABLE `'.$tabPrefix.'_core_logman_records` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `eventid` int(11) unsigned NOT NULL COMMENT "Identifier that point to which event the record belongs",
                `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "Time of the record creation",
                `insertion` varchar(1024) NOT NULL DEFAULT "" COMMENT "Record content",
                `user` varchar(20) NOT NULL DEFAULT "M" COMMENT "A user who made the record",
                FOREIGN KEY (`eventid`) REFERENCES `'.$tabPrefix.'_core_logman_events` (`id`),
                PRIMARY KEY (`id`),
                KEY `event` (`eventid`),
                KEY `time` (`time`),
                KEY `user` (`user`)
            ) ENGINE='.$sEngine.' DEFAULT CHARSET=utf8 COMMENT "Log records" AUTO_INCREMENT=1 ;'
        );
        foreach ($queries as $query) {
            $sql->query($query);
            if ($sql->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "createDbTables: ".$sql->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    // step #2
    public function installPackage() {
        $this->zeroizeError();
        $packPath = "meccano_core_v0.0.1alpha.zip";
        if (!is_file($packPath)) {
            $this->setError(ERROR_NOT_FOUND, 'installPackage: installation package not found');
            return FALSE;
        }
        $sql = new \mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
        $sql->set_charset('utf8');
        $policy = new Policy($sql);
        $log = new LogMan($policy);
        $lang = new LangMan($log);
        $plugin = new Plugins($lang);
        $plugin->applyPolicy(FALSE);
        if(!$plugin->unpack($packPath)) {
            $this->setError($plugin->errId(), "installPackage -> ".$plugin->errExp());
            return FALSE;
        }
        if (!$plugin->install("core") || !$plugin->delUnpacked("core")) {
            $this->setError($plugin->errId(), "installPackage -> ".$plugin->errExp());
            return FALSE;
        }
        return TRUE;
    }
    
    // step #3
    public function groupUsers($userParam) {
        $this->zeroizeError();
        require_once MECCANO_CORE_DIR . '/userman.php';
        $mysqli = new \mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
        $mysqli->set_charset("utf8");
        $policy = new Policy($mysqli);
        $logman = new LogMan($policy);
        $userman = new UserMan($logman);
        $userman->applyPolicy(FALSE);
        if (!$groupId = $userman->createGroup($userParam['groupname'], $userParam['groupdesc'])) {
            $this->setError($userman->errId(), "groupUsers -> ".$userman->errExp());
            return FALSE;
        }
        if (!$userman->createUser($userParam['username'], $userParam['passw'], $userParam['email'], $groupId)) {
            $this->setError($userman->errId(), "groupUsers -> ".$userman->errExp());
            return FALSE;
        }
        return TRUE;
    }
    
}
