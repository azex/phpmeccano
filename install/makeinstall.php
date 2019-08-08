<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Component of the web installer.
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

header('Content-type: application/json; charset=utf-8');

require_once 'getconf.php';
require_once 'webinstaller.php';

$webinst = new \core\WebInstaller();

if (isset($_SESSION['webinstaller_step'])) {
    // do the step
    if ($_SESSION['webinstaller_step'] == 1) {
        $webinst->createDbTables();
    }
    elseif ($_SESSION['webinstaller_step'] == 2) {
        $webinst->installPackage();
    }
    elseif ($_SESSION['webinstaller_step'] == 3) {
        $webinst->groupUsers($_SESSION['user_param']);
    }
    // check step
    if (!$webinst->errId()) {
        $_SESSION['webinstaller_step'] += 1;
        $removeId = core\makeIdent();
        echo json_encode(array("response" => $_SESSION['webinstaller_step'], "rid" => $removeId));
        if ($_SESSION['webinstaller_step'] == 4) {
            $_SESSION = array();
            $_SESSION['rid'] = $removeId;
        }
    }
    else {
        echo json_encode(array("response" => FALSE, "error" => $webinst->errExp()));
        $_SESSION = array();
    }
}
else {
    if ($webinst->revalidateAll($_POST)) {
        $_SESSION['webinstaller_step'] = 1;
        $_SESSION['user_param'] = $_POST;
        echo json_encode(array("response" => $_SESSION['webinstaller_step']));
    }
    else {
        echo json_encode(array("response" => 0, "error" => $webinst->errExp()));
    }
}
