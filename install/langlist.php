<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Component of the web installer.
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

header('Content-type: application/json; charset=utf-8');

if (is_readable("lang")) {
    $langList = array();
    foreach (array_diff(scandir('lang'), array('.', '..')) as $value) {
        if (preg_match('/^[a-z]{2}-[A-Z]{2}\.json$/', $value)) {
            if (is_readable("lang/$value")) {
                $langFile = file_get_contents("lang/$value");
                $langData = json_decode($langFile);
                $langList[$langData->metadata->code] = $langData->metadata->name;
            }
        }
    }
    echo json_encode($langList);
}
else {
    echo json_encode((object) []);
}