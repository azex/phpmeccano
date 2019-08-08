<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Core module [policy.php].
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

require_once MECCANO_CORE_DIR.'/extclass.php';

interface intPolicy {
    function __construct(\mysqli $dbLink);
    public function delPolicy($plugin);
    public function setFuncAccess($plugin, $func, $groupId, $access = TRUE); // old name [funcAccess]
    public function installPolicy(\DOMDocument $policy, $validate = TRUE); // old name [install]
    public function groupPolicyList($plugin, $groupId, $code = MECCANO_DEF_LANG);
    public function getPolicyDescById($id);
}

class Policy extends ServiceMethods implements intPolicy {
    protected $dbLink; // database link
    
    public function __construct(\mysqli $dbLink) {
        $this->dbLink = $dbLink;
    }
    
    public function delPolicy($plugin) {
        $this->zeroizeError();
        if (!pregPlugin($plugin)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delPolicy: incorrect plugin name');
            return FALSE;
        }
        // checking if plugin exists
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$plugin' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delPolicy: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delPolicy: unable to find plugin');
            return FALSE;
        }
        $queries = array(
            "DELETE `d` FROM `".MECCANO_TPREF."_core_policy_descriptions` `d` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`d`.`policyid` "
            . "WHERE `s`.`name`='$plugin' ;",
            "DELETE `a` FROM `".MECCANO_TPREF."_core_policy_access` `a` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`a`.`funcid` "
            . "WHERE `s`.`name`='$plugin' ;",
            "DELETE `n` FROM `".MECCANO_TPREF."_core_policy_nosession` `n` "
            . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
            . "ON `s`.`id`=`n`.`funcid` "
            . "WHERE `s`.`name`='$plugin' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_policy_summary_list` "
            . "WHERE `name`='$plugin' ;");
        foreach ($queries as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delPolicy: something went wrong -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public function setFuncAccess($plugin, $func, $groupId, $access = TRUE) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'policy_func_access')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setFuncAccess: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($groupId) || !pregPlugin($plugin) || !pregPlugin($func)) {
            $this->setError(ERROR_NOT_EXECUTED, 'setFuncAccess: incorect type of incoming parameters');
            return FALSE;
        }
        if (!$groupId) {
            if ($access) {
                $access = 1;
            }
            else {
                $access = 0;
            }
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_nosession` `n` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `n`.`funcid`=`s`.`id` "
                    . "SET `n`.`access`=$access "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$plugin' ;");
        }
        elseif ($access) {
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=1 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$plugin' "
                    . "AND `a`.`groupid`=$groupId ;");
        }
        elseif (!$access && $groupId!=1) {
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_access` `a` "
                    . "JOIN `".MECCANO_TPREF."_core_policy_summary_list` `s` "
                    . "ON `a`.`funcid`=`s`.`id` "
                    . "SET `a`.`access`=0 "
                    . "WHERE `s`.`func`='$func' "
                    . "AND  `s`.`name`='$plugin' "
                    . "AND `a`.`groupid`=$groupId ;");
        }
        else {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'setFuncAccess: impossible to disable access for system group');
            return FALSE;
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setFuncAccess: unable to change access -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'setFuncAccess: plugin name, function or group does not exist or access flag was not changed');
            return FALSE;
        }
        return TRUE;
    }
    
    public function installPolicy(\DOMDocument $policy, $validate = TRUE) {
        $this->zeroizeError();
        if ($validate && !@$policy->relaxNGValidate(MECCANO_CORE_DIR.'/validation-schemas/policy-v01.rng')) {
            $this->setError(ERROR_INCORRECT_DATA, 'installPolicy: incorrect structure of incoming data');
            return FALSE;
        }
        $pluginName = $policy->getElementsByTagName('policy')->item(0)->getAttribute('plugin');
        // check whether plugin is installed
        $qPlugin = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_plugins_installed` "
                . "WHERE `name`='$pluginName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "installPolicy: unable to check whether the plugin [$pluginName] is installed -> ".$this->dbLink->errno);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "installPolicy: plugin [$pluginName] is not installed");
            return FALSE;
        }
        // get list of available languages
        $qAvaiLang = $this->dbLink->query("SELECT `code`, `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to get list of available languages: '.$this->dbLink->error);
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
        $defaultRules = array();
        $funcNodes = $policy->getElementsByTagName('function');
        foreach ($funcNodes as $funcNode) {
            $funcName = $funcNode->getAttribute('name');
            $nonAuthRule = $funcNode->getAttribute('nonauth');
            $authRule = $funcNode->getAttribute('auth');
            $defaultRules[$funcName] = array((int) $nonAuthRule, (int) $authRule);
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
        $qPolicy = $this->dbLink->query("SELECT `func`, `id` "
                . "FROM `".MECCANO_TPREF."_core_policy_summary_list` "
                . "WHERE `name`='$pluginName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installEvents: unable to get installed events -> '.$this->dbLink->error);
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
                $this->dbLink->query($dQuery);
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, "installPolicy: unable to delete outdated policy -> ".$this->dbLink->error);
                    return FALSE;
                }
            }
        }
        // getting of group identifiers
        $qGroupIds = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to get group identifiers -> '.$this->dbLink->error);
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
                    $updateShort = $this->dbLink->real_escape_string($desc['short']);
                    $updateDetailed = $this->dbLink->real_escape_string($desc['detailed']);
                    // update policy description
                    $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_policy_descriptions` "
                            . "SET `short`='$updateShort', `detailed`='$updateDetailed' "
                            . "WHERE `policyid`=$funcId "
                            . "AND `codeid`=$codeId ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to update policy description -> '.$this->dbLink->error);
                        return FALSE;
                    }
                }
            }
            // install policy
            else {
                // create record in the summary list
                $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_summary_list` (`name`, `func`) "
                        . "VALUES ('$pluginName', '$funcName') ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to add policy into the summary list -> '.$this->dbLink->error);
                    return FALSE;
                }
                $insertId = $this->dbLink->insert_id;
                // get default rules
                list($nonAuthRule, $authRule) = $defaultRules[$funcName];
                // policy for the inactive session (non-authorized user)
                $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_nosession` (`funcid`, `access`) "
                        . "VALUES ($insertId, $nonAuthRule) ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to create policy for the inactive session -> '.$this->dbLink->error);
                    return FALSE;
                }
                // policy for the groups
                foreach ($groupIds as $groupId) {
                    if ($groupId == 1) {
                        $access = 1;
                    }
                    else {
                        $access = $authRule;
                    }
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`, `access`) "
                            . "VALUES ($groupId, $insertId, $access) ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to install group policy -> '.$this->dbLink->error);
                        return FALSE;
                    }
                }
                // create policy description
                foreach ($descriptions as $inCode => $desc) {
                    $codeId = $avLangIds[$inCode];
                    $insertShort = $this->dbLink->real_escape_string($desc['short']);
                    $insertDetailed = $this->dbLink->real_escape_string($desc['detailed']);
                    $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_descriptions` "
                            . "(`codeid`, `policyid`, `short`, `detailed`) "
                            . "VALUES ($codeId, $insertId, '$insertShort', '$insertDetailed') ;");
                    if ($this->dbLink->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'installPolicy: unable to install policy description -> '.$this->dbLink->error);
                        return FALSE;
                    }
                }
                
            }
        }
        return TRUE;
    }
    
    public function groupPolicyList($plugin, $groupId, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'policy_list_about')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "groupPolicyList: restricted by the policy");
            return FALSE;
        }
        if (!pregPlugin($plugin) || !(is_integer($groupId) || is_bool($groupId)) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, 'groupPolicyList: incorect incoming parameters');
            return FALSE;
        }
        if (!$groupId) {
            $qList = $this->dbLink->query("SELECT `d`.`id`, `d`.`short`, `s`.`func`, `n`.`access` "
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
            $qList = $this->dbLink->query("SELECT `d`.`id`, `d`.`short`, `s`.`func`, `a`.`access` "
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
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'groupPolicyList: something went wrong -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'groupPolicyList: not found');
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $policyNode = $xml->createElement('policy');
            $xml->appendChild($policyNode);
            $attr_plugin = $xml->createAttribute('plugin');
            $attr_plugin->value = $plugin;
            $policyNode->appendChild($attr_plugin);
            $attr_group = $xml->createAttribute('group');
            $attr_group->value = $groupId;
            $policyNode->appendChild($attr_group);
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
        else {
            $policyNode = array();
            $policyNode['plugin'] = $plugin;
            $policyNode['group'] = $groupId;
            $policyNode['functions'] = array();
            while ($row = $qList->fetch_row()) {
                $policyNode['functions'][] = array(
                    'id' => (int) $row[0],
                    'short' => $row[1],
                    'name' => $row[2],
                    'access' => (int) $row[3]
                );
            }
            if ($this->outputType == 'array') {
                return $policyNode;
            }
            else {
                return json_encode($policyNode);
            }
        }
    }
    
    public function getPolicyDescById($id) {
        $this->zeroizeError();
        if ($this->usePolicy && !$this->checkFuncAccess('core', 'policy_list_about')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getPolicyDescById: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($id)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getPolicyDescById: identifier must be integer');
            return FALSE;
        }
        $qDesc = $this->dbLink->query("SELECT `short`, `detailed` "
                . "FROM `".MECCANO_TPREF."_core_policy_descriptions` "
                . "WHERE `id`=$id ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getPolicyDescById: unable to get description -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getPolicyDescById: description was not found');
            return FALSE;
        }
        list($short, $detailed) = $qDesc->fetch_row();
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $polycyNode = $xml->createElement('policy');
            $xml->appendChild($polycyNode);
            $idNode = $xml->createElement('id', $id);
            $shortNode = $xml->createElement('short', $short);
            $detailedNode = $xml->createElement('detailed', $detailed);
            $polycyNode->appendChild($idNode);
            $polycyNode->appendChild($shortNode);
            $polycyNode->appendChild($detailedNode);
            return $xml;
        }
        else {
            if ($this->outputType == 'json') {
                return json_encode(array('id' => $id, 'short' => $short, 'detailed' => $detailed));
            }
            else {
                return array('id' => $id, 'short' => $short, 'detailed' => $detailed);
            }
        }
        
    }
    
}
