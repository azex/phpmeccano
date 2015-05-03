<?php

/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Core module [swconst.php].
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

/*
 * systemwide constants
 */

// error codes
define('ERROR_INCORRECT_DATA', -1); //incorrect value or type of the data
define('ERROR_NOT_EXECUTED', -2); //function or query was not executed
define('ERROR_RESTRICTED_ACCESS', -3); //access to function is restricted by policy
define('ERROR_SYSTEM_INTERVENTION', -4); //intervention that violates system structure
define('ERROR_NOT_CRITICAL', -5); //not critical error
define('ERROR_NOT_FOUND', -6); //not found
define('ERROR_ALREADY_EXISTS', -7); //already exists

// authentication session variables
define('AUTH_USERNAME', 'core_auth_uname');
define('AUTH_USER_ID', 'core_auth_uid');
define('AUTH_LIMITED', 'core_auth_limited');
define('AUTH_LANGUAGE', 'core_auth_lang');
define('AUTH_LANGUAGE_DIR', 'core_auth_lang_dir');
define('AUTH_PASSWORD_ID', 'core_auth_pid');
define('AUTH_UNIQUE_SESSION_ID', 'core_auth_usid');
define('AUTH_IP', 'core_auth_ip');
define('AUTH_USER_AGENT', 'core_auth_uagent');
define('AUTH_TOKEN', 'core_auth_token');

// cookie variables
define('COOKIE_UNIQUE_SESSION_ID', 'core_auth_usid');
