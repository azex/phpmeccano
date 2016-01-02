<?php

/*
 *     phpMeccano v0.0.2. Web-framework written with php programming language. Core module [share.php].
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

require_once MECCANO_CORE_DIR.'/logman.php';
require_once MECCANO_CORE_DIR.'/files.php';

interface intShare {
    public function __construct(LogMan $logObject);
    public function createCircle($userId, $name);
    public function userCircles($userId, $output = 'json');
    public function renameCircle($userId, $circleId, $newName);
    public function addToCircle($contactId, $circleId, $userId);
    public function circleContacts($userId, $circleId, $output = 'json');
    public function rmFromCircle($userId, $circleId, $contactId);
    public function delCircle($userId, $circleId);
    public function createMsg($userId, $title, $text);
    public function stageFile($file, $filename, $userid, $title, $comment);
    public function shareFile($fileId, $userId, $circles);
    public function getFile($fileId, $contDisp = 'inline');
    public function attachFile($fileId, $msgId, $userId);
    public function unattachFile($fileId, $msgId, $userId);
    public function delFile($fileId, $userId, $force = FALSE);
    public function getFileInfo($fileId, $output = 'json');
    public function shareMsg($msgId, $userId, $circles);
    public function getMsg($msgId, $output = 'json');
}

class Share extends ServiceMethods implements intShare {
    private $dbLink; // database link
    private $logObject; // log object
    private $policyObject; // policy object
    
    public function __construct(LogMan $logObject) {
        $this->dbLink = $logObject->dbLink;
        $this->logObject = $logObject;
        $this->policyObject = $logObject->policyObject;
    }
    
    private function checkFileAccess($fileId) {
        $this->zeroizeError();
        if (!pregGuid($fileId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'checkFileAccess: incorrect file identifier');
            return FALSE;
        }
        //check whether file exists
        $this->dbLink->query(
                "SELECT `id` FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkFileAccess: '.$this->dbLink->error);
            return FALSE;
        }
        elseif (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "checkFileAccess: file [$fileId] not found in the database");
            return FALSE;
        }
        // check for public access
        $this->dbLink->query(
                "SELECT `id` FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
                . "WHERE `fid`='$fileId' "
                . "AND `cid`='' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkFileAccess: '.$this->dbLink->error);
            return FALSE;
        }
        elseif ($this->dbLink->affected_rows) {
            return TRUE;
        }
        elseif (!isset($_SESSION[AUTH_USER_ID])) {
            return FALSE;
        }
        else {
            $sql = array(
                // check for access shared with circles
                "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `a`.`cid`=`b`.`cid` "
                . "WHERE `b`.`bid`=".$_SESSION[AUTH_USER_ID]." "
                . "AND `a`.`fid`='$fileId' ;",
                
                // check for owner access
                "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `a`.`cid`=`c`.`id` "
                . "WHERE `c`.`userid`=".$_SESSION[AUTH_USER_ID]." "
                . "AND `a`.`fid`='$fileId';"
            );
            foreach ($sql as $value) {
                $this->dbLink->query($value);
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'checkFileAccess: '.$this->dbLink->error);
                    return FALSE;
                }
                elseif ($this->dbLink->affected_rows) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }
    
    private function checkMsgAccess($msgId) {
        $this->zeroizeError();
        if (!pregGuid($msgId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'checkMsgAccess: incorrect message identifier');
            return FALSE;
        }
        //check whether message exists
        $this->dbLink->query(
                "SELECT `id` FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkMsgAccess: '.$this->dbLink->error);
            return FALSE;
        }
        elseif (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "checkMsgAccess: message [$msgId] not found in the database");
            return FALSE;
        }
        // check for public access
        $this->dbLink->query(
                "SELECT `id` FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
                . "WHERE `mid`='$msgId' "
                . "AND `cid`='' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'checkMsgAccess: '.$this->dbLink->error);
            return FALSE;
        }
        elseif ($this->dbLink->affected_rows) {
            return TRUE;
        }
        elseif (!isset($_SESSION[AUTH_USER_ID])) {
            return FALSE;
        }
        else {
            $sql = array(
                // check for access shared with circles
                "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `a`.`cid`=`b`.`cid` "
                . "WHERE `b`.`bid`=".$_SESSION[AUTH_USER_ID]." "
                . "AND `a`.`mid`='$msgId' ;",
                
                // check for owner access
                "SELECT `a`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `a`.`cid`=`c`.`id` "
                . "WHERE `c`.`userid`=".$_SESSION[AUTH_USER_ID]." "
                . "AND `a`.`mid`='$msgId';"
            );
            foreach ($sql as $value) {
                $this->dbLink->query($value);
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'checkMsgAccess: '.$this->dbLink->error);
                    return FALSE;
                }
                elseif ($this->dbLink->affected_rows) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }
    
    function createCircle($userId, $name) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_string($name) || !strlen($name)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createCircle: incorrect parameters');
            return FALSE;
        }
        $qUser = $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createCircle: '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createCircle: user not found');
            return FALSE;
        }
        $name = $this->dbLink->real_escape_string($name);
        $id = guid();
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_circles` "
                . "(`id`, `userid`, `cname`) "
                . "VALUES('$id', $userId, '$name') ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createCircle: unable to create circle -> '.$this->dbLink->error);
            return FALSE;
        }
        return $id;
    }
    
    public function userCircles($userId, $output = 'json') {
        $this->zeroizeError();
        if (!is_integer($userId) || !in_array($output, array('xml', 'json'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'userCircles: incorrect parameter');
            return FALSE;
        }
        $qCircles = $this->dbLink->query(
                "SELECT `id`, `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `userid`=$userId "
                . "ORDER BY `cname` ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userCircles: '.$this->dbLink->error);
            return FALSE;
        }
        if ($output == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $circlesNode = $xml->createElement('circles');
            $xml->appendChild($circlesNode);
            while ($row = $qCircles->fetch_row()) {
                $circleNode = $xml->createElement('circle');
                $idAttribute = $xml->createAttribute('id');
                $idAttribute->value = $row[0];
                $nameAttribute = $xml->createAttribute('name');
                $nameAttribute->value = htmlspecialchars($row[1]);
                $circleNode->appendChild($idAttribute);
                $circleNode->appendChild($nameAttribute);
                $circlesNode->appendChild($circleNode);
            }
            return $xml;
        }
        else {
            $circlesNode = array();
            while ($row = $qCircles->fetch_row()) {
                $circleNode = array();
                $circleNode['id'] = $row[0];
                $circleNode['name'] = htmlspecialchars($row[1]);
                $circlesNode[] = $circleNode;
            }
            return json_encode($circlesNode);
        }
    }
    
    public function renameCircle($userId, $circleId, $newName) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregGuid($circleId) || !is_string($newName)) {
            $this->setError(ERROR_INCORRECT_DATA, 'renameCircle: incorrect parameters');
            return FALSE;
        }
        $newName = $this->dbLink->real_escape_string($newName);
        $this->dbLink->query(
                "UPDATE `".MECCANO_TPREF."_core_share_circles` "
                . "SET `cname`='$newName' "
                . "WHERE `id`='$circleId' "
                . "AND `userid`=$userId "
                . "LIMIT 1;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'renameCircle: '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function addToCircle($contactId, $circleId, $userId) {
        $this->zeroizeError();
        if (!is_integer($contactId) || !pregGuid($circleId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'addToCircle: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`='$circleId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addToCircle: unable to check user and circle -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addToCircle: circle or user not exist');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$contactId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addToCircle: unable to check contact -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'addToCircle: contact not found');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_share_buddy_list` "
                . "WHERE `cid`='$circleId' AND "
                . "`bid`=$contactId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addToCircle: unable to check contact -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->dbLink->affected_rows) {
            $this->setError(ERROR_ALREADY_EXISTS, 'addToCircle: contact already in circle');
            return FALSE;
        }
        $id = guid();
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_buddy_list` "
                . "(`id`, `cid`, `bid`) "
                . "VALUES('$id', '$circleId', $contactId) "
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'addToCircle: unable to insert contact into circle -> '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function circleContacts($userId, $circleId, $output = 'json') {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregGuid($circleId) || !in_array($output, array('xml', 'json'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'circleContacts: incorrect parameters');
            return FALSE;
        }
        $qCircle = $this->dbLink->query(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`='$circleId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'circleContacts: unable to check circle'.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'circleContacts: circle no found');
            return FALSE;
        }
        list($circleName) = $qCircle->fetch_row();
        $qContacts = $this->dbLink->query(
                "SELECT `u`.`id`, `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `u`.`id`=`i`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `i`.`id`=`l`.`bid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `l`.`cid`=`c`.`id`"
                . "WHERE `c`.`id`='$circleId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'circleContacts: '.$this->dbLink->error);
            return FALSE;
        }
        if ($output == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $contactsNode = $xml->createElement('contacts');
            $xml->appendChild($contactsNode);
            $cidAttribute = $xml->createAttribute('cid');
            $cidAttribute->value = $circleId;
            $cNameAttribute = $xml->createAttribute('cname');
            $cNameAttribute->value = $circleName;
            $contactsNode->appendChild($cidAttribute);
            $contactsNode->appendChild($cNameAttribute);
            while ($row = $qContacts->fetch_row()) {
                $contactNode = $xml->createElement('contact');
                $userIdNode = $xml->createElement('id', $row[0]);
                $userNameNode = $xml->createElement('username', $row[1]);
                $fullNameNode = $xml->createElement('fullname', $row[2]);
                $contactNode->appendChild($userIdNode);
                $contactNode->appendChild($userNameNode);
                $contactNode->appendChild($fullNameNode);
                $contactsNode->appendChild($contactNode);
            }
            return $xml;
        }
        else {
            $rootNode = array();
            $rootNode['cid'] = $circleId;
            $rootNode['cname'] = $circleName;
            $rootNode['contacts'] = array();
            while ($row = $qContacts->fetch_row()) {
                $rootNode['contacts'][] = array(
                    'id' => $row[0],
                    'username' => $row[1],
                    'fullname' =>$row[2]
                        );
            }
            return json_encode($rootNode);
        }
    }
    
    public function rmFromCircle($userId, $circleId, $contactId) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregGuid($circleId) || !is_integer($contactId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'rmFromCircle: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`='$circleId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'rmFromCircle: unable to check circle -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'rmFromCircle: circle not found');
            return FALSE;
        }
        $this->dbLink->query(
                "DELETE FROM `".MECCANO_TPREF."_core_share_buddy_list` "
                . "WHERE `cid`='$circleId' "
                . "AND `bid`=$contactId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'rmFromCircle: unable to remove contact -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'rmFromCircle: contact not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public function delCircle($userId, $circleId) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregGuid($circleId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delCircle: incorrect parameters');
            return FALSE;
        }
        $qCircles = $this->dbLink->query(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`='$circleId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delCircle:  unable to check circle -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createCircle: circle not found');
            return FALSE;
        }
        $sql = array(
            "DELETE FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
            . "WHERE `cid`='$circleId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
            . "WHERE `cid`='$circleId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_share_buddy_list` "
            . "WHERE `cid`='$circleId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_share_circles` "
            . "WHERE `id`='$circleId' ;",
        );
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delCircle: unable to delete circle -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public function createMsg($userId, $title, $text = '') {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_string($title) || !strlen($title) || !is_string($text)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createMsg: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createMsg: unable to check user -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createMsg: user not found');
            return FALSE;
        }
        $title = $this->dbLink->real_escape_string($title);
        $text = $this->dbLink->real_escape_string($text);
        $id = guid();
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_msgs` "
                . "(`id`, `userid`, `title`, `text`) "
                . "VALUES('$id', $userId, '$title', '$text') ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createMsg: unable to create message -> '.$this->dbLink->error);
            return FALSE;
        }
        return $id;
    }
    
    public function stageFile($file, $filename, $userid, $title = '', $comment = '') {
        $this->zeroizeError();
        if (!is_string($file) || !is_string($filename) || !is_integer($userid) || !is_string($title) || !is_string($comment)) {
            $this->setError(ERROR_INCORRECT_DATA, 'stageFile: incorrect parameters');
            return FALSE;
        }
        if (!is_file($file) || is_link($file)) {
            $this->setError(ERROR_INCORRECT_DATA, 'stageFile: this is no file');
            return FALSE;
        }
        elseif (!is_readable($file)) {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'stageFile: file is not readable');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userid ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'stageFile: unable to check user -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'stageFile: user not found');
            return FALSE;
        }
        $id = guid();
        $title = $this->dbLink->real_escape_string($title);
        $comment = $this->dbLink->real_escape_string($comment);
        $mimeType = mime_content_type($file);
        $fileSize = filesize($file);
        $storageDir = MECCANO_SHARED_STDIR;
        if (!is_dir(MECCANO_SHARED_FILES."/$storageDir")) {
           if (!@mkdir(MECCANO_SHARED_FILES."/$storageDir")) {
               $this->setError(ERROR_NOT_EXECUTED, "stageFile: unable to create storage directory");
               return FALSE;
           }
        }
        if (!Files::move($file, MECCANO_SHARED_FILES.'/'."$storageDir/$id")) {
            $this->setError(Files::errId(), 'stageFile -> '.Files::errExp());
            return FALSE;
        }
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_files` "
                . "(`id`, `userid`, `title`, `name`, `comment`, `stdir`, `mime`, `size`) "
                . "VALUES('$id', $userid, '$title', '$filename', '$comment', '$storageDir', '$mimeType', '$fileSize') ;"
                );
        if ($this->dbLink->errno) {
            unlink(MECCANO_SHARED_FILES."/$id");
            $this->setError(ERROR_NOT_EXECUTED, 'stageFile: unable to stage file -> '.$this->dbLink->error);
            return FALSE;
        }
        return $id;
    }
    
    public function shareFile($fileId, $userId, $circles) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !is_integer($userId) || !is_array($circles)) {
            $this->setError(ERROR_INCORRECT_DATA, 'shareFile: incorrect parameters');
            return FALSE;
        }
        // check file
        $this->dbLink->query(
                "SELECT `name` "
                . "FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' "
                . "AND `userid`=$userId ;"
                );
        if ($stmt->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareFile: unable to check file -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'shareFile: file or user not found');
            return FALSE;
        }
        // check circles
        $cKeys = \array_keys($circles);
        foreach ($cKeys as $value) {
            if (!pregGuid($value) && !(is_string($value) && strlen($value) == 0)) {
                $this->setError(ERROR_INCORRECT_DATA, 'shareFile: incorrect circle identifiers');
                return FALSE;
            }
        }
        $stmt = $this->dbLink->prepare(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`=? "
                . "AND `userid`=? ;"
                );
        if (!$stmt) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareFile: unable to check circle ->'.$this->dbLink->error);
            return FALSE;
        }
        foreach ($cKeys as $value) {
            if (strlen($value) != 0) {
                $stmt->bind_param('si', $value, $userId);
                $stmt->execute();
                $stmt->store_result();
                if (!$stmt->affected_rows) {
                    $this->setError(ERROR_NOT_FOUND, "shareFile: circle [$value] not found");
                    return FALSE;
                }
            }
        }
        $stmt->close();
        // share/unshare file
        $stmtInsert = $this->dbLink->prepare(
                "INSERT INTO `".MECCANO_TPREF."_core_share_files_accessibility` "
                . "(`id`, `fid`, `cid`) "
                . "VALUES (?, ?, ?) ;"
                );
        if (!$stmtInsert) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareFile: unable to grant access ->'.$this->dbLink->error);
            return FALSE;
        }
        $stmtDelete = $this->dbLink->prepare(
                "DELETE FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
                . "WHERE `fid`=? "
                . "AND `cid`=? ;"
                );
        foreach ($circles as $key => $value) {
            if ($value) {
                $id = guid();
                $stmtInsert->bind_param('sss', $id, $fileId, $key);
                $stmtInsert->execute();
            }
            else {
                $stmtDelete->bind_param('ss', $fileId, $key);
                $stmtDelete->execute();
            }
        }
        $stmtInsert->close();
        $stmtDelete->close();
        return TRUE;
    }
    
    public function getFile($fileId, $contDisp = 'inline') {
        $this->zeroizeError();
        if (!in_array($contDisp, array('inline', 'attachment'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'getFile: incorrect content disposition value');
            return FALSE;
        }
        if ($this->checkFileAccess($fileId)) {
            $qFile = $this->dbLink->query(
                    "SELECT `name`, `stdir`, `mime`, `size` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `id`='$fileId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getFile: unable to get file information -> '.$this->dbLink->error);
                return FALSE;
            }
            list($fileName, $storageDir, $mimeType, $fileSize) = $qFile->fetch_row();
            $fullPath = realpath(MECCANO_SHARED_FILES."/$storageDir/$fileId");
            if (is_file($fullPath) && is_readable($fullPath)) {
                if (isset($_SERVER['SERVER_SOFTWARE'])) {
                    if (preg_match('/.*Apache.*/', $_SERVER['SERVER_SOFTWARE'])) {
                        // https://tn123.org/mod_xsendfile/
                        header("X-SendFile: $fullPath");
                    }
                    elseif (preg_match('/.*nginx.*/', $_SERVER['SERVER_SOFTWARE'])) {
                        // https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
                        header("X-Accel-Redirect: /".basename(MECCANO_SHARED_FILES)."/$storageDir/$fileId");
                    }
                    else {
                        $this->setError(ERROR_NOT_EXECUTED, "getFile: unknown web server");
                        return FALSE;
                    }
                }
                else {
                    $this->setError(ERROR_NOT_EXECUTED, "getFile: must be run with web server (Apache or nginx)");
                    return FALSE;
                }
                header("Content-Type: $mimeType");
                header("Content-Length: $fileSize");
                header("Content-Disposition: $contDisp; filename=$fileName");
                exit;
            }
            else {
                $this->setError(ERROR_NOT_FOUND, "getFile: file [$fileId] not found on the disk");
                return FALSE;
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'getFile -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'getFile: access denied');
            return FALSE;
        }
    }
    
    public function attachFile($fileId, $msgId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'attachFile: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `name` "
                . "FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'attachFile: unable to check file -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'attachFile: file not found');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `title` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'attachFile: unable to check message -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'attachFile: message not found');
            return FALSE;
        }
        $id = guid();
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_msgfile_relations` "
                . "(`id`, `mid`, `fid`, `userid`) "
                . "VALUES ('$id', '$msgId', '$fileId', $userId) ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'attacheFile: unable to create relation -> '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function unattachFile($fileId, $msgId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'unattachFile: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "DELETE FROM `".MECCANO_TPREF."_core_share_msgfile_relations` "
                . "WHERE (`fid`='$fileId' "
                . "AND `mid`='$msgId') "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'unattachFiles: unable to unattach file -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'unattachFile: file not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public function delFile($fileId, $userId, $force = FALSE) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delFile: incorrect parameters');
            return FALSE;
        }
        // whether file exists
        $qFile = $this->dbLink->query(
                "SELECT `stdir` "
                . "FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delFile: unable to get file info -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delFile: file not found');
            return FALSE;
        }
        // whether file is related
        if (!$force) {
            $this->dbLink->query(
                    "SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgfile_relations` "
                    . "WHERE `fid`='$fileId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delFile: unable to check file ralations -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $this->setError(ERROR_ALREADY_EXISTS, 'delFile: unable to delete file related with message(s)');
                return FALSE;
            }
        }
        // directory of the staged file
        list($stdir) = $qFile->fetch_row();
        Files::remove(MECCANO_SHARED_FILES."/$stdir/$fileId");
        if (Files::errId()) {
            $this->setError(Files::errId(), 'delFile -> '.Files::errExp());
            return FALSE;
        }
        $sql = array(
            "DELETE FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
            . "WHERE `fid`='$fileId' ;",
            "DELETE FROM `".MECCANO_TPREF."_core_share_files` "
            . "WHERE `id`='$fileId' ;"
        );
        if ($force) { 
            array_unshift(
                    $sql, 
                    "DELETE FROM `".MECCANO_TPREF."_core_share_msgfile_relations` "
                    . "WHERE `fid`='$fileId' ;"
                    );
        }
        foreach ($sql as $value) {
            $this->dbLink->query($value);
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delFile: unable to delete file from database -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        return TRUE;
    }
    
    public function getFileInfo($fileId, $output = 'json') {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !in_array($output, array('xml', 'json'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'getFileInfo: incorrect parameters');
            return FALSE;
        }
        if ($this->checkFileAccess($fileId)) {
            $qFileInfo = $this->dbLink->query(
                    "SELECT `f`.`id`, `u`.`username`, `i`.`fullname`, `f`.`title`, `f`.`name`, `f`.`comment`, `f`.`mime`, `f`.`size`, `f`.`filetime` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `u`.`id`=`f`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                    . "ON `i`.`id`=`f`.`userid` "
                    . "WHERE `f`.`id`='$fileId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getFileInfo: unable to get file info -> '.$this->dbLink->error);
                return FALSE;
            }
            elseif (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, "getFileInfo: file not found");
            }
            
            $fileInfo = $qFileInfo->fetch_row();
            if ($output == 'xml') {
                $xml = new \DOMDocument('1.0', 'utf-8');
                $fileInfoNode = $xml->createElement('fileinfo');
                $xml->appendChild($fileInfoNode);
                //
                $fileIdAttribute = $xml->createAttribute('id');
                $fileIdAttribute->value = $fileInfo[0];
                $fileInfoNode->appendChild($fileIdAttribute);
                //
                $userNameNode = $xml->createElement('username', $fileInfo[1]);
                $fileInfoNode->appendChild($userNameNode);
                $fullNameNode = $xml->createElement('fullname', $fileInfo[2]);
                $fileInfoNode->appendChild($fullNameNode);
                $titleNode = $xml->createElement('title', htmlspecialchars($fileInfo[3]));
                $fileInfoNode->appendChild($titleNode);
                $fileNameNode = $xml->createElement('filename', htmlspecialchars($fileInfo[4]));
                $fileInfoNode->appendChild($fileNameNode);
                $commentNode = $xml->createElement('comment', htmlspecialchars($fileInfo[5]));
                $fileInfoNode->appendChild($commentNode);
                $mimeNode = $xml->createElement('mime', $fileInfo[6]);
                $fileInfoNode->appendChild($mimeNode);
                $sizeNode = $xml->createElement('size', $fileInfo[7]);
                $fileInfoNode->appendChild($sizeNode);
                $timeNode = $xml->createElement('time', $fileInfo[8]);
                $fileInfoNode->appendChild($timeNode);
                return $xml;
            }
            else {
                $fileInfoNode = array();
                //
                $fileInfoNode['id'] = $fileInfo[0];
                $fileInfoNode['username'] = $fileInfo[1];
                $fileInfoNode['fullname'] = $fileInfo[2];
                $fileInfoNode['title'] = htmlspecialchars($fileInfo[3]);
                $fileInfoNode['filename'] = htmlspecialchars($fileInfo[4]);
                $fileInfoNode['comment'] = htmlspecialchars($fileInfo[5]);
                $fileInfoNode['mime'] = $fileInfo[6];
                $fileInfoNode['size'] = $fileInfo[7];
                $fileInfoNode['time'] = $fileInfo[8];
                return json_encode($fileInfoNode);
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'getFileInfo -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'getFileInfo: access denied');
            return FALSE;
        }
    }
    
    public function shareMsg($msgId, $userId, $circles) {
        $this->zeroizeError();
        if (!pregGuid($msgId) || !is_integer($userId) || !is_array($circles)) {
            $this->setError(ERROR_INCORRECT_DATA, 'shareMsg: incorrect parameters');
            return FALSE;
        }
        // check message
        $this->dbLink->query(
                "SELECT `name` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`=$userId ;"
                );
        if ($stmt->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareMsg: unable to check message -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'shareMsg: message or user not found');
            return FALSE;
        }
        // check circles
        $cKeys = \array_keys($circles);
        foreach ($cKeys as $value) {
            if (!pregGuid($value) && !(is_string($value) && strlen($value) == 0)) {
                $this->setError(ERROR_INCORRECT_DATA, 'shareMsg: incorrect circle identifiers');
                return FALSE;
            }
        }
        $stmt = $this->dbLink->prepare(
                "SELECT `cname` "
                . "FROM `".MECCANO_TPREF."_core_share_circles` "
                . "WHERE `id`=? "
                . "AND `userid`=? ;"
                );
        if (!$stmt) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareMsg: unable to check circle ->'.$this->dbLink->error);
            return FALSE;
        }
        foreach ($cKeys as $value) {
            if (strlen($value) != 0) {
                $stmt->bind_param('si', $value, $userId);
                $stmt->execute();
                $stmt->store_result();
                if (!$stmt->affected_rows) {
                    $this->setError(ERROR_NOT_FOUND, "shareMsg: circle [$value] not found");
                    return FALSE;
                }
            }
        }
        $stmt->close();
        // share/unshare message
        $stmtInsert = $this->dbLink->prepare(
                "INSERT INTO `".MECCANO_TPREF."_core_share_msg_accessibility` "
                . "(`id`, `mid`, `cid`) "
                . "VALUES (?, ?, ?) ;"
                );
        if (!$stmtInsert) {
            $this->setError(ERROR_NOT_EXECUTED, 'shareMsg: unable to grant access ->'.$this->dbLink->error);
            return FALSE;
        }
        $stmtDelete = $this->dbLink->prepare(
                "DELETE FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
                . "WHERE `mid`=? "
                . "AND `cid`=? ;"
                );
        foreach ($circles as $key => $value) {
            if ($value) {
                $id = guid();
                $stmtInsert->bind_param('sss', $id, $msgId, $key);
                $stmtInsert->execute();
            }
            else {
                $stmtDelete->bind_param('ss', $msgId, $key);
                $stmtDelete->execute();
            }
        }
        $stmtInsert->close();
        $stmtDelete->close();
        return TRUE;
    }
    
    public function getMsg($msgId, $output = 'json') {
        $this->zeroizeError();
        if (!in_array($output, array('xml', 'json'))) {
            $this->setError(ERROR_INCORRECT_DATA, 'getMsg: incorrect parameter');
            return FALSE;
        }
        if ($this->checkMsgAccess($msgId)) {
            $qMsg = $this->dbLink->query(
                    "SELECT `m`.`source`, `m`.`title`, `m`.`text`, `m`.`msgtime`, `u`.`username`, `i`.`fullname` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                    . "ON `u`.`id`=`m`.`userid` "
                    . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                    . "ON `i`.`id`=`m`.`userid` "
                    . "WHERE `m`.`id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getMsg: unable to get message');
                return FALSE;
            }
            list($msgSource, $msgTitle, $msgText, $msgTime, $username, $fullName) = $qMsg->fetch_row();
            if ($output == 'xml') {
                $xml = new \DOMDocument('1.0', 'utf-8');
                $msgNode = $xml->createElement('message');
                $xml->appendChild($msgNode);
                //
                $msgIdAttribute = $xml->createAttribute('id');
                $msgIdAttribute->value = $msgId;
                $msgNode->appendChild($msgIdAttribute);
                //
                $sourceNode = $xml->createElement('source', $msgSource);
                $msgNode->appendChild($sourceNode);
                $titleNode = $xml->createElement('title', htmlentities($msgTitle));
                $msgNode->appendChild($titleNode);
                $textNode = $xml->createElement('text', $msgText);
                $msgNode->appendChild($textNode);
                $timeNode = $xml->createElement('time', $msgTime);
                $msgNode->appendChild($timeNode);
                $unNode = $xml->createElement('username', $username);
                $msgNode->appendChild($unNode);
                $fnNode = $xml->createElement('fullname', $fullName);
                $msgNode->appendChild($fnNode);
                return $xml;
            }
            else {
                $msgNode = array();
                //
                $msgNode['id'] = $msgId;
                $msgNode['source'] = $msgSource;
                $msgNode['title'] = htmlspecialchars($msgTitle);
                $msgNode['text'] = $msgText;
                $msgNode['time'] = $msgTime;
                $msgNode['username'] = $username;
                $msgNode['fullname'] = $fullName;
                return json_encode($msgNode);
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'getMsg -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'getMsg: access denied');
            return FALSE;
        }
    }
}
