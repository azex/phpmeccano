<!--
    phpMeccano v0.2.0. Web-framework written with php programming language. Web installer.
    Copyright (C) 2015-2019  Alexei Muzarov
    
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
    
    e-mail: azexmail@gmail.com
    e-mail: azexmail@mail.ru
    https://bitbucket.org/azexmail/phpmeccano
-->

<?php

require_once 'getconf.php';

if (defined("MECCANO_DEF_LANG") && is_string(MECCANO_DEF_LANG) && in_array(MECCANO_DEF_LANG, ["en-US", "ru-RU"])) {
    $defLang = MECCANO_DEF_LANG;
}
else {
    $defLang = "en-US";
}

if (!session_id()) {
    session_start();
}
$_SESSION = [];
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html> 
<html> 
    <head>
        <title>phpMeccano WebInstaller</title>
        <link href="css/reset.css" media="all" rel="stylesheet" type="text/css" />
        <link href="css/style.css" media="all" rel="stylesheet" type="text/css" />
        <link href="favicon.ico" rel="shortcut icon" type="image/x-icon" />
        <meta name="viewport" content="width=device-width, user-scalable=no" />
        <script type="text/javascript" src="js/webinstaller.js"></script>
    </head>
    <body>
        <div id="errormsg" class="hidden" style="position: fixed; width: 100%; z-index: 1000; top: 0; left: 0; opacity: 0.8;">
            <div class="false">
                <h1 id="error"></h1>
                <p id="errexp"></p>
            </div>
        </div>
        <div class="logo">
            <embed height="46" width="320" src="svg/logo.svg" />
        </div>
        <div id="languages" class="languages"></div>
        <div id="main" class="main" dir="">
