<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';

class LangMan {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    private static $language = MECCANO_DEF_LANG; // current language
    
    public function __construct($dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setDbLink($dbLink) {
        self::$dbLink = $dbLink;
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
    
    public static function addLang($code, $name) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregLang($code) || !is_string($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addLang: incorrect incoming parameters');
            return FALSE;
        }
        $name = self::$dbLink->real_escape_string(htmlspecialchars($name));
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_languages` (`code`, `name`) "
                . "VALUES('$code', '$name') ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addLang: '.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delLang($code) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregLang($code)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delLang: incorrect incoming parameter');
            return FALSE;
        }
        if ($code == MECCANO_DEF_LANG) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('delLang: it is impossible to delete default language');
            return FALSE;
        }
        $defLang = MECCANO_DEF_LANG;
        $sql = array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
            . "ON `l`.`id`=`t`.`codeid` "
            . "WHERE `l`.`code`='$code' ;",
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
            . "ON `l`.`id`=`t`.`codeid` "
            . "WHERE `l`.`code`='$code' ;",
            "DELETE `p` FROM `".MECCANO_TPREF."_core_langman_policy_description` `p` "
            . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
            . "ON `l`.`id`=`p`.`codeid` "
            . "WHERE `l`.`code`='$code' ;",
            "UPDATE `".MECCANO_TPREF."_core_userman_users` "
            . "SET `langid`=("
                . "SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$defLang') "
            . "WHERE `langid`=("
                . "SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code') ;");
        foreach ($sql as $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delLang: '.self::$dbLink->error);
                return FALSE;
            }
        }
        self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delLang: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delLang: defined language code was not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function setLang($code) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregLang($code)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('setLang: incorrect language code');
            return FALSE;
        }
        $qCode = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('setLang: can\'t get language code | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('setLang: can\'t find defined language');
            return FALSE;
        }
        self::$language = $code;
        return TRUE;
    }

    public static function langList() {
        self::$errid = 0;        self::$errexp = '';
        $qLang = self::$dbLink->query("SELECT `id`, `code`, `name` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('langList: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('langList: there wasn\'t found any language');
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
        }
        return $xml;
    }
    
    public static function installPolicyDesc($description) {
        self::$errid = 0;        self::$errexp = '';
        if (!$description->relaxNGValidate(MECCANO_CORE_DIR.'/langman/policy-description-schema-v01.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('installPolicyDesc: incorrect structure of policy description');
            return FALSE;
        }
        //getting of the list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installPolicyDesc: can\'t get list of available languages: '.self::$dbLink->error);
            return FALSE;
        }
        $avaiLang = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avaiLang[$row[1]] = $row[0];
        }
        //getting of list of installed policies
        $plugName = $description->getElementsByTagName('description')->item(0)->getAttribute('plugin'); //getting of plugin name
        $qPolicies = self::$dbLink->query("SELECT `id`, `func` "
                . "FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                . "WHERE `name`='$plugName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installPolicyDesc: can\'t get list of available languages | '.self::$dbLink->error);
            return FALSE;
        }
        $plugPolicies = array(); // installed policies
        while ($row = $qPolicies->fetch_row()) {
            $plugPolicies[$row[1]] = $row[0];
        }
        // installing/updating of policy descriptions
        $funcNodes = $description->getElementsByTagName('function');
        foreach ($funcNodes as $funcNode) {
            $funcName = $funcNode->getAttribute('name'); // name of function
            if (isset($plugPolicies[$funcName])) {
                $policyId = $plugPolicies[$funcName]; // policy identifier
                $langList = $funcNode->getElementsByTagName('language');
                $isDefault = 0; // flag of availability of default language
                foreach ($langList as $lang) {
                    $langCode = $lang->getAttribute('code');
                    if (isset($avaiLang[$langCode])) {
                        $codeId = $avaiLang[$langCode];
                        $shortDesc = self::$dbLink->real_escape_string($lang->getElementsByTagName('short')->item(0)->nodeValue); // short description
                        $detailedDesc = self::$dbLink->real_escape_string($lang->getElementsByTagName('detailed')->item(0)->nodeValue); //detailed description
                        $qDesc = self::$dbLink->query("SELECT `id` "
                                . "FROM `".MECCANO_TPREF."_core_langman_policy_description` "
                                . "WHERE `policyid`=$policyId "
                                . "AND `codeid`=$codeId LIMIT 1 ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                            self::setErrExp('installPolicyDesc: '.self::$dbLink->error);
                            return FALSE;
                        }
                        if (self::$dbLink->affected_rows) {
                            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_policy_description` "
                                    . "SET `short`='$shortDesc', `detailed`='$detailedDesc' "
                                    . "WHERE `policyid`=$policyId "
                                    . "AND `codeid`=$codeId");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('installPolicyDesc: can\'t update description | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                        else {
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_policy_description` "
                                    . "(`codeid`, `policyid`, `short`, `detailed`) "
                                    . "VALUES ($codeId, $policyId, '$shortDesc', '$detailedDesc') ;");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('installPolicyDesc: can\'t install description | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                    if (MECCANO_DEF_LANG == $langCode) {
                        $isDefault = 1;
                    }
                }
                if (!$isDefault) { // if there is not description for default language
                    $codeId = $avaiLang[MECCANO_DEF_LANG];
                    $qDesc = self::$dbLink->query("SELECT `id` "
                            . "FROM `".MECCANO_TPREF."_core_langman_policy_description` "
                            . "WHERE `policyid`=$policyId "
                            . "AND `codeid`=$codeId LIMIT 1 ;");
                    if (!self::$dbLink->affected_rows) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_policy_description` "
                                . "(`codeid`, `policyid`, `short`, `detailed`) "
                                . "VALUES ($codeId, $policyId, '$funcName', '$funcName') ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('installPolicyDesc: can\'t install description | '.self::$dbLink->error);
                            return FALSE;
                        }
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function installTiles($titles) {
        self::$errid = 0;        self::$errexp = '';
        if (!$titles->relaxNGValidate(MECCANO_CORE_DIR.'/langman/title-schema-v1.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('installTitles: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installPolicyDesc: can\'t get list of available languages: '.self::$dbLink->error);
            return FALSE;
        }
        $avaiLang = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avaiLang[$row[1]] = $row[0];
        }
        //getting name of the plugin
        $plugName = $titles->getElementsByTagName('titles')->item(0)->getAttribute('plugin');
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_install` "
                . "WHERE `name`='$plugName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installTitles: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('installTitles: can\'t find plugin');
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
                self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                        . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t rename section | '.self::$dbLink->error);
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
        $qSections = self::$dbLink->query("SELECT `s`.`section`, `s`.`static`, `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
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
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::errId(ERROR_NOT_EXECUTED);                    self::errExp('installTitles: can\'t delete outdated data | '.self::$dbLink->error);
                    return FALSE;
                }
            }
            
        }
        // installing/updating titles
        foreach ($sections as $sectionName => $titlePool) {
            // updating
            if (isset($dbSectionTypes[$sectionName])) {
                // static section
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
                        self::$dbLink->query($value);
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                            self::setErrExp('installTitles: can\'t clear data before updating titles | '.self::$dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($titlePool as $titleName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t update name | '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($title);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t update title | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non static section
                else {
                    self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_title_sections` "
                            . "SET `static`=0 "
                            . "WHERE `section`='$sectionName' ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                        self::setErrExp('installTitles: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
            // installing
            else {
                // static section section
                if ($sectionTypes[$sectionName]) {
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 1) ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t create section | '.self::$dbLink->error);
                        return FALSE;
                    }
                    $sectionId = self::$dbLink->insert_id;
                    foreach ($titlePool as $titleName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t create title name | '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($title);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: can\'t create title | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non static section
                else {
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 0) ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTitles: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function installTexts($texts) {
        self::$errid = 0;        self::$errexp = '';
        if (!$texts->relaxNGValidate(MECCANO_CORE_DIR.'/langman/text-schema-v1.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('installTexts: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installPolicyDesc: can\'t get list of available languages: '.self::$dbLink->error);
            return FALSE;
        }
        $avaiLang = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avaiLang[$row[1]] = $row[0];
        }
        //getting name of the plugin
        $plugName = $texts->getElementsByTagName('texts')->item(0)->getAttribute('plugin');
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_install` "
                . "WHERE `name`='$plugName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installTexts: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('installTexts: can\'t find plugin');
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
                self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                        . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t rename section | '.self::$dbLink->error);
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
        $qSections = self::$dbLink->query("SELECT `s`.`section`, `s`.`static`, `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
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
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::errId(ERROR_NOT_EXECUTED);                    self::errExp('installTexts: can\'t delete outdated data | '.self::$dbLink->error);
                    return FALSE;
                }
            }
            
        }
        // installing/updating texts
        foreach ($sections as $sectionName => $textPool) {
            // updating
            if (isset($dbSectionTypes[$sectionName])) {
                // static section
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
                        self::$dbLink->query($value);
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                            self::setErrExp('installTexts: can\'t clear data before updating texts | '.self::$dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($textPool as $textName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t update name | '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $text) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($text[0]);
                            $document = self::$dbLink->real_escape_string($text[1]);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` (`codeid`, `nameid`, `title`, `document`) "
                                    . "VALUES ($codeId, $nameId, '$title', '$document') ;");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t update text | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non static section
                else {
                    self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_text_sections` "
                            . "SET `static`=0 "
                            . "WHERE `section`='$sectionName' ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                        self::setErrExp('installTexts: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
            // installing
            else {
                // static section section
                if ($sectionTypes[$sectionName]) {
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 1) ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t create section | '.self::$dbLink->error);
                        return FALSE;
                    }
                    $sectionId = self::$dbLink->insert_id;
                    foreach ($textPool as $textName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if (self::$dbLink->errno) {
                            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t create text name | '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $text) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($text[0]);
                            $document = self::$dbLink->real_escape_string($text[1]);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` (`codeid`, `nameid`, `title`, `document`) "
                                    . "VALUES ($codeId, $nameId, '$title', '$document') ;");
                            if (self::$dbLink->errno) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: can\'t create text | '.self::$dbLink->error);
                                return FALSE;
                            }
                        }
                    }
                }
                // non static section
                else {
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`section`, `plugid`, `static`) "
                            . "VALUES ('$sectionName', $plugId, 0) ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('installTexts: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function delPlugin($name) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPlugin: incorrect plugin name');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_install` "
                . "WHERE `name`='$name' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delPlugin: can\'t find plugin');
            return FALSE;
        }
        // deleting all the data related to plugin
        $sql = array(
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;", 
            "DELETE `s` FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;", 
            "DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "ON `n`.`id`=`t`.`nameid` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;", 
            "DELETE `n` FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
            . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "ON `s`.`id`=`n`.`sid` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;", 
            "DELETE `s` FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
            . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
            . "ON `p`.`id`=`s`.`plugid` "
            . "WHERE `p`.`name`='$name' ;"
        );
        foreach ($sql as $key => $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delPlugin: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public static function addTitleSection($section, $plugin) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregPlugin($plugin)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addTitleSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_install` "
                . "WHERE `name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addTitleSection: can\'t find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if (self::$dbLink->errno) {
            self::setErrExp(ERROR_NOT_EXECUTED);            self::setErrExp('addTitleSection: '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitleSection($sid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($sid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delTitleSection: incorrect identifier');
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delTitleSection: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delTitleSection: defined section doesn\'t exist or your are trying to delete static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTextSection($section, $plugin) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregPlugin($plugin)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addTextSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_install` "
                . "WHERE `name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addTextSection: can\'t find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if (self::$dbLink->errno) {
            self::setErrExp(ERROR_NOT_EXECUTED);            self::setErrExp('addTextSection: '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    public static function delTextSection($sid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($sid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delTextSection: incorrect identifier');
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delTextSection: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delTextSection: defined section doesn\'t exist or your are trying to delete static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTitleName($name, $section, $plugin) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addTitleName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = self::$dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTitleName: can\'t check section and plugin | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addTitleName: can\'t find matchable section and plugin or you are trying to create name in static section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTitleName: can\'t create name | '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitleName($nameid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($nameid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delTitleName: incorrect identifier');
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delTitleName: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delTitleName: defined tile name doesn\'t exist or your are trying to delete name from static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTextName($name, $section, $plugin) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addTextName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = self::$dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTextName: can\'t check section and plugin | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addTextName: can\'t find matchable section and plugin or you are trying to create name in static section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTextName: can\'t create name | '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTextName($nameid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($nameid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delTextName: incorrect identifier');
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("delTextName: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delTextName: defined tile name doesn\'t exist or your are trying to delete name from static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTitle($title, $name, $section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_string($title) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addTitle: incorrect incoming parameters');
            return FALSE;
        }
        $qTitle = self::$dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTitle: can\'t get name identifier | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addTitle: can\'t find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qTitle->fetch_row();
        if (is_null($code)) {
            $code = self::$language;
        }
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTitle: can\'t get language code identifier | '.self::$dbLink->error);
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = self::$dbLink->real_escape_string($title);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` "
                . "(`title`, `nameid`, `codeid`) "
                . "VALUES ('$title', $nameId, $codeId) ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addTitle: can\'t insert title | '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitle($tid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($tid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delTitle: incorrect title identifier');
            return FALSE;
        }
        self::$dbLink->query("DELETE `t` FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `t`.`id`=$tid "
                . "AND `s`.`static`=0 ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delTitle: can\'t delete defined title');
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delTitle: can\'t find defined title');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addText($title, $document, $name, $section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_string($title) || !is_string($document) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addText: incorrect incoming parameters');
            return FALSE;
        }
        $qText = self::$dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addText: can\'t get name identifier | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('addText: can\'t find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qText->fetch_row();
        if (is_null($code)) {
            $code = self::$language;
        }
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addText: can\'t get language code identifier | '.self::$dbLink->error);
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = self::$dbLink->real_escape_string($title);
        $document = self::$dbLink->real_escape_string($document);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` "
                . "(`title`, `document`, `nameid`, `codeid`) "
                . "VALUES ('$title', '$document', $nameId, $codeId) ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('addText: can\'t insert text | '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delText($tid) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($tid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delText: incorrect text identifier');
            return FALSE;
        }
        self::$dbLink->query("DELETE `t` FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "WHERE `t`.`id`=$tid "
                . "AND `s`.`static`=0 ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('delText: can\'t delete defined text');
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delText: can\'t find defined text');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function updateTitle($id, $title) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id) || !is_string($title)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('updateTitle: incorrect incoming parameters');
            return FALSE;
        }
        $title = self::$dbLink->real_escape_string($title);
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "SET `t`.`title`='$title' "
                . "WHERE `t`.`id`=$id "
                . "AND `s`.`static`=0 ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('updateTitle: can\'t update title | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('updateTitle: can\'t find defined title');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function updateText($id, $title, $document) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id) || !is_string($title) || !is_string($document)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('updateText: incorrect incoming parameters');
            return FALSE;
        }
        $title = self::$dbLink->real_escape_string($title);
        $document = self::$dbLink->real_escape_string($document);
        self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "SET `t`.`title`='$title', `t`.`document`='$document' "
                . "WHERE `t`.`id`=$id "
                . "AND `s`.`static`=0 ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('updateText: can\'t update text | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('updateText: can\'t find defined text');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function getTitle($name, $section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitle: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $qTitle = self::$dbLink->query("SELECT `t`.`title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `n`.`name`='$name' "
                . "AND `s`.`section`='$section' "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitle: can\'t get title | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitle: can\'t find defined title');
            return FALSE;
        }
        list($title) = $qTitle->fetch_row();
        return $title;
    }
    
    public static function getText($name, $section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getText: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $qText = self::$dbLink->query("SELECT `t`.`title`, `t`.`document`, `t`.`created`, `t`.`edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `n`.`name`='$name' "
                . "AND `s`.`section`='$section' "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getText: can\'t get text | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getText: can\'t find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited);
    }
    
    public static function getTitles($section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitles: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $qTitles = self::$dbLink->query("SELECT `n`.`name`, `t`.`title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitles: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitles: can\'t find defined section');
            return FALSE;
        }
        $titles = array();
        while ($result = $qTitles->fetch_row()) {
            $titles[$result[0]] = $result[1];
        }
        return $titles;
    }
    
    public static function getAllTextsXML($section, $plugin, $orderBy = 'id', $ascent = FALSE, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllTextsXML: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $rightEntry = array('id', 'title', 'name', 'created', 'edited');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllTextsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qTexts = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getAllTextsXML: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getAllTextsXML: can\'t find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $textsNode = $xml->createElement('texts');
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
    
    public static function getTextById($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextById: identifier must be integer');
            return FALSE;
        }
        $qText = self::$dbLink->query("SELECT `title`, `document`, `created`, `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTextById: can\'t get text | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTextById: can\'t find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited);
    }
    
    public static function sumTexts($section, $plugin, $rpp = 20, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTexts: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qTexts = self::$dbLink->query("SELECT count(`t`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id` =`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTexts: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTexts: no one text was found');
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
    
    public static function getTextsXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextsXML: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $rightEntry = array('id', 'title', 'name', 'created', 'edited');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextsXML: value of $orderBy must be string or array');
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
        $qTexts = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTextsXML: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTextsXML: can\'t find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $textsNode = $xml->createElement('texts');
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
    
    public static function getTexts($section, $plugin, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTexts: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $qTexts = self::$dbLink->query("SELECT `n`.`name`, `t`.`title` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTexts: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTexts: can\'t find defined section');
            return FALSE;
        }
        $texts = array();
        while ($result = $qTexts->fetch_row()) {
            $texts[$result[0]] = $result[1];
        }
        return $texts;
    }
    
    public static function sumTitles($section, $plugin, $rpp = 20, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitles: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qTitles = self::$dbLink->query("SELECT count(`t`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id` =`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTitles: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTitles: no one title was found');
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
    
    public static function getTitlesXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitlesXML: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $rightEntry = array('id', 'title', 'name');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitlesXML: value of $orderBy must be string or array');
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
        $qTitles = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitlesXML: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitlesXML: can\'t find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $titlesNode = $xml->createElement('titles');
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
    
    public static function getAllTitlesXML($section, $plugin, $orderBy = 'id', $ascent = FALSE, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregName40($section) || !pregName40($plugin) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllTitlesXML: incorrect incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        $rightEntry = array('id', 'title', 'name');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllTitlesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qTitles = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` `t` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "ON `n`.`id`=`t`.`nameid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                . "ON `l`.`id`=`t`.`codeid` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' "
                . "AND `l`.`code`='$code' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getAllTitlesXML: can\'t get section | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getAllTitlesXML: can\'t find defined section');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $titlesNode = $xml->createElement('titles');
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
    
    public static function getTitleById($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitleById: identifier must be integer');
            return FALSE;
        }
        $qTitle = self::$dbLink->query("SELECT `title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitleById: can\'t get title | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitleById: can\'t find defined title');
            return FALSE;
        }
        list($title) = $qTitle->fetch_row();
        return $title;
    }
    
    public static function groupPolicyList($plugin, $groupId, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !(is_integer($groupId) || is_bool($groupId)) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('policyList: incorect type of incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = self::$language;
        }
        if (is_bool($groupId)) {
            $qList = self::$dbLink->query("SELECT `d`.`id`, `d`.`short`, `s`.`func`, `n`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_nosession` `n` "
                    . "ON `s`.`id`=`n`.`funcid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_policy_description` `d` "
                    . "ON `d`.`policyid`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `d`.`codeid`=`l`.`id` "
                    . "WHERE `s`.`name`='$plugin' "
                    . "AND `l`.`code`='$code' ;");
        }
        else {
            $qList = self::$dbLink->query("SELECT `d`.`id`, `d`.`short`, `s`.`func`, `a`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "ON `s`.`id`=`a`.`funcid` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_policy_description` `d` "
                    . "ON `d`.`policyid`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "ON `d`.`codeid`=`l`.`id` "
                    . "WHERE `s`.`name`='$plugin' "
                    . "AND `a`.`groupid`=$groupId "
                    . "AND `l`.`code`='$code' ;");
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('policyList: something went wrong | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('policyList: name or group don\'t exist');
            return FALSE;
        }
        $xml = new \DOMDocument('1.0', 'utf-8');
        $policyNode = $xml->createElement('policy');
        $xml->appendChild($policyNode);
        while ($row = $qList->fetch_row()) {
            $funcNode = $xml->createElement('function');
            $policyNode->appendChild($funcNode);
            $funcNode->appendChild($xml->createElement('id', $row[0]));
            $funcNode->appendChild($xml->createElement('short', $row[1]));
            $funcNode->appendChild($xml->createElement('name', $row[2]));
            $funcNode->appendChild($xml->createElement('access', $row[3]));
        }
        return $xml;
    }
    
    public static function getPolicyDescById($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getPolicyDescById: identifier must be integer');
            return FALSE;
        }
        $qDesc = self::$dbLink->query("SELECT `short`, `detailed` "
                . "FROM `".MECCANO_TPREF."_core_langman_policy_description` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getPolicyDescById: can\'t get description | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getPolicyDescById: description was not found');
            return FALSE;
        }
        list($short, $detailed) = $qDesc->fetch_row();
        return array('short' => $short, 'detailed' => $detailed);
    }
    
    public static function sumTextSections($plugin, $rpp = 20) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumTextSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = self::$dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTextSections: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTextSections: no one section was found');
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
    
    public static function getTextSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'static');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getAllTextsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qSections = self::$dbLink->query("SELECT `s`.`id` `id`, `s`.`section` `name`, `s`.`static` `static`, (SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_langman_text_names` WHERE `sid`=`s`.`id`) `contains` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getAllTextsXML: can\'t get sections | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getAllTextsXML: can\'t find defined plugin');
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
    
    public static function sumTitleSections($plugin, $rpp = 20) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumTitleSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = self::$dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTitleSections: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTitleSections: no one section was found');
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
    
    public static function getTitleSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitleSectionsXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'static');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitleSectionsXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qSections = self::$dbLink->query("SELECT `s`.`id` `id`, `s`.`section` `name`, `s`.`static` `static`, (SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_langman_title_names` WHERE `sid`=`s`.`id`) `contains` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitleSectionsXML: can\'t get sections | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitleSectionsXML: can\'t find defined plugin');
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
    
    public static function sumTextNames($plugin, $section, $rpp = 20) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumTextNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = self::$dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTextNames: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTextNames: no one name was found');
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
    
    public static function getTextNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextNamesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTextNamesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qNames = self::$dbLink->query("SELECT `n`.`id`, `n`.`name`, "
                . "(SELECT GROUP_CONCAT(`l`.`code` ORDER BY `l`.`code` SEPARATOR ';') "
                    . "FROM `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_texts` `t` "
                    . "ON `t`.`codeid`=`l`.`id` WHERE `t`.`nameid`=`n`.`id` ) `languages` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTextNamesXML: can\'t get names | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTextNamesXML: can\'t find defined section');
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
    
    public static function sumTitleNames($plugin, $section, $rpp = 20) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('sumTitleNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = self::$dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('sumTitleNames: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('sumTitleNames: no one name was found');
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
    
    public static function getTitleNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = 'id', $ascent = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitleNamesXML: incorrect incoming parameters');
            return FALSE;
        }
        $rightEntry = array('id', 'name');
        if (is_string($orderBy)) {
            if (!in_array($orderBy, $rightEntry, TRUE)) {
            $orderBy = 'id';
            }
        }
        elseif (is_array($orderBy)) {
            if (count(array_intersect($orderBy, $rightEntry))) {
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
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('getTitleNamesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qNames = self::$dbLink->query("SELECT `n`.`id`, `n`.`name`, "
                . "(SELECT GROUP_CONCAT(`l`.`code` ORDER BY `l`.`code` SEPARATOR ';') "
                    . "FROM `".MECCANO_TPREF."_core_langman_languages` `l` "
                    . "JOIN `".MECCANO_TPREF."_core_langman_titles` `t` "
                    . "ON `t`.`codeid`=`l`.`id` WHERE `t`.`nameid`=`n`.`id` ) `languages` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_install` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('getTitleNamesXML: can\'t get names | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('getTitleNamesXML: can\'t find defined section');
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
