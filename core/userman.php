<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Core module [userman.php].
 *     Copyright (C) 2015-2016  Alexei Muzarov
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

loadPHP('extclass');

interface intUserMan {
    public function __construct(\mysqli $dbLink);
    public function createGroup($groupName, $description, $log = TRUE);
    public function groupStatus($groupId, $active, $log = TRUE);
    public function groupExists($groupName);
    public function moveGroupTo($groupId, $destId, $log = TRUE);
    public function aboutGroup($groupId);
    public function setGroupName($groupId, $groupName, $log = TRUE);
    public function setGroupDesc($groupId, $description, $log = TRUE);
    public function delGroup($groupId, $log = TRUE);
    public function sumGroups($rpp = 20);
    public function getGroups($pageNumber, $totalGroups, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public function getAllGroups($orderBy = array('id'), $ascent = FALSE);
    public function createUser($username, $password, $email, $groupId, $active = TRUE, $langCode = MECCANO_DEF_LANG, $log = TRUE);
    public function userExists($username);
    public function mailExists($email);
    public function userStatus($userId, $active, $log = TRUE);
    public function moveUserTo($userId, $destId, $log = TRUE);
    public function delUser($userId, $log = TRUE);
    public function aboutUser($userId);
    public function userPasswords($userId);
    public function createPassword($userId, $description, $length = 8, $underline = TRUE, $minus = FALSE, $special = FALSE, $log = TRUE);
    public function addPassword($userId, $password, $description='', $log = TRUE);
    public function delPassword($passwId, $userId, $log = TRUE);
    public function setPassword($passwId, $userId, $password, $log = TRUE);
    public function setUserName($userId, $username, $log = TRUE);
    public function setUserMail($userId, $email, $log = TRUE);
    public function setFullName($userId, $name, $log = TRUE);
    public function changePassword($passwId, $userId, $oldPassw, $newPassw, $log = TRUE);
    public function sumUsers($rpp = 20);
    public function getUsers($pageNumber, $totalUsers, $rpp = 20, $orderBy = array('id'), $ascent = FALSE);
    public function getAllUsers($orderBy = array('id'), $ascent = FALSE);
    public function setUserLang($userId, $code = MECCANO_DEF_LANG);
    public function enableDoubleAuth($passwId, $userId);
}

class UserMan extends ServiceMethods implements intUserMan{
    
    public function __construct(\mysqli $dbLink) {
        $this->dbLink = $dbLink;
    }
    
