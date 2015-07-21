<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Component of the web installer.
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
 */

header('Content-Type: text/plain; charset=utf-8');

require_once 'conf.php';
require_once MECCANO_CORE_DIR.'/files.php';

if (!session_id()) {
    session_start();
}

if (isset($_GET['rid']) && isset($_SESSION['rid']) && (isset($_GET['rid']) == isset($_SESSION['rid']))) {
    $path = dirname(__FILE__);
    if (\core\Files::remove($path)) {
        echo json_encode(array("response" => true));
    }
    else {
        echo json_encode(array("response" => false, "error" => \core\Files::errExp()));    }
}
else {
    echo json_encode(array("response" => false, "error" => "unauthorized request for self-removing"));
}
