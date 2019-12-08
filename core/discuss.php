<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Core module [discuss.php].
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

interface intDiscuss {
    public function __construct(\mysqli $dbLink);
    public function createTopic($topic = '');
    public function createComment($comment, $userId, $topicId, $parentId = '');
    public function getComments($topicId, $rpp = 20);
    public function getAllComments($topicId);
    public function appendComments($topicId, $minMark, $rpp = 20);
    public function updateComments($topicId, $maxMark);
    public function editComment($comment, $commentId, $userId);
    public function getComment($commentId, $userId);
    public function eraseComment($commentId, $userId);
}

class Discuss extends ServiceMethods implements intDiscuss {

    public function __construct(\mysqli $dbLink) {
        $this->dbLink = $dbLink;
    }
    
    public function createTopic($topic = '') {
        $this->zeroizeError();
        if (!is_string($topic)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createTopic: incorrect parameter');
            return false;
        }
        $topicId = guid();
        $topicText = $this->dbLink->escape_string($topic);
        $this->dbLink->query(
                "INSERT INTO `".MECCANO_TPREF."_core_discuss_topics` "
                . "(`id`, `topic`) "
                . "VALUES('$topicId', '$topicText') ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createTopic: unable to create topic -> '.$this->dbLink->error);
            return false;
        }
        return $topicId;
    }
    
