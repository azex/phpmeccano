# phpMeccano - a framework for development of web services #

phpMeccano is an open-source module-structured PHP framework. Framework phpMeccano gives APIs with the following abilities:

* creation and management of the user groups;
* authentication of the users, including 2FA;
* control of access to functions through the group policy;
* creation of the multilingual interface;
* copying, moving and removing of the local files and folders;
* logging of the events;
* creation and sharing of the messages and files between contacts of the user like in simple social network;
* creation of the topics to discuss and to comment them;
* management of the web service's maintenance mode.

Plug-in system allows to extend capabilities and to add new features. Current framework version is the third alpha version.

## Requirements ##

phpMeccano requires web server (*Apache*, *NGINX* or *lighttpd*) with installed and configured PHP and MySQL/MariaDB.  
phpMeccano was tested with the following environments:

* **Debian 10**
* Apache 2.4.38
* PHP 7.3.9 
* MariaDB 10.3.17

===================================

* **CentOS Linux 7**
* Apache 2.4.6
* PHP 5.4.16 
* MariaDB 5.5.64

===================================

* **Ubuntu 18.04**
* Apache 2.4.29
* PHP 7.2.19
* MySQL 5.7.27

===================================

* **Ubuntu 18.04**
* nginx/1.14.0
* PHP 7.2.19
* MySQL 8.0.17

===================================

* **Ubuntu 18.04**
* lighttpd/1.4.45
* PHP 7.2.19
* MySQL 8.0.17

To run web installer you should use the recent versions of Firefox or Pale Moon; any WebKit/Blink based browser (Chromium, Google Chrome, Yandex Browser, Opera, Safari etc.); or Microsoft Edge. Web installer has been tested with desktop, iOS and Android versions of browsers.

## Installation ##

Make sure that framework components are placed into the web-accessible directory. Then edit file *conf.php* and set the database parameters:

* **MECCANO_DBSTORAGE_ENGINE** - database storage engine. Available values are "*MyISAM*" and "*InnoDB*";
* **MECCANO_DBANAME** - name of the database administrator;
* **MECCANO_DBAPASS** - password of the database administrator;
* **MECCANO_DBHOST** - database host;
* **MECCANO_DBPORT** - database port;
* **MECCANO_DBNAME** - name of the database;
* **MECCANO_TPREF** - prefix of the database tables.

Also you may edit system paths at your opinion. Make sure that web server has read/write access to files and directories.

By editing value of **MECCANO_DEF_LANG** you can set default language. Initially available values are "*en-US*" (English) and "*ru-RU*" (Russian).

*Refer to the documentation to get more info.*

Save changes.

Now open web browser and go to address ```http://hostname/install/``` to run web installer.

## API Reference ##

Please, follow the wiki to get the API reference. There are available English and Russian versions.

[Bitbucket](https://bitbucket.org/azexmail/phpmeccano/wiki)  
[GitHub](https://github.com/azex/phpmeccano/wiki)

## Code example ##

Write the following code to pass an authentication:

```
#!php

<?php

header('Content-Type: text/html; charset=utf-8');

require_once 'conf.php';
\core\loadPHP('auth');

$db = \core\dbLink();
$auth = new \core\Auth($db);

$auth_code = $auth->userLogin("your_username", "your_password");
if (is_string($auth_code)) {
    if ($auth->login2FA($auth_code)) {
        echo "You have passed two-factor authentication";
    }
    else {
        echo $auth->errExp();
    }
}
elseif ($auth_code) {
    echo "You have passed single-factor authentication";
}
else {
    echo $auth->errExp();
}
```

## License ##

GNU General Public License, version 2, or (at your option) any later version.
