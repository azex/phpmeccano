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
require_once MECCANO_CORE_DIR.'/discuss.php';

interface intShare {
    public function __construct(LogMan $logObject);
    public function createCircle($userId, $name);
    public function userCircles($userId);
    public function renameCircle($userId, $circleId, $newName);
    public function addToCircle($contactId, $circleId, $userId);
    public function circleContacts($userId, $circleId);
    public function rmFromCircle($userId, $circleId, $contactId);
    public function delCircle($userId, $circleId);
    public function createMsg($userId, $title, $text);
    public function stageFile($file, $filename, $userId, $title, $comment);
    public function shareFile($fileId, $userId, $circles);
    public function getFile($fileId, $contDisp = 'inline');
    public function attachFile($fileId, $msgId, $userId);
    public function unattachFile($fileId, $msgId, $userId);
    public function delFile($fileId, $userId, $force = FALSE);
    public function getFileInfo($fileId);
    public function shareMsg($msgId, $userId, $circles);
    public function getMsg($msgId);
    public function msgFiles($msgId);
    public function getFileShares($fileId, $userId);
    public function getMsgShares($msgId, $userId);
    public function updateFile($fileId, $userid, $title, $comment);
    public function repostMsg($msgId, $userId, $hlink = TRUE);
    public function delMsg($msgId, $userId, $keepFiles = TRUE);
    public function repostFile($fileId, $userId, $hlink = TRUE);
    public function sumUserMsgs($userId, $rpp = 20);
    public function userMsgs($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE);
    public function msgStripe($userId, $rpp = 20);
    public function appendMsgStripe($userId, $mtmark, $rpp = 20);
    public function updateMsgStripe($userId, $mtmark);
    public function sumUserFiles($userId, $rpp = 20);
    public function userFiles($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE);
    public function fileStripe($userId, $rpp = 20);
    public function appendFileStripe($userId, $mtmark, $rpp = 20);
    public function updateFileStripe($userId, $mtmark);
    public function sumUserSubs($userId, $rpp = 20);
    public function userSubs($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE);
    public function subStripe($userId, $rpp = 20);
    public function appendSubStripe($userId, $mtmark, $rpp = 20);
    public function updateSubStripe($userId, $mtmark);
    public function createMsgComment($msgId, $userId, $comment, $parentId = '');
    public function editMsgComment($comment, $commentId, $userId);
    public function getMsgComment($commentId, $userId);
    public function eraseMsgComment($commentId, $userId);
    public function getMsgComments($msgId, $rpp = 20);
    public function appendMsgComments($msgId, $minMark, $rpp = 20);
    public function updateMsgComments($msgId, $maxMark);
}

class Share extends Discuss implements intShare {
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
                . "WHERE `b`.`bid`={$_SESSION[AUTH_USER_ID]} "
                . "AND `a`.`fid`='$fileId' ;",
                
                // check for owner access
                "SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' "
                . "AND `userid`={$_SESSION[AUTH_USER_ID]} ;"
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
                . "WHERE `b`.`bid`={$_SESSION[AUTH_USER_ID]} "
                . "AND `a`.`mid`='$msgId' ;",
                
                // check for owner access
                "SELECT `id` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`={$_SESSION[AUTH_USER_ID]} ;"
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
    
