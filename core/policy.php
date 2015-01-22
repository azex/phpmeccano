<?php

namespace core;

class Policy {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dbLink; // database link
    
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
    
    public static function installPolicy($policy) {
        self::$errid = 0;        self::$errexp = '';
        if (!$policy->relaxNGValidate(MECCANO_CORE_DIR.'/policy/schema-v01.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('createPlicy: incorrect policy structure');
            return FALSE;
        }
        $pluginName = $policy->getElementsByTagName('plugin')->item(0)->getAttribute('name');
        $funcNames = $policy->getElementsByTagName('function');
        $functions = array();
        foreach ($funcNames as $func) {
            $functions[] = $func->nodeValue;
        }
        $qDbFuncs = self::$dbLink->query("SELECT `id`, `func` "
                . "FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                . "WHERE `name`='$pluginName' ;");
        $dbFuncs = array();
        while ($row = $qDbFuncs->fetch_row()) {
            $dbFuncs[$row[0]] = $row[1];
        }
        $oldFuncs = array_keys(array_diff($dbFuncs, $functions));
        $newFuncs = array_diff($functions, $dbFuncs);
        // deleting of outdated policies
        foreach ($oldFuncs as $funcId) {
            self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_langman_policy_description` "
                    . "WHERE `policyid`=$funcId ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy description | '.self::$dbLink->error);
                return FALSE;
            }
            self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                    . "WHERE `funcid`=$funcId ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete group policy | '.self::$dbLink->error);
                return FALSE;
            }
            self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_nosession` "
                    . "WHERE `funcid`=$funcId ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy for inactive session | '.self::$dbLink->error);
                return FALSE;
            }
            self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                    . "WHERE `id`=$funcId ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy from summary list | '.self::$dbLink->error);
                return FALSE;
            }
        }
        // getting of the group identifiers
        $qGroupIds = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        $groupIds = array();
        while ($row = $qGroupIds->fetch_row()) {
            $groupIds[] = $row[0];
        }
        // installing of new policies
        foreach ($newFuncs as $func) {
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_summary_list` (`name`, `func`) "
                    . "VALUES ('$pluginName', '$func') ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install policy to summary list | '.self::$dbLink->error);
                return FALSE;
            }
            $insertId = self::$dbLink->insert_id;
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_nosession` (`funcid`, `access`) "
                    . "VALUES ($insertId, 0) ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install policy for inactive session | '.self::$dbLink->error);
                return FALSE;
            }
            foreach ($groupIds as $groupId) {
                if ($groupId == 1) {
                    $access = 1;
                }
                else {
                    $access = 0;
                }
                self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`, `access`) "
                        . "VALUES ($groupId, $insertId, $access) ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install group policy | '.self::$dbLink->error);
                    return FALSE;
                }
            }
        }
        return TRUE;
    }
    
    public static function delPolicy($name) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPolicy: name must be string');
            return FALSE;
        }
        $plugName = self::$dbLink->real_escape_string($name);
        $queries = array(
            "DELETE `d` FROM `".MECCANO_TPREF."_core_langman_policy_description` `d` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`d`.`policyid` "
            . "WHERE `s`.`name`='$plugName' ;",
            "DELETE `a` FROM `".MECCANO_TPREF."_core_policy_access` `a` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`a`.`funcid` "
            . "WHERE `s`.`name`='$plugName' ;",
            "DELETE `n` FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`n`.`funcid` "
            . "WHERE `s`.`name`='$plugName' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
            . "WHERE `name`='$plugName' ;");
        foreach ($queries as $value) {
            self::$dbLink->query($value);
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delPolicy: something went wrong | '.self::$dbLink->error);
                return FALSE;
            }
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('delPolicy: defined name doesn\'t exist');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addGroup($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addGroup: id must be integer');
            return FALSE;
        }
        $qIsGroup = self::$dbLink->query("SELECT `g`.`id` FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "WHERE `g`.`id`=$id "
                . "AND NOT EXISTS ("
                . "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                . "WHERE `a`.`groupid`=$id LIMIT 1) ;");
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_ALREADY_EXISTS);        self::setErrExp('addGroup: defined group doesn\'t exist or already was added');
            return FALSE;
        }
        $qDbFuncs = self::$dbLink->query("SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_policy_summary_list` ;");
        while (list($row) = $qDbFuncs->fetch_row()) {
            self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`) "
                    . "VALUES ($id, $row) ;");
            if (self::$dbLink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('addPolicy: can\'t add policy | '.self::$dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public static function delGroup($id) {
        self::$errid = 0;        self::$errexp = '';
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delGroup: id must be integer');
            return FALSE;
        }
        self::$dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                . "WHERE `groupid`=$id ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('addPolicy: can\'t delete policy | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);        self::setErrExp('delGroup: defined group doesn\'t exist');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function funcAccess($name, $func, $groupid, $access = TRUE) {
        self::$errid = 0;        self::$errexp = '';
        if (!(is_integer($groupid) || is_bool($groupid)) || !pregPlugin($name) || !pregPlugin($func)) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: incorect type of incoming parameters');
            return FALSE;
        }
        if (is_bool($groupid)) {
            if ($access) {
                $access = 1;
            }
            else {
                $access = 0;
            }
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_nosession` `n` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `n`.`funcid`=`s`.`id` "
                    . "SET `n`.`access`=$access "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$name' ;");
        }
        elseif ($access) {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=1 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$name' "
                    . "AND `a`.`groupid`=$groupid ;");
        }
        elseif (!$access && $groupid!=1) {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=0 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$name' "
                    . "AND `a`.`groupid`=$groupid ;");
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('funcAccess: access can\'t be disabled for system group');
            return FALSE;
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: access wasn\'t changed | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('funcAccess: plugin name, function or group don\'t exist or access flag wasn\'t changed');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function checkAccess($name, $func) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($name) || !pregPlugin($func)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('checkAccess: check incoming parameters');
            return FALSE;
        }
        if (isset($_SESSION[AUTH_USER_ID])) {
            $qAccess = self::$dbLink->query("SELECT `a`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                    . "ON `a`.`groupid`=`g`.`id` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `g`.`id`=`u`.`groupid` "
                    . "WHERE `u`.`id`=".$_SESSION[AUTH_USER_ID]." "
                    . "AND `s`.`name`='$name' "
                    . "AND `s`.`func`='$func' "
                    . "LIMIT 1 ;");
        }
        else {
            $qAccess = self::$dbLink->query("SELECT `n`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `n`.`funcid`=`s`.`id` "
                    . "WHERE `s`.`name`='$name' "
                    . "AND `s`.`func`='$func' "
                    . "LIMIT 1 ;");
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('checkAccess: something went wrong | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('checkAccess: policy was not found');
            return FALSE;
        }
        list($access) = $qAccess->fetch_row();
        return (int) $access;
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
    
    public static function groupPolicyList($plugin, $groupId, $code = NULL) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !(is_integer($groupId) || is_bool($groupId)) || !(is_null($code) || pregLang($code))) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('policyList: incorect type of incoming parameters');
            return FALSE;
        }
        if (is_null($code)) {
            $code = MECCANO_DEF_LANG;
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
    
}
