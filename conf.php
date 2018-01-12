<?php

/*
 *     phpMeccano v0.1.0. Web-framework written with php programming language. Configuration file [conf.php].
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

// system configurations

// database parameters
define('MECCANO_DBSTORAGE_ENGINE', 'MyISAM');
define('MECCANO_DBANAME', 'root');
define('MECCANO_DBAPASS', 'MySQLpassw');
define('MECCANO_DBHOST', 'localhost');
define('MECCANO_DBPORT', '3306');
define('MECCANO_DBNAME', 'phpmeccano');
define('MECCANO_TPREF', 'meccano');

// system paths
define('MECCANO_CONF_FILE', __FILE__);
define('MECCANO_ROOT_DIR', dirname(__FILE__));
define('MECCANO_CORE_DIR', MECCANO_ROOT_DIR.'/core');
define('MECCANO_TMP_DIR', MECCANO_ROOT_DIR.'/tmp');
define('MECCANO_PHP_DIR', MECCANO_ROOT_DIR.'/phplibs');
define('MECCANO_JS_DIR', MECCANO_ROOT_DIR.'/jslibs');
define('MECCANO_CSS_DIR', MECCANO_ROOT_DIR.'/csslibs');
define('MECCANO_DOCUMENTS_DIR', MECCANO_ROOT_DIR.'/documents');
define('MECCANO_UNPACKED_PLUGINS', MECCANO_ROOT_DIR.'/unpacked');
define('MECCANO_UNINSTALL', MECCANO_ROOT_DIR.'/uninstall');

// storage of shared files
define('MECCANO_SHARED_FILES', MECCANO_ROOT_DIR.'/shfiles');
define('MECCANO_SHARED_STDIR', date('Y-m-d'));

// default language
define('MECCANO_DEF_LANG', 'en-US');

// temporary blocking of the user authentication
define('MECCANO_AUTH_LIMIT', 5);
define('MECCANO_AUTH_BLOCK_PERIOD', '00:05:00');

// displaying of errors
define('MECCANO_SHOW_ERRORS', FALSE);