    public function userCircles($userId) {
        $this->zeroizeError();
        if (!is_integer($userId)) {
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
        if ($this->outputType == 'xml') {
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
    
    public function circleContacts($userId, $circleId) {
        $this->zeroizeError();
        if (!is_integer($userId) || !pregGuid($circleId)) {
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
        if ($this->outputType == 'xml') {
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
        $mtMark = microtime(TRUE);
        // create message
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_msgs` "
                . "(`id`, `userid`, `title`, `text`, `microtime`) "
                . "VALUES('$id', $userId, '$title', '$text', $mtMark) ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createMsg: unable to create message -> '.$this->dbLink->error);
            return FALSE;
        }
        // create topic
        if (!$topicId = $this->createTopic()) {
            return FALSE;
        }
        // relate message and topic
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                . "(`id`, `tid`) "
                . "VALUES ('$id', '$topicId') ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createMsg: unable to relate message and topic -> '.$this->dbLink->error);
            return FALSE;
        }
        return $id;
    }
    
    public function stageFile($file, $filename, $userId, $title = '', $comment = '') {
        $this->zeroizeError();
        if (!is_string($file) || !is_string($filename) || !is_integer($userId) || !is_string($title) || !is_string($comment)) {
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
                . "WHERE `id`=$userId ;"
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
        $mtMark = microtime(TRUE);
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
                . "(`id`, `userid`, `title`, `name`, `comment`, `stdir`, `mime`, `size`, `microtime`) "
                . "VALUES('$id', $userId, '$title', '$filename', '$comment', '$storageDir', '$mimeType', '$fileSize', $mtMark) ;"
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
        if ($this->dbLink->errno) {
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
    
    public function getFileInfo($fileId) {
        $this->zeroizeError();
        if (!pregGuid($fileId)) {
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
            if ($this->outputType == 'xml') {
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
                "SELECT `msgtime` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
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
    
    public function getMsg($msgId) {
        $this->zeroizeError();
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
                $this->setError(ERROR_NOT_EXECUTED, 'getMsg: unable to get message -> '.$this->dbLink->error);
                return FALSE;
            }
            list($msgSource, $msgTitle, $msgText, $msgTime, $username, $fullName) = $qMsg->fetch_row();
            if ($this->outputType == 'xml') {
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
                $titleNode = $xml->createElement('title', htmlspecialchars($msgTitle));
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
    
    public function msgFiles($msgId) {
        $this->zeroizeError();
        if ($this->checkMsgAccess($msgId)) {
            $qFiles = $this->dbLink->query(
                    "SELECT `r`.`fid`, `f`.`title`, `f`.`name`, `f`.`mime` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgfile_relations` `r` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files` `f` "
                    . "ON `f`.`id`=`r`.`fid` "
                    . "WHERE `r`.`mid`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'msgFiles: unable to get message files -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->outputType == 'xml') {
                $xml = new \DOMDocument('1.0', 'utf-8');
                $filesNode = $xml->createElement('files');
                $xml->appendChild($filesNode);
                //
                $msgIdAttribute = $xml->createAttribute('msgid');
                $msgIdAttribute->value = $msgId;
                $filesNode->appendChild($msgIdAttribute);
                //
                while ($fileInfo = $qFiles->fetch_row()) {
                    if ($this->checkFileAccess($fileInfo[0])) {
                        $fileNode = $xml->createElement('file');
                        $idNode = $xml->createElement('id', $fileInfo[0]);
                        $fileNode->appendChild($idNode);
                        $titleNode = $xml->createElement('title', htmlspecialchars($fileInfo[1]));
                        $fileNode->appendChild($titleNode);
                        $nameNode = $xml->createElement('filename', htmlspecialchars($fileInfo[2]));
                        $fileNode->appendChild($nameNode);
                        $mimeNode = $xml->createElement('mime', $fileInfo[3]);
                        $fileNode->appendChild($mimeNode);
                        $filesNode->appendChild($fileNode);
                    }
                }
                return $xml;
            }
            else {
                $filesNode = array();
                //
                $filesNode['msgid'] = $msgId;
                $filesNode['files'] = array();
                while ($fileInfo = $qFiles->fetch_row()) {
                    if ($this->checkFileAccess($fileInfo[0])) {
                        $filesNode['files'][] = array(
                            'id' => $fileInfo[0],
                            'title' => htmlspecialchars($fileInfo[1]),
                            'filename' => htmlspecialchars($fileInfo[2]),
                            'mime' => $fileInfo[3]
                        );
                    }
                }
                return json_encode($filesNode);
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'msgFiles -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'msgFiles: access denied');
            return FALSE;
        }
    }
    
    public function getFileShares($fileId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getFileShares: incorrect parameters');
            return FALSE;
        }
        // check whether the user owns the file
        $this->dbLink->query("SELECT `filetime` "
                . "FROM `".MECCANO_TPREF."_core_share_files` "
                . "WHERE `id`='$fileId' "
                . "AND `userid`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getFileShares: unable to check whether the user owns the file -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getFileShares: file not found');
            return FALSE;
        }
        // get file shares
        $qShares = $this->dbLink->query("SELECT `a`.`cid`, `c`.`cname` "
                . "FROM `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `a`.`cid`=`c`.`id` "
                . "WHERE `a`.`fid`='$fileId' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getFileShares: unable to get file shares -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $sharesNode = $xml->createElement('shares');
            $xml->appendChild($sharesNode);
            //
            $fileIdAttribute = $xml->createAttribute('fileId');
            $fileIdAttribute->value = $fileId;
            $sharesNode->appendChild($fileIdAttribute);
            // check for public access
            $this->dbLink->query(
                    "SELECT `id` FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
                    . "WHERE `fid`='$fileId' "
                    . "AND `cid`='' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getFileShares: unable to check for public access -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $circleNode = $xml->createElement('circle');
                $cIdNode = $xml->createElement('id', '');
                $circleNode->appendChild($cIdNode);
                $cNameNode = $xml->createElement('name', '');
                $circleNode->appendChild($cNameNode);
                $sharesNode->appendChild($circleNode);
            }
            // check access to other circles
            while ($circleData = $qShares->fetch_row()) {
                $circleNode = $xml->createElement('circle');
                $cIdNode = $xml->createElement('id', $circleData[0]);
                $circleNode->appendChild($cIdNode);
                $cNameNode = $xml->createElement('name', htmlspecialchars($circleData[1]));
                $circleNode->appendChild($cNameNode);
                $sharesNode->appendChild($circleNode);
            }
            return $xml;
        }
        else {
            $sharesNode = array();
            $sharesNode['fileId'] = $fileId;
            $sharesNode['circles'] = array();
            // check for public access
            $this->dbLink->query(
                    "SELECT `id` FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
                    . "WHERE `fid`='$fileId' "
                    . "AND `cid`='' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getFileShares: unable to check for public access -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $sharesNode['circles'][] = array('id' => '', 'name' => '');
            }
            // check access to other circles
            while ($circleData = $qShares->fetch_row()) {
                $sharesNode['circles'][] = array('id' => $circleData[0], 'name' => $circleData[1]);
            }
            return json_encode($sharesNode);
        }
    }
    
    public function getMsgShares($msgId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getMsgShares: incorrect parameters');
            return FALSE;
        }
        // check whether the user owns the message
        $this->dbLink->query("SELECT `msgtime` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getMsgShares: unable to check whether the user owns the message -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getMsgShares: message not found');
            return FALSE;
        }
        // get message shares
        $qShares = $this->dbLink->query("SELECT `a`.`cid`, `c`.`cname` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `a`.`cid`=`c`.`id` "
                . "WHERE `a`.`mid`='$msgId' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getMsgShares: unable to get message shares -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $sharesNode = $xml->createElement('shares');
            $xml->appendChild($sharesNode);
            //
            $msgIdAttribute = $xml->createAttribute('msgId');
            $msgIdAttribute->value = $msgId;
            $sharesNode->appendChild($msgIdAttribute);
            // check for public access
            $this->dbLink->query(
                    "SELECT `id` FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
                    . "WHERE `mid`='$msgId' "
                    . "AND `cid`='' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getMsgShares: unable to check for public access ->'.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $circleNode = $xml->createElement('circle');
                $cIdNode = $xml->createElement('id', '');
                $circleNode->appendChild($cIdNode);
                $cNameNode = $xml->createElement('name', '');
                $circleNode->appendChild($cNameNode);
                $sharesNode->appendChild($circleNode);
            }
            // check access to other circles
            while ($circleData = $qShares->fetch_row()) {
                $circleNode = $xml->createElement('circle');
                $cIdNode = $xml->createElement('id', $circleData[0]);
                $circleNode->appendChild($cIdNode);
                $cNameNode = $xml->createElement('name', htmlspecialchars($circleData[1]));
                $circleNode->appendChild($cNameNode);
                $sharesNode->appendChild($circleNode);
            }
            return $xml;
        }
        else {
            $sharesNode = array();
            $sharesNode['msgId'] = $msgId;
            $sharesNode['circles'] = array();
            // check for public access
            $this->dbLink->query(
                    "SELECT `id` FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
                    . "WHERE `mid`='$msgId' "
                    . "AND `cid`='' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getMsgShares: unable to check for public access -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                $sharesNode['circles'][] = array('id' => '', 'name' => '');
            }
            // check access to other circles
            while ($circleData = $qShares->fetch_row()) {
                $sharesNode['circles'][] = array('id' => $circleData[0], 'name' => $circleData[1]);
            }
            return json_encode($sharesNode);
        }
    }
    
    public function updateFile($fileId, $userid, $title, $comment) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !is_integer($userid) || !is_string($title) || !is_string($comment)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateFile: incorrect parameters');
            return FALSE;
        }
        $title = $this->dbLink->real_escape_string($title);
        $comment = $this->dbLink->real_escape_string($comment);
        $this->dbLink->query("UPDATE `".MECCANO_TPREF."_core_share_files` "
                . "SET `title`='$title', `comment`='$comment' "
                . "WHERE `id`='$fileId' "
                . "AND `userid`=$userid ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateFile: unable to change file description -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'updateFile: file not found');
            return FALSE;
        }
        return TRUE;
    }
    
    public function repostMsg($msgId, $userId, $hlink = TRUE) {
        $this->zeroizeError();
        if (!pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'repostMsg: incorrect parameters');
            return FALSE;
        }
        // check whether user exists
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to check whether user exists -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'repostMsg: user not found');
            return FALSE;
        }
        if ($this->checkMsgAccess($msgId)) {
            // repost id
            $newMsgId = guid();
            // microtime mark
            $mtMark = microtime(TRUE);
            // copy message title and text
            $this->dbLink->query(
                    "INSERT INTO `".MECCANO_TPREF."_core_share_msgs` "
                    . "(`id`, `source`, `userid`, `title`, `text`, `microtime`) "
                    . "SELECT '$newMsgId', `id`, $userId, `title`, `text` , $mtMark"
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to repost message -> '.$this->dbLink->error);
                return FALSE;
            }
            // create topic
            if (!$topicId = $this->createTopic()) {
                return FALSE;
            }
            // relate message and topic
            $this->dbLink->query(
                    "INSERT INTO `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                    . "(`id`, `tid`) "
                    . "VALUES ('$newMsgId', '$topicId') ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to relate message and topic -> '.$this->dbLink->error);
                return FALSE;
            }
            // get files related with message
            $qFiles = $this->dbLink->query(
                    "SELECT `r`.`fid`, `f`.`stdir` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgfile_relations` `r` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files` `f` "
                    . "ON `f`.`id`=`r`.`fid` "
                    . "WHERE `r`.`mid`='$msgId' ;"
                    );
            $relFiles = array();
            $fileDirs = array();
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to get file identifiers -> '.$this->dbLink->error);
                return FALSE;
            }
            // old ids => new ids
            while ($fileData = $qFiles->fetch_row()) {
                if ($this->checkFileAccess($fileData[0])) {
                    $relFiles[$fileData[0]] = guid();
                    $fileDirs[$fileData[0]] = $fileData[1];
                }
            }
            // if message has related files
            if ($relFiles) {
                // file storage directory
                $storageDir = MECCANO_SHARED_STDIR;
                if (!is_dir(MECCANO_SHARED_FILES."/$storageDir")) {
                   if (!@mkdir(MECCANO_SHARED_FILES."/$storageDir")) {
                       $this->setError(ERROR_NOT_EXECUTED, "repostMsg: unable to create storage directory");
                       return FALSE;
                   }
                }
                // copy records of related files
                $stmtAdd = $this->dbLink->prepare(
                        "INSERT INTO `".MECCANO_TPREF."_core_share_files` "
                        . "(`id`, `userid`, `title`, `name`, `comment`, `stdir`, `mime`, `size`, `microtime`) "
                        . "SELECT ?, $userId, `title`, `name`, `comment`, '$storageDir', `mime`, `size`, ? "
                        . "FROM `meccano_core_share_files` "
                        . "WHERE `id`=?;"
                        );
                if (!$stmtAdd) {
                    $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to copy file enty -> '.$this->dbLink->error);
                    return FALSE;
                }
                // relate file to reposted message
                $stmtRelate = $this->dbLink->prepare(
                        "INSERT INTO `".MECCANO_TPREF."_core_share_msgfile_relations` "
                        . "(`id`, `mid`, `fid`, `userid`) "
                        . "VALUES(?, '$newMsgId', ?, $userId) ;"
                        );
                if (!$stmtRelate) {
                    $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to relate copied file enty -> '.$this->dbLink->error);
                    return FALSE;
                }
                foreach ($relFiles as $key => $value) {
                    $stmtAdd->bind_param('sss', $newId, $fmtMark, $oldId);
                    $stmtRelate->bind_param('ss', $newRelId, $newId);
                    $oldId = $key;
                    $fmtMark = microtime(TRUE);
                    $newRelId = guid();
                    $newId = $value;
                    $stmtAdd->execute();
                    if ($stmtAdd->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to execute copying of file enty -> '.$stmtAdd->error);
                        return FALSE;
                    }
                    $stmtRelate->execute();
                    if ($stmtRelate->errno) {
                        $this->setError(ERROR_NOT_EXECUTED, 'repostMsg: unable to execute relating of copied file enty -> '.$stmtRelate->error);
                        return FALSE;
                    }
                    if (
                            !$hlink && 
                            !Files::copy(MECCANO_SHARED_FILES."/".$fileDirs[$key]."/$key", MECCANO_SHARED_FILES."/$storageDir/$value")
                            ) {
                        $this->setError(Files::errId(), 'repostMsg -> '.Files::errExp());
                        return FALSE;
                    }
                    elseif (
                            $hlink && 
                            !@link(MECCANO_SHARED_FILES."/".$fileDirs[$key]."/$key", MECCANO_SHARED_FILES."/$storageDir/$value") &&
                            !Files::copy(MECCANO_SHARED_FILES."/".$fileDirs[$key]."/$key", MECCANO_SHARED_FILES."/$storageDir/$value")
                            ) {
                        $this->setError(Files::errId(), "repostMsg: unable to create hard link for $key -> ".Files::errExp());
                        return FALSE;
                    }
                }
                $stmtAdd->close();
                $stmtRelate->close();
            }
            return array('message' => $newMsgId, 'files' => array_values($relFiles));
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'repostMsg -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'repostMsg: access denied');
            return FALSE;
        }
    }
    
    public function delMsg($msgId, $userId, $keepFiles = TRUE) {
        $this->zeroizeError();
        if (!pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'delMsg: incorrect parameters');
            return FALSE;
        }
