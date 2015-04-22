<?php

namespace core;

require_once 'swconst.php';
require_once 'unifunctions.php';

interface intLangMan {
    public function __construct(\mysqli $dbLink);
    public static function setDbLink(\mysqli $dbLink);
    public static function errId();
    public static function errExp();
    public static function addLang($code, $name);
    public static function delLang($code);
    public static function langList();
    public static function installTitles(\DOMDocument $titles, $validate = TRUE);
    public static function installTexts(\DOMDocument $texts, $validate = TRUE);
    public static function delPlugin($plugin);
    public static function addTitleSection($section, $plugin);
    public static function delTitleSection($sid);
    public static function addTextSection($section, $plugin);
    public static function delTextSection($sid);
    public static function addTitleName($name, $section, $plugin);
    public static function delTitleName($nameid);
    public static function addTextName($name, $section, $plugin);
    public static function delTextName($nameid);
    public static function addTitle($title, $name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public static function delTitle($tid);
    public static function addText($title, $document, $name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public static function delText($tid);
    public static function updateTitle($id, $title);
    public static function updateText($id, $title, $document);
    public static function getTitle($name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public static function getText($name, $section, $plugin, $code = MECCANO_DEF_LANG);
    public static function getTitles($section, $plugin, $code = MECCANO_DEF_LANG);
    public static function getAllTextsXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getTextById($id);
    public static function sumTexts($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20);
    public static function getTextsXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getTexts($section, $plugin, $code = MECCANO_DEF_LANG);
    public static function sumTitles($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20);
    public static function getTitlesXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getAllTitlesXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE);
    public static function getTitleById($id);
    public static function sumTextSections($plugin, $rpp = 20);
    public static function getTextSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public static function sumTitleSections($plugin, $rpp = 20);
    public static function getTitleSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public static function sumTextNames($plugin, $section, $rpp = 20);
    public static function getTextNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public static function sumTitleNames($plugin, $section, $rpp = 20);
    public static function getTitleNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
}

class LangMan implements intLangMan{
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    
    public function __construct(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
    }
    
    public static function setDbLink(\mysqli $dbLink) {
        self::$dbLink = $dbLink;
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
    
    public static function addLang($code, $name, $dir = 'ltr') {
        self::zeroizeError();
        if (!pregLang($code) || !is_string($name) || !in_array($dir, array('ltr', 'rtl'))) {
            self::setError(ERROR_INCORRECT_DATA, 'addLang: incorrect incoming parameters');
            return FALSE;
        }
        $name = self::$dbLink->real_escape_string(htmlspecialchars($name));
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_languages` (`code`, `name`, `dir`) "
                . "VALUES('$code', '$name', '$dir') ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addLang: '.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public static function delLang($code) {
        self::zeroizeError();
        if (!pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'delLang: incorrect incoming parameter');
            return FALSE;
        }
        if ($code == MECCANO_DEF_LANG) {
            self::setError(ERROR_SYSTEM_INTERVENTION, 'delLang: it is impossible to delete default language');
            return FALSE;
        }
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delLang: unable to get language identifier |'.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "delLang: language [$code] is not found");
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $defLang = MECCANO_DEF_LANG;
        $qDefLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$defLang' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delLang: unable to get default language identifier |'.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, "delLang:  default language [$defLang] is not found");
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, 'delLang: '.self::$dbLink->error);
                return FALSE;
            }
        }
        self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delLang: '.self::$dbLink->error);
            return FALSE;
        }
        return TRUE;
    }

    public static function langList() {
        self::zeroizeError();
        $qLang = self::$dbLink->query("SELECT `id`, `code`, `name`, `dir` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'langList: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'langList: there wasn\'t found any language');
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
    
    public static function installTitles(\DOMDocument $titles, $validate = TRUE) {
        self::zeroizeError();
        if ($validate && !@$titles->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/langman-title-v01.rng')) {
            self::setError(ERROR_INCORRECT_DATA, 'installTitles: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'installPolicyDesc: unable to get list of available languages: '.self::$dbLink->error);
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
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugName' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'installTitles: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'installTitles: unable to find plugin');
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
                        . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if (self::$dbLink->errno) {
                    self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to rename section -> '.self::$dbLink->error);
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
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::errId(ERROR_NOT_EXECUTED);                    self::errExp('installTitles: unable to delete outdated data -> '.self::$dbLink->error);
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
                            self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to clear data before updating titles -> '.self::$dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($titlePool as $titleName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if (self::$dbLink->errno) {
                            self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to update name -> '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($title);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if (self::$dbLink->errno) {
                                self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to update title -> '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTitles: '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create section -> '.self::$dbLink->error);
                        return FALSE;
                    }
                    $sectionId = self::$dbLink->insert_id;
                    foreach ($titlePool as $titleName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$titleName') ;");
                        if (self::$dbLink->errno) {
                            self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create title name -> '.self::$dbLink->error);
                            return FALSE;
                        }
                        $nameId = self::$dbLink->insert_id;
                        foreach ($langPool as $langCode => $title) {
                            $codeId = $avaiLang[$langCode];
                            $title = self::$dbLink->real_escape_string($title);
                            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` (`codeid`, `nameid`, `title`) "
                                    . "VALUES ($codeId, $nameId, '$title') ;");
                            if (self::$dbLink->errno) {
                                self::setError(ERROR_NOT_EXECUTED, 'installTitles: unable to create title -> '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTitles: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function installTexts(\DOMDocument $texts, $validate = TRUE) {
        self::zeroizeError();
        if ($validate && !@$texts->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/langman-text-v01.rng')) {
            self::setError(ERROR_INCORRECT_DATA, 'installTexts: incorrect structure of policy description');
            return FALSE;
        }
        //getting list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `id`, `code` FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'installPolicyDesc: unable to get list of available languages: '.self::$dbLink->error);
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
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugName' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'installTexts: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'installTexts: unable to find plugin');
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
                        . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                        . "ON `s`.`plugid`=`p`.`id` "
                        . "SET `s`.`section`='$sectionName' "
                        . "WHERE `p`.`name`='$plugName' "
                        . "AND `s`.`section`='$sectionOldName' ;");
                if (self::$dbLink->errno) {
                    self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to rename section -> '.self::$dbLink->error);
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
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::errId(ERROR_NOT_EXECUTED);                    self::errExp('installTexts: unable to delete outdated data -> '.self::$dbLink->error);
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
                            self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to clear data before updating texts -> '.self::$dbLink->error);
                            return FALSE;
                        }
                    }
                    $sectionId = $dbSectionIds[$sectionName];
                    foreach ($textPool as $textName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if (self::$dbLink->errno) {
                            self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to update name -> '.self::$dbLink->error);
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
                                self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to update text -> '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTexts: '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create section -> '.self::$dbLink->error);
                        return FALSE;
                    }
                    $sectionId = self::$dbLink->insert_id;
                    foreach ($textPool as $textName => $langPool) {
                        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` (`sid`, `name`) "
                                . "VALUES ($sectionId, '$textName') ;");
                        if (self::$dbLink->errno) {
                            self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create text name -> '.self::$dbLink->error);
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
                                self::setError(ERROR_NOT_EXECUTED, 'installTexts: unable to create text -> '.self::$dbLink->error);
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
                        self::setError(ERROR_NOT_EXECUTED, 'installTexts: '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
        }
        return TRUE;
    }
    
    public static function delPlugin($plugin) {
        self::zeroizeError();
        if (!pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, 'delPlugin: incorrect plugin name');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delPlugin: unable to find plugin');
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
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setError(ERROR_NOT_EXECUTED, "delPlugin: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public static function addTitleSection($section, $plugin) {
        self::zeroizeError();
        if (!pregName40($section) || !pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, 'addTitleSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTitleSection: unable to find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitleSection: '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitleSection($sid) {
        self::zeroizeError();
        if (!is_integer($sid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delTitleSection: incorrect identifier');
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
                self::setError(ERROR_NOT_EXECUTED, "delTitleSection: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delTitleSection: defined section doesn\'t exist or your are trying to delete static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTextSection($section, $plugin) {
        self::zeroizeError();
        if (!pregName40($section) || !pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, 'addTextSection: incorrect incoming parameters');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'delPlugin: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTextSection: unable to find plugin');
            return FALSE;
        }
        list($plugid) = $qPlugin->fetch_row();
        // creation of the new section
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_sections` (`plugid`, `section` , `static`) "
                . "VALUES ($plugid, '$section', 0) ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTextSection: '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTextSection($sid) {
        self::zeroizeError();
        if (!is_integer($sid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delTextSection: incorrect identifier');
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
                self::setError(ERROR_NOT_EXECUTED, "delTextSection: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delTextSection: defined section doesn\'t exist or your are trying to delete static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTitleName($name, $section, $plugin) {
        self::zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, 'addTitleName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = self::$dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitleName: unable to check section and plugin -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTitleName: unable to find matchable section and plugin or you are trying to create name in static section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_title_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitleName: unable to create name -> '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitleName($nameid) {
        self::zeroizeError();
        if (!is_integer($nameid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delTitleName: incorrect identifier');
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
                self::setError(ERROR_NOT_EXECUTED, "delTitleName: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delTitleName: defined tile name doesn\'t exist or your are trying to delete name from static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTextName($name, $section, $plugin) {
        self::zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin)) {
            self::setError(ERROR_INCORRECT_DATA, 'addTextName: incorrect incoming parameters');
            return FALSE;
        }
        $qIdentifiers = self::$dbLink->query("SELECT `s`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTextName: unable to check section and plugin -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTextName: unable to find matchable section and plugin or you are trying to create name in static section');
            return FALSE;
        }
        list($sid) = $qIdentifiers->fetch_row();
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_text_names` "
                . "(`sid`, `name`) "
                . "VALUES ($sid, '$name') ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTextName: unable to create name -> '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTextName($nameid) {
        self::zeroizeError();
        if (!is_integer($nameid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delTextName: incorrect identifier');
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
                self::setError(ERROR_NOT_EXECUTED, "delTextName: query #$key ".self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delTextName: defined tile name doesn\'t exist or your are trying to delete name from static section');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addTitle($title, $name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!is_string($title) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'addTitle: incorrect incoming parameters');
            return FALSE;
        }
        $qTitle = self::$dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitle: unable to get name identifier -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTitle: unable to find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qTitle->fetch_row();
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitle: unable to get language code identifier -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addTitle: defined language was not found');
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = self::$dbLink->real_escape_string($title);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_titles` "
                . "(`title`, `nameid`, `codeid`) "
                . "VALUES ('$title', $nameId, $codeId) ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addTitle: unable to insert title -> '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delTitle($tid) {
        self::zeroizeError();
        if (!is_integer($tid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delTitle: incorrect title identifier');
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
            self::setError(ERROR_NOT_EXECUTED, 'delTitle: unable to delete defined title');
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delTitle: unable to find defined title');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addText($title, $document, $name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!is_string($title) || !is_string($document) || !pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'addText: incorrect incoming parameters');
            return FALSE;
        }
        $qText = self::$dbLink->query("SELECT `n`.`id` "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `n`.`name`='$name'"
                . "AND `s`.`section`='$section' "
                . "AND `s`.`static`=0 "
                . "AND `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addText: unable to get name identifier -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addText: unable to find name, section or plugin');
            return FALSE;
        }
        list($nameId) = $qText->fetch_row();
        $qLang = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$code' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addText: unable to get language code identifier -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'addText: defined language was not found');
            return FALSE;
        }
        list($codeId) = $qLang->fetch_row();
        $title = self::$dbLink->real_escape_string($title);
        $document = self::$dbLink->real_escape_string($document);
        self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_langman_texts` "
                . "(`title`, `document`, `nameid`, `codeid`) "
                . "VALUES ('$title', '$document', $nameId, $codeId) ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'addText: unable to insert text -> '.self::$dbLink->error);
            return FALSE;
        }
        return (int) self::$dbLink->insert_id;
    }
    
    public static function delText($tid) {
        self::zeroizeError();
        if (!is_integer($tid)) {
            self::setError(ERROR_INCORRECT_DATA, 'delText: incorrect text identifier');
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
            self::setError(ERROR_NOT_EXECUTED, 'delText: unable to delete defined text');
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'delText: unable to find defined text');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function updateTitle($id, $title) {
        self::zeroizeError();
        if (!is_integer($id) || !is_string($title)) {
            self::setError(ERROR_INCORRECT_DATA, 'updateTitle: incorrect incoming parameters');
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
            self::setError(ERROR_NOT_EXECUTED, 'updateTitle: unable to update title -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'updateTitle: unable to find defined title');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function updateText($id, $title, $document) {
        self::zeroizeError();
        if (!is_integer($id) || !is_string($title) || !is_string($document)) {
            self::setError(ERROR_INCORRECT_DATA, 'updateText: incorrect incoming parameters');
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
            self::setError(ERROR_NOT_EXECUTED, 'updateText: unable to update text -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'updateText: unable to find defined text');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function getTitle($name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitle: incorrect incoming parameters');
            return FALSE;
        }
        $qTitle = self::$dbLink->query("SELECT `t`.`title` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitle: unable to get title -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitle: unable to find defined title');
            return FALSE;
        }
        list($title) = $qTitle->fetch_row();
        return $title;
    }
    
    public static function getText($name, $section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!pregName40($name) || !pregName40($section) || !pregPlugin($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getText: incorrect incoming parameters');
            return FALSE;
        }
        $qText = self::$dbLink->query("SELECT `t`.`title`, `t`.`document`, `t`.`created`, `t`.`edited`, `l`.`dir` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getText: unable to get text -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getText: unable to find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited, $direction) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited, 'dir' => $direction);
    }
    
    public static function getTitles($section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitles: incorrect incoming parameters');
            return FALSE;
        }
        $qTitles = self::$dbLink->query("SELECT `n`.`name`, `t`.`title` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitles: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitles: unable to find defined section');
            return FALSE;
        }
        $titles = array();
        while ($result = $qTitles->fetch_row()) {
            $titles[$result[0]] = $result[1];
        }
        return $titles;
    }
    
    public static function getAllTextsXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getAllTextsXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getAllTextsXML: orderBy must be array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        // get section texts
        $qTexts = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getAllTextsXML: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getAllTextsXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = self::$dbLink->query("SELECT `dir` "
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
    
    public static function getTextById($id) {
        self::zeroizeError();
        if (!is_integer($id)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTextById: identifier must be integer');
            return FALSE;
        }
        $qText = self::$dbLink->query("SELECT `title`, `document`, `created`, `edited` "
                . "FROM `".MECCANO_TPREF."_core_langman_texts` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTextById: unable to get text -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTextById: unable to find defined text');
            return FALSE;
        }
        list($title, $document, $created, $edited) = $qText->fetch_row();
        return array('title' => $title, 'document' => $document, 'created' => $created, 'edited' => $edited);
    }
    
    public static function sumTexts($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTexts: incorrect incoming parameters');
            return FALSE;
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTexts: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTexts: no one text was found');
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
    
    public static function getTextsXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTextsXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTextsXML: orderBy must be array');
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
        $qTexts = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name`, `t`.`created` `created`, `t`.`edited` `edited` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTextsXML: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTextsXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = self::$dbLink->query("SELECT `dir` "
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
    
    public static function getTexts($section, $plugin, $code = MECCANO_DEF_LANG) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTexts: incorrect incoming parameters');
            return FALSE;
        }
        $qTexts = self::$dbLink->query("SELECT `n`.`name`, `t`.`title` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTexts: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTexts: unable to find defined section');
            return FALSE;
        }
        $texts = array();
        while ($result = $qTexts->fetch_row()) {
            $texts[$result[0]] = $result[1];
        }
        return $texts;
    }
    
    public static function sumTitles($section, $plugin, $code = MECCANO_DEF_LANG, $rpp = 20) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !is_integer($rpp) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitles: incorrect incoming parameters');
            return FALSE;
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `l`.`code`='$code' "
                . "AND `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTitles: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTitles: no one title was found');
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
    
    public static function getTitlesXML($section, $plugin, $pageNumber, $totalPages, $rpp = 20, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregName40($section) || 
                !pregName40($plugin) || 
                !is_integer($pageNumber) || 
                !is_integer($totalPages) || 
                !is_integer($rpp) || 
                !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitlesXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTitlesXML: orderBy must be array');
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
        $qTitles = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitlesXML: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitlesXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = self::$dbLink->query("SELECT `dir` "
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
    
    public static function getAllTitlesXML($section, $plugin, $code = MECCANO_DEF_LANG, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregName40($section) || !pregName40($plugin) || !pregLang($code)) {
            self::setError(ERROR_INCORRECT_DATA, 'getAllTitlesXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getAllTitlesXML: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        // get section titles
        $qTitles = self::$dbLink->query("SELECT `t`.`id` `id`, `t`.`title` `title`, `n`.`name` `name` "
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
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getAllTitlesXML: unable to get section -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getAllTitlesXML: unable to find defined section');
            return FALSE;
        }
        // get text direction for defined language
        $qDirection = self::$dbLink->query("SELECT `dir` "
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
    
    public static function getTitleById($id) {
        self::zeroizeError();
        if (!is_integer($id)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitleById: identifier must be integer');
            return FALSE;
        }
        $qTitle = self::$dbLink->query("SELECT `title` "
                . "FROM `".MECCANO_TPREF."_core_langman_titles` "
                . "WHERE `id`=$id ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitleById: unable to get title -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitleById: unable to find defined title');
            return FALSE;
        }
        list($title) = $qTitle->fetch_row();
        return $title;
    }
    
    public static function sumTextSections($plugin, $rpp = 20) {
        self::zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumTextSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = self::$dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTextSections: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTextSections: no one section was found');
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
    
    public static function getTextSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTextSectionsXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTextSectionsXML: value of $orderBy must be string or array');
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTextSectionsXML: unable to get sections -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTextSectionsXML: unable to find defined plugin');
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
        self::zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumTitleSections: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qSections = self::$dbLink->query("SELECT count(`s`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTitleSections: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTitleSections: no one section was found');
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
    
    public static function getTitleSectionsXML($plugin, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregPlugin($plugin) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitleSectionsXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTitleSectionsXML: value of $orderBy must be string or array');
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitleSectionsXML: unable to get sections -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitleSectionsXML: unable to find defined plugin');
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
        self::zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumTextNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = self::$dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_text_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_text_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTextNames: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTextNames: no one name was found');
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
    
    public static function getTextNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTextNamesXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTextNamesXML: value of $orderBy must be string or array');
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTextNamesXML: unable to get names -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTextNamesXML: unable to find defined section');
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
        self::zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'sumTitleNames: incorrect incoming parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qNames = self::$dbLink->query("SELECT count(`n`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_langman_title_names` `n` "
                . "JOIN `".MECCANO_TPREF."_core_langman_title_sections` `s` "
                . "ON `s`.`id`=`n`.`sid` "
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' "
                . "AND `s`.`section`='$section' ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'sumTitleNames: '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'sumTitleNames: no one name was found');
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
    
    public static function getTitleNamesXML($plugin, $section, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        self::zeroizeError();
        if (!pregPlugin($plugin) || !pregName40($section) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            self::setError(ERROR_INCORRECT_DATA, 'getTitleNamesXML: incorrect incoming parameters');
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
            self::setError(ERROR_INCORRECT_DATA, 'getTitleNamesXML: value of $orderBy must be string or array');
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
                . "JOIN `".MECCANO_TPREF."_core_plugins_installed` `p` "
                . "ON `p`.`id`=`s`.`plugid` "
                . "WHERE `p`.`name`='$plugin' AND `s`.`section`='$section' "
                . "ORDER BY `$orderBy` $direct ;");
        if (self::$dbLink->errno) {
            self::setError(ERROR_NOT_EXECUTED, 'getTitleNamesXML: unable to get names -> '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setError(ERROR_NOT_FOUND, 'getTitleNamesXML: unable to find defined section');
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
