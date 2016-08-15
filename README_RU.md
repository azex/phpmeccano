# phpMeccano web programming framework #

phpMeccano is the open-source module-structured PHP framework. Framework phpMeccano gives API next abilities:

* to create and to manage user groups;
* to authenticate the user;
* to control access to functions through group policy;
* to create the multilingual interface;
* to copy, to move and to remove local files and folders;
* to log events.

Plug-in system allows to extend capacity and to add new features. Current version 0.0.1 of this framework is the first alpha and it is not recommended to use in production.

## Requirements ##

phpMeccano requires web server with installed and configured PHP and MySQL/MariaDB.
phpMeccano has been tested with the following environments:

* Apache 2.2.15 (Red Hat)
* PHP 5.3.3/ 5.4.40
* MariaDB 5.5
* MySQL 5.1.73/ 5.5.41

==================================================

* Apache 2.4.7 (Ubuntu)
* PHP 5.5.9
* MariaDB 10.0.20

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

Then open web browser and go to address ```http://hostname/install/``` to run web installer.

Now save changes.

## API Reference ##
Russian: [EPUB](https://bitbucket.org/azexmail/phpmeccano/downloads/phpmeccano_api_reference_russian.epub), [PDF](https://bitbucket.org/azexmail/phpmeccano/downloads/phpmeccano_api_reference_russian.pdf).

English: coming...

## Code example ##

To pass authentication write:


```
#!php

<?php

require_once 'conf.php';
require_once MECCANO_CORE_DIR . '/auth.php';

$db = new mysqli(MECCANO_DBHOST, MECCANO_DBANAME, MECCANO_DBAPASS, MECCANO_DBNAME, MECCANO_DBPORT);
$policy = new core\Policy($db);
$log = new core\LogMan($policy);
$auth = new core\Auth($log);

if ($auth->userLogin("your_username", "your_password")) {
    echo "You have passed authentication";
}
else {
    echo $auth->errExp();
}
```


## License ##

GNU General Public License, version 2, or (at your option) any later version.
