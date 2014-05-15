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
            self::$dblink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                    . "WHERE `id`=$funcId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy | '.self::$dblink->error);
                return FALSE;
            }
            self::$dblink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                    . "WHERE `funcid`=$funcId ;");
            if (self::$dblink->errno) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t delete policy | '.self::$dblink->error);
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
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install policy | '.self::$dblink->error);
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
                    self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('installPolicy: can\'t install policy | '.self::$dblink->error);
                    return FALSE;
                }
            }
        }
        return TRUE;
    }
    
}