    // in case of using MyISAM storage engine you should check correctness of userid argument before you call this method
    public function createComment($comment, $userId, $topicId, $parentId = '') {
        $this->zeroizeError();
        if (!is_string($comment) || !strlen($comment) || !pregGuid($topicId) || (!pregGuid($parentId) && $parentId != '')) {
            $this->setError(ERROR_INCORRECT_DATA, 'createComment: incorrect parameters');
            return false;
        }
        if (MECCANO_DBSTORAGE_ENGINE == 'MyISAM') {
            // check whether topic exists
            $this->dbLink->query(
                    "SELECT `id` "
                    . "FROM `".MECCANO_TPREF."_core_discuss_topics` "
                    . "WHERE `id`='$topicId' ;"
                    );
            if ($this->dbLink->errno) {
                $this->setError(ERROR_NOT_EXECUTED, 'createComment: unable to check whether topic exists -> '.$this->dbLink->error);
                return false;
            }
            if (!$this->dbLink->affected_rows) {
                $this->setError(ERROR_NOT_FOUND, 'createComment: topic not found');
                return false;
            }
            if ($parentId) {
                // check whether parent comment exists
                $this->dbLink->query(
                        "SELECT `id` "
                        . "FROM `".MECCANO_TPREF."_core_discuss_comments` "
                        . "WHERE `id`='$parentId' ;"
                        );
                if ($this->dbLink->errno) {
                    $this->setError(ERROR_NOT_EXECUTED, 'createComment: unable to check whether parent comment exists -> '.$this->dbLink->error);
                    return false;
                }
                if (!$this->dbLink->affected_rows) {
                    $this->setError(ERROR_NOT_FOUND, 'createComment: parent comment not found');
                    return false;
                }
            }
        }
        $commentId = guid();
        $commentText = $this->dbLink->escape_string($comment);
        $mtMark = microtime(true);
        if ($parentId) {
            $query = "INSERT INTO `".MECCANO_TPREF."_core_discuss_comments` "
                . "(`id`, `tid`, `pcid`, `userid`, `comment`, `microtime`) "
                . "VALUES('$commentId', '$topicId', '$parentId', $userId, '$commentText', $mtMark) ;";
        }
        else {
            $query = "INSERT INTO `".MECCANO_TPREF."_core_discuss_comments` "
                . "(`id`, `tid`, `userid`, `comment`, `microtime`) "
                . "VALUES('$commentId', '$topicId', $userId, '$commentText', $mtMark) ;";
        }
        $this->dbLink->query($query);
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'createComment: unable to create comment -> '.$this->dbLink->error);
            return false;
        }
        return $commentId;
    }
    
    public function getComments($topicId, $rpp = 20) {
        $this->zeroizeError();
        if (!pregGuid($topicId) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getComments: incorrect parameter');
            return false;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        // check whether topic exists
        $qTopic = $this->dbLink->query(
                "SELECT `topic` "
                . "FROM `".MECCANO_TPREF."_core_discuss_topics` "
                . "WHERE `id`='$topicId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getComments: unable to check whether topic exists -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getComments: topic not found -> '.$this->dbLink->error);
            return false;
        }
        $topicRow = $qTopic->fetch_row();
        $topic = $topicRow[0];
        // get comments of topic
        $qComments = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname`, `c`.`id`, `c`.`pcid`, `c`.`comment`, `c`.`time`, `c`.`microtime` "
                . "FROM `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`c`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`c`.`userid` "
                . "WHERE `c`.`tid`='$topicId' "
                . "ORDER BY `c`.`microtime` DESC LIMIT $rpp ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getComments: unable to get comments -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $comsNode = $xml->createElement('comments');
            $xml->appendChild($comsNode);
        }
        else {
            $comsNode['comments'] = [];
        }
        // default values of min and max microtime marks
        $minMark = 0;
        $maxMark = 0;
        //
        while ($comData= $qComments->fetch_row()) {
            list($userName, $fullName, $comId, $parId, $text, $comTime, $mtMark) = $comData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                // create nodes
                $comNode = $xml->createElement('comment');
                $userNode = $xml->createElement('username', $userName);
                $nameNode = $xml->createElement('fullname', $fullName);
                $cidNode = $xml->createElement('cid', $comId);
                $pcidNode = $xml->createElement('pcid', $parId);
                $textNode = $xml->createElement('text', $text);
                $timeNode = $xml->createElement('time', $comTime);
                // insert nodes
                $comsNode->appendChild($comNode);
                $comNode->appendChild($userNode);
                $comNode->appendChild($nameNode);
                $comNode->appendChild($cidNode);
                $comNode->appendChild($pcidNode);
                $comNode->appendChild($textNode);
                $comNode->appendChild($timeNode);
            }
            else {
                $comsNode['comments'][] = [
                    'username' => $userName,
                    'fullname' => $fullName,
                    'cid' => $comId,
                    'pcid' => $parId,
                    'text' => $text,
                    'time' => $comTime
                ];
            }
        }
        if ($maxMark && !$minMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $topicNode = $xml->createAttribute('topic');
            $topicNode->value = $topic;
            $tidNode = $xml->createAttribute('tid');
            $tidNode->value = $topicId;
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $comsNode->appendChild($topicNode);
            $comsNode->appendChild($tidNode);
            $comsNode->appendChild($minNode);
            $comsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $comsNode['topic'] = $topic;
            $comsNode['tid'] = $topicId;
            $comsNode['minmark'] = (double) $minMark;
            $comsNode['maxmark'] = (double) $maxMark;
            if ($this->outputType == 'json') {
                return json_encode($comsNode);
            }
            else {
                return $comsNode;
            }
        }
    }
    
    public function getAllComments($topicId) {
        $this->zeroizeError();
        if (!pregGuid($topicId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getAllComments: incorrect parameter');
            return false;
        }
        // check whether topic exists
        $qTopic = $this->dbLink->query(
                "SELECT `topic` "
                . "FROM `".MECCANO_TPREF."_core_discuss_topics` "
                . "WHERE `id`='$topicId' ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllComments: unable to check whether topic exists -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getAllComments: topic not found -> '.$this->dbLink->error);
            return false;
        }
        $topicRow = $qTopic->fetch_row();
        $topic = $topicRow[0];
        // get comments of topic
        $qComments = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname`, `c`.`id`, `c`.`pcid`, `c`.`comment`, `c`.`time`, `c`.`microtime` "
                . "FROM `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`c`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`c`.`userid` "
                . "WHERE `c`.`tid`='$topicId' "
                . "ORDER BY `c`.`microtime` DESC ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getAllComments: unable to get comments -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $comsNode = $xml->createElement('comments');
            $xml->appendChild($comsNode);
        }
        else {
            $comsNode['comments'] = [];
        }
        // default values of min and max microtime marks
        $minMark = 0;
        $maxMark = 0;
        //
        while ($comData= $qComments->fetch_row()) {
            list($userName, $fullName, $comId, $parId, $text, $comTime, $mtMark) = $comData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                // create nodes
                $comNode = $xml->createElement('comment');
                $userNode = $xml->createElement('username', $userName);
                $nameNode = $xml->createElement('fullname', $fullName);
                $cidNode = $xml->createElement('cid', $comId);
                $pcidNode = $xml->createElement('pcid', $parId);
                $textNode = $xml->createElement('text', $text);
                $timeNode = $xml->createElement('time', $comTime);
                // insert nodes
                $comsNode->appendChild($comNode);
                $comNode->appendChild($userNode);
                $comNode->appendChild($nameNode);
                $comNode->appendChild($cidNode);
                $comNode->appendChild($pcidNode);
                $comNode->appendChild($textNode);
                $comNode->appendChild($timeNode);
            }
            else {
                $comsNode['comments'][] = [
                    'username' => $userName,
                    'fullname' => $fullName,
                    'cid' => $comId,
                    'pcid' => $parId,
                    'text' => $text,
                    'time' => $comTime
                ];
            }
        }
        if ($maxMark && !$minMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $topicNode = $xml->createAttribute('topic');
            $topicNode->value = $topic;
            $tidNode = $xml->createAttribute('tid');
            $tidNode->value = $topicId;
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $comsNode->appendChild($topicNode);
            $comsNode->appendChild($tidNode);
            $comsNode->appendChild($minNode);
            $comsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $comsNode['topic'] = $topic;
            $comsNode['tid'] = $topicId;
            $comsNode['minmark'] = (double) $minMark;
            $comsNode['maxmark'] = (double) $maxMark;
            if ($this->outputType == 'json') {
                return json_encode($comsNode);
            }
            else {
                return $comsNode;
            }
        }
    }
    
    public function appendComments($topicId, $minMark, $rpp = 20) {
        $this->zeroizeError();
        if (!pregGuid($topicId) || !is_double($minMark) || !is_integer($rpp)) {
            $this->setError(ERROR_INCORRECT_DATA, 'appendComments: incorrect parameter');
            return false;
        }
        if ($rpp < 1) {
            $rpp = 1;
        }
        // get comments of topic
        $qComments = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname`, `c`.`id`, `c`.`pcid`, `c`.`comment`, `c`.`time`, `c`.`microtime` "
                . "FROM `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`c`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`c`.`userid` "
                . "WHERE `c`.`tid`='$topicId' "
                . "AND `c`.`microtime`<$minMark "
                . "ORDER BY `c`.`microtime` DESC LIMIT $rpp ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'appendComments: unable to get comments -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $comsNode = $xml->createElement('comments');
            $xml->appendChild($comsNode);
        }
        else {
            $comsNode['comments'] = [];
        }
        // default values of max microtime mark
        $maxMark = 0;
        //
        while ($comData= $qComments->fetch_row()) {
            list($userName, $fullName, $comId, $parId, $text, $comTime, $mtMark) = $comData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                // create nodes
                $comNode = $xml->createElement('comment');
                $userNode = $xml->createElement('username', $userName);
                $nameNode = $xml->createElement('fullname', $fullName);
                $cidNode = $xml->createElement('cid', $comId);
                $pcidNode = $xml->createElement('pcid', $parId);
                $textNode = $xml->createElement('text', $text);
                $timeNode = $xml->createElement('time', $comTime);
                // insert nodes
                $comsNode->appendChild($comNode);
                $comNode->appendChild($userNode);
                $comNode->appendChild($nameNode);
                $comNode->appendChild($cidNode);
                $comNode->appendChild($pcidNode);
                $comNode->appendChild($textNode);
                $comNode->appendChild($timeNode);
            }
            else {
                $comsNode['comments'][] = [
                    'username' => $userName,
                    'fullname' => $fullName,
                    'cid' => $comId,
                    'pcid' => $parId,
                    'text' => $text,
                    'time' => $comTime
                ];
            }
        }
        if ($maxMark) {
            $minMark = $mtMark;
        }
        if ($this->outputType == 'xml') {
            $minNode = $xml->createAttribute('minmark');
            $minNode->value = $minMark;
            $comsNode->appendChild($minNode);
            return $xml;
        }
        else {
            $comsNode['minmark'] = (double) $minMark;
            if ($this->outputType == 'json') {
                return json_encode($comsNode);
            }
            else {
                return $comsNode;
            }
        }
    }
    
    public function updateComments($topicId, $maxMark) {
        $this->zeroizeError();
        if (!pregGuid($topicId) || !is_double($maxMark)) {
            $this->setError(ERROR_INCORRECT_DATA, 'updateComments: incorrect parameter');
            return false;
        }
        // get comments of topic
        $qComments = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname`, `c`.`id`, `c`.`pcid`, `c`.`comment`, `c`.`time`, `c`.`microtime` "
                . "FROM `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `u`.`id`=`c`.`userid` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `i`.`id`=`c`.`userid` "
                . "WHERE `c`.`tid`='$topicId' "
                . "AND `c`.`microtime`>$maxMark "
                . "ORDER BY `c`.`microtime` DESC ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'updateComments: unable to get comments -> '.$this->dbLink->error);
            return false;
        }
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $comsNode = $xml->createElement('comments');
            $xml->appendChild($comsNode);
        }
        else {
            $comsNode['comments'] = [];
        }
        // default value of max microtime mark
        $maxMarkBak = $maxMark;
        $maxMark = 0;
        //
        while ($comData= $qComments->fetch_row()) {
            list($userName, $fullName, $comId, $parId, $text, $comTime, $mtMark) = $comData;
            if (!$maxMark) {
                $maxMark = $mtMark;
            }
            if ($this->outputType == 'xml') {
                // create nodes
                $comNode = $xml->createElement('comment');
                $userNode = $xml->createElement('username', $userName);
                $nameNode = $xml->createElement('fullname', $fullName);
                $cidNode = $xml->createElement('cid', $comId);
                $pcidNode = $xml->createElement('pcid', $parId);
                $textNode = $xml->createElement('text', $text);
                $timeNode = $xml->createElement('time', $comTime);
                // insert nodes
                $comsNode->appendChild($comNode);
                $comNode->appendChild($userNode);
                $comNode->appendChild($nameNode);
                $comNode->appendChild($cidNode);
                $comNode->appendChild($pcidNode);
                $comNode->appendChild($textNode);
                $comNode->appendChild($timeNode);
            }
            else {
                $comsNode['comments'][] = [
                    'username' => $userName,
                    'fullname' => $fullName,
                    'cid' => $comId,
                    'pcid' => $parId,
                    'text' => $text,
                    'time' => $comTime
                ];
            }
        }
        // if there is not any new comment
        if (!$maxMark) {
            $maxMark = $maxMarkBak;
        }
        if ($this->outputType == 'xml') {
            $maxNode = $xml->createAttribute('maxmark');
            $maxNode->value = $maxMark;
            $comsNode->appendChild($maxNode);
            return $xml;
        }
        else {
            $comsNode['maxmark'] = (double) $maxMark;
            if ($this->outputType == 'json') {
                return json_encode($comsNode);
            }
            else {
                return $comsNode;
            }
        }
    }
    
    public function editComment($comment, $commentId, $userId) {
        $this->zeroizeError();
        if (!is_string($comment) || !pregGuid($commentId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'editComment: incorrect parameters');
            return false;
        }
        $text = $this->dbLink->escape_string($comment);
        $this->dbLink->query(
                "UPDATE `".MECCANO_TPREF."_core_discuss_comments` "
                . "SET `comment`='$text' "
                . "WHERE `id`='$commentId' "
                . "AND `userid`=$userId "
                . "AND `comment` IS NOT null ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'editComment: unable to edit comment -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'editComment: comment not found');
            return false;
        }
        return true;
    }
    
    public function getComment($commentId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($commentId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'getComment: incorrect parameters');
            return false;
        }
        $qComment = $this->dbLink->query(
                "SELECT `u`.`username`, `i`.`fullname`, `c`.`comment` "
                . "FROM `".MECCANO_TPREF."_core_discuss_comments` `c` "
                . "JOIN `".MECCANO_TPREF."_core_userman_users` `u` "
                . "ON `c`.`userid`=`u`.`id` "
                . "JOIN `".MECCANO_TPREF."_core_userman_userinfo` `i` "
                . "ON `c`.`userid` = `i`.`id` "
                . "WHERE `c`.`id`='$commentId' "
                . "AND `c`.`userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'getComment: unable to get comment -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'getComment: comment not found');
            return false;
        }
        list($userName, $fullName, $text) = $qComment->fetch_row();
        if ($this->outputType == 'xml') {
            $xml = new \DOMDocument('1.0', 'utf-8');
            $commentNode = $xml->createElement('comment');
            $xml->appendChild($commentNode);
            $uidNode = $xml->createElement('uid', $userId);
            $userNode = $xml->createElement('username', $userName);
            $fullNode = $xml->createElement('fullname', $fullName);
            $cidNode = $xml->createElement('cid', $commentId);
            $textNode = $xml->createElement('text', $text);
            $commentNode->appendChild($uidNode);
            $commentNode->appendChild($userNode);
            $commentNode->appendChild($fullNode);
            $commentNode->appendChild($cidNode);
            $commentNode->appendChild($textNode);
            return $xml;
        }
        else {
            $comment = [
                'uid' => $userId, 
                'username' => $userName, 
                'fullname' => $fullName, 
                'cid' => $commentId, 
                'text' => $text];
            if ($this->outputType == 'json') {
                return json_encode($comment);
            }
            else {
                return $comment;
            }
        }
    }
    
    public function eraseComment($commentId, $userId) {
        $this->zeroizeError();
        if (!pregGuid($commentId) || !is_integer($userId)) {
            $this->setError(ERROR_INCORRECT_DATA, 'eraseComment: incorrect parameters');
            return false;
        }
        $this->dbLink->query(
                "UPDATE `".MECCANO_TPREF."_core_discuss_comments` "
                . "SET `comment`=null "
                . "WHERE `id`='$commentId' "
                . "AND `userid`=$userId ;"
                );
        if ($this->dbLink->errno) {
            $this->setError(ERROR_NOT_EXECUTED, 'eraseComment: unable to erase comment -> '.$this->dbLink->error);
            return false;
        }
        if (!$this->dbLink->affected_rows) {
            $this->setError(ERROR_NOT_FOUND, 'eraseComment: comment not found');
            return false;
        }
        return true;
    }
}