<!--            <div id="errormsg" class="hidden" style="position: fixed; width: 100%;">
                <h1 id="error"></h1>
                <p id="errexp" class="false"></p>
                <br>
            </div>-->
            <div id="progress" class="center">
                <h1 id="loading"></h1>
                <h1 id="instprogress" class="hidden"></h1>
                <h1 id="completed" class="hidden"></h1>
                <iframe style="border: none;" name="gears" src="svg/progress-gears-animated.svg" width="200" height="112"></iframe>
                <p id="creatingdb" class="hidden"></p>
                <p id="instpack" class="hidden"></p>
                <p id="creatingroot" class="hidden"></p>
                <br>
                <p id="selfremove" class="hidden">
                    <br>
                    <span id="selfrmv"></span>
                    <br>
                    <iframe id="trash" name="trash" style="border: none;" src="svg/trash.svg" width="54" height="64"></iframe>
                    <iframe id="waitgear" name="waitgear" class="hidden" style="border: none;" src="svg/wait-gear.svg" width="62" height="62"></iframe>
                    <br>
                    <br>
                </p>
                <p id="removed" class="hidden">
                    <br>
                    <span id="rmvd"></span>
                    <br>
                    <br>
                </p>
            </div>
            <div id="settings" class="hidden">
                <h1 id="head" class="center"></h1>
                <div class="user">
                    <form action="javascript:void(0)" method="POST" name="userconf">
                        <h2 id="rootgroup"></h2>
                        <p id="groupname"></p>
                        <input type="text" name="groupname" value="" required="">
                        <p id="groupdesc"></p>
                        <textarea name="groupdesc"></textarea>
                        <h2 id="rootuser"></h2>
                        <p id="username"></p>
                        <input type="text" name="username" value="" required="">
                        <p id="entpassw"></p>
                        <input type="password" name="passw" value="" required="">
                        <p id="repassw"></p>
                        <input type="password" name="repassw" value="" required="">
                        <p id="email"></p>
                        <input type="email" name="email" value="" required="">
                        <p>
                            <input id="runinst" type="submit" name="submit" disabled value="">
                        </p>
                    </form>
                </div>
                <div class="conf">
                    <h2 id="dbparam"></h2>
                    <p>
                        <span id="dbsengine"></span> / MECCANO_DBSTORAGE_ENGINE:
                        <span id="MECCANO_DBSTORAGE_ENGINE" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="dbaname"></span> / MECCANO_DBANAME:
                        <span id="MECCANO_DBANAME" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="dbapassw"></span> / MECCANO_DBAPASS:
                        <span id="MECCANO_DBAPASS" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="dbhost"></span> / MECCANO_DBHOST:
                        <span id="MECCANO_DBHOST" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="dbport"></span> / MECCANO_DBPORT:
                        <span id="MECCANO_DBPORT" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="dbname"></span> / MECCANO_DBNAME:
                        <span id="MECCANO_DBNAME" class="false">N/A</span>
                    </p>
                    <p>
                        <span id="tpref"></span> / MECCANO_TPREF:
                        <span id="MECCANO_TPREF" class="false">N/A</span>
                    </p>
                    <h2 id="syspaths"></h2>
                    <p>
                        MECCANO_CONF_FILE:
                        <span id="MECCANO_CONF_FILE" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_ROOT_DIR:
                        <span id="MECCANO_ROOT_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_CORE_DIR:
                        <span id="MECCANO_CORE_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_TMP_DIR:
                        <span id="MECCANO_TMP_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_PHP_DIR:
                        <span id="MECCANO_PHP_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_JS_DIR:
                        <span id="MECCANO_JS_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_CSS_DIR:
                        <span id="MECCANO_CSS_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_DOCUMENTS_DIR:
                        <span id="MECCANO_DOCUMENTS_DIR" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_UNPACKED_PLUGINS:
                        <span id="MECCANO_UNPACKED_PLUGINS" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_UNINSTALL:
                        <span id="MECCANO_UNINSTALL" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_SERVICE_PAGES:
                        <span id="MECCANO_SERVICE_PAGES" class="false">N/A</span>
                    </p>
                    <h2 id="sharedfiles"></h2>
                    <p>
                        MECCANO_SHARED_FILES:
                        <span id="MECCANO_SHARED_FILES" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_SHARED_STDIR:
                        <span id="MECCANO_SHARED_STDIR" class="false">N/A</span>
                    </p>
                    <h2 id="deflang"></h2>
                    <p>
                        MECCANO_DEF_LANG:
                        <span id="MECCANO_DEF_LANG" class="false">N/A</span>
                    </p>
                    <h2 id="blockauth"></h2>
                    <p>
                        MECCANO_AUTH_LIMIT:
                        <span id="MECCANO_AUTH_LIMIT" class="false">N/A</span>
                    </p>
                    <p>
                        MECCANO_AUTH_BLOCK_PERIOD:
                        <span id="MECCANO_AUTH_BLOCK_PERIOD" class="false">N/A</span>
                    </p>
                    <h2 id="showerrors"></h2>
                    <p>
                        MECCANO_SHOW_ERRORS:
                        <span id="MECCANO_SHOW_ERRORS" class="false">N/A</span>
                    </p>
                    <h2 id="mntcip"></h2>
                    <p>
                        MECCANO_MNTC_IP:
                        <span id="MECCANO_MNTC_IP" class="false">N/A</span>
                    </p>
                </div>
            </div>
            <div class="cut"></div>
            <p class="licence center">phpMeccano v0.2.0alpha</p>
            <p class="licence center">Licensed under GNU GPLv2+. Copyright (C) 2015-2019 Alexei Muzarov</p>
        </div>
        <script>
            document.forms.userconf.onsubmit = function (value) {
                WebInstaller.submitForm('makeinstall.php?' + Math.random(), document.forms.userconf, WebInstaller.makeInstall);
            };
            document.forms.userconf.elements.groupname.oninput = WebInstaller.pregGroupName;
            document.forms.userconf.elements.username.oninput = WebInstaller.pregUserName;
            document.forms.userconf.elements.passw.oninput = WebInstaller.pregPassw;
            document.forms.userconf.elements.repassw.oninput = WebInstaller.pregRePassw;
            document.forms.userconf.elements.email.oninput = WebInstaller.pregMail;
            WebInstaller.currentLanguage = "<?php echo $defLang; ?>";
            WebInstaller.sendRequest('langlist.php?' + Math.random(), WebInstaller.showLanguages); // show list of available languages
            WebInstaller.sendRequest('lang/<?php echo $defLang; ?>.json?' + Math.random(), WebInstaller.loadLanguage); 
            setTimeout("WebInstaller.sendRequest('valconf.php?' + Math.random(), WebInstaller.showMeForm)", 2000);
            window.onerror = WebInstaller.showError;
        </script>
    </body>
</html>
