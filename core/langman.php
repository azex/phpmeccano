<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [langman.php].
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

require_once 'logman.php';

interface intLangMan {
    public function __construct(LogMan $logObject);
    public function addLang($code, $name, $log = TRUE);
    public function delLang($code, $log = TRUE);
    public function langList();
    public function installTitles(\DOMDocument $titles, $validate = TRUE);
    public function installTexts(\DOMDocument $texts, $validate = TRUE);
    public function delPlugin($plugin);
    public function addTitleSection($section, $plugin, $log = TRUE);
    public function delTitleSection($sid, $log = TRUE);
    public function addTextSection($section, $plugin, $log = TRUE);
    public function delTextSection($sid, $log = TRUE);
    public function addTitleName($name, $section, $plugin, $log = TRUE);
    public function delTitleName($nameid, $log = TRUE);
    public function addTextName($name, $section, $plugin, $log = TRUE);
    public function delTextName($nameid, $log = TRUE);
    public function addTitle($title, $name, $section, $plugin, $code = MECCANO_DEF_LANG, $log = TRUE);
    public function delTitle($tid, $log = TRUE);
    public function addText($title, $document, $name, $section, $plugin, $code = MECCANO_DEF_LANG, $log = TRUE);
    public function delText($tid, $log = TRUE);
    public function updateTitle($id, $title, $log = TRUE);
    public function updateText($id, $title, $document, $log = TRUE);
    public function getTitle($name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public function getText($name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public function getTitles($section, $plugin, $code = MECCANO_DEF_LANG);
    public function getAllTextsXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getTextById($id);
    public function sumTexts($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20);
    public function getTextsXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getTexts($section, $plugin, $code = MECCANO_DEF_LANG);
    public function sumTitles($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20);
    public function getTitlesXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getAllTitlesXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public function getTitleById($id);
    public function sumTextSections($plugin, $rpp = 20);
    public function getTextSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public function sumTitleSections($plugin, $rpp = 20);
    public function getTitleSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public function sumTextNames($plugin, $section, $rpp = 20);
    public function getTextNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public function sumTitleNames($plugin, $section, $rpp = 20);
    public function getTitleNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
}

class LangMan extends ServiceMethods implements intLangMan{
    private $dbLink; // database link
    public $logObject; // log object
    private $policyObject; // policy objectobject
    
    public function __construct(LogMan $logObject) {
        $this->dbLink = $logObject->dbLink;
        $this->logObject = $logObject;
        $this->policyObject = $logObject->policyObject;
    }
    
    public function addLang($code, $name, $dir = 'ltr', $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_syswide_lang')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addLang: restricted by the policy");
            return FALSE;
        }
        if (!pregLang($code) || !is_string($name) || !in_array($dir, array('ltr', 'rtl'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'addLang: incorrect incoming parameters');
            return FALSE;
        }
        $name = $this->dbLink->real_escape_string(htmlspecialchars($name));
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_languages` (`code`, `name`, `dir`) "
                . "VALUES('$code', '$name', '$dir') ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addLang: '.$this->dbLink->error);
            return FALSE;
        }
        $codeId = $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_lang', "$name; code: $code; DIR: $dir; ID: $codeId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addLang -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function delLang($code, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_syswide_lang')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delLang: restricted by the policy");
            return FALSE;
        }
        if (!pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delLang: incorrect incoming parameter');
            return FALSE;
        }
        if ($code == MECCANO_DEF_LANG) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'delLang: it is impossible to delete default language');
            return FALSE;
        }
        $qLang = $this->dbLink->query("SELECT `id`, `name`, `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delLang: unable to get language identifier |'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "delLang: language [$code] is not found");
            return FALSE;
        }
        list($codeId, $name, $dir) = $qLang->fetch_row();
        $defLang = MECCANO_DEF_LANG;
        $qDefLang = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$defLang' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delLang: unable to get default language identifier |'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "delLang:  default language [$defLang] is not found");
            return FALSE;
        }
        list($defLangId) = $qDefLang->fetch_row();
        $sql = array(
            "DELETE FROM `".MECCANO_TPREF."_core_langman_titles` "
            . "WHERE `codeid`=$codeId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_langman_texts` "
            . "WHERE `codeid`=$codeId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_policy_descriptions` "
            . "WHERE `codeid`=$codeId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_logman_descriptions` "
            . "WHERE `codeid`=$codeId ;",
            "UPDATE `".MECCANO_TPREF."_core_userman_users` "
            . "SET `langid`=$defLangId "
            . "WHERE `langid`=$codeId ;");
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delLang: '.$this->dbLink->error);
                return FALSE;
            }
        }
        $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delLang: '.$this->dbLink->error);
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_lang', "$name; code: $code; DIR: $dir; ID: $codeId")) {
            $this->setError(ERROR_NOT_CRITICAL, "delLang -> ".$this->logObject->errExp());
        }
        return TRUE;
    }

    public function langList() {
        $this->zeroizeError();
        $qLang = $this->dbLink->query("SELECT `id`, `code`, `name`, `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'langList: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'langList: there was not found any language');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $languages = $xml->createElement('languages');
        $xml->appendChild($languages);
        while ($row = $qLang->fetch_row()) {
            $lang = $xml->createElement('lang');
            $languages->appendChild($lang);
            $lang->appendChild($xml->createElement('id', $row[0]));
            $lang->appendChild($xml->createElement('code', $row[1]));
            $lang->appendChild($xml->createElement('name', $row[2]));
            $lang->appendChild($xml->createElement('dir', $row[3]));
        }
        return $xml;
    }
    
    public function installTitles(\DOMDocument $titles, $validate = TRUE) {
        $this->zeroizeError();
        if ($validate && !@$titles->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/langman-title-v01.rng')) {
            $this->setError(ERROR_INCORRECT_DATA, 'installTitles: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = $this->dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installPolicyDesc: unable to get list of available languages: '.$this->dbLink->error);
            return FALSE;
        }
        $avaiLang = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avaiLang[$row[1]] = $row[0];
        }
        //getting name of the plugin
        $plugName = $titles->getElementsByTagName('titles')->item(0)->getAttribute('plugin');
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installTitles: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'installTitles: unable to find plugin');
            return FALSE;
        }
        list($plugId) = $qPlugin->fetch_row();
        //parsing the DOM tree
        $sections = array();
        $sectionTypes = array();
        $sectionNodes = $titles->getElementsByTagName('section');
        foreach ($sectionNodes as $sectionNode) {
            $sectionName = $sectionNode->getAttribute('name');
            $static = $sectionNode->getAttribute('static');
            $sections[$sectionName] = array();
            $sectionTypes[$sectionName] = $static;
            // renaming of the section
            if ($sectionOldName = $sectionNode->getAttribute('oldname')) {
                $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                        . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to rename section -> '.$this->dbLink->error);
                    return FALSE;
                }
            }
            $titleNodes = $sectionNode->getElementsByTagName('title');
            foreach ($titleNodes as $titleNode) {
                $titleName = $titleNode->getAttribute('name');
                $langNodes = $titleNode->getElementsByTagName('language');
                foreach ($langNodes as $langNode) {
                    $langCode = $langNode->getAttribute('code');
                    if (isset($avaiLang[$langCode])) {
                        $sections[$sectionName][$titleName][$langCode] = $langNode->nodeValue;
                    }
                }
            }
        }
        // getting list of installed section if the the pugin is installed
        $qSections = $this->dbLink->query("SELECT `s`.`section`, `s`.`static`, `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugName' ;");
        $dbSectionTypes = array();
        $dbSectionIds = array();
        while ($row = $qSections->fetch_row()) {
            $dbSectionTypes[$row[0]] = $row[1];
            $dbSectionIds[$row[0]] = $row[2];
        }
        // deleting outdated sections
        $outdatedSections = array_diff(array_keys($dbSectionTypes), array_keys($sectionTypes));
        foreach ($outdatedSections as $value) {
            $sql = array(
                "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t`"
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `s`.`section`='$value' ;", 
                "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n`"
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `s`.`section`='$value' ;", 
                "DELETE FROM `".MECCANO_TPREF."_core_langman_title_sections` "
                . "WHERE `section`='$value' ;"
            );
            foreach ($sql as $dQuery) {
                $this->dbLink->query($dQuery);
                if ($this->dbLink->errno) {
                    $this->errId(ERROR_NOT_EXECUTED);                    $this->errExp('installTitles: unable to delete outdated data -> '.$this->dbLink->error);
                    return FALSE;
                }
            }
            
        }
        // installing/updating titles
        foreach ($sections as $sectionName => $titlePool) {
            // updating
            if (isset($dbSectionTypes[$sectionName])) {
                // section
                if ($sectionTypes[$sectionName]) {
                    $sql = array(
                        "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t`"
                        . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                        . "ON `n`.`id`=`t`.`nameid` "
                        . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                        . "ON `s`.`id`=`n`.`sid` "
                        . "WHERE `s`.`section`='$sectionName' ;", 
                        "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n`"
                        . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                        . "ON `s`.`id`=`n`.`sid` "
                        . "WHERE `s`.`section`='$sectionName' ;", 
                        "UPDATE `".MECCANO_TPREF."_core_langman_title_sections` "
                        . "SET `static`=1 "
                        . "WHERE `section`='$sectionName' ;"
                    );
                    foreach ($sql as $value) {
                        $this->dbLink->query($value);
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to clear data before updating titles -> '.$this->dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($titlePool as $titleName => $langPool) {
                        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to update name -> '.$this->dbLink->error);
                            return FALSE;
                        }
                        $nameId = $this->dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = $this->dbLink->real_escape_string($title);
                            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if ($this->dbLink->errno) {
                                $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to update title -> '.$this->dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non section
                else {
                    $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_title_sections` "
                            . "SET `static`=0 "
                            . "WHERE `section`='$sectionName' ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTitles: '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
            // installing
            else {
                // section section
                if ($sectionTypes[$sectionName]) {
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 1) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create section -> '.$this->dbLink->error);
                        return FALSE;
                    }
                    $sectionId = $this->dbLink->insert_id;
                    foreach ($titlePool as $titleName => $langPool) {
                        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create title name -> '.$this->dbLink->error);
                            return FALSE;
                        }
                        $nameId = $this->dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = $this->dbLink->real_escape_string($title);
                            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if ($this->dbLink->errno) {
                                $this->setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create title -> '.$this->dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non section
                else {
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 0) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTitles: '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public function installTexts(\DOMDocument $texts, $validate = TRUE) {
        $this->zeroizeError();
        if ($validate && !@$texts->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/langman-text-v01.rng')) {
            $this->setError(ERROR_INCORRECT_DATA, 'installTexts: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = $this->dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installPolicyDesc: unable to get list of available languages: '.$this->dbLink->error);
            return FALSE;
        }
        $avaiLang = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avaiLang[$row[1]] = $row[0];
        }
        //getting name of the plugin
        $plugName = $texts->getElementsByTagName('texts')->item(0)->getAttribute('plugin');
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installTexts: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'installTexts: unable to find plugin');
            return FALSE;
        }
        list($plugId) = $qPlugin->fetch_row();
        //parsing the DOM tree
        $sections = array();
        $sectionTypes = array();
        $sectionNodes = $texts->getElementsByTagName('section');
        foreach ($sectionNodes as $sectionNode) {
            $sectionName = $sectionNode->getAttribute('name');
            $static = $sectionNode->getAttribute('static');
            $sections[$sectionName] = array();
            $sectionTypes[$sectionName] = $static;
            // renaming of the section
            if ($sectionOldName = $sectionNode->getAttribute('oldname')) {
                $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                        . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to rename section -> '.$this->dbLink->error);
                    return FALSE;
                }
            }
            $textNodes = $sectionNode->getElementsByTagName('text');
            foreach ($textNodes as $textNode) {
                $textName = $textNode->getAttribute('name');
                $langNodes = $textNode->getElementsByTagName('language');
                foreach ($langNodes as $langNode) {
                    $langCode = $langNode->getAttribute('code');
                    if (isset($avaiLang[$langCode])) {
                        $title = $langNode->getElementsByTagName('title')->item(0)->nodeValue;
                        $document = $langNode->getElementsByTagName('document')->item(0)->nodeValue;
                        $sections[$sectionName][$textName][$langCode] = array($title, $document);
                    }
                }
            }
        }
        // getting list of installed section if the the pugin is installed
        $qSections = $this->dbLink->query("SELECT `s`.`section`, `s`.`static`, `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugName' ;");
        $dbSectionTypes = array();
        $dbSectionIds = array();
        while ($row = $qSections->fetch_row()) {
            $dbSectionTypes[$row[0]] = $row[1];
            $dbSectionIds[$row[0]] = $row[2];
        }
        // deleting outdated sections
        $outdatedSections = array_diff(array_keys($dbSectionTypes), array_keys($sectionTypes));
        foreach ($outdatedSections as $value) {
            $sql = array(
                "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t`"
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `s`.`section`='$value' ;", 
                "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n`"
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `s`.`section`='$value' ;", 
                "DELETE FROM `".MECCANO_TPREF."_core_langman_text_sections` "
                . "WHERE `section`='$value' ;"
            );
            foreach ($sql as $dQuery) {
                $this->dbLink->query($dQuery);
                if ($this->dbLink->errno) {
                    $this->errId(ERROR_NOT_EXECUTED);                    $this->errExp('installTexts: unable to delete outdated data -> '.$this->dbLink->error);
                    return FALSE;
                }
            }
            
        }
        // installing/updating texts
        foreach ($sections as $sectionName => $textPool) {
            // updating
            if (isset($dbSectionTypes[$sectionName])) {
                // section
                if ($sectionTypes[$sectionName]) {
                    $sql = array(
                        "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t`"
                        . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                        . "ON `n`.`id`=`t`.`nameid` "
                        . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                        . "ON `s`.`id`=`n`.`sid` "
                        . "WHERE `s`.`section`='$sectionName' ;", 
                        "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n`"
                        . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                        . "ON `s`.`id`=`n`.`sid` "
                        . "WHERE `s`.`section`='$sectionName' ;", 
                        "UPDATE `".MECCANO_TPREF."_core_langman_text_sections` "
                        . "SET `static`=1 "
                        . "WHERE `section`='$sectionName' ;"
                    );
                    foreach ($sql as $value) {
                        $this->dbLink->query($value);
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to clear data before updating texts -> '.$this->dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($textPool as $textName => $langPool) {
                        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to update name -> '.$this->dbLink->error);
                            return FALSE;
                        }
                        $nameId = $this->dbLink->insert_id;
                        foreach ($langPool as $langCode => $text) {
                            $codeId = $avaiLang[$langCode];
                            $title = $this->dbLink->real_escape_string($text[0]);
                            $document = $this->dbLink->real_escape_string($text[1]);
                            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` (`codeid`, `nameid`, `title`, `document`, `created`) "
                                    . "VALUES ($codeId, $nameId, '$title', '$document', CURRENT_TIMESTAMP) ;");
                            if ($this->dbLink->errno) {
                                $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to update text -> '.$this->dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non section
                else {
                    $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_text_sections` "
                            . "SET `static`=0 "
                            . "WHERE `section`='$sectionName' ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTexts: '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
            // installing
            else {
                // section section
                if ($sectionTypes[$sectionName]) {
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 1) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create section -> '.$this->dbLink->error);
                        return FALSE;
                    }
                    $sectionId = $this->dbLink->insert_id;
                    foreach ($textPool as $textName => $langPool) {
                        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if ($this->dbLink->errno) {
                            $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create text name -> '.$this->dbLink->error);
                            return FALSE;
                        }
                        $nameId = $this->dbLink->insert_id;
                        foreach ($langPool as $langCode => $text) {
                            $codeId = $avaiLang[$langCode];
                            $title = $this->dbLink->real_escape_string($text[0]);
                            $document = $this->dbLink->real_escape_string($text[1]);
                            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` (`codeid`, `nameid`, `title`, `document`, `created`) "
                                    . "VALUES ($codeId, $nameId, '$title', '$document', CURRENT_TIMESTAMP) ;");
                            if ($this->dbLink->errno) {
                                $this->setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create text -> '.$this->dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non section
                else {
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 0) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installTexts: '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public function delPlugin($plugin) {
        $this->zeroizeError();
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delPlugin: incorrect plugin name');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delPlugin: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delPlugin: unable to find plugin');
            return FALSE;
        }
        // deleting all the data related to plugin
        $sql = array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;", 
            "DELETE `s` FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;", 
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;", 
            "DELETE `s` FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$plugin' ;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delPlugin: query #$key ".$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public function addTitleSection($section, $plugin, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_title_sec')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addTitleSection: restricted by the policy");
            return FALSE;
        }
        if (!pregName40($section) || !pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addTitleSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delPlugin: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTitleSection: unable to find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitleSection: '.$this->dbLink->error);
            return FALSE;
        }
        $sectionId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_title_sec', "$section; plugin: $plugin; ID: $sectionId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addTitleSection -> ".$this->logObject->errExp());
        }
        return $sectionId;
    }
    
    public function delTitleSection($sid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_title_sec')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delTitleSection: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($sid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delTitleSection: incorrect identifier');
            return FALSE;
        }
        $sql=array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n`"
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `s`.`id`='$sid' "
                . "AND `s`.`static`=0;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `s`.`id`='$sid' "
                . "AND `s`.`static`=0;", 
            "DELETE FROM `".MECCANO_TPREF."_core_langman_title_sections` "
            . "WHERE `id`='$sid' "
                . "AND `static`=0;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delTitleSection: query #$key ".$this->dbLink->error);
                return FALSE;
            }
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delTitleSection: defined section does not exist or your are trying to delete static section');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_title_sec', "ID: $sid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delTitleSection -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function addTextSection($section, $plugin, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_text_sec')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addTextSection: restricted by the policy");
            return FALSE;
        }
        if (!pregName40($section) || !pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addTextSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delPlugin: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTextSection: unable to find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTextSection: '.$this->dbLink->error);
            return FALSE;
        }
        $sectionId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_text_sec', "$section; plugin: $plugin; ID: $sectionId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addTextSection -> ".$this->logObject->errExp());
        }
        return $sectionId;
    }
    
    public function delTextSection($sid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_text_sec')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delTextSection: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($sid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delTextSection: incorrect identifier');
            return FALSE;
        }
        $sql=array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n`"
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `s`.`id`='$sid' "
                . "AND `s`.`static`=0;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `s`.`id`='$sid' "
                . "AND `s`.`static`=0;", 
            "DELETE FROM `".MECCANO_TPREF."_core_langman_text_sections` "
            . "WHERE `id`='$sid' "
                . "AND `static`=0;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delTextSection: query #$key ".$this->dbLink->error);
                return FALSE;
            }
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delTextSection: defined section does not exist or your are trying to delete static section');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_text_sec', "ID: $sid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delTitleSection -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function addTitleName($name, $section, $plugin, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_title')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addTitleName: restricted by the policy");
            return FALSE;
        }
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addTitleName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = $this->dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitleName: unable to check section and plugin -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTitleName: unable to find matchable section and plugin or you are trying to create name in section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitleName: unable to create name -> '.$this->dbLink->error);
            return FALSE;
        }
        $nameId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_title_name', "$name; section: $section; plugin: $plugin; ID: $nameId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addTitleName -> ".$this->logObject->errExp());
        }
        return $nameId;
    }
    
    public function delTitleName($nameid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_title')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delTitleName: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($nameid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delTitleName: incorrect identifier');
            return FALSE;
        }
        $sql=array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n`"
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `n`.`id`=$nameid "
                . "AND `s`.`static`=0;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `n`.`id`=$nameid "
                . "AND `s`.`static`=0;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delTitleName: query #$key ".$this->dbLink->error);
                return FALSE;
            }
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delTitleName: defined tile name does not exist or your are trying to delete name from section');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_title_name', "ID: $nameid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delTitleName -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function addTextName($name, $section, $plugin, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_text')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addTextName: restricted by the policy");
            return FALSE;
        }
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addTextName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = $this->dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTextName: unable to check section and plugin -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTextName: unable to find matchable section and plugin or you are trying to create name in section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTextName: unable to create name -> '.$this->dbLink->error);
            return FALSE;
        }
        $nameId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_text_name', "$name; section: $section; plugin: $plugin; ID: $nameId")) {
            $this->setError(ERROR_NOT_CRITICAL, " -> ".$this->logObject->errExp());
        }
        return $nameId;
    }
    
    public function delTextName($nameid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_text')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delTextName: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($nameid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delTextName: incorrect identifier');
            return FALSE;
        }
        $sql=array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n`"
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `n`.`id`=$nameid "
                . "AND `s`.`static`=0;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "WHERE `n`.`id`=$nameid "
                . "AND `s`.`static`=0;"
        );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "delTextName: query #$key ".$this->dbLink->error);
                return FALSE;
            }
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delTextName: defined tile name does not exist or your are trying to delete name from section');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_text_name', "ID: $nameid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delTexteName -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function addTitle($title, $name, $section, $plugin, $code = MECCANO_DEF_LANG, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_title')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addTitle: restricted by the policy");
            return FALSE;
        }
        if (!is_string($title) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addTitle: incorrect incoming parameters');
            return FALSE;
        }
        $qTitle = $this->dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitle: unable to get name identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTitle: unable to find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qTitle->fetch_row();
        $qLang = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitle: unable to get language code identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addTitle: defined language was not found');
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = $this->dbLink->real_escape_string($title);
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` "
                . "(`title`, `nameid`, `codeid`) "
                . "VALUES ('$title', $nameId, $codeId) ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addTitle: unable to insert title -> '.$this->dbLink->error);
            return FALSE;
        }
        $titleId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_title', "name: $name; section: $section; plugin: $plugin; code: $code; ID: $titleId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addTitle -> ".$this->logObject->errExp());
        }
        return $titleId;
    }
    
    public function delTitle($tid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_title')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delTitle: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($tid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delTitle: incorrect title identifier');
            return FALSE;
        }
        $this->dbLink->query("DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `t`.`id`=$tid "
                . "AND `s`.`static`=0 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delTitle: unable to delete defined title -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delTitle: unable to find defined title');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_title', "ID: $tid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delTitle -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function addText($title, $document, $name, $section, $plugin, $code = MECCANO_DEF_LANG, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_add_text')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addText: restricted by the policy");
            return FALSE;
        }
        if (!is_string($title) || !is_string($document) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addText: incorrect incoming parameters');
            return FALSE;
        }
        $qText = $this->dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addText: unable to get name identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addText: unable to find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qText->fetch_row();
        $qLang = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addText: unable to get language code identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addText: defined language was not found');
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = $this->dbLink->real_escape_string($title);
        $document = $this->dbLink->real_escape_string($document);
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` "
                . "(`title`, `document`, `nameid`, `codeid`, `created`) "
                . "VALUES ('$title', '$document', $nameId, $codeId, CURRENT_TIMESTAMP) ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addText: unable to insert text -> '.$this->dbLink->error);
            return FALSE;
        }
        $textId = (int) $this->dbLink->insert_id;
        if ($log && !$this->logObject->newRecord('core', 'langman_add_text', "name: $name; section: $section; plugin: $plugin; code: $code; ID: $textId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addText -> ".$this->logObject->errExp());
        }
        return $textId;
    }
    
    public function delText($tid, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_del_text')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delText: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($tid)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delText: incorrect text identifier');
            return FALSE;
        }
        $this->dbLink->query("DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `t`.`id`=$tid "
                . "AND `s`.`static`=0 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delText: unable to delete defined text');
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delText: unable to find defined text');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_del_text', "ID: $tid")) {
            $this->setError(ERROR_NOT_CRITICAL, "delText -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function updateTitle($id, $title, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_update_title')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "updateTitle: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($id) || !is_string($title)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateTitle: incorrect incoming parameters');
            return FALSE;
        }
        $title = $this->dbLink->real_escape_string($title);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "SET `t`.`title`='$title' "
                . "WHERE `t`.`id`=$id "
                . "AND `s`.`static`=0 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateTitle: unable to update title -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'updateTitle: unable to find defined title');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_update_title', "ID: $id")) {
            $this->setError(ERROR_NOT_CRITICAL, "updateTitle -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function updateText($id, $title, $document, $log = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->policyObject->checkAccess('core', 'langman_update_text')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "updateText: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($id) || !is_string($title) || !is_string($document)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateText: incorrect incoming parameters');
            return FALSE;
        }
        $title = $this->dbLink->real_escape_string($title);
        $document = $this->dbLink->real_escape_string($document);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "SET `t`.`title`='$title', `t`.`document`='$document' "
                . "WHERE `t`.`id`=$id "
                . "AND `s`.`static`=0 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateText: unable to update text -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'updateText: unable to find defined text');
            return FALSE;
        }
        if ($log && !$this->logObject->newRecord('core', 'langman_update_text', "ID: $id")) {
            $this->setError(ERROR_NOT_CRITICAL, " -> ".$this->logObject->errExp());
        }
        return TRUE;
    }
    
    public function getTitle($name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitle: incorrect incoming parameters');
            return FALSE;
        }
        $qTitle = $this->dbLink->query("SELECT `t`.`title`, `l`.`dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `n`.`name`='$name' "
                . "AND `s`.`section`='$section' "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitle: unable to get title -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitle: unable to find defined title');
            return FALSE;
        }
        list($title, $direction) = $qTitle->fetch_row();
        return array('title' => $title, 'dir' => $direction);
    }
    
    public function getText($name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getText: incorrect incoming parameters');
            return FALSE;
        }
        $qText = $this->dbLink->query("SELECT `t`.`title`, `t`.`document`, `t`.`created`, `t`.`edited`, `l`.`dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `n`.`name`='$name' "
                . "AND `s`.`section`='$section' "
                . "AND `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getText: unable to get text -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getText: unable to find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited, $direction) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited, 'dir' => $direction);
    }
    
    public function getTitles($section, $plugin, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitles: incorrect incoming parameters');
            return FALSE;
        }
        $qTitles = $this->dbLink->query("SELECT `n`.`name`, `t`.`title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitles: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitles: unable to find defined section');
            return FALSE;
        }
        $titles = array();
        while ($result = $qTitles->fetch_row()) {
            $titles[$result[0]] = $result[1];
        }
        return $titles;
    }
    
    public function getAllTextsXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getAllTextsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'title', 'name', 'created', 'edited');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getAllTextsXML: orderBy must be array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        // get section texts
        $qTexts = $this->dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllTextsXML: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getAllTextsXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = $this->dbLink->query("SELECT `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code';");
        list($direction) = $qDirection->fetch_row();
        // create DOM
        $xml = new \DOMDocument('1.0', 'utf-8');
        $textsNode = $xml->createElement('texts');
        $codeAttribute =  $xml->createAttribute('code');
        $codeAttribute->value = $code;
        $dirAttribute = $xml->createAttribute('dir');
        $dirAttribute->value = $direction;
        $textsNode->appendChild($codeAttribute);
        $textsNode->appendChild($dirAttribute);
        $xml->appendChild($textsNode);
        while ($row = $qTexts->fetch_row()) {
            $textNode = $xml->createElement('text');
            $textsNode->appendChild($textNode);
            $textNode->appendChild($xml->createElement('id', $row[0]));
            $textNode->appendChild($xml->createElement('title', $row[1]));
            $textNode->appendChild($xml->createElement('name', $row[2]));
            $textNode->appendChild($xml->createElement('created', $row[3]));
            $textNode->appendChild($xml->createElement('edited', $row[4]));
        }
        return $xml;
    }
    
    public function getTextById($id) {
        $this->zeroizeError();
        if (!is_integer($id)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextById: identifier must be integer');
            return FALSE;
        }
        $qText = $this->dbLink->query("SELECT `title`, `document`, `created`, `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` "
                . "WHERE `id`=$id ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTextById: unable to get text -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTextById: unable to find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited);
    }
    
    public function sumTexts($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTexts: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qTexts = $this->dbLink->query("SELECT count(`t`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id` =`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTexts: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTexts: no one text was found');
            return FALSE;
        }
        list($totalTexts) = $qTexts->fetch_row();
        $totalPages = $totalTexts/$rpp;
        $remainer = fmod($totalTexts, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalTexts, 'pages' => (int) $totalPages);
    }
    
    public function getTextsXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'title', 'name', 'created', 'edited');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextsXML: orderBy must be array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalPages && $totalPages) {
            $pageNumber = $totalPages;
        }
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $start = ($pageNumber - 1) * $rpp;
        // get section texts
        $qTexts = $this->dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTextsXML: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTextsXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = $this->dbLink->query("SELECT `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code';");
        list($direction) = $qDirection->fetch_row();
        // create DOM
        $xml = new \DOMDocument('1.0', 'utf-8');
        $textsNode = $xml->createElement('texts');
        $codeAttribute =  $xml->createAttribute('code');
        $codeAttribute->value = $code;
        $dirAttribute = $xml->createAttribute('dir');
        $dirAttribute->value = $direction;
        $textsNode->appendChild($codeAttribute);
        $textsNode->appendChild($dirAttribute);
        $xml->appendChild($textsNode);
        while ($row = $qTexts->fetch_row()) {
            $textNode = $xml->createElement('text');
            $textsNode->appendChild($textNode);
            $textNode->appendChild($xml->createElement('id', $row[0]));
            $textNode->appendChild($xml->createElement('title', $row[1]));
            $textNode->appendChild($xml->createElement('name', $row[2]));
            $textNode->appendChild($xml->createElement('created', $row[3]));
            $textNode->appendChild($xml->createElement('edited', $row[4]));
        }
        return $xml;
    }
    
    public function getTexts($section, $plugin, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTexts: incorrect incoming parameters');
            return FALSE;
        }
        $qTexts = $this->dbLink->query("SELECT `n`.`name`, `t`.`title` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTexts: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTexts: unable to find defined section');
            return FALSE;
        }
        $texts = array();
        while ($result = $qTexts->fetch_row()) {
            $texts[$result[0]] = $result[1];
        }
        return $texts;
    }
    
    public function sumTitles($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitles: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qTitles = $this->dbLink->query("SELECT count(`t`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id` =`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTitles: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTitles: no one title was found');
            return FALSE;
        }
        list($totalTitles) = $qTitles->fetch_row();
        $totalPages = $totalTitles/$rpp;
        $remainer = fmod($totalTitles, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalTitles, 'pages' => (int) $totalPages);
    }
    
    public function getTitlesXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitlesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'title', 'name');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitlesXML: orderBy must be array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalPages && $totalPages) {
            $pageNumber = $totalPages;
        }
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        // get section titles
        $start = ($pageNumber - 1) * $rpp;
        $qTitles = $this->dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitlesXML: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitlesXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = $this->dbLink->query("SELECT `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code';");
        list($direction) = $qDirection->fetch_row();
        // create DOM
        $xml = new \DOMDocument('1.0', 'utf-8');
        $titlesNode = $xml->createElement('titles');
        $codeAttribute =  $xml->createAttribute('code');
        $codeAttribute->value = $code;
        $dirAttribute = $xml->createAttribute('dir');
        $dirAttribute->value = $direction;
        $titlesNode->appendChild($codeAttribute);
        $titlesNode->appendChild($dirAttribute);
        $xml->appendChild($titlesNode);
        while ($row = $qTitles->fetch_row()) {
            $titleNode = $xml->createElement('title');
            $titlesNode->appendChild($titleNode);
            $titleNode->appendChild($xml->createElement('id', $row[0]));
            $titleNode->appendChild($xml->createElement('title', $row[1]));
            $titleNode->appendChild($xml->createElement('name', $row[2]));
        }
        return $xml;
    }
    
    public function getAllTitlesXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getAllTitlesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'title', 'name');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getAllTitlesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        // get section titles
        $qTitles = $this->dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllTitlesXML: unable to get section -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getAllTitlesXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = $this->dbLink->query("SELECT `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code';");
        list($direction) = $qDirection->fetch_row();
        // create DOM
        $xml = new \DOMDocument('1.0', 'utf-8');
        $titlesNode = $xml->createElement('titles');
        $codeAttribute =  $xml->createAttribute('code');
        $codeAttribute->value = $code;
        $dirAttribute = $xml->createAttribute('dir');
        $dirAttribute->value = $direction;
        $titlesNode->appendChild($codeAttribute);
        $titlesNode->appendChild($dirAttribute);
        $xml->appendChild($titlesNode);
        while ($row = $qTitles->fetch_row()) {
            $titleNode = $xml->createElement('title');
            $titlesNode->appendChild($titleNode);
            $titleNode->appendChild($xml->createElement('id', $row[0]));
            $titleNode->appendChild($xml->createElement('title', $row[1]));
            $titleNode->appendChild($xml->createElement('name', $row[2]));
        }
        return $xml;
    }
    
    public function getTitleById($id) {
        $this->zeroizeError();
        if (!is_integer($id)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitleById: identifier must be integer');
            return FALSE;
        }
        $qTitle = $this->dbLink->query("SELECT `title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` "
                . "WHERE `id`=$id ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitleById: unable to get title -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitleById: unable to find defined title');
            return FALSE;
        }
        list($title) = $qTitle->fetch_row();
        return $title;
    }
    
    public function sumTextSections($plugin, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumTextSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = $this->dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTextSections: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTextSections: no one section was found');
            return FALSE;
        }
        list($totalSections) = $qSections->fetch_row();
        $totalPages = $totalSections/$rpp;
        $remainer = fmod($totalSections, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalSections, 'pages' => (int) $totalPages);
    }
    
    public function getTextSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextSectionsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'static');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextSectionsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qSections = $this->dbLink->query("SELECT `s`.`id` `id`, `s`.`section` `name`, `s`.`static` `static`, (SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_langman_text_names` WHERE `sid`=`s`.`id`) `contains` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTextSectionsXML: unable to get sections -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTextSectionsXML: unable to find defined plugin');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $sectionsNode = $xml->createElement('sections');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $sectionsNode->appendChild($attr_plugin);
        $xml->appendChild($sectionsNode);
        while ($row = $qSections->fetch_row()) {
            $sectionNode = $xml->createElement('section');
            $sectionsNode->appendChild($sectionNode);
            $sectionNode->appendChild($xml->createElement('id', $row[0]));
            $sectionNode->appendChild($xml->createElement('name', $row[1]));
            $sectionNode->appendChild($xml->createElement('static', $row[2]));
            $sectionNode->appendChild($xml->createElement('contains', $row[3]));
        }
        return $xml;
    }
    
    public function sumTitleSections($plugin, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumTitleSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = $this->dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTitleSections: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTitleSections: no one section was found');
            return FALSE;
        }
        list($totalSections) = $qSections->fetch_row();
        $totalPages = $totalSections/$rpp;
        $remainer = fmod($totalSections, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalSections, 'pages' => (int) $totalPages);
    }
    
    public function getTitleSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitleSectionsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'static');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitleSectionsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qSections = $this->dbLink->query("SELECT `s`.`id` `id`, `s`.`section` `name`, `s`.`static` `static`, (SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_langman_title_names` WHERE `sid`=`s`.`id`) `contains` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitleSectionsXML: unable to get sections -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitleSectionsXML: unable to find defined plugin');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $sectionsNode = $xml->createElement('sections');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $sectionsNode->appendChild($attr_plugin);
        $xml->appendChild($sectionsNode);
        while ($row = $qSections->fetch_row()) {
            $sectionNode = $xml->createElement('section');
            $sectionsNode->appendChild($sectionNode);
            $sectionNode->appendChild($xml->createElement('id', $row[0]));
            $sectionNode->appendChild($xml->createElement('name', $row[1]));
            $sectionNode->appendChild($xml->createElement('static', $row[2]));
            $sectionNode->appendChild($xml->createElement('contains', $row[3]));
        }
        return $xml;
    }
    
    public function sumTextNames($plugin, $section, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumTextNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = $this->dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTextNames: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTextNames: no one name was found');
            return FALSE;
        }
        list($totalNames) = $qNames->fetch_row();
        $totalPages = $totalNames/$rpp;
        $remainer = fmod($totalNames, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalNames, 'pages' => (int) $totalPages);
    }
    
    public function getTextNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextNamesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTextNamesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qNames = $this->dbLink->query("SELECT `n`.`id`, `n`.`name`, "
                . "(SELECT GROUP_CONCAT(`l`.`code` ORDER BY `l`.`code` SEPARATOR ';') "
                    . "FROM `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_texts` `t` "
                    . "ON `t`.`codeid`=`l`.`id` WHERE `t`.`nameid`=`n`.`id` ) `languages` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTextNamesXML: unable to get names -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTextNamesXML: unable to find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $pageNode = $xml->createElement('page');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $pageNode->appendChild($attr_plugin);
        $attr_section = $xml->createAttribute('section');
        $attr_section->value = $section;
        $pageNode->appendChild($attr_section);
        $xml->appendChild($pageNode);
        while ($row = $qNames->fetch_row()) {
            $textNode = $xml->createElement('text');
            $pageNode->appendChild($textNode);
            $textNode->appendChild($xml->createElement('id', $row[0]));
            $textNode->appendChild($xml->createElement('name', $row[1]));
            $languagesNode = $xml->createElement('languages');
            $languagesArray = explode(";", $row[2]);
            foreach ($languagesArray as $langCode) {
                $languagesNode->appendChild($xml->createElement('code', $langCode));
            }
            $textNode->appendChild($languagesNode);
        }
        return $xml;
    }
    
    public function sumTitleNames($plugin, $section, $rpp = 20) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumTitleNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = $this->dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumTitleNames: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'sumTitleNames: no one name was found');
            return FALSE;
        }
        list($totalNames) = $qNames->fetch_row();
        $totalPages = $totalNames/$rpp;
        $remainer = fmod($totalNames, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalNames, 'pages' => (int) $totalPages);
    }
    
    public function getTitleNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitleNamesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name');
        if (is_array($orderBy)) {
            $arrayLen = count($orderBy);
            if ($arrayLen && count(array_intersect($orderBy, $rightEntry)) == $arrayLen) {
                $orderList = '';
                foreach ($orderBy as $value) {
                    $orderList = $orderList.$value.'`, `';
                }
                $orderBy = substr($orderList, 0, -4);
            }
            else {
                $orderBy = 'id';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'getTitleNamesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qNames = $this->dbLink->query("SELECT `n`.`id`, `n`.`name`, "
                . "(SELECT GROUP_CONCAT(`l`.`code` ORDER BY `l`.`code` SEPARATOR ';') "
                    . "FROM `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_titles` `t` "
                    . "ON `t`.`codeid`=`l`.`id` WHERE `t`.`nameid`=`n`.`id` ) `languages` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getTitleNamesXML: unable to get names -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getTitleNamesXML: unable to find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $pageNode = $xml->createElement('page');
        $attr_plugin = $xml->createAttribute('plugin');
        $attr_plugin->value = $plugin;
        $pageNode->appendChild($attr_plugin);
        $attr_section = $xml->createAttribute('section');
        $attr_section->value = $section;
        $pageNode->appendChild($attr_section);
        $xml->appendChild($pageNode);
        while ($row = $qNames->fetch_row()) {
            $titleNode = $xml->createElement('title');
            $pageNode->appendChild($titleNode);
            $titleNode->appendChild($xml->createElement('id', $row[0]));
            $titleNode->appendChild($xml->createElement('name', $row[1]));
            $languagesNode = $xml->createElement('languages');
            $languagesArray = explode(";", $row[2]);
            foreach ($languagesArray as $langCode) {
                $languagesNode->appendChild($xml->createElement('code', $langCode));
            }
            $titleNode->appendChild($languagesNode);
        }
        return $xml;
    }
}
