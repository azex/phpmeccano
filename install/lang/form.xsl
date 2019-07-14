<?xml version="1.0"?>

<!--
    phpMeccano v0.1.0. Web-framework written with php programming language. Transformation stylesheet for localization of the web installer.
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

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" />

    <xsl:template match="/interface">
        <div class="main" dir="{@dir}">
            <div id="error" class="hidden">
                <h1><xsl:value-of select="error" /></h1>
                <p id="errexp" class="false"></p><br/>
            </div>
            <div id="progress" class="center">
                <h1 id="loading"><xsl:value-of select="loading" /></h1>
                <h1 id="instprogress" class="hidden"><xsl:value-of select="instprogress" /></h1>
                <h1 id="completed" class="hidden"><xsl:value-of select="completed" /></h1>
                <iframe style="border: none;" name="gears" src="svg/progress-gears-animated.svg" width="200" height="112" />
                <p id="creatingdb" class="hidden"><xsl:value-of select="creatingdb" /></p>
                <p id="instpack" class="hidden"><xsl:value-of select="instpack" /></p>
                <p id="creatingroot" class="hidden"><xsl:value-of select="creatingroot" /></p>
                <br/>
                <p id="selfremove" class="hidden">
                    <br/>
                    <xsl:value-of select="selfremove" />
                    <br/>
                    <iframe id="trash" name="trash" style="border: none;" src="svg/trash.svg" width="54" height="64" />
                    <iframe id="waitgear" name="waitgear" class="hidden" style="border: none;" src="svg/wait-gear.svg" width="62" height="62" />
                    <br/>
                    <br/>
                </p>
                <p id="removed" class="hidden">
                    <br/>
                    <xsl:value-of select="removed" />
                    <br/>
                    <br/>
                </p>
                <p id="removed" class="hidden"><br/><xsl:value-of select="removed" /><br/><br/></p>
            </div>
            <div id="settings" class="hidden">
                <h1 class="center"><xsl:value-of select="head"/></h1>
                <div class="user">
                    <form action="javascript:void(0)" method="POST" name="userconf">
                        <h2>
                            <xsl:value-of select="rootgroup"/>
                        </h2>
                        <p>
                            <xsl:value-of select="groupname"/></p>
                        <input type="text" name="groupname" value="" required="" />
                        <p>
                            <xsl:value-of select="groupdesc"/></p>
                        <textarea name="groupdesc"></textarea>
                        <h2>
                            <xsl:value-of select="rootuser"/></h2>
                        <p>
                            <xsl:value-of select="username"/></p>
                        <input type="text" name="username" value="" required="" />
                        <p>
                            <xsl:value-of select="entpassw"/></p>
                        <input type="password" name="passw" value="" required="" />
                        <p>
                            <xsl:value-of select="repassw"/></p>
                        <input type="password" name="repassw" value="" required="" />
                        <p>
                            <xsl:value-of select="email"/></p>
                        <input type="email" name="email" value="" required="" />
                        <p>
                            <input type="submit" name="submit" disabled="" value="{runinst}" />
                        </p>
                    </form>
                </div>
                <div class="conf">
                    <h2><xsl:value-of select="dbparam" /></h2>
                    <p>
                        <xsl:value-of select="dbsengine" /> / MECCANO_DBSTORAGE_ENGINE:
                        <span id="MECCANO_DBSTORAGE_ENGINE"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbaname" /> / MECCANO_DBANAME:
                        <span id="MECCANO_DBANAME"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbapassw" /> / MECCANO_DBAPASS:
                        <span id="MECCANO_DBAPASS"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbhost" /> / MECCANO_DBHOST:
                        <span id="MECCANO_DBHOST"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbport" /> / MECCANO_DBPORT:
                        <span id="MECCANO_DBPORT"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbname" /> / MECCANO_DBNAME:
                        <span id="MECCANO_DBNAME"></span>
                    </p>
                    <p>
                        <xsl:value-of select="tpref" /> / MECCANO_TPREF:
                        <span id="MECCANO_TPREF"></span>
                    </p>
                    <h2><xsl:value-of select="syspaths" /></h2>
                    <p>
                        MECCANO_CONF_FILE:
                        <span id="MECCANO_CONF_FILE"></span>
                    </p>
                    <p>
                        MECCANO_ROOT_DIR:
                        <span id="MECCANO_ROOT_DIR"></span>
                    </p>
                    <p>
                        MECCANO_CORE_DIR:
                        <span id="MECCANO_CORE_DIR"></span>
                    </p>
                    <p>
                        MECCANO_TMP_DIR:
                        <span id="MECCANO_TMP_DIR"></span>
                    </p>
                    <p>
                        MECCANO_PHP_DIR:
                        <span id="MECCANO_PHP_DIR"></span>
                    </p>
                    <p>
                        MECCANO_JS_DIR:
                        <span id="MECCANO_JS_DIR"></span>
                    </p>
                    <p>
                        MECCANO_CSS_DIR:
                        <span id="MECCANO_CSS_DIR"></span>
                    </p>
                    <p>
                        MECCANO_DOCUMENTS_DIR:
                        <span id="MECCANO_DOCUMENTS_DIR"></span>
                    </p>
                    <p>
                        MECCANO_UNPACKED_PLUGINS:
                        <span id="MECCANO_UNPACKED_PLUGINS"></span>
                    </p>
                    <p>
                        MECCANO_UNINSTALL:
                        <span id="MECCANO_UNINSTALL"></span>
                    </p>
                    <p>
                        MECCANO_SERVICE_PAGES:
                        <span id="MECCANO_SERVICE_PAGES"></span>
                    </p>
                    <h2><xsl:value-of select="sharedfiles" /></h2>
                    <p>
                        MECCANO_SHARED_FILES:
                        <span id="MECCANO_SHARED_FILES"></span>
                    </p>
                    <p>
                        MECCANO_SHARED_STDIR:
                        <span id="MECCANO_SHARED_STDIR"></span>
                    </p>
                    <h2><xsl:value-of select="deflang" /></h2>
                    <p>
                        MECCANO_DEF_LANG:
                        <span id="MECCANO_DEF_LANG"></span>
                    </p>
                    <h2><xsl:value-of select="blockauth" /></h2>
                    <p>
                        MECCANO_AUTH_LIMIT:
                        <span id="MECCANO_AUTH_LIMIT"></span>
                    </p>
                    <p>
                        MECCANO_AUTH_BLOCK_PERIOD:
                        <span id="MECCANO_AUTH_BLOCK_PERIOD"></span>
                    </p>
                    <h2><xsl:value-of select="showerrors" /></h2>
                    <p>
                        MECCANO_SHOW_ERRORS:
                        <span id="MECCANO_SHOW_ERRORS"></span>
                    </p>
                    <h2><xsl:value-of select="mntcip" /></h2>
                    <p>
                        MECCANO_MNTC_IP:
                        <span id="MECCANO_MNTC_IP"></span>
                    </p>
                </div>
            </div>
            <div class="cut"></div>
            <p class="licence center">phpMeccano v0.1.0alpha</p>
            <p class="licence center">Licensed under GNU GPLv2+. Copyright (C) 2015-2017 Alexei Muzarov</p>
        </div>
    </xsl:template>
</xsl:stylesheet>
