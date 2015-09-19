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

require_once 'logman.php';

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
    
    public function createMsg($userId, $title, $text) {
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
}
