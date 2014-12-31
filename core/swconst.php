<?php
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
define('AUTH_PASSWORD_ID', 'core_auth_pid');
define('AUTH_USER_SESSION_ID', 'core_auth_usid');
define('AUTH_IP', 'core_auth_ip');
define('AUTH_USER_AGENT', 'core_auth_uagent');

// cookie variables
define('COOKIE_USER_SESSION_ID', 'core_auth_usid');
