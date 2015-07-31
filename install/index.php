<!--
    phpMeccano v0.0.1. Web-framework written with php programming language. Web installer.
    Copyright (C) 2015  Alexei Muzarov
    
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
        <div class="logo">
            <embed height="46" width="320" src="svg/logo.svg" />
        </div>
        <?php
        require_once 'getconf.php';
        require_once MECCANO_CORE_DIR . '/unifunctions.php';
        
        if (!session_id()) {
            session_start();
        }
        $_SESSION = array();
        
        $code = MECCANO_DEF_LANG;
        $rng = new DOMDocument();
        $xml = core\openRead("lang/$code.xml");
        if (!$xml || !@$rng->loadXML($xml) || !@$rng->relaxNGValidate("lang/schema.rng")) {
            $xml = base64_decode("PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KCjwhLS0KICAgIHBocE1lY2Nhbm8gdjAuMC4xLiBXZWItZnJhbWV3b3JrIHdyaXR0ZW4gd2l0aCBwaHAgcHJvZ3JhbW1pbmcgbGFuZ3VhZ2UuIExvY2FsaXphdGlvbiBmaWxlIG9mIHRoZSB3ZWIgaW5zdGFsbGVyLgogICAgQ29weXJpZ2h0IChDKSAyMDE1ICBBbGV4ZWkgTXV6YXJvdgogICAgCiAgICBUaGlzIHByb2dyYW0gaXMgZnJlZSBzb2Z0d2FyZTsgeW91IGNhbiByZWRpc3RyaWJ1dGUgaXQgYW5kL29yIG1vZGlmeQogICAgaXQgdW5kZXIgdGhlIHRlcm1zIG9mIHRoZSBHTlUgR2VuZXJhbCBQdWJsaWMgTGljZW5zZSBhcyBwdWJsaXNoZWQgYnkKICAgIHRoZSBGcmVlIFNvZnR3YXJlIEZvdW5kYXRpb247IGVpdGhlciB2ZXJzaW9uIDIgb2YgdGhlIExpY2Vuc2UsIG9yCiAgICAoYXQgeW91ciBvcHRpb24pIGFueSBsYXRlciB2ZXJzaW9uLgoKICAgIFRoaXMgcHJvZ3JhbSBpcyBkaXN0cmlidXRlZCBpbiB0aGUgaG9wZSB0aGF0IGl0IHdpbGwgYmUgdXNlZnVsLAogICAgYnV0IFdJVEhPVVQgQU5ZIFdBUlJBTlRZOyB3aXRob3V0IGV2ZW4gdGhlIGltcGxpZWQgd2FycmFudHkgb2YKICAgIE1FUkNIQU5UQUJJTElUWSBvciBGSVRORVNTIEZPUiBBIFBBUlRJQ1VMQVIgUFVSUE9TRS4gIFNlZSB0aGUKICAgIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbnNlIGZvciBtb3JlIGRldGFpbHMuCiAgICAKICAgIFlvdSBzaG91bGQgaGF2ZSByZWNlaXZlZCBhIGNvcHkgb2YgdGhlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbnNlIGFsb25nCiAgICB3aXRoIHRoaXMgcHJvZ3JhbTsgaWYgbm90LCB3cml0ZSB0byB0aGUgRnJlZSBTb2Z0d2FyZSBGb3VuZGF0aW9uLCBJbmMuLAogICAgNTEgRnJhbmtsaW4gU3RyZWV0LCBGaWZ0aCBGbG9vciwgQm9zdG9uLCBNQSAwMjExMC0xMzAxIFVTQS4KICAgIAogICAgZS1tYWlsOiBhemV4bWFpbEBnbWFpbC5jb20KICAgIGUtbWFpbDogYXpleG1haWxAbWFpbC5ydQogICAgaHR0cHM6Ly9iaXRidWNrZXQub3JnL2F6ZXhtYWlsL3BocG1lY2Nhbm8KLS0+Cgo8aW50ZXJmYWNlIGNvZGU9ImVuLVVTIiBuYW1lPSJFbmdsaXNoIChVU0EpIiBkaXI9Imx0ciI+CiAgICA8bG9hZGluZz5Mb2FkaW5nLi4uPC9sb2FkaW5nPgogICAgPGhlYWQ+SW5zdGFsbGF0aW9uPC9oZWFkPgogICAgPHJvb3Rncm91cD5Sb290IGdyb3VwPC9yb290Z3JvdXA+CiAgICA8Z3JvdXBuYW1lPk5hbWU6PC9ncm91cG5hbWU+CiAgICA8Z3JvdXBkZXNjPkRlc2NyaXB0aW9uOjwvZ3JvdXBkZXNjPgogICAgPHJvb3R1c2VyPlJvb3QgdXNlcjwvcm9vdHVzZXI+CiAgICA8dXNlcm5hbWU+VXNlcm5hbWU6PC91c2VybmFtZT4KICAgIDxlbnRwYXNzdz5FbnRlciBwYXNzd29yZCAoYXQgbGVhc3QgOCBzeW1ib2xzKTwvZW50cGFzc3c+CiAgICA8cmVwYXNzdz5SZXBlYXQgcGFzc3dvcmQ6PC9yZXBhc3N3PgogICAgPGVtYWlsPkUtbWFpbCBhZGRyZXNzOjwvZW1haWw+CiAgICA8cnVuaW5zdD5SdW4gaW5zdGFsbGF0aW9uPC9ydW5pbnN0PgogICAgPGRicGFyYW0+UGFyYW1ldGVycyBvZiB0aGUgZGF0YWJhc2U8L2RicGFyYW0+CiAgICA8ZGJzZW5naW5lPlN0b3JhZ2UgZW5naW5lPC9kYnNlbmdpbmU+CiAgICA8ZGJhbmFtZT5Vc2VybmFtZTwvZGJhbmFtZT4KICAgIDxkYmFwYXNzdz5Vc2VyIHBhc3N3b3JkPC9kYmFwYXNzdz4KICAgIDxkYmhvc3Q+SG9zdDwvZGJob3N0PgogICAgPGRicG9ydD5Qb3J0PC9kYnBvcnQ+CiAgICA8ZGJuYW1lPkRhdGFiYXNlIG5hbWU8L2RibmFtZT4KICAgIDx0cHJlZj5QcmVmaXggb2YgdGhlIHRhYmxlczwvdHByZWY+CiAgICA8c3lzcGF0aHM+U3lzdGVtIHBhdGhzPC9zeXNwYXRocz4KICAgIDxkZWZsYW5nPkRlZmF1bHQgbGFuZ3VhZ2U8L2RlZmxhbmc+CiAgICA8aW5zdHByb2dyZXNzPkluc3RhbGxhdGlvbiBpbiBwcm9ncmVzcy4uLjwvaW5zdHByb2dyZXNzPgogICAgPGNyZWF0aW5nZGI+Q3JlYXRpb24gb2YgdGhlIGRhdGFiYXNlIGFuZCB0aGUgdGFibGVzPC9jcmVhdGluZ2RiPgogICAgPGluc3RwYWNrPkluc3RhbGxhdGlvbiBvZiB0aGUgcGFja2FnZTwvaW5zdHBhY2s+CiAgICA8Y3JlYXRpbmdyb290PkNyZWF0aW9uIG9mIHRoZSByb290IGdyb3VwIGFuZCB1c2VyPC9jcmVhdGluZ3Jvb3Q+CiAgICA8ZXJyb3I+RXJyb3I6IDwvZXJyb3I+CiAgICA8Y29tcGxldGVkPkluc3RhbGxhdGlvbiBoYXMgYmVlbiBzdWNjZXNzZnVsbHkgY29tcGxldGVkLiBUaGUgZW5naW5lIHdlbnQgdG8gZnVsbCBzcGVlZCBhbmQgcmVhZHkgdG8gdXNlIGluIHlvdXIgcHJvamVjdC48L2NvbXBsZXRlZD4KICAgIDxzZWxmcmVtb3ZlPkl0IGlzIGhpZ2hseSByZWNvbW1lbmRlZCB0byByZW1vdmUgaW5zdGFsbGVyIHdoZW4gdGhlIGluc3RhbGxhdGlvbiBpcyBmaW5pc2hlZDwvc2VsZnJlbW92ZT4KICAgIDxyZW1vdmVkPkluc3RhbGxlciBzdWNjZXNzZnVsbHkgcmVtb3ZlZDwvcmVtb3ZlZD4KPC9pbnRlcmZhY2U+Cg==");
        }
        $xsl = core\openRead("lang/form.xsl");
        echo core\xmlTransform($xml, $xsl)->saveHTML();
        
        ?>
        <script>
            document.forms.userconf.onsubmit = function(value) { WebInstaller.submitForm('makeinstall.php?' + Math.random(), document.forms.userconf, WebInstaller.makeInstall); } ;
            document.forms.userconf.elements.groupname.oninput = WebInstaller.pregGroupName;
            document.forms.userconf.elements.username.oninput = WebInstaller.pregUserName;
            document.forms.userconf.elements.passw.oninput = WebInstaller.pregPassw;
            document.forms.userconf.elements.repassw.oninput = WebInstaller.pregRePassw;
            document.forms.userconf.elements.email.oninput = WebInstaller.pregMail;
            setTimeout("WebInstaller.sendRequest('valconf.php?' + Math.random(), WebInstaller.showMeForm)", 2000);
        </script>
    </body>
</html>
