<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Core module [__loader__.php].
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

// a function to load PHP libraries of the core or any installed plugin
function loadPHP($lib, $plugin = "core") {
    if ($plugin == "core") {
        if (is_file(MECCANO_CORE_DIR."/$lib.php")) {
            require_once MECCANO_CORE_DIR."/$lib.php";
            return TRUE;
        }
    }
    else {
        if (is_file(MECCANO_PHP_DIR."/$plugin/$lib.php")) {
            require_once MECCANO_PHP_DIR."/$plugin/$lib.php";
            return TRUE;
        }
    }
    return FALSE;
}

// a function to load JavaScript libraries of the core or any installed plugin
function loadJS($lib, $plugin = "core") {
    if (is_file(MECCANO_JS_DIR."/$plugin/$lib.js")) {
        return file_get_contents(MECCANO_JS_DIR."/$plugin/$lib.js");
    }
    return FALSE;
}

// a function to load CSS libraries of the core or any installed plugin
function loadCSS($lib, $plugin = "core") {
    if (is_file(MECCANO_CSS_DIR."/$plugin/$lib.css")) {
        return file_get_contents(MECCANO_CSS_DIR."/$plugin/$lib.css");
    }
    return FALSE;
}
