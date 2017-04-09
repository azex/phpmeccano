# phpMeccano web programming framework #

phpMeccano is the open-source module-structured PHP framework. Framework phpMeccano gives API next abilities:

* to create and to manage user groups;
* to authenticate the user;
* to control access to functions through group policy;
* to create the multilingual interface;
* to copy, to move and to remove local files and folders;
* to log events;
* to create and to share messages and files between contacts of the user like in simple social network;
* to create topics to discuss and to comment them.

Plug-in system allows to extend capacity and to add new features. Current version 0.1.0 is the second alpha version.

## Requirements ##

phpMeccano requires web server (*Apache*, *NGINX* or *lighttpd*) with installed and configured PHP and MySQL/MariaDB.
phpMeccano has been tested with the following environments:

* Apache 2.2.15 (Red Hat)
* PHP 5.3.3/ 5.4.40
* MySQL 5.1.73/ 5.5.50

==================================================

* Apache 2.4.7 (Ubuntu)
* PHP 5.5.9
* MariaDB 10.0.26

==================================================

* Apache 2.4.18 (Ubuntu)
* PHP 7.0.8
* MariaDB 10.0.26

==================================================

* nginx/1.10.0 (Ubuntu)
* PHP 7.0.8
* MySQL 5.7.13

==================================================

* lighttpd/1.4.35 (Ubuntu)
* PHP 7.0.8
* MySQL 5.7.13

To run web installer you should use recent versions of Firefox, any WebKit based browser (Chromium, Google Chrome, Yandex Browser, Opera etc.) or IE10+. Web installer has been tested with desktop, iOS and Android versions of browsers.

## Installation ##

Make sure that framework is placed into the web-accessible directory. Then edit file *conf.php* and set the database parameters:

* **MECCANO_DBSTORAGE_ENGINE** - database storage engine. Available values are "*MyISAM*" and "*InnoDB*";
* **MECCANO_DBANAME** - name of the database administrator;
* **MECCANO_DBAPASS** - password of the database administrator;
* **MECCANO_DBHOST** - database host;
* **MECCANO_DBPORT** - database port;
* **MECCANO_DBNAME** - name of the database;
* **MECCANO_TPREF** - prefix of the database tables.

Also you may edit system paths at your opinion. Make sure that web server has read/write access to files and directories.

By editing value of **MECCANO_DEF_LANG** you can set default language. Initially available values are "*en-US*" (English) and "*ru-RU*" (Russian).

Save changes.

Now open web browser and go to address ```http://hostname/install/``` to run web installer.

## API Reference ##
Please, follow the [wiki page](wiki) to get the API reference. There are available English and Russian versions.

## Code example ##

To pass authentication write:


```
#!php

<?php

header('Content-Type: text/html; charset=utf-8');

require_once 'conf.php';
require_once MECCANO_CORE_DIR . '/auth.php';

$db = new mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
$auth = new core\Auth($db);

if ($auth->userLogin("your_username", "your_password")) {
    echo "You have passed authentication";
}
else {
    echo $auth->errExp();
}
```


## License ##

GNU General Public License, version 2, or (at your option) any later version.
