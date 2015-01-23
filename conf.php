<?php
// system configurations

// database parameters
define('MECCANO_DBANAME', 'root');
define('MECCANO_DBAPASS', 'MySQLpassw');
define('MECCANO_DBHOST', 'localhost');
define('MECCANO_DBPORT', '3306');
define('MECCANO_DBNAME', 'phpmeccano');
define('MECCANO_TPREF', 'meccano');

// system folders
define('MECCANO_ROOT_DIR', dirname(__FILE__));
define('MECCANO_CORE_DIR', MECCANO_ROOT_DIR.'/core');
define('MECCANO_TMP_DIR', MECCANO_ROOT_DIR.'/tmp');
define('MECCANO_PHP_DIR', MECCANO_ROOT_DIR.'/php');
define('MECCANO_JS_DIR', MECCANO_ROOT_DIR.'/js');
define('MECCANO_DOCUMENTS_DIR', MECCANO_ROOT_DIR.'/documents');
define('MECCANO_UNPACKED_PLUGINS', MECCANO_ROOT_DIR.'/unpacked');

//default language
define('MECCANO_DEF_LANG', 'en-US');
