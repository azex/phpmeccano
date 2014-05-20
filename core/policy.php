<?php

namespace core;

class Policy {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    private static $dblink; // database link
    
    public function __construct($dblink = FALSE) {
        self::$dblink = $dblink;
    }
    
    public static function setDbLink($dblink) {
        self::$dblink = $dblink;
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
        $qDbFuncs = self::$dblink->query("SELECT `id`, `func` "
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
            self::$dblink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                    . "WHERE `funcid`=$funcId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete group policy | '.self::$dblink->error);
                return FALSE;
            }
            self::$dblink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                    . "WHERE `id`=$funcId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy from summary list | '.self::$dblink->error);
                return FALSE;
            }
        }
        // getting of the group identifiers
        $qGroupIds = self::$dblink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        $groupIds = array();
        while ($row = $qGroupIds->fetch_row()) {
            $groupIds[] = $row[0];
        }
        // installing of new policies
        foreach ($newFuncs as $func) {
            self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_summary_list` (`name`, `func`) "
                    . "VALUES ('$pluginName', '$func') ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install policy to summary list | '.self::$dblink->error);
                return FALSE;
            }
            $insertId = self::$dblink->insert_id;
            foreach ($groupIds as $groupId) {
                if ($groupId == 1) {
                    $access = 1;
                }
                else {
                    $access = 0;
                }
                self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`, `access`) "
                        . "VALUES ($groupId, $insertId, $access) ;");
                if (self::$dblink->errno) {
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install group policy | '.self::$dblink->error);
                    return FALSE;
                }
            }
        }
        return TRUE;
    }
    
    public static function delPolicy($name) {
        if (!is_string($name)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPolicy: name must be string');
            return FALSE;
        }
        $plugName = self::$dblink->real_escape_string($name);
        $queries = array(
            "DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
            . "WHERE `funcid` "
            . "IN (SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_policy_summary_list` "
            . "WHERE `name`='$plugName') ;",
            "DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
            . "WHERE `name`='$plugName' ;");
        foreach ($queries as $value) {
            self::$dblink->query($value);
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('delPolicy: something went wrong | '.self::$dblink->error);
                return FALSE;
            }
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delPolicy: defined name doesn\'t exist');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function addGroup($id) {
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('addGroup: id must be integer');
            return FALSE;
        }
        $qIsGroup = self::$dblink->query("SELECT `g`.`id` FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "WHERE `g`.`id`=$id "
                . "AND NOT EXISTS ("
                . "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                . "WHERE `a`.`groupid`=$id LIMIT 1) ;");
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);        self::setErrExp('addGroup: defined group doesn\'t exist or already was added');
            return FALSE;
        }
        $qDbFuncs = self::$dblink->query("SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_policy_summary_list` ;");
        while (list($row) = $qDbFuncs->fetch_row()) {
            self::$dblink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`) "
                    . "VALUES ($id, $row) ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('addPolicy: can\'t add policy | '.self::$dblink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public static function delGroup($id) {
        if (!is_integer($id)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('delGroup: id must be integer');
            return FALSE;
        }
        self::$dblink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                . "WHERE `groupid`=$id ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('addPolicy: can\'t delete policy | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);        self::setErrExp('delGroup: defined group doesn\'t exist');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function funcAccess($id, $groupid, $access = TRUE) {
        if (!is_integer($id) || !is_integer($groupid)) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: incorect type of incoming parameters');
            return FALSE;
        }
        if ($access) {
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` "
                    . "SET `access`=1 "
                    . "WHERE `funcid`=$id "
                    . "AND `groupid`=$groupid ;");
        }
        elseif (!$access && $groupid!=1) {
            self::$dblink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` "
                    . "SET `access`=0 "
                    . "WHERE `funcid`=$id "
                    . "AND `groupid`=$groupid ;");
        }
        else {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: access can\'t be disabled for system group');
            return FALSE;
        }
        if (self::$dblink->errno) {
            self::setErrId(ERROR_SYSTEM_INTERVENTION);            self::setErrExp('funcAccess: access wasn\'t changed | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('funcAccess: function or group don\'t exist or access flag wasn\'t changed');
            return FALSE;
        }
        return TRUE;
    }
    
    public static function policyList($name, $groupid) {
        if (!is_string($name) || !is_integer($groupid)) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('policyList: incorect type of incoming parameters');
            return FALSE;
        }
        $plugName =  self::$dblink->real_escape_string($name);
        $qList = self::$dblink->query("SELECT `s`.`func`, `a`.`access` "
                . "FROM `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                . "JOIN `".MECCANO_TPREF."_core_policy_access` `a` "
                . "ON `s`.`id`=`a`.`funcid` "
                . "WHERE `s`.`name`='$plugName' "
                . "AND `a`.`groupid`=$groupid ;");
        if (self::$dblink->errno) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp('policyList: something went wrong | '.self::$dblink->error);
            return FALSE;
        }
        if (!self::$dblink->affected_rows) {
            self::setErrId(ERROR_INCORRECT_DATA);            self::setErrExp('policyList: name or group don\'t exist');
            return FALSE;
        }
        return $qList;
    }
}
