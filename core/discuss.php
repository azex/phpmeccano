<?php

/*
 *     phpMeccano v0.0.2. Web-framework written with php programming language. Core module [discuss.php].
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

interface intDiscuss {
    public function __construct(LogMan $logObject);
    public function createTopic($topic = '');
}

class Discuss extends ServiceMethods implements intDiscuss {
    private $dbLink; // database link
    private $logObject; // log object
    private $policyObject; // policy object
    
    public function __construct(LogMan $logObject) {
        $this->dbLink = $logObject->dbLink;
        $this->logObject = $logObject;
        $this->policyObject = $logObject->policyObject;
    }
    
    public function createTopic($topic = '') {
        $this->zeroizeError();
        if (!is_string($topic)) {
            $this->setError(ERROR_INCORRECT_DATA, 'createTopic: incorrect parameter');
            return FALSE;
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
            return FALSE;
        }
        return $topicId;
    }
}
