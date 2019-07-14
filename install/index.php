<!--
    phpMeccano v0.1.0. Web-framework written with php programming language. Web installer.
    Copyright (C) 2015-2016  Alexei Muzarov
    
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
            $xml = base64_decode("PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KCjwhLS0KICAgIHBocE1lY2Nhbm8gdjAuMS4wLiBXZWItZnJhbWV3b3JrIHdyaXR0ZW4gd2l0aCBwaHAgcHJvZ3JhbW1pbmcgbGFuZ3VhZ2UuIExvY2FsaXphdGlvbiBmaWxlIG9mIHRoZSB3ZWIgaW5zdGFsbGVyLgogICAgQ29weXJpZ2h0IChDKSAyMDE1LTIwMTYgIEFsZXhlaSBNdXphcm92CiAgICAKICAgIFRoaXMgcHJvZ3JhbSBpcyBmcmVlIHNvZnR3YXJlOyB5b3UgY2FuIHJlZGlzdHJpYnV0ZSBpdCBhbmQvb3IgbW9kaWZ5CiAgICBpdCB1bmRlciB0aGUgdGVybXMgb2YgdGhlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbnNlIGFzIHB1Ymxpc2hlZCBieQogICAgdGhlIEZyZWUgU29mdHdhcmUgRm91bmRhdGlvbjsgZWl0aGVyIHZlcnNpb24gMiBvZiB0aGUgTGljZW5zZSwgb3IKICAgIChhdCB5b3VyIG9wdGlvbikgYW55IGxhdGVyIHZlcnNpb24uCgogICAgVGhpcyBwcm9ncmFtIGlzIGRpc3RyaWJ1dGVkIGluIHRoZSBob3BlIHRoYXQgaXQgd2lsbCBiZSB1c2VmdWwsCiAgICBidXQgV0lUSE9VVCBBTlkgV0FSUkFOVFk7IHdpdGhvdXQgZXZlbiB0aGUgaW1wbGllZCB3YXJyYW50eSBvZgogICAgTUVSQ0hBTlRBQklMSVRZIG9yIEZJVE5FU1MgRk9SIEEgUEFSVElDVUxBUiBQVVJQT1NFLiAgU2VlIHRoZQogICAgR05VIEdlbmVyYWwgUHVibGljIExpY2Vuc2UgZm9yIG1vcmUgZGV0YWlscy4KICAgIAogICAgWW91IHNob3VsZCBoYXZlIHJlY2VpdmVkIGEgY29weSBvZiB0aGUgR05VIEdlbmVyYWwgUHVibGljIExpY2Vuc2UgYWxvbmcKICAgIHdpdGggdGhpcyBwcm9ncmFtOyBpZiBub3QsIHdyaXRlIHRvIHRoZSBGcmVlIFNvZnR3YXJlIEZvdW5kYXRpb24sIEluYy4sCiAgICA1MSBGcmFua2xpbiBTdHJlZXQsIEZpZnRoIEZsb29yLCBCb3N0b24sIE1BIDAyMTEwLTEzMDEgVVNBLgogICAgCiAgICBlLW1haWw6IGF6ZXhtYWlsQGdtYWlsLmNvbQogICAgZS1tYWlsOiBhemV4bWFpbEBtYWlsLnJ1CiAgICBodHRwczovL2JpdGJ1Y2tldC5vcmcvYXpleG1haWwvcGhwbWVjY2FubwotLT4KCjxpbnRlcmZhY2UgY29kZT0iZW4tVVMiIG5hbWU9IkVuZ2xpc2ggKFVTQSkiIGRpcj0ibHRyIj4KICAgIDxsb2FkaW5nPkxvYWRpbmcuLi48L2xvYWRpbmc+CiAgICA8aGVhZD5JbnN0YWxsYXRpb248L2hlYWQ+CiAgICA8cm9vdGdyb3VwPlJvb3QgZ3JvdXA8L3Jvb3Rncm91cD4KICAgIDxncm91cG5hbWU+TmFtZTo8L2dyb3VwbmFtZT4KICAgIDxncm91cGRlc2M+RGVzY3JpcHRpb246PC9ncm91cGRlc2M+CiAgICA8cm9vdHVzZXI+Um9vdCB1c2VyPC9yb290dXNlcj4KICAgIDx1c2VybmFtZT5Vc2VybmFtZTo8L3VzZXJuYW1lPgogICAgPGVudHBhc3N3PkVudGVyIHBhc3N3b3JkIChhdCBsZWFzdCA4IHN5bWJvbHMpPC9lbnRwYXNzdz4KICAgIDxyZXBhc3N3PlJlcGVhdCBwYXNzd29yZDo8L3JlcGFzc3c+CiAgICA8ZW1haWw+RS1tYWlsIGFkZHJlc3M6PC9lbWFpbD4KICAgIDxydW5pbnN0PlJ1biBpbnN0YWxsYXRpb248L3J1bmluc3Q+CiAgICA8ZGJwYXJhbT5QYXJhbWV0ZXJzIG9mIHRoZSBkYXRhYmFzZTwvZGJwYXJhbT4KICAgIDxkYnNlbmdpbmU+U3RvcmFnZSBlbmdpbmU8L2Ric2VuZ2luZT4KICAgIDxkYmFuYW1lPlVzZXJuYW1lPC9kYmFuYW1lPgogICAgPGRiYXBhc3N3PlVzZXIgcGFzc3dvcmQ8L2RiYXBhc3N3PgogICAgPGRiaG9zdD5Ib3N0PC9kYmhvc3Q+CiAgICA8ZGJwb3J0PlBvcnQ8L2RicG9ydD4KICAgIDxkYm5hbWU+RGF0YWJhc2UgbmFtZTwvZGJuYW1lPgogICAgPHRwcmVmPlByZWZpeCBvZiB0aGUgdGFibGVzPC90cHJlZj4KICAgIDxzeXNwYXRocz5TeXN0ZW0gcGF0aHM8L3N5c3BhdGhzPgogICAgPHNoYXJlZGZpbGVzPlN0b3JhZ2Ugb2Ygc2hhcmVkIGZpbGVzPC9zaGFyZWRmaWxlcz4KICAgIDxkZWZsYW5nPkRlZmF1bHQgbGFuZ3VhZ2U8L2RlZmxhbmc+CiAgICA8YmxvY2thdXRoPlRlbXBvcmFyeSBibG9ja2luZyBvZiBhdXRoZW50aWNhdGlvbjwvYmxvY2thdXRoPgogICAgPHNob3dlcnJvcnM+RGlzcGxheWluZyBvZiBlcnJvcnM8L3Nob3dlcnJvcnM+CiAgICA8bW50Y2lwPklQIGFkZHJlc3NlcyB0aGF0IGlnbm9yZSBtYWludGVuYW5jZSBtb2RlPC9tbnRjaXA+CiAgICA8aW5zdHByb2dyZXNzPkluc3RhbGxhdGlvbiBpbiBwcm9ncmVzcy4uLjwvaW5zdHByb2dyZXNzPgogICAgPGNyZWF0aW5nZGI+Q3JlYXRpb24gb2YgdGhlIGRhdGFiYXNlIGFuZCB0aGUgdGFibGVzPC9jcmVhdGluZ2RiPgogICAgPGluc3RwYWNrPkluc3RhbGxhdGlvbiBvZiB0aGUgcGFja2FnZTwvaW5zdHBhY2s+CiAgICA8Y3JlYXRpbmdyb290PkNyZWF0aW9uIG9mIHRoZSByb290IGdyb3VwIGFuZCB1c2VyPC9jcmVhdGluZ3Jvb3Q+CiAgICA8ZXJyb3I+RXJyb3I6IDwvZXJyb3I+CiAgICA8Y29tcGxldGVkPkluc3RhbGxhdGlvbiBoYXMgYmVlbiBzdWNjZXNzZnVsbHkgY29tcGxldGVkLiBUaGUgZW5naW5lIHdlbnQgdG8gZnVsbCBzcGVlZCBhbmQgcmVhZHkgdG8gdXNlIGluIHlvdXIgcHJvamVjdC48L2NvbXBsZXRlZD4KICAgIDxzZWxmcmVtb3ZlPkl0IGlzIGhpZ2hseSByZWNvbW1lbmRlZCB0byByZW1vdmUgaW5zdGFsbGVyIHdoZW4gdGhlIGluc3RhbGxhdGlvbiBpcyBmaW5pc2hlZDwvc2VsZnJlbW92ZT4KICAgIDxyZW1vdmVkPkluc3RhbGxlciBoYXMgYmVlbiBzdWNjZXNzZnVsbHkgcmVtb3ZlZDwvcmVtb3ZlZD4KPC9pbnRlcmZhY2U+Cg==");
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
