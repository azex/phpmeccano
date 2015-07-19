<?xml version="1.0"?>
<!-- comment -->
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
                        <xsl:value-of select="dbsengine" /> (MECCANO_DBSTORAGE_ENGINE):
                        <span id="MECCANO_DBSTORAGE_ENGINE"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbaname" /> (MECCANO_DBANAME):
                        <span id="MECCANO_DBANAME"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbapassw" /> (MECCANO_DBAPASS):
                        <span id="MECCANO_DBAPASS"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbhost" /> (MECCANO_DBHOST):
                        <span id="MECCANO_DBHOST"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbport" /> (MECCANO_DBPORT):
                        <span id="MECCANO_DBPORT"></span>
                    </p>
                    <p>
                        <xsl:value-of select="dbname" /> (MECCANO_DBNAME):
                        <span id="MECCANO_DBNAME"></span>
                    </p>
                    <p>
                        <xsl:value-of select="tpref" /> (MECCANO_TPREF):
                        <span id="MECCANO_TPREF"></span>
                    </p>
                    <h2><xsl:value-of select="syspaths" /></h2>
                    <p>
                        MECCANO_CONF_FILE
                        <span id="MECCANO_CONF_FILE"></span>
                    </p>
                    <p>
                        MECCANO_ROOT_DIR
                        <span id="MECCANO_ROOT_DIR"></span>
                    </p>
                    <p>
                        MECCANO_CORE_DIR
                        <span id="MECCANO_CORE_DIR"></span>
                    </p>
                    <p>
                        MECCANO_TMP_DIR
                        <span id="MECCANO_TMP_DIR"></span>
                    </p>
                    <p>
                        MECCANO_PHP_DIR
                        <span id="MECCANO_PHP_DIR"></span>
                    </p>
                    <p>
                        MECCANO_JS_DIR
                        <span id="MECCANO_JS_DIR"></span>
                    </p>
                    <p>
                        MECCANO_DOCUMENTS_DIR
                        <span id="MECCANO_DOCUMENTS_DIR"></span>
                    </p>
                    <p>
                        MECCANO_UNPACKED_PLUGINS
                        <span id="MECCANO_UNPACKED_PLUGINS"></span>
                    </p>
                    <p>
                        MECCANO_UNINSTALL
                        <span id="MECCANO_UNINSTALL"></span>
                    </p>
                    <h2><xsl:value-of select="deflang" /></h2>
                    <p>
                        MECCANO_DEF_LANG
                        <span id="MECCANO_DEF_LANG"></span>
                    </p>
                </div>
            </div>
            <div class="cut"></div>
            
        </div>
    </xsl:template>
</xsl:stylesheet>
