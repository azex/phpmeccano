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
    
    public static function delPolicy($name) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPolicy: name must be string');
            return FALSE;
        }
        $plugName = self::$dbLink->real_escape_string($name);
        $queries = array(
            "DELETE `d` FROM `".MECCANO_TPREF."_core_policy_descriptions` `d` "
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
            self::setErrId(ERROR_ALREADY_EXISTS);        self::setErrExp('addGroup: defined group is not found or already was added');
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
            self::setErrId(ERROR_NOT_FOUND);        self::setErrExp('delGroup: defined group is not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function funcAccess($plugin, $func, $groupid, $access = TRUE) {
        self::$errid = 0;        self::$errexp = '';
        if (!(is_integer($groupid) || is_bool($groupid)) || !pregPlugin($plugin) || !pregPlugin($func)) {
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
                    . "AND  `s`.`name`='$plugin' ;");
        }
        elseif ($access) {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=1 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$plugin' "
                    . "AND `a`.`groupid`=$groupid ;");
        }
        elseif (!$access && $groupid!=1) {
            self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=0 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$plugin' "
                    . "AND `a`.`groupid`=$groupid ;");
        }
        else {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('funcAccess: impossible to disable access for system group');
            return FALSE;
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: unable to change access | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('funcAccess: plugin name, function or group does not exist or access flag was not changed');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function checkAccess($plugin, $func) {
        self::$errid = 0;        self::$errexp = '';
        if (!pregPlugin($plugin) || !pregPlugin($func)) {
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
                    . "AND `s`.`name`='$plugin' "
                    . "AND `s`.`func`='$func' "
                    . "LIMIT 1 ;");
        }
        else {
            $qAccess = self::$dbLink->query("SELECT `n`.`access` "
                    . "FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `n`.`funcid`=`s`.`id` "
                    . "WHERE `s`.`name`='$plugin' "
                    . "AND `s`.`func`='$func' "
                    . "LIMIT 1 ;");
        }
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('checkAccess: something went wrong | '.self::$dbLink->error);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('checkAccess: policy is not found');
            return FALSE;
        }
        list($access) = $qAccess->fetch_row();
        return (int) $access;
    }
    
    public static function install(\DOMDocument $policy) {
        self::$errid = 0;        self::$errexp = '';
        if (!@$policy->relaxNGValidate(MECCANO_CORE_DIR.'/policy/policy-schema-v01.rng')) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('install: incorrect structure of incoming data');
            return FALSE;
        }
        $pluginName = $policy->getElementsByTagName('policy')->item(0)->getAttribute('plugin');
        // check whether plugin is installed
        $qPlugin = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$pluginName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp("install: unable to check whether the plugin [$pluginName] is installed | ".self::$dbLink->errno);
            return FALSE;
        }
        if (!self::$dbLink->affected_rows) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("install: plugin [$pluginName] is not installed");
            return FALSE;
        }
        // get list of available languages
        $qAvaiLang = self::$dbLink->query("SELECT `code`, `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('install: unable to get list of available languages: '.self::$dbLink->error);
            return FALSE;
        }
        // avaiable languages
        $avLangIds = array();
        $avLangCodes = array();
        while ($row = $qAvaiLang->fetch_row()) {
            $avLangIds[$row[0]] = $row[1];
            $avLangCodes[] = $row[0];
        }
        $incomingPolicy = array();
        $funcNodes = $policy->getElementsByTagName('function');
        foreach ($funcNodes as $funcNode) {
            $funcName = $funcNode->getAttribute('name');
            $incomingPolicy[$funcName] = array();
            $langNodes = $funcNode->getElementsByTagName('description');
            foreach ($langNodes as $langNode){
                $code = $langNode->getAttribute('code');
                if (isset($avLangIds[$code])) {
                    $incomingPolicy[$funcName][$code]['short'] = $langNode->getElementsByTagName('short')->item(0)->nodeValue;
                    $incomingPolicy[$funcName][$code]['detailed'] = $langNode->getElementsByTagName('detailed')->item(0)->nodeValue;
                }
            }
        }
        // get installed policies of the plugin
        $qPolicy = self::$dbLink->query("SELECT `func`, `id` "
                . "FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                . "WHERE `name`='$pluginName' ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('installEvents: unable to get installed events | '.self::$dbLink->error);
            return FALSE;
        }
        $installedPolicy = array();
        while ($row = $qPolicy->fetch_row()) {
            $installedPolicy[$row[0]] = $row[1];
        }
        // delete outdated policies
        $outdatedPolicy = array_diff(array_keys($installedPolicy), array_keys($incomingPolicy));
        foreach ($outdatedPolicy as $func) {
            $funcId = $installedPolicy[$func];
            $sql = array(
                "DELETE FROM `".MECCANO_TPREF."_core_policy_descriptions` "
                . "WHERE `policyid`=$funcId ;",
                "DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                . "WHERE `funcid`=$funcId ;",
                "DELETE FROM `".MECCANO_TPREF."_core_policy_nosession` "
                . "WHERE `funcid`=$funcId ;",
                "DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                . "WHERE `id`=$funcId ;"
            );
            foreach ($sql as $dQuery) {
                self::$dbLink->query($dQuery);
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("install: unable to delete outdated policy | ".self::$dbLink->error);
                    return FALSE;
                }
            }
        }
        // getting of group identifiers
        $qGroupIds = self::$dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        if (self::$dbLink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('install: unable to get group identifiers | '.self::$dbLink->error);
            return FALSE;
        }
        $groupIds = array();
        while ($row = $qGroupIds->fetch_row()) {
            $groupIds[] = $row[0];
        }
        // install/update policies
        foreach ($incomingPolicy as $funcName => $descriptions) {
            $missingCodes = array_diff($avLangCodes, array_keys($descriptions));
            if ($missingCodes) {
                foreach ($missingCodes as $code) {
                    $descriptions[$code]['short'] = "$funcName";
                    $descriptions[$code]['detailed'] = "$funcName";
                }
            }
            // update policy
            if (isset($installedPolicy[$funcName])) {
                $funcId = $installedPolicy[$funcName];
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $updateShort = self::$dbLink->real_escape_string($desc['short']);
                    $updateDetailed = self::$dbLink->real_escape_string($desc['detailed']);
                    // update policy description
                    self::$dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_descriptions` "
                            . "SET `short`='$updateShort', `detailed`='$updateDetailed' "
                            . "WHERE `policyid`=$funcId "
                            . "AND `codeid`=$codeId ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('install: unable to update policy description | '.self::$dbLink->error);
                        return FALSE;
                    }
                }
            }
            // install policy
            else {
                // create record in the summary list
                self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_summary_list` (`name`, `func`) "
                        . "VALUES ('$pluginName', '$funcName') ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('install: unable to add policy into the summary list | '.self::$dbLink->error);
                    return FALSE;
                }
                $insertId = self::$dbLink->insert_id;
                // policy for the inactive session (non-authorized user)
                self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_nosession` (`funcid`, `access`) "
                        . "VALUES ($insertId, 0) ;");
                if (self::$dbLink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('install: unable to create policy for the inactive session | '.self::$dbLink->error);
                    return FALSE;
                }
                // policy for the groups
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
                        self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('install: unable to install group policy | '.self::$dbLink->error);
                        return FALSE;
                    }
                }
                // create policy description
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $insertShort = self::$dbLink->real_escape_string($desc['short']);
                    $insertDetailed = self::$dbLink->real_escape_string($desc['detailed']);
                    self::$dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_descriptions` "
                            . "(`codeid`, `policyid`, `short`, `detailed`) "
                            . "VALUES ($codeId, $insertId, '$insertShort', '$insertDetailed') ;");
                    if (self::$dbLink->errno) {
                        self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp('install: unable to install policy description | '.self::$dbLink->error);
                        return FALSE;
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
                    . "JOIN `".MECCANO_TPREF."_core_policy_descriptions` `d` "
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
                    . "JOIN `".MECCANO_TPREF."_core_policy_descriptions` `d` "
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
                . "FROM `".MECCANO_TPREF."_core_policy_descriptions` "
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