    //group methods
    public function createGroup($groupName, $description, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_create_group')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "createGroup: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'createGroup: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!pregGName($groupName) || !is_string($description)) {
            $this->setError(ERROR_NOT_EXECUTED, 'createGroup: incorect type of incoming parameters');
            return FALSE;
        }
        $groupName = $this->dbLink->real_escape_string($groupName);
        $description = $this->dbLink->real_escape_string($description);
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_groups` (`groupname`, `description`) "
                . "VALUES ('$groupName', '$description') ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createGroup: group was not created -> '.$this->dbLink->error);
            return FALSE;
        }
        $groupId = $this->dbLink->insert_id;
        if (!$this->addPolicyToGroup($groupId)) {
            $this->setError(ERROR_NOT_EXECUTED, "createGroup -> ".$this->errExp());
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_create_group', "$groupName; ID: $groupId")) {
            $this->setError(ERROR_NOT_CRITICAL, "createGroup -> ".$this->errExp());
        }
        return (int) $groupId;
    }
    
    // method came from core module policy.php
    // old name [addGroup]
    private function addPolicyToGroup($id) {
        $this->zeroizeError();
        if (!is_integer($id)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addPolicyToGroup: id must be integer');
            return FALSE;
        }
        $qIsGroup = $this->dbLink->query("SELECT `g`.`id` FROM `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "WHERE `g`.`id`=$id "
                . "AND NOT EXISTS ("
                . "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_policy_access` `a` "
                . "WHERE `a`.`groupid`=$id LIMIT 1) ;");
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'addPolicyToGroup: defined group is not found or already was added');
            return FALSE;
        }
        $qDbFuncs = $this->dbLink->query("SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_policy_summary_list` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addPolicy: unable to get policies -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($id == 1) {
            $access = 1;
        }
        else {
            $access = 0;
        }
        while (list($row) = $qDbFuncs->fetch_row()) {
            $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_policy_access` (`groupid`, `funcid`, `access`) "
                    . "VALUES ($id, $row, $access) ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'addPolicy: unable to add policy -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public function groupStatus($groupId, $active, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_group_status')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "groupStatus: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'groupStatus: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($groupId) || $groupId<1) {
            $this->setError(ERROR_INCORRECT_DATA, 'groupStatus: group id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $groupId>1) {
            $active = 0;
        }
        else {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'groupStatus: system group cannot be disabled');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `active`=$active "
                . "WHERE `id`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'groupStatus: status was not changed -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'groupStatus: incorrect group status or group does not exist');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_group_status', "ID: $groupId; status: $active")) {
            $this->setError(ERROR_NOT_CRITICAL, "groupStatus -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function groupExists($groupName) {
        $this->zeroizeError();
        if (!pregGName($groupName)) {
            $this->setError(ERROR_INCORRECT_DATA, 'groupExists: incorrect group name');
            return FALSE;
        }
        $qId = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `groupname`='$groupName' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'groupExists: unable to check group existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public function moveGroupTo($groupId, $destId, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_move_group')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "moveGroupTo: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'moveGroupTo: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_integer($destId) || $destId<1 || $destId == $groupId) {
            $this->setError(ERROR_INCORRECT_DATA, 'moveGroupTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($groupId == 1) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'moveGroupTo: unable to move system group');
            return FALSE;
        }
        $this->dbLink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'moveGroupTo: unable to check destination group existence |'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'moveGroupTo: no one user of the group was not moved');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `groupid`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'moveGroupTo: unable to move users into another group |'.$this->dbLink->error);
            return FALSE;
        }
        $movedUsers = (int) $this->dbLink->affected_rows;
        if ($log && !$this->newLogRecord('core', 'userman_move_group', "∑=$movedUsers; ID:$groupId -> ID:$destId")) {
            $this->setError(ERROR_NOT_CRITICAL, "moveGroupTo -> ".$this->errExp());
        }
        return $movedUsers;
    }
    
    public function aboutGroup($groupId) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_get_groups')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "aboutGroup: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($groupId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'aboutGroup: identifier must be integer');
            return FALSE;
        }
        $qAbout = $this->dbLink->query("SELECT `groupname`, `description`, `creationtime`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'aboutGroup: something went wrong -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'aboutGroup: group not found');
            return FALSE;
        }
        $qSum = $this->dbLink->query("SELECT COUNT(`id`) "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `groupid`=$groupId ;");
        $about = $qAbout->fetch_row();
        $sum = $qSum->fetch_row();
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $aboutNode = $xml->createElement('group');
            $xml->appendChild($aboutNode);
            $aboutNode->appendChild($xml->createElement('id', $groupId));
            $aboutNode->appendChild($xml->createElement('name', $about[0]));
            $aboutNode->appendChild($xml->createElement('description', $about[1]));
            $aboutNode->appendChild($xml->createElement('time', $about[2]));
            $aboutNode->appendChild($xml->createElement('active', $about[3]));
            $aboutNode->appendChild($xml->createElement('usum', $sum[0]));
            return $xml;
        }
        else {
            $aboutNode = array(
                            'id' => $groupId,
                            'name' => $about[0],
                            'description' => $about[1],
                            'time' => $about[2],
                            'active' => (int) $about[3],
                            'usum' => (int) $sum[0]
                            );
            if ($this->outputType == 'array') {
                return $aboutNode;
            }
            else {
                return json_encode($aboutNode);
            }
        }
    }
    
    public function setGroupName($groupId, $groupName, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_set_about_group')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setGroupName: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'setGroupName: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($groupId) || !pregGName($groupName)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setGroupName: incorrect incoming parameters');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `groupname`='$groupName' "
                . "WHERE `id`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setGroupName: unable to set groupname -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setGroupName: group not found or group name already exists');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_group_name', "ID: $groupId; $groupName")) {
            $this->setError(ERROR_NOT_CRITICAL, "setGroupName -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function setGroupDesc($groupId, $description, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_set_about_group')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setGroupDesc: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'setGroupDesc: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($groupId) || !is_string($description)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setGroupDesc: incorrect incoming parameters');
            return FALSE;
        }
        $description = $this->dbLink->real_escape_string($description);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_groups` "
                . "SET `description`='$description' "
                . "WHERE `id`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setGroupDesc: unable to set description -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setGroupDesc: group not found or description was repeated');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_group_desc', "ID: $groupId")) {
            $this->setError(ERROR_NOT_CRITICAL, "setGroupDesc -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function delGroup($groupId, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_del_group')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delGroup: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($groupId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delGroup: identifier must be integer');
            return FALSE;
        }
        $qUsers = $this->dbLink->query("SELECT COUNT(`id`) "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `groupid`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delGroup: unable to check existence of users in the group -> '.$this->dbLink->error);
            return FALSE;
        }
        $users = $qUsers->fetch_row();
        if ($users[0]) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'delGroup: the group contains users');
            return FALSE;
        }
        if (!$this->delPolicyFromGroup($groupId) && !in_array($this->errId(), array(ERROR_NOT_FOUND, ''))) {
            $this->setError(ERROR_INCORRECT_DATA, $this->errExp());
            return FALSE;
        }
        $sql = array(
            "SELECT `groupname` "
            . "FROM `".MECCANO_TPREF."_core_userman_groups` "
            . "WHERE `id`=$groupId ;", 
            "DELETE FROM `".MECCANO_TPREF."_core_userman_groups` "
            . "WHERE `id`=$groupId ;"
        );
        foreach ($sql as $key => $value) {
            $qGroup = $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delGroup: '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'delGroup: group not found');
                return FALSE;
            }
            if ($key == 0) {
                list($groupname) = $qGroup->fetch_row();
            }
        }
        if ($log && !$this->newLogRecord('core', 'userman_del_group', "$groupname; ID: $groupId")) {
            $this->setError(ERROR_NOT_CRITICAL, "delGroup -> ".$this->errExp());
        }
        return TRUE;
    }
    
    // method came from core module policy.php
    // old name [delGroup]
    private function delPolicyFromGroup($id) {
        $this->zeroizeError();
        if (!is_integer($id)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delPolicyFromGroup: id must be integer');
            return FALSE;
        }
        $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_policy_access` "
                . "WHERE `groupid`=$id ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addPolicy: unable to delete policy -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delPolicyFromGroup: defined group is not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public function sumGroups($rpp = 20) { // gpp - groups per page
        $this->zeroizeError();
        if (!is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumGroups: value of groups per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_groups` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumGroups: total users could not be counted -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalGroups) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalGroups/$rpp;
        $remainer = fmod($totalGroups, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalRecs, 'pages' => (int) $totalPages);
    }
    
    public function getGroups($pageNumber, $totalGroups, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_get_groups')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getGroups: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($pageNumber) || !is_integer($totalGroups) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getGroups: values of $pageNumber, $totalGroups, $gpp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'time', 'active');
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
            $this->setError(ERROR_INCORRECT_DATA, 'getGroups: orderBy must be array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalGroups && $totalGroups) {
            $pageNumber = $totalGroups;
        }
        if ($totalGroups < 1) {
            $totalGroups = 1;
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
        $qResult = $this->dbLink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getGroups: group info page could not be gotten -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $groupsNode = $xml->createElement('groups');
            $xml->appendChild($groupsNode);
            while ($row = $qResult->fetch_row()) {
                $groupNode = $xml->createElement('group');
                $groupsNode->appendChild($groupNode);
                $groupNode->appendChild($xml->createElement('id', $row[0]));
                $groupNode->appendChild($xml->createElement('name', $row[1]));
                $groupNode->appendChild($xml->createElement('time', $row[2]));
                $groupNode->appendChild($xml->createElement('active', $row[3]));
            }
            return $xml;
        }
        else {
            $groupsNode = array();
            while ($row = $qResult->fetch_row()) {
                $groupsNode[] = array(
                    'id' => (int) $row[0],
                    'name' => $row[1],
                    'time' => $row[2],
                    'active' => (int) $row[3]
                );
            }
            if ($this->outputType == 'array') {
                return $groupsNode;
            }
            else {
                return json_encode($groupsNode);
            }
        }
    }
    
    public function getAllGroups($orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_get_groups')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getAllGroups: restricted by the policy");
            return FALSE;
        }
        $rightEntry = array('id', 'name', 'time', 'active');
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
            $this->setError(ERROR_INCORRECT_DATA, 'getAllGroups: value of $orderBy must be string or array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = $this->dbLink->query("SELECT  `id`, `groupname` `name`, `creationtime` `time`, `active` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllGroups: group info page could not be gotten -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $groupsNode = $xml->createElement('groups');
            $xml->appendChild($groupsNode);
            while ($row = $qResult->fetch_row()) {
                $groupNode = $xml->createElement('group');
                $groupsNode->appendChild($groupNode);
                $groupNode->appendChild($xml->createElement('id', $row[0]));
                $groupNode->appendChild($xml->createElement('name', $row[1]));
                $groupNode->appendChild($xml->createElement('time', $row[2]));
                $groupNode->appendChild($xml->createElement('active', $row[3]));
            }
            return $xml;
        }
        else {
            $groupsNode = array();
            while ($row = $qResult->fetch_row()) {
                $groupsNode[] = array(
                    'id' => (int) $row[0],
                    'name' => $row[1],
                    'time' => $row[2],
                    'active' => (int) $row[3]
                );
            }
            if ($this->outputType == 'array') {
                return $groupsNode;
            }
            else {
                return json_encode($groupsNode);
            }
        }
    }

    //user methods
    public function createUser($username, $password, $email, $groupId, $active = TRUE, $langCode = MECCANO_DEF_LANG, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_create_user')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "createUser: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'createUser: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!pregUName($username) || !pregPassw($password) || !pregMail($email) || !is_integer($groupId) || !pregLang($langCode)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createUser: incorrect incoming parameters');
            return FALSE;
        }
        $this->dbLink->query("SELECT `u`.`id`, `i`.`id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u`, `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "WHERE `u`.`username`='$username' "
                . "OR `i`.`email`='$email' "
                . "LIMIT 1;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createUser: unable to check username and email -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'createUser: username or email are already in use');
            return FALSE;
        }
        $qLang = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_langman_languages` "
                . "WHERE `code`='$langCode' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createUser: unable to get language identifier -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createUser: language not found');
            return FALSE;
        }
        list($langId) = $qLang->fetch_row();
        $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$groupId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createUser: unable to check group -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createUser: group not found');
            return FALSE;
        }
        $salt = makeSalt($username);
        $passw = passwHash($password, $salt);
        $usi = makeIdent($username);
        if ($active) { $active = 1; }
        else { $active = 0; }
        $passId = guid();
        $sql = array(
            'userid' => "INSERT INTO `".MECCANO_TPREF."_core_userman_users` (`username`, `groupid`, `salt`, `active`, `langid`) "
            . "VALUES ('$username', '$groupId', '$salt', $active, $langId) ;",
            'mail' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userinfo` (`id`, `email`) "
            . "VALUES (LAST_INSERT_ID(), '$email') ;",
            'tblock' => "INSERT INTO `".MECCANO_TPREF."_core_userman_temp_block` (`id`) "
            . "VALUES (LAST_INSERT_ID()) ;",
            'passw' => "INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`id`, `userid`, `password`, `limited`) "
            . "VALUES ('$passId', LAST_INSERT_ID(), '$passw', 0) ;"
            );
        foreach ($sql as $key => $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'createUser: something went wrong -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($key == 'userid') {
                $userid = $this->dbLink->insert_id;
            }
        }
        if ($log && !$this->newLogRecord('core', 'userman_create_user', "$username; ID: $userid")) {
            $this->setError(ERROR_NOT_CRITICAL, "createUser -> ".$this->errExp());
        }
        return (int) $userid;
    }
    
    public function userExists($username) {
        $this->zeroizeError();
        if (!pregUName($username)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userExists: incorrect username');
            return FALSE;
        }
        $qId = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `username`='$username' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userExists: unable to check user existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public function mailExists($email) {
        $this->zeroizeError();
        if (!pregMail($email)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userExists: incorrect email');
            return FALSE;
        }
        $qId = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` "
                . "WHERE `email`='$email' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userExists: unable to check email existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $id = $qId->fetch_row();
            return (int) $id[0];
        }
        return FALSE;
    }
    
    public function userStatus($userId, $active, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_user_status')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "userStatus: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'userStatus: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId) || $userId<1) {
            $this->setError(ERROR_INCORRECT_DATA, 'userStatus: user id must be integer and greater than zero');
            return FALSE;
        }
        if ($active) {
            $active = 1;
        }
        elseif (!$active && $userId>1) {
            $active = 0;
        }
        else {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'userStatus: unable to disable system user');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `active`=$active "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userStatus: status was not changed -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'userStatus: incorrect user status or group not found');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_user_status', "ID: $userId; status: $active")) {
            $this->setError(ERROR_NOT_CRITICAL, "userStatus -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function moveUserTo($userId, $destId, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_move_user')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "moveUserTo: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'moveUserTo: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId) || !is_integer($destId) || $destId<1 || $destId == $userId) {
            $this->setError(ERROR_INCORRECT_DATA, 'moveUserTo: incorrect incoming parameters');
            return FALSE;
        }
        if ($userId == 1) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'moveUserTo: unable to move system user');
            return FALSE;
        }
        $this->dbLink->query("SELECT `id` FROM `".MECCANO_TPREF."_core_userman_groups` "
                . "WHERE `id`=$destId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'moveUserTo: unable to check destination group existence |'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'moveUserTo: destination group not found');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `groupid`=$destId "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'moveUserTo: unable to move user into another group |'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'moveUserTo: user not found');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_move_user', "USER_ID:$userId -> GROUP_ID:$destId")) {
            $this->setError(ERROR_NOT_CRITICAL, "moveUserTo -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function delUser($userId, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_del_user')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delUser: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'delUser: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delUser: identifier must be integer');
            return FALSE;
        }
        if ($userId == 1) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'delUser: unable to delete system user');
            return FALSE;
        }
        $qName = $this->dbLink->query("SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delUser: user not found');
            return FALSE;
        }
        $sql = array(
            "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
            . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "ON `si`.`id`=`s`.`id` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId ;",
            "DELETE `s` FROM `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "JOIN `".MECCANO_TPREF."_core_userman_userpass` `p` "
            . "ON `s`.`pid`=`p`.`id`"
            . "JOIN  `".MECCANO_TPREF."_core_userman_users` `u` "
            . "ON `p`.`userid`=`u`.`id` "
            . "WHERE `u`.`id`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `userid`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userinfo` "
            . "WHERE `id`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_temp_block` "
            . "WHERE `id`=$userId ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_users` "
            . "WHERE `id`=$userId ;"
        );
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delUser: something went wrong -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        list($username) = $qName->fetch_row();
        if ($log && !$this->newLogRecord('core', 'userman_del_user', "$username; ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "delUser -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function aboutUser($userId) {
        $this->zeroizeError();
        if (!is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'aboutUser: id must be integer');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_about_user', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "aboutUser: restricted by the policy");
            return FALSE;
        }
        $qAbout = $this->dbLink->query("SELECT `u`.`username`, `i`.`fullname`, `i`.`email`, `u`.`creationtime`, `u`.`active`, `g`.`id`, `g`.`groupname` "
                . "FROM `".MECCANO_TPREF."_core_userman_userinfo` `i`, `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid`=`g`.`id` "
                . "WHERE `i`.`id`=$userId "
                . "AND `u`.`id`=$userId "
                . "LIMIT 1 ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'aboutUser: something went wrong -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'aboutUser: user not found');
            return FALSE;
        }
        $about = $qAbout->fetch_row();
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $aboutNode = $xml->createElement('user');
            $xml->appendChild($aboutNode);
            $aboutNode->appendChild($xml->createElement('id', $userId));
            $aboutNode->appendChild($xml->createElement('username', $about[0]));
            $aboutNode->appendChild($xml->createElement('fullname', $about[1]));
            $aboutNode->appendChild($xml->createElement('email', $about[2]));
            $aboutNode->appendChild($xml->createElement('time', $about[3]));
            $aboutNode->appendChild($xml->createElement('active', $about[4]));
            $aboutNode->appendChild($xml->createElement('gid', $about[5]));
            $aboutNode->appendChild($xml->createElement('group', $about[6]));
            return $xml;
        }
        else {
            $aboutNode = array(
                        'id' => $userId,
                        'username' => $about[0],
                        'fullname' => $about[1],
                        'email' => $about[2],
                        'time' => $about[3],
                        'active' => (int) $about[4],
                        'gid' => (int) $about[5],
                        'group' => $about[6]
                        );
            if ($this->outputType == 'array') {
                return $aboutNode;
            }
            else {
                return json_encode($aboutNode);
            }
        }
    }
    
    public function userPasswords($userId) {
        $this->zeroizeError();
        if (!is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userPasswords: id must be integer');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_user_passwords', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "userPasswords: restricted by the policy");
            return FALSE;
        }
        $qPassw = $this->dbLink->query("SELECT `id`, `description`, `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid` = $userId "
                . "ORDER BY `limited` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userPasswords: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'userPasswords: user not found');
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $securityNode = $xml->createElement('security');
            $xml->appendChild($securityNode);
            $uidAttribute = $xml->createAttribute('uid');
            $uidAttribute->value = $userId;
            $securityNode->appendChild($uidAttribute);
            while ($row = $qPassw->fetch_row()) {
                $passwNode = $xml->createElement('password');
                $securityNode->appendChild($passwNode);
                $passwNode->appendChild($xml->createElement('id', $row[0]));
                $passwNode->appendChild($xml->createElement('description', $row[1]));
                $passwNode->appendChild($xml->createElement('limited', $row[2]));
            }
            return $xml;
        }
        else {
            $securityNode = array();
            $securityNode['uid'] = $userId;
            $securityNode['passwords'] = array();
            while ($row = $qPassw->fetch_row()) {
                $securityNode['passwords'][] = array(
                    'id' => $row[0],
                    'description' => $row[1],
                    'limited' => (int) $row[2]
                );
            }
            if ($this->outputType == 'array') {
                return $securityNode;
            }
            else {
                return json_encode($securityNode);
            }
        }
    }
    
    public function createPassword($userId, $description, $length = 8, $underline = TRUE, $minus = FALSE, $special = FALSE, $log = TRUE) {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'createPassword: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId) || !is_string($description)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createPassword: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_add_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "createPassword: restricted by the policy");
            return FALSE;
        }
        if (!$password = genPassword($length, TRUE, TRUE, TRUE, $underline, $minus, $special)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createPassword: incorrect password length');
            return FALSE;
        }
        // get password salt
        $qSalt = $this->dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createPassword: unable to get salt');
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createPassword: user not found');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        for ($i = 0; $i < 5; $i++) {
            $passwHash = passwHash($password, $salt);
            $this->dbLink->query("SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                    . "WHERE `userid`=$userId "
                    . "AND `password`='$passwHash' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "createPassword: unable to check uniqueness of the password -> ".$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $insertId = guid();
                $description = $this->dbLink->real_escape_string($description);
                $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`id`, `userid`, `password`, `description`, `limited`) "
                        . "VALUES('$insertId', $userId, '$passwHash', '$description', 1) ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'createPassword: unable to add password -> '.$this->dbLink->error);
                    return FALSE;
                }
                if ($log && !$this->newLogRecord('core', 'userman_add_password', "PASSW_ID: $insertId; USER_ID: $userId")) {
                    $this->setError(ERROR_NOT_CRITICAL, "createPassword -> ".$this->errExp());
                }
                return $password;
            }
            $password = genPassword($length, TRUE, TRUE, TRUE, $underline, $minus, $special);
        }
        $this->setError(ERROR_ALREADY_EXISTS, 'createPassword: unable to create password, try again');
        return FALSE;
    }
    
    public function addPassword($userId, $password, $description='', $log = TRUE) {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'addPassword: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId) || !pregPassw($password) || !is_string($description)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addPassword: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_add_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "addPassword: restricted by the policy");
            return FALSE;
        }
        $qHash = $this->dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addPassword: unable to check user existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addPassword: user not found');
            return FALSE;
        }
        list($salt) = $qHash->fetch_row();
        $passwHash = passwHash($password, $salt);
        // check whether the new password repeates existing password
        $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid`=$userId "
                . "AND `password`='$passwHash' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "addPassword: unable to check uniqueness of the password -> ".$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, "addPassword: password already in use");
            return FALSE;
        }
        $description = $this->dbLink->real_escape_string($description);
        $insertId = guid();
        $this->dbLink->query("INSERT INTO `".MECCANO_TPREF."_core_userman_userpass` (`id`, `userid`, `password`, `description`, `limited`) "
                . "VALUES('$insertId', $userId, '$passwHash', '$description', 1) ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addPassword: unable to add password -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_add_password', "PASSW_ID: $insertId; USER_ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "addPassword -> ".$this->errExp());
        }
        return $insertId;
    }
    
    public function delPassword($passwId, $userId, $log = TRUE) {
        $this->zeroizeError();
        if (!pregGuid($passwId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delPassword: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_del_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "delPassword: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'delPassword: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        $qLimited = $this->dbLink->query("SELECT `limited` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `id`='$passwId' "
                . "AND `userid`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_INCORRECT_DATA, 'delPassword: unable to check limitation status of the password -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delPassword: check incoming parameters');
            return FALSE;
        }
        list($limited) = $qLimited->fetch_row();
        if (!$limited) {
            $this->setError(ERROR_SYSTEM_INTERVENTION, 'delPassword: impossible to delete primary password');
            return FALSE;
        }
        $sql = array(
            "DELETE `si` FROM `".MECCANO_TPREF."_core_auth_session_info` `si` "
            . "JOIN `".MECCANO_TPREF."_core_auth_usi` `s` "
            . "ON `si`.`id`=`s`.`id` "
            . "WHERE `s`.`pid`='$passwId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_auth_usi` "
            . "WHERE `pid`='$passwId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_userman_userpass` "
            . "WHERE `id`='$passwId' ;"
                );
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delPassword: '.$this->dbLink->error);
                return FALSE;
            }
        }
        if ($log && !$this->newLogRecord('core', 'userman_del_password', "PASSW_ID: $passwId; USER_ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "delPassword -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function setPassword($passwId, $userId, $password, $log = TRUE) {
        $this->zeroizeError();
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'setPassword: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!pregGuid($passwId) || !is_integer($userId) || !pregPassw($password)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setPassword: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_set_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setPassword: restricted by the policy");
            return FALSE;
        }
        $qSalt = $this->dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setPassword: unable to check user existence');
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'setPassword: user not found');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $passwHash = passwHash($password, $salt);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                . "SET `password`='$passwHash' "
                . "WHERE `id`='$passwId' "
                . "AND `userid`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setPassword: unable to update password -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setPassword: password not found or password was repeated');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_password', "PASSW_ID: $passwId; USER_ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "setPassword -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function setUserName($userId, $username, $log = TRUE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_set_username')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setUserName: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'setUserName: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        if (!is_integer($userId) || !pregUName($username)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setUserName: incorrect incoming parameters');
            return FALSE;
        }
        $qNewName = $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `username`='$username' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setUserName: unable to check new name -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setUserName: username already in use');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `username`='$username' "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setUserName: unable to set username -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'setUserName: unable to find user');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_username', "$username; ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "setUserName -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function setUserMail($userId, $email, $log = TRUE) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregMail($email)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setUserMail: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_set_user_mail', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setUserMail: restricted by the policy");
            return FALSE;
        }
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'setUserMail: execution of the function was aborted because you are authenticated with the secondary password');
            return FALSE;
        }
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `email`='$email' "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setUserMail: unable to set email -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setUserMail: user does not exist or email was repeated');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_user_mail', "$email; ID: $userId;")) {
            $this->setError(ERROR_NOT_CRITICAL, "setUserMail -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function setFullName($userId, $name, $log = TRUE) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_string($name)) {
            $this->setError(ERROR_INCORRECT_DATA, 'setFullName: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_set_full_name', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setFullName: restricted by the policy");
            return FALSE;
        }
        $name = $this->dbLink->real_escape_string($name);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userinfo` "
                . "SET `fullname`='$name' "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'setFullName: unable to set name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'setFullName: user not found or name was repeated');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_set_full_name', "$name; ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "setFullName -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function changePassword($passwId, $userId, $oldPassw, $newPassw, $log = TRUE) {
        $this->zeroizeError();
        if (!pregGuid($passwId) || !is_integer($userId) || !pregPassw($oldPassw) || !pregPassw($newPassw)) {
            $this->setError(ERROR_INCORRECT_DATA, 'changePassword: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_change_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "changePassword: restricted by the policy");
            return FALSE;
        }
        $qSalt = $this->dbLink->query("SELECT `salt` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'changePassword: unable to check user existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'changePassword: user not found');
            return FALSE;
        }
        list($salt) = $qSalt->fetch_row();
        $oldPasswHash = passwHash($oldPassw, $salt);
        $newPasswHash = passwHash($newPassw, $salt);
        // check whether the new password repeates existing password
        $this->dbLink->query("SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_userman_userpass` "
                . "WHERE `userid`=$userId "
                . "AND `password`='$newPasswHash' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "changePassword: unable to check uniqueness of the new password -> ".$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, "changePassword: new password already in use");
            return FALSE;
        }
        // change password
        if (isset($_SESSION[AUTH_LIMITED]) && $_SESSION[AUTH_LIMITED]) {
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`='$passwId' "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' "
                    . "AND `limited`=1 ;");
        }
        else {
            $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
                    . "SET `password`='$newPasswHash' "
                    . "WHERE `id`='$passwId' "
                    . "AND `userid`=$userId "
                    . "AND `password`='$oldPasswHash' ;");
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'changePassword: unable to update password -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'changePassword: password not found, or it has been received invalid old password, or maybe your authentication is limited');
            return FALSE;
        }
        if ($log && !$this->newLogRecord('core', 'userman_change_password', "PASSW_ID: $passwId; USER_ID: $userId")) {
            $this->setError(ERROR_NOT_CRITICAL, "changePassword -> ".$this->errExp());
        }
        return TRUE;
    }
    
    public function sumUsers($rpp = 20) { // rpp - records per page
        $this->zeroizeError();
        if (!is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumUsers: value of users per page must be integer');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query("SELECT COUNT(`id`) FROM `".MECCANO_TPREF."_core_userman_users` ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumUsers: total users could not be counted -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalUsers) = $qResult->fetch_array(MYSQLI_NUM);
        $totalPages = $totalUsers/$rpp;
        $remainer = fmod($totalUsers, $rpp);
        if ($totalPages<1 && $totalPages>0) {
            $totalPages = 1;
        }
        elseif ($totalPages>1 && $remainer != 0) {
            $totalPages += 1;
        }
        elseif ($totalPages == 0) {
            $totalPages = 1;
        }
        return array('records' => (int) $totalUsers, 'pages' => (int) $totalPages);
    }
    
    public function getUsers($pageNumber, $totalUsers, $rpp = 20, $orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_get_users')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getUsers: restricted by the policy");
            return FALSE;
        }
        if (!is_integer($pageNumber) || !is_integer($totalUsers) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getUsers: values of $pageNumber, $totalUsers, $upp must be integers');
            return FALSE;
        }
        $rightEntry = array('id', 'username', 'time', 'fullname', 'email', 'group', 'gid', 'active');
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
            $this->setError(ERROR_INCORRECT_DATA, 'getUsers: orderBy must be array');
            return FALSE;
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }
        elseif ($pageNumber>$totalUsers && $totalUsers) {
            $pageNumber = $totalUsers;
        }
        if ($totalUsers < 1) {
            $totalUsers = 1;
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
        $qResult = $this->dbLink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `fullname`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getUsers: unable to get user info page -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $usersNode = $xml->createElement('users');
            $xml->appendChild($usersNode);
            while ($row = $qResult->fetch_row()) {
                $userNode = $xml->createElement('user');
                $usersNode->appendChild($userNode);
                $userNode->appendChild($xml->createElement('id', $row[0]));
                $userNode->appendChild($xml->createElement('username', $row[1]));
                $userNode->appendChild($xml->createElement('fullname', $row[2]));
                $userNode->appendChild($xml->createElement('email', $row[3]));
                $userNode->appendChild($xml->createElement('group', $row[4]));
                $userNode->appendChild($xml->createElement('gid', $row[5]));
                $userNode->appendChild($xml->createElement('time', $row[6]));
                $userNode->appendChild($xml->createElement('active', $row[7]));
            }
            return $xml;
        }
        else {
            $usersNode = array();
            while ($row = $qResult->fetch_row()) {
                $usersNode[] = array(
                    'id' => (int) $row[0],
                    'username' => $row[1],
                    'fullname' => $row[2],
                    'email' => $row[3],
                    'group' => $row[4],
                    'gid' => (int) $row[5],
                    'time' => $row[6],
                    'active' => (int) $row[7]
                );
            }
            if ($this->outputType == 'array') {
                return $usersNode;
            }
            else {
                return json_encode($usersNode);
            }
        }
    }
    
    public function getAllUsers($orderBy = array('id'), $ascent = FALSE) {
        $this->zeroizeError();
        if (!$this->checkFuncAccess('core', 'userman_get_users')) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "getAllUsers: restricted by the policy");
            return FALSE;
        }
        $rightEntry = array('id', 'username', 'time', 'fullname', 'email', 'group', 'gid', 'active');
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
            $this->setError(ERROR_INCORRECT_DATA, 'getAllUsers: orderBy must be array');
            return FALSE;
        }
        if ($ascent == TRUE) {
            $direct = '';
        }
        elseif ($ascent == FALSE) {
            $direct = 'DESC';
        }
        $qResult = $this->dbLink->query("SELECT `u`.`id` `id`, `u`.`username` `username`, `i`.`fullname` `fullname`, `i`.`email` `email`, `g`.`groupname` `group`, `u`.`groupid` `gid`, `u`.`creationtime` `time`, `u`.`active` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id` = `i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_groups` `g` "
                . "ON `u`.`groupid` = `g`.`id` "
                . "ORDER BY `$orderBy` $direct ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllUsers: unable to get user info page -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $usersNode = $xml->createElement('users');
            $xml->appendChild($usersNode);
            while ($row = $qResult->fetch_row()) {
                $userNode = $xml->createElement('user');
                $usersNode->appendChild($userNode);
                $userNode->appendChild($xml->createElement('id', $row[0]));
                $userNode->appendChild($xml->createElement('username', $row[1]));
                $userNode->appendChild($xml->createElement('fullname', $row[2]));
                $userNode->appendChild($xml->createElement('email', $row[3]));
                $userNode->appendChild($xml->createElement('group', $row[4]));
                $userNode->appendChild($xml->createElement('gid', $row[5]));
                $userNode->appendChild($xml->createElement('time', $row[6]));
                $userNode->appendChild($xml->createElement('active', $row[7]));
            }
            return $xml;
        }
        else {
            $usersNode = array();
            while ($row = $qResult->fetch_row()) {
                $usersNode[] = array(
                    'id' => (int) $row[0],
                    'username' => $row[1],
                    'fullname' => $row[2],
                    'email' => $row[3],
                    'group' => $row[4],
                    'gid' => (int) $row[5],
                    'time' => $row[6],
                    'active' => (int) $row[7]
                );
            }
            if ($this->outputType == 'array') {
                return $usersNode;
            }
            else {
                return json_encode($usersNode);
            }
        }
    }
    
    public function setUserLang($userId, $code = MECCANO_DEF_LANG) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregLang($code)) {
            $this->setError(ERROR_INCORRECT_DATA, "setUserLang: incorrect argument(s)");
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_set_user_lang', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "setUserLang: restricted by the policy");
            return FALSE;
        }
        $sql = array(
            "user" => "SELECT `username` "
            . "FROM `".MECCANO_TPREF."_core_userman_users` "
            . "WHERE `id` = $userId ;",
            "language" => "SELECT `id` "
            . "FROM `".MECCANO_TPREF."_core_langman_languages` "
            . "WHERE `code` = '$code' ;"
        );
        foreach ($sql as $key => $value) {
            $qCheck = $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, "setUserLang: ".$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, "setUserLang: $key not found");
                return FALSE;
            }
        }
        list($codeId) = $qCheck->fetch_row();
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_userman_users` "
                . "SET `langid` = $codeId "
                . "WHERE `id`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "setUserLang: unable to set user language -> ".$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function enableDoubleAuth($passwId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($passwId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'enableDoubleAuth: incorrect incoming parameters');
            return FALSE;
        }
        if (!$this->checkFuncAccess('core', 'userman_change_password', $userId)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, "enableDoubleAuth: restricted by the policy");
            return FALSE;
        }
        // enable double authentication
        $this->dbLink->query(
            "UPDATE `".MECCANO_TPREF."_core_userman_userpass` "
            . "SET `doubleauth`=1 "
            . "WHERE `id`='$passwId' "
            . "AND `userid`=$userId ;"
        );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, "enableDoubleAuth: couldn't enable double authenication -> ".$this->dbLink->error);
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "enableDoubleAuth: double authentication was already enabled or password is not found");
            return FALSE;
        }
        return TRUE;
    }
}