//        $this->dbLink = new \mysqli();
        // check whether message exists
        $this->dbLink->query("SELECT `msgtime` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' "
                . "AND `userid`=$userId ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to check message existence -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'delMsg: message not found');
            return FALSE;
        }
        if ($keepFiles) {
            // delete relations
            $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_share_msgfile_relations` "
                    . "WHERE `mid`='$msgId' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to unrelate files -> '.$this->dbLink->error);
                return FALSE;
            }
        }
        else {
            // get related files
            $qFiles = $this->dbLink->query("SELECT `r`.`fid`, `f`.`stdir` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgfile_relations` `r` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files` `f` "
                    . "ON `f`.`id`=`r`.`fid` "
                    . "WHERE `r`.`mid`='$msgId' ;");
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to get related files -> '.$this->dbLink->error);
                return FALSE;
            }
            if ($this->dbLink->affected_rows) {
                // ids and storage dirs of related files
                $relFiles = array();
                while (list($fileId, $storageDir) = $qFiles->fetch_row()) {
                    $relFiles[$fileId] = $storageDir;
                }
                // delete relations
                $stmtDelRel = $this->dbLink->prepare("DELETE FROM `".MECCANO_TPREF."_core_share_msgfile_relations` "
                        . "WHERE `fid`=? ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to delete file relations -> '.$this->dbLink->error);
                    return FALSE;
                }
                // delete file access rights
                $stmtDelAccess = $this->dbLink->prepare("DELETE FROM `".MECCANO_TPREF."_core_share_files_accessibility` "
                        . "WHERE `fid`=? ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to delete file access rights -> '.$this->dbLink->error);
                    return FALSE;
                }
                // delete file
                $stmtDelFile = $this->dbLink->prepare("DELETE FROM `".MECCANO_TPREF."_core_share_files` "
                        . "WHERE `id`=? ;");
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to delete file record -> '.$this->dbLink->error);
                    return FALSE;
                }
                foreach ($relFiles as $fid => $storageDir) {
                    $stmtDelRel->bind_param('s', $fid);
                    $stmtDelAccess->bind_param('s', $fid);
                    $stmtDelFile->bind_param('s', $fid);
                    $stmtDelRel->execute();
                    $stmtDelAccess->execute();
                    $stmtDelFile->execute();
                    if (!Files::remove(MECCANO_SHARED_FILES."/$storageDir/$fid")) {
                        $this->setError(Files::errId(), 'delMsg -> '.Files::errExp());
                        return FALSE;
                    }
                }
            }
        }
        // delete message access rights
        $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_share_msg_accessibility` "
                . "WHERE `mid`='$msgId' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to delete message access rights -> '.$this->dbLink->error);
            return FALSE;
        }
        // delete message
        $this->dbLink->query("DELETE FROM `".MECCANO_TPREF."_core_share_msgs` "
                . "WHERE `id`='$msgId' ;");
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'delMsg: unable to delete message -> '.$this->dbLink->error);
            return FALSE;
        }
        return TRUE;
    }
    
    public function repostFile($fileId, $userId, $hlink = TRUE) {
        $this->zeroizeError();
        if (!pregGuid($fileId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'repostFile: incorrect parameters');
            return FALSE;
        }
        // check whether user exists
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'repostFile: unable to check whether user exists -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'repostFile: user not found');
            return FALSE;
        }
        if ($this->checkFileAccess($fileId)) {
            // get file record
            $qFile = $this->dbLink->query(
                    "SELECT `title`, `name`, `comment`, `stdir`, `mime`, `size` "
                    . "FROM `meccano_core_share_files` "
                    . "WHERE `id`='$fileId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'repostFile: unable to get file record -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'repostFile: file not found');
                return FALSE;
            }
            list($title, $fileName, $comment, $stdir, $mimeType, $fileSize) = $qFile->fetch_row();
            $newFileId = guid();
            $mtMark = microtime(TRUE);
            // file storage directory
            $storageDir = MECCANO_SHARED_STDIR;
            if (!is_dir(MECCANO_SHARED_FILES."/$storageDir")) {
               if (!@mkdir(MECCANO_SHARED_FILES."/$storageDir")) {
                   $this->setError(ERROR_NOT_EXECUTED, "repostFile: unable to create storage directory");
                   return FALSE;
               }
            }
            // replicate file data
            $this->dbLink->query(
                    "INSERT INTO `".MECCANO_TPREF."_core_share_files` "
                    . "(`id`, `userid`, `title`, `name`, `comment`, `stdir`, `mime`, `size`, `microtime`) "
                    . "VALUES('$newFileId', $userId, '$title', '$fileName', '$comment', '$storageDir', '$mimeType', '$fileSize', $mtMark) ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'repostFile: file data not replicated -> '.$this->dbLink->error);
                return FALSE;
            }
            // replicate file
            if (
                    !$hlink && 
                    !Files::copy(MECCANO_SHARED_FILES."/".$stdir."/$fileId", MECCANO_SHARED_FILES."/$storageDir/$newFileId")
                    ) {
                $this->setError(Files::errId(), 'repostFile -> '.Files::errExp());
                return FALSE;
            }
            elseif (
                    $hlink && 
                    !@link(MECCANO_SHARED_FILES."/".$stdir."/$fileId", MECCANO_SHARED_FILES."/$storageDir/$newFileId") &&
                    !Files::copy(MECCANO_SHARED_FILES."/".$stdir."/$fileId", MECCANO_SHARED_FILES."/$storageDir/$newFileId")
                    ) {
                $this->setError(Files::errId(), "repostFile: unable to create hard link for $key -> ".Files::errExp());
                return FALSE;
            }
            return $newFileId;
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'repostFile -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'repostFile: access denied');
            return FALSE;
        }
    }
    
    public function sumUserMsgs($userId, $rpp = 20) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumUserMsgs: incorrect parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `userid`=$userId ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`m`.`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `m`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`m`.`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `m`.`userid`=$userId ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumUserMsgs: unable to count total messages -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_row();
        $totalPages = $totalRecs/$rpp;
        $remainer = fmod($totalRecs, $rpp);
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
    
    public function userMsgs($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userMsgs: incorrect parameters');
            return FALSE;
        }
        $rightEntry = array('time', 'title');
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
                $orderBy = 'time';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'userMsgs: check order parameters');
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
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userMsgs: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "userMsgs: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `source`, `title`, IF(LENGTH(`text`)>512, CONCAT(SUBSTRING(`text`, 1, 512), '...'), `text`), `msgtime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `userid`=$userId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `m`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `m`.`userid`=$userId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userMsgs: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $msgsNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $msgsNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $msgsNode->appendChild($fnameAtt);
        }
        else {
            $msgsNode = array();
            $msgsNode['username'] = $userName;
            $msgsNode['uid'] = $userId;
            $msgsNode['fullname'] = $fullName;
            $msgsNode['messages'] = array();
        }
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark) = $msgData;
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode['messages'][] = array(
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime
                );
            }
        }
        if ($this->outputType == 'xml') {
            return $xml;
        }
        else {
            return json_encode($msgsNode);
        }
    }
    
    public function msgStripe($userId, $rpp = 20) {
        $this->zeroizeError();// validate parameters
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'msgStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'msgStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this ->setError(ERROR_NOT_FOUND, "msgStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `source`, `title`, IF(LENGTH(`text`)>512, CONCAT(SUBSTRING(`text`, 1, 512), '...'), `text`), `msgtime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `m`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `m`.`userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'msgStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $msgsNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $msgsNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $msgsNode->appendChild($fnameAtt);
        }
        else {
            $msgsNode = array();
            $msgsNode['username'] = $userName;
            $msgsNode['uid'] = $userId;
            $msgsNode['fullname'] = $fullName;
            $msgsNode['messages'] = array();
        }
        // default values of min and max microtime marks
        $minMark = 0;
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode['messages'][] = array(
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime
                );
            }
        }
        if ($maxMark && !$minMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $msgsNode->appendChild($minNode);
            $msgsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $msgsNode['minmark'] = (double) $minMark;
            $msgsNode['maxmark'] = (double) $maxMark;
            return json_encode($msgsNode);
        }
    }
    
    public function appendMsgStripe($userId, $minMark, $rpp = 20) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_integer($rpp) || !is_double($minMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'appendMsgStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendMsgStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this ->setError(ERROR_NOT_FOUND, "appendMsgStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `source`, `title`, IF(LENGTH(`text`)>512, CONCAT(SUBSTRING(`text`, 1, 512), '...'), `text`), `msgtime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `userid`=$userId "
                    . "AND `microtime`<$minMark "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `m`.`userid`=$userId "
                    . "AND `m`.`microtime`<$minMark "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `a`.`cid`='' "
                    . "AND `m`.`microtime`<$minMark "
                    . "WHERE `m`.`userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendMsgStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $msgsNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $msgsNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $msgsNode->appendChild($fnameAtt);
        }
        else {
            $msgsNode = array();
            $msgsNode['username'] = $userName;
            $msgsNode['uid'] = $userId;
            $msgsNode['fullname'] = $fullName;
            $msgsNode['messages'] = array();
        }
        // default value max microtime mark
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgNode->appendChild($xml->createElement('mtmark', $mtMark));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode['messages'][] = array(
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime,
                    'mtmark' => $mtMark
                );
            }
        }
        if ($maxMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $msgsNode->appendChild($minNode);
            return $xml;
        }
        else {
            $msgsNode['minmark'] = (double) $minMark;
            return json_encode($msgsNode);
        }
    }
    
    public function updateMsgStripe($userId, $maxMark) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_double($maxMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateMsgStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateMsgStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this ->setError(ERROR_NOT_FOUND, "updateMsgStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `source`, `title`, IF(LENGTH(`text`)>512, CONCAT(SUBSTRING(`text`, 1, 512), '...'), `text`), `msgtime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` "
                    . "WHERE `userid`=$userId "
                    . "AND `microtime`>$maxMark "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `m`.`userid`=$userId "
                    . "AND `m`.`microtime`>$maxMark "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                    . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                    . "ON `a`.`mid`=`m`.`id` "
                    . "AND `a`.`cid`='' "
                    . "AND `m`.`microtime`>$maxMark "
                    . "WHERE `m`.`userid`=$userId "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateMsgStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $msgsNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $msgsNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $msgsNode->appendChild($fnameAtt);
        }
        else {
            $msgsNode = array();
            $msgsNode['username'] = $userName;
            $msgsNode['uid'] = $userId;
            $msgsNode['fullname'] = $fullName;
            $msgsNode['messages'] = array();
        }
        // default value of max microtime mark
        $maxMarkBak = $maxMark;
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgNode->appendChild($xml->createElement('mtmark', $mtMark));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode['messages'][] = array(
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime,
                    'mtmark' => $mtMark
                );
            }
        }
        // if there is not any new message
        if (!$maxMark) {
            $maxMark = $maxMarkBak;
        }
        if ($this->outputType == 'xml') {
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $msgsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $msgsNode['maxmark'] = (double) $maxMark;
            return json_encode($msgsNode);
        }
    }
    
    public function sumUserFiles($userId, $rpp = 20) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumUserFiles: incorrect parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        // if file data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `userid`=$userId ;"
                    );
        }
        // if file data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`f`.`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `f`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId ;"
                    );
        }
        // if file data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT COUNT(`f`.`id`) "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `f`.`userid`=$userId ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumUserFiles: unable to count total files -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_row();
        $totalPages = $totalRecs/$rpp;
        $remainer = fmod($totalRecs, $rpp);
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
    
    public function userFiles($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userFiles: incorrect parameters');
            return FALSE;
        }
        $rightEntry = array('time', 'title', 'mime');
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
                $orderBy = 'time';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'userFiles: check order parameters');
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
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userFiles: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "userFiles: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `title`, `name`, IF(LENGTH(`comment`)>512, CONCAT(SUBSTRING(`comment`, 1, 512), '...'), `comment`), `mime`, `size`, `filetime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `userid`=$userId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `f`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time`  "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `f`.`userid`=$userId "
                    . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userFiles: unable to get files -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $filesNode = $xml->createElement('files');
            $xml->appendChild($filesNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $filesNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $filesNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $filesNode->appendChild($fnameAtt);
        }
        else {
            $filesNode = array();
            $filesNode['username'] = $userName;
            $filesNode['uid'] = $userId;
            $filesNode['fullname'] = $fullName;
            $filesNode['files'] = array();
        }
        while ($fileData = $qResult->fetch_row()) {
            list($fileId, $title, $fileName, $comment, $mimeType, $fileSize, $fileTime, $mtMark) = $fileData;
            if ($this->outputType == 'xml') {
                $fileNode = $xml->createElement('file');
                $fileNode->appendChild($xml->createElement('id', $fileId));
                $fileNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $fileNode->appendChild($xml->createElement('filename', $fileName));
                $fileNode->appendChild($xml->createElement('comment', htmlspecialchars($comment)));
                $fileNode->appendChild($xml->createElement('mime', $mimeType));
                $fileNode->appendChild($xml->createElement('size', $fileSize));
                $fileNode->appendChild($xml->createElement('time', $fileTime));
                $filesNode->appendChild($fileNode);
            }
            else {
                $filesNode['files'][] = array(
                    'id' => $fileId,
                    'title' => htmlspecialchars($title),
                    'filename' => $fileName,
                    'comment' => htmlspecialchars($comment),
                    'mime' => $mimeType,
                    'size' => $fileSize,
                    'time' => $fileTime
                );
            }
        }
        if ($this->outputType == 'xml') {
            return $xml;
        }
        else {
            return json_encode($filesNode);
        }
    }
    
    public function fileStripe($userId, $rpp = 20) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'fileStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'fileStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "fileStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `title`, `name`, IF(LENGTH(`comment`)>512, CONCAT(SUBSTRING(`comment`, 1, 512), '...'), `comment`), `mime`, `size`, `filetime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `f`.`userid`=$userId "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time`  "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `a`.`cid`='' "
                    . "WHERE `f`.`userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'fileStripe: unable to get files -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $filesNode = $xml->createElement('files');
            $xml->appendChild($filesNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $filesNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $filesNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $filesNode->appendChild($fnameAtt);
        }
        else {
            $filesNode = array();
            $filesNode['username'] = $userName;
            $filesNode['uid'] = $userId;
            $filesNode['fullname'] = $fullName;
            $filesNode['files'] = array();
        }
        // default values of min and max microtime marks
        $minMark = 0;
        $maxMark = 0;
        //
        while ($fileData = $qResult->fetch_row()) {
            list($fileId, $title, $fileName, $comment, $mimeType, $fileSize, $fileTime, $mtMark) = $fileData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $fileNode = $xml->createElement('file');
                $fileNode->appendChild($xml->createElement('id', $fileId));
                $fileNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $fileNode->appendChild($xml->createElement('filename', $fileName));
                $fileNode->appendChild($xml->createElement('comment', htmlspecialchars($comment)));
                $fileNode->appendChild($xml->createElement('mime', $mimeType));
                $fileNode->appendChild($xml->createElement('size', $fileSize));
                $fileNode->appendChild($xml->createElement('time', $fileTime));
                $fileNode->appendChild($xml->createElement('mtmark', $mtMark));
                $filesNode->appendChild($fileNode);
            }
            else {
                $filesNode['files'][] = array(
                    'id' => $fileId,
                    'title' => htmlspecialchars($title),
                    'filename' => $fileName,
                    'comment' => htmlspecialchars($comment),
                    'mime' => $mimeType,
                    'size' => $fileSize,
                    'time' => $fileTime,
                    'mtmark' => $mtMark
                );
            }
        }
        if ($maxMark && !$minMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $filesNode->appendChild($minNode);
            $filesNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $filesNode['minmark'] = (double) $minMark;
            $filesNode['maxmark'] = (double) $maxMark;
            return json_encode($filesNode);
        }
    }
    
    public function appendFileStripe($userId, $minMark, $rpp = 20) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($rpp) || !is_double($minMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'appendFileStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendFileStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "appendFileStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `title`, `name`, IF(LENGTH(`comment`)>512, CONCAT(SUBSTRING(`comment`, 1, 512), '...'), `comment`), `mime`, `size`, `filetime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `userid`=$userId "
                    . "AND `microtime`<$minMark "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `f`.`userid`=$userId "
                    . "AND `f`.`microtime`<$minMark "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time`  "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `a`.`cid`='' "
                    . "AND `f`.`microtime`<$minMark "
                    . "WHERE `f`.`userid`=$userId "
                    . "ORDER BY `time` DESC LIMIT $rpp ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendFileStripe: unable to get files -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $filesNode = $xml->createElement('files');
            $xml->appendChild($filesNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $filesNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $filesNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $filesNode->appendChild($fnameAtt);
        }
        else {
            $filesNode = array();
            $filesNode['username'] = $userName;
            $filesNode['uid'] = $userId;
            $filesNode['fullname'] = $fullName;
            $filesNode['files'] = array();
        }
        // default value max microtime mark
        $maxMark = 0;
        //
        while ($fileData = $qResult->fetch_row()) {
            list($fileId, $title, $fileName, $comment, $mimeType, $fileSize, $fileTime, $mtMark) = $fileData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $fileNode = $xml->createElement('file');
                $fileNode->appendChild($xml->createElement('id', $fileId));
                $fileNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $fileNode->appendChild($xml->createElement('filename', $fileName));
                $fileNode->appendChild($xml->createElement('comment', htmlspecialchars($comment)));
                $fileNode->appendChild($xml->createElement('mime', $mimeType));
                $fileNode->appendChild($xml->createElement('size', $fileSize));
                $fileNode->appendChild($xml->createElement('time', $fileTime));
                $fileNode->appendChild($xml->createElement('mtmark', $mtMark));
                $filesNode->appendChild($fileNode);
            }
            else {
                $filesNode['files'][] = array(
                    'id' => $fileId,
                    'title' => htmlspecialchars($title),
                    'filename' => $fileName,
                    'comment' => htmlspecialchars($comment),
                    'mime' => $mimeType,
                    'size' => $fileSize,
                    'time' => $fileTime,
                    'mtmark' => $mtMark
                );
            }
        }
        if ($maxMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $filesNode->appendChild($minNode);
            return $xml;
        }
        else {
            $filesNode['minmark'] = (double) $minMark;
            return json_encode($filesNode);
        }
    }
    
    public function updateFileStripe($userId, $maxMark) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_double($maxMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateFileStripe: incorrect parameters');
            return FALSE;
        }
        // get username and full name
        $qUser = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` `u` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`u`.`id` "
                . "WHERE `u`.`id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateFileStripe: unable to get username and full name -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, "updateFileStripe: user not found");
            return FALSE;
        }
        //
        list($userName, $fullName) = $qUser->fetch_row();
        // if message data is required by owner
        if (isset($_SESSION[AUTH_USER_ID]) && $_SESSION[AUTH_USER_ID] == $userId) {
            $qResult = $this->dbLink->query(
                    "SELECT `id`, `title`, `name`, IF(LENGTH(`comment`)>512, CONCAT(SUBSTRING(`comment`, 1, 512), '...'), `comment`), `mime`, `size`, `filetime`, `microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` "
                    . "WHERE `userid`=$userId "
                    . "AND `microtime`>$maxMark "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        // if message data is required by not owner
        elseif (isset($_SESSION[AUTH_USER_ID])) {
            $visiterId = $_SESSION[AUTH_USER_ID];
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time` "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `f`.`userid`=$userId "
                    . "AND `f`.`microtime`>$maxMark "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                    . "ON `c`.`id`=`a`.`cid` "
                    . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                    . "ON `l`.`cid`=`c`.`id` "
                    . "WHERE `a`.`cid`='' "
                    . "OR `l`.`bid`=$visiterId "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        // if messages data is required by unauthenticated user
        else {
            $qResult = $this->dbLink->query(
                    "SELECT `f`.`id`, `f`.`title` `title`, `f`.`name`, IF(LENGTH(`f`.`comment`)>512, CONCAT(SUBSTRING(`f`.`comment`, 1, 512), '...'), `f`.`comment`), `f`.`mime`, `f`.`size`, `f`.`filetime`, `f`.`microtime` `time`  "
                    . "FROM `".MECCANO_TPREF."_core_share_files` `f` "
                    . "JOIN `".MECCANO_TPREF."_core_share_files_accessibility` `a` "
                    . "ON `a`.`fid`=`f`.`id` "
                    . "AND `a`.`cid`='' "
                    . "AND `f`.`microtime`>$maxMark "
                    . "WHERE `f`.`userid`=$userId "
                    . "ORDER BY `time` DESC ;"
                    );
        }
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateFileStripe: unable to get files -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $filesNode = $xml->createElement('files');
            $xml->appendChild($filesNode);
            $unameAtt = $xml->createAttribute('username');
            $unameAtt->value = $userName;
            $filesNode->appendChild($unameAtt);
            $uidAtt = $xml->createAttribute('uid');
            $uidAtt->value = $userId;
            $filesNode->appendChild($uidAtt);
            $fnameAtt = $xml->createAttribute('fullname');
            $fnameAtt->value = $fullName;
            $filesNode->appendChild($fnameAtt);
        }
        else {
            $filesNode = array();
            $filesNode['username'] = $userName;
            $filesNode['uid'] = $userId;
            $filesNode['fullname'] = $fullName;
            $filesNode['files'] = array();
        }
        // default value of max microtime mark
        $maxMarkBak = $maxMark;
        $maxMark = 0;
        //
        while ($fileData = $qResult->fetch_row()) {
            list($fileId, $title, $fileName, $comment, $mimeType, $fileSize, $fileTime, $mtMark) = $fileData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $fileNode = $xml->createElement('file');
                $fileNode->appendChild($xml->createElement('id', $fileId));
                $fileNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $fileNode->appendChild($xml->createElement('filename', $fileName));
                $fileNode->appendChild($xml->createElement('comment', htmlspecialchars($comment)));
                $fileNode->appendChild($xml->createElement('mime', $mimeType));
                $fileNode->appendChild($xml->createElement('size', $fileSize));
                $fileNode->appendChild($xml->createElement('time', $fileTime));
                $fileNode->appendChild($xml->createElement('mtmark', $mtMark));
                $filesNode->appendChild($fileNode);
            }
            else {
                $filesNode['files'][] = array(
                    'id' => $fileId,
                    'title' => htmlspecialchars($title),
                    'filename' => $fileName,
                    'comment' => htmlspecialchars($comment),
                    'mime' => $mimeType,
                    'size' => $fileSize,
                    'time' => $fileTime,
                    'mtmark' => $mtMark
                );
            }
        }
        // if there is not any new file
        if (!$maxMark) {
            $maxMark = $maxMarkBak;
        }
        if ($this->outputType == 'xml') {
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $filesNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $filesNode['maxmark'] = (double) $maxMark;
            return json_encode($filesNode);
        }
    }
    
    public function sumUserSubs($userId, $rpp = 20) {
        $this->zeroizeError();
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'sumUserSubs: incorrect parameters');
            return FALSE;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        $qResult = $this->dbLink->query(
                "SELECT COUNT(DISTINCT `m`.`id`) "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `b`.`bid`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `c`.`id`=`b`.`cid` "
                . "AND `c`.`userid`=$userId "
                . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "ON `m`.`id`=`a`.`mid` "
                . "AND NOT `m`.`userid`=$userId "
                . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `a`.`cid`=`l`.`cid` "
                . "WHERE `l`.`bid`=$userId "
                . "OR `a`.`cid`='' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'sumUserSubs: unable to count total messages -> '.$this->dbLink->error);
            return FALSE;
        }
        list($totalRecs) = $qResult->fetch_row();
        $totalPages = $totalRecs/$rpp;
        $remainer = fmod($totalRecs, $rpp);
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
    
    public function userSubs($userId, $pageNumber, $totalPages, $rpp = 20, $orderBy = array('time'), $ascent = FALSE) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($pageNumber) || !is_integer($totalPages) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'userSubs: incorrect parameters');
            return FALSE;
        }
        $rightEntry = array('time', 'title');
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
                $orderBy = 'time';
            }
        }
        else {
            $this->setError(ERROR_INCORRECT_DATA, 'userSubs: check order parameters');
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
        // get subscriptions
        $qResult = $this->dbLink->query(
                "SELECT DISTINCT `m`.`id`, `m`.`source`, `m`.`title` `title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time`, `u`.`id`, `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `b`.`bid`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `c`.`id`=`b`.`cid` "
                . "AND `c`.`userid`=$userId "
                . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "ON `m`.`id`=`a`.`mid` "
                . "AND NOT `m`.`userid`=$userId "
                . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `a`.`cid`=`l`.`cid` "
                . "WHERE `l`.`bid`=$userId "
                . "OR `a`.`cid`=''"
                . "ORDER BY `$orderBy` $direct LIMIT $start, $rpp ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'userSubs: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
        }
        else {
            $msgsNode = array();
        }
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark, $userId, $userName, $fullName) = $msgData;
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                // user data
                $uidAtt = $xml->createAttribute('uid');
                $uidAtt->value = $userId;
                $msgNode->appendChild($uidAtt);
                $unameAtt = $xml->createAttribute('username');
                $unameAtt->value = $userName;
                $msgNode->appendChild($unameAtt);
                $fnameAtt = $xml->createAttribute('fullname');
                $fnameAtt->value = $fullName;
                $msgNode->appendChild($fnameAtt);
                // message data
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode[] = array(
                    'uid' => $userId,
                    'username' => $userName,
                    'fullname' => $fullName,
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime
                );
            }
        }
        if ($this->outputType == 'xml') {
            return $xml;
        }
        else {
            return json_encode($msgsNode);
        }
    }
    
    public function subStripe($userId, $rpp = 20) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'subStripe: incorrect parameters');
            return FALSE;
        }
        // get subscriptions
        $qResult = $this->dbLink->query(
                "SELECT DISTINCT `m`.`id`, `m`.`source`, `m`.`title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time`, `u`.`id`, `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `b`.`bid`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `c`.`id`=`b`.`cid` "
                . "AND `c`.`userid`=$userId "
                . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "ON `m`.`id`=`a`.`mid` "
                . "AND NOT `m`.`userid`=$userId "
                . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `a`.`cid`=`l`.`cid` "
                . "WHERE `l`.`bid`=$userId "
                . "OR `a`.`cid`=''"
                . "ORDER BY `time` DESC LIMIT $rpp ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'subStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
        }
        else {
            $msgsNode = array();
        }
        // default values of min and max microtime marks
        $minMark = 0;
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark, $userId, $userName, $fullName) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                // user data
                $uidAtt = $xml->createAttribute('uid');
                $uidAtt->value = $userId;
                $msgNode->appendChild($uidAtt);
                $unameAtt = $xml->createAttribute('username');
                $unameAtt->value = $userName;
                $msgNode->appendChild($unameAtt);
                $fnameAtt = $xml->createAttribute('fullname');
                $fnameAtt->value = $fullName;
                $msgNode->appendChild($fnameAtt);
                // message data
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgNode->appendChild($xml->createElement('mtmark', $mtMark));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode[] = array(
                    'uid' => $userId,
                    'username' => $userName,
                    'fullname' => $fullName,
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime,
                    'mtmark' => $mtMark
                );
            }
        }
        if ($maxMark && !$minMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $msgsNode->appendChild($minNode);
            $msgsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $msgsNode['minmark'] = (double) $minMark;
            $msgsNode['maxmark'] = (double) $maxMark;
            return json_encode($msgsNode);
        }
    }
    
    public function appendSubStripe($userId, $minMark, $rpp = 20) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_double($minMark) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'appendSubStripe: incorrect parameters');
            return FALSE;
        }
        // get subscriptions
        $qResult = $this->dbLink->query(
                "SELECT DISTINCT `m`.`id`, `m`.`source`, `m`.`title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time`, `u`.`id`, `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`m`.`userid` "
                . "AND `m`.`microtime`<$minMark "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `b`.`bid`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `c`.`id`=`b`.`cid` "
                . "AND `c`.`userid`=$userId "
                . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "ON `m`.`id`=`a`.`mid` "
                . "AND NOT `m`.`userid`=$userId "
                . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `a`.`cid`=`l`.`cid` "
                . "WHERE `l`.`bid`=$userId "
                . "OR `a`.`cid`=''"
                . "ORDER BY `time` DESC LIMIT $rpp ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendSubStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
        }
        else {
            $msgsNode = array();
        }
        // default value max microtime mark
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark, $userId, $userName, $fullName) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                // user data
                $uidAtt = $xml->createAttribute('uid');
                $uidAtt->value = $userId;
                $msgNode->appendChild($uidAtt);
                $unameAtt = $xml->createAttribute('username');
                $unameAtt->value = $userName;
                $msgNode->appendChild($unameAtt);
                $fnameAtt = $xml->createAttribute('fullname');
                $fnameAtt->value = $fullName;
                $msgNode->appendChild($fnameAtt);
                // message data
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgNode->appendChild($xml->createElement('mtmark', $mtMark));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode[] = array(
                    'uid' => $userId,
                    'username' => $userName,
                    'fullname' => $fullName,
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime,
                    'mtmark' => $mtMark
                );
            }
        }
        if ($maxMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $msgsNode->appendChild($minNode);
            return $xml;
        }
        else {
            $msgsNode['minmark'] = (double) $minMark;
            return json_encode($msgsNode);
        }
    }
    
    public function updateSubStripe($userId, $maxMark) {
        $this->zeroizeError();
        // validate parameters
        if (!is_integer($userId) || !is_double($maxMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateSubStripe: incorrect parameters');
            return FALSE;
        }
        // get subscriptions
        $qResult = $this->dbLink->query(
                "SELECT DISTINCT `m`.`id`, `m`.`source`, `m`.`title`, IF(LENGTH(`m`.`text`)>512, CONCAT(SUBSTRING(`m`.`text`, 1, 512), '...'), `m`.`text`), `m`.`msgtime`, `m`.`microtime` `time`, `u`.`id`, `u`.`username`, `i`.`fullname` "
                . "FROM `".MECCANO_TPREF."_core_share_msgs` `m` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`m`.`userid` "
                . "AND `m`.`microtime`>$maxMark "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_buddy_list` `b` "
                . "ON `b`.`bid`=`m`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_share_circles` `c` "
                . "ON `c`.`id`=`b`.`cid` "
                . "AND `c`.`userid`=$userId "
                . "JOIN `".MECCANO_TPREF."_core_share_msg_accessibility` `a` "
                . "ON `m`.`id`=`a`.`mid` "
                . "AND NOT `m`.`userid`=$userId "
                . "LEFT OUTER JOIN `".MECCANO_TPREF."_core_share_buddy_list` `l` "
                . "ON `a`.`cid`=`l`.`cid` "
                . "WHERE `l`.`bid`=$userId "
                . "OR `a`.`cid`=''"
                . "ORDER BY `time` DESC ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateSubStripe: unable to get messages -> '.$this->dbLink->error);
            return FALSE;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $msgsNode = $xml->createElement('messages');
            $xml->appendChild($msgsNode);
        }
        else {
            $msgsNode = array();
        }
        // default value of max microtime mark
        $maxMarkBak = $maxMark;
        $maxMark = 0;
        //
        while ($msgData = $qResult->fetch_row()) {
            list($msgId, $source, $title, $text, $msgTime, $mtMark, $userId, $userName, $fullName) = $msgData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                $msgNode = $xml->createElement('message');
                // user data
                $uidAtt = $xml->createAttribute('uid');
                $uidAtt->value = $userId;
                $msgNode->appendChild($uidAtt);
                $unameAtt = $xml->createAttribute('username');
                $unameAtt->value = $userName;
                $msgNode->appendChild($unameAtt);
                $fnameAtt = $xml->createAttribute('fullname');
                $fnameAtt->value = $fullName;
                $msgNode->appendChild($fnameAtt);
                // message data
                $msgNode->appendChild($xml->createElement('id', $msgId));
                $msgNode->appendChild($xml->createElement('source', $source));
                $msgNode->appendChild($xml->createElement('title', htmlspecialchars($title)));
                $msgNode->appendChild($xml->createElement('text', $text));
                $msgNode->appendChild($xml->createElement('time', $msgTime));
                $msgNode->appendChild($xml->createElement('mtmark', $mtMark));
                $msgsNode->appendChild($msgNode);
            }
            else {
                $msgsNode[] = array(
                    'uid' => $userId,
                    'username' => $userName,
                    'fullname' => $fullName,
                    'id' => $msgId,
                    'source' => $source,
                    'title' => htmlspecialchars($title),
                    'text' => $text,
                    'time' => $msgTime,
                    'mtmark' => $mtMark
                );
            }
        }
        // if there is not any new message
        if (!$maxMark) {
            $maxMark = $maxMarkBak;
        }
        if ($this->outputType == 'xml') {
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $msgsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $msgsNode['maxmark'] = (double) $maxMark;
            return json_encode($msgsNode);
        }
    }
    
    public function createMsgComment($msgId, $userId, $comment, $parentId = '') {
        $this->zeroizeError();
        if (!pregGuid($msgId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createMsgComment: incorrect parameters');
            return FALSE;
        }
        $this->dbLink->query(
                "SELECT `username` "
                . "FROM `".MECCANO_TPREF."_core_userman_users` "
                . "WHERE `id`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createMsgComment: unable to find user -> '.$this->dbLink->error);
            return;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'createMsgComment: user not found');
            return FALSE;
        }
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            $qTopicId = $this->dbLink->query(
                    "SELECT `tid` "
                    . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                    . "WHERE `id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'createMsgComment: unable to get topic id -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'createMsgComment: topic of message not found');
                return FALSE;
            }
            list($topicId) = $qTopicId->fetch_row();
            if ($commentId = $this->createComment($comment, $userId, $topicId, $parentId)) {
                return $commentId;
            }
            return FALSE;
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'createMsgComment -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'createMsgComment: access denied');
            return FALSE;
        }
    }
    
    public function editMsgComment($comment, $commentId, $userId) {
        $this->zeroizeError();
        if(!pregGuid($commentId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'editMsgComment: incorrect parameters');
            return FALSE;
        }
        $qTopic = $this->dbLink->query(
                "SELECT `r`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` `r` "
                . "JOIN `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "ON `c`.`tid`=`r`.`tid` "
                . "AND `c`.`id`='$commentId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'editMsgComment: unable to get message identifies -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'editMsgComment: message not found');
            return FALSE;
        }
        list($msgId) = $qTopic->fetch_row();
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            if ($this->editComment($comment, $commentId, $userId)) {
                return TRUE;
            }
            else {
                FALSE;
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'editMsgComment -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'editMsgComment: access denied');
            return FALSE;
        }
    }
    
    public function getMsgComment($commentId, $userId) {
        $this->zeroizeError();
        if(!pregGuid($commentId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getMsgComment: incorrect parameters');
            return FALSE;
        }
        $qTopic = $this->dbLink->query(
                "SELECT `r`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` `r` "
                . "JOIN `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "ON `c`.`tid`=`r`.`tid` "
                . "AND `c`.`id`='$commentId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getMsgComment: unable to get message identifies -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getMsgComment: message not found');
            return FALSE;
        }
        list($msgId) = $qTopic->fetch_row();
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            if ($comment = $this->getComment($commentId, $userId)) {
                return $comment;
            }
            else {
                FALSE;
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'getMsgComment -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'getMsgComment: access denied');
            return FALSE;
        }
    }
    
    public function eraseMsgComment($commentId, $userId) {
        $this->zeroizeError();
        if(!pregGuid($commentId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'eraseMsgComment: incorrect parameters');
            return FALSE;
        }
        $qTopic = $this->dbLink->query(
                "SELECT `r`.`id` "
                . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` `r` "
                . "JOIN `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "ON `c`.`tid`=`r`.`tid` "
                . "AND `c`.`id`='$commentId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'eraseMsgComment: unable to get message identifies -> '.$this->dbLink->error);
            return FALSE;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'eraseMsgComment: message not found');
            return FALSE;
        }
        list($msgId) = $qTopic->fetch_row();
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            if ($this->eraseComment($commentId, $userId)) {
                return TRUE;
            }
            else {
                FALSE;
            }
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'eraseMsgComment -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'eraseMsgComment: access denied');
            return FALSE;
        }
    }
    
    public function getMsgComments($msgId, $rpp = 20) {
        $this->zeroizeError();
        if (!pregGuid($msgId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getMsgComments: incorrect parameters');
            return FALSE;
        }
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            $qTopicId = $this->dbLink->query(
                    "SELECT `tid` "
                    . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                    . "WHERE `id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'getMsgComments: unable to get topic id -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'getMsgComments: topic of message not found');
                return FALSE;
            }
            list($topicId) = $qTopicId->fetch_row();
            if ($comments = $this->getComments($topicId, $rpp)) {
                return $comments;
            }
            return FALSE;
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'getMsgComments -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'getMsgComments: access denied');
            return FALSE;
        }
    }
    
    public function appendMsgComments($msgId, $minMark, $rpp = 20) {
        $this->zeroizeError();
        if (!pregGuid($msgId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'appendMsgComments: incorrect parameters');
            return FALSE;
        }
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            $qTopicId = $this->dbLink->query(
                    "SELECT `tid` "
                    . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                    . "WHERE `id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'appendMsgComments: unable to get topic id -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'appendMsgComments: topic of message not found');
                return FALSE;
            }
            list($topicId) = $qTopicId->fetch_row();
            if ($comments = $this->appendComments($topicId, $minMark, $rpp)) {
                return $comments;
            }
            return FALSE;
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'appendMsgComments -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'appendMsgComments: access denied');
            return FALSE;
        }
    }
    
    public function updateMsgComments($msgId, $maxMark) {
        $this->zeroizeError();
        if (!pregGuid($msgId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateMsgComments: incorrect parameters');
            return FALSE;
        }
        if (isset($_SESSION[AUTH_USER_ID]) && $this->checkMsgAccess($msgId)) {
            $qTopicId = $this->dbLink->query(
                    "SELECT `tid` "
                    . "FROM `".MECCANO_TPREF."_core_share_msg_topic_rel` "
                    . "WHERE `id`='$msgId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'updateMsgComments: unable to get topic id -> '.$this->dbLink->error);
                return FALSE;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'updateMsgComments: topic of message not found');
                return FALSE;
            }
            list($topicId) = $qTopicId->fetch_row();
            if ($comments = $this->updateComments($topicId, $maxMark)) {
                return $comments;
            }
            return FALSE;
        }
        elseif ($this->errid) {
            $this->setError($this->errid, 'updateMsgComments -> '.$this->errexp);
            return FALSE;
        }
        else {
            $this->setError(ERROR_RESTRICTED_ACCESS, 'updateMsgComments: access denied');
            return FALSE;
        }
    }
}
