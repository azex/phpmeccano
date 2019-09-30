# Changelog #

## v0.2.0 ##

1) Web-installer:

* in component *webinstaller.php*:
    * added comments to some tables of the installed database;
    * little fix to prevent an appearance of errors while the installation of the database to new MySQL versions;
    * fixed some other bugs;
* corrected the headers inside of components *makeinstall.php*, *valconf.php* and *selfremove.php*;
* fixed the interface styles to display them correctly on Firefox browser on Ubuntu OS while using of some 3rd party desktop themes;
* added an ability to change the web interface language without the page reloading;
* considerably improved the caching and processing of errors.

2) In module *plugins.php* in class **Plugins**:

* fixed the bug that had been made by a misprint while the coding;
* fixed the bug that appeared in methods **unpack** and **install** on php7.2;
* method **pluginData** now returns only **false** and doesn't set an error code if a requested plug-in isn't found.

3) In module *auth.php* in class **Auth**:

* fixed the bug of not saving the cookies;
* added an ability to use the e-mail address equally with the username in method **userLogin**;
* added an ability to create an unlimited number of the sessions for every password of the user;
* added an ability to pass the first step of 2-factor authentication through method **userLogin**;
* added new method **login2FA** to pass the second step of 2-factor authentication;
* added a new session variable with the key **AUTH_2FA_SWAP** in which are saved session variables of the user until the passing of the second step of the authentication;
* added new method **userSessions** to get an information about actual sessions of the user (the access is controled by the new policy *'auth_user_sessions'*);
* added new method **destroySession** to delete a defined session of the user (the access is controled by the new policy *'auth_destroy_sessions'*);
* added new method **destroyAllSessions** to delete all sessions of the user (the access is controled by the new policy *'auth_destroy_sessions'*).

4) In module *userman.php* in class **UserMan**:

* added new method **enable2FA** to enable 2-factor authentication of the passwords;
* added new method **disable2FA** to disable 2-factor authentication of the passwords.

5) In module *discuss.php* in class **Discuss**:

* added new method **getAllComments** to get instantly all the comments to the topic with the defined identifier.

6) В модуле *share.php* в классе **Share**:

* added new method **getMsgAllComments** to get instantly all the comments to the message with the defined identifier;
*  in case of failures method **getFile** now returns pages of the HTTP-statuses instead of **false**.

7) The specification of the installation package of the plug-ins is updated - added an ability to install CSS-styles. The specification is raised to 0.3.

8) New core module *\_\_loader\_\_.php*, that is loaded with configuration file *conf.php*, contains:

* function **loadPHP** to load PHP libraries of the core or any other installed plug-in;
* function **loadJS** to load JavaScript libraries of the core or any other installed plug-in;
* function **loadCSS** to load CSS libraries of the core or any other installed plug-in;
* function **loadDOC** to get files from the core documents or any other installed plug-in with the help of *mod_xsendfile* (for *Apache2*) or *X-Accel-Redirect* (for *NGINX*) or *X-LIGHTTPD-send-file* (for *lighttpd*);
* function **mntc** to replace a requested page by the maintenance mode stub-page of the web service.

9) New core module *maintenance.php* with class **Maintenance** to manage the maintenance mode contains:
* method **state** to get settings of the maintenance mode;
* method **write** to write settings of the maintenance mode;
* method **enable** to enable the maintenance mode;
* method **disable** to disable the maintenance mode;
* method **timeout** to set an estimated time of the work of the maintenance mode;
* method **startpoint** to set a start point of the timeout counting out of the maintenance mode;
* method **prmsg** to set a primary message of the maintenance mode;
* method **secmsg** to set a secondary message of the maintenance mode;
* method **reset** to reset settings of the maintenance mode at default values;
* the access to the methods is controled by new policy *'maintenance_configure'*.

10) In configuration file *conf.php*:

* added new constant **MECCANO_CSS_DIR** to define a path to the folder storing CSS-styles of the installed plug-ins;
* added new constant **MECCANO_SERVICE_PAGES** to define a path to the folder storing pages of the HTTP-statuses, a stub-page of the maintenance mode and a file with the settings of the maintenance mode;
* added new constant **MECCANO_MNTC_IP** to define IP addresses which aren't affected by the maintenance mode;
* core module **__loader__.php** is loaded with the configuration file.

11) In module *extclass.php* in class **ServiceMethods**:

* added new format *'array'* to method **outputFormat**;
* new argument *$userId* is added to method **checkFuncAccess** to provide an user access to the functions bypassing access policies in cases connected to  the user's personal data and thus reducing a load at the database.

12) In module *langman.php* in class **LangMan**:

* the following methods are renamed:
    * **getTitleSectionsXML** => **getTitleSectionsList**;
    * **getTitleNamesXML** => **getTitleNamesList**;
    * **getTitlesXML** => **getTitlesList**;
    * **getAllTitlesXML** => **getAllTitlesList**;
    * **getTextSectionsXML** => **getTextSectionsList**;
    * **getTextNamesXML** => **getTextNamesList**;
    * **getTextsXML** => **getTextNamesList**;
    * **getAllTextsXML** => **getTextNamesList**.

## v0.1.0 ##

1) New module *discuss.php* with class **Discuss** to create topics to discuss and to comment something;

2) New module *share.php* with **Share** to exchange messages and files like in simple social network. New access policies have been added together with the module:

* policy *share_viewing_access* refers to methods:
	* **getFile**, **getFileInfo**, **getMsg**, **msgFiles**, **sumUserMsgs**, **userMsgs**, **msgStripe**, **appendMsgStripe**, **updateMsgStripe**, **sumUserFiles**, **userFiles**, **fileStripe**, **appendFileStripe**, **updateFileStripe**, **getMsgComment**, **getMsgComments**, **appendMsgComments**, **updateMsgComments** и **repostMsg**.

* policy *share_modify_msgs_files* refers to methods:
	* **delFile**, **updateFile**, **delMsg** и **updateMsg**.

* policy *share_modify_comments* refers to methods:
	* **editMsgComment** и **eraseMsgComment**.

3) Changes in module *unifunctions.php*:

* new function **pregDbName** to validate name of being created database;
* allowed length of incoming string in function **pregPref** was reduced to 10 symbols;
* symbol *№* was exclude from allwed symbols in passwords (**pregPassw**);
* function *rand* is not used any more in generating salt (**makeSalt**) and identifiers (**makeIdent**), it was replaced with function *mt_rand*;
* new function **genPassword**  to generate random passwords by defined criterion of complexity;
* new function **guid** to generate globally unique identifier;
* new function **pregGuid** to validate incoming string to match format of globally unique identifier;

4) Changes in module *auth.php*:

* new argument *blockBrute* was added to method **userLogin** to temporarily block authentication of user by password after some attempts of input of incorrect passwords;
* new constants were added to configuration file *conf.php*. **MECCANO_AUTH_LIMIT** sets number of allowed attempts of input of incorrect passwords before temporary blocking of user authentication, **MECCANO_AUTH_BLOCK_PERIOD** sets period of temporary blocking of user authentication;
* policy *auth_session* was removed.

5) Changes in module *langman.php*:

* new method **installLang** to install system languages;
* file *languages.xml* was added to installation package, the file contents information about languages installed from package. For this reason version of specification inside file *metainfo.xml*, which describes structure of package, was changed to *0.2*. Correctness of structure of file *languages.xml* is validated by new validation schema *langman-language-v01.rng*;
* bugs in method **addLang** was fixed.

6) Changes in module *userman.php*:

* new method **createPassword** to generate secondary passwords of users.

7) Changes in module *logman.php*:

* Some methods were renamed:
	* **delEvents** -> **delLogEvents**;
	* **newRecord** -> **newLogRecord**.

8) Changes in module *policy.php*:

* Some methods were renamed:
	* **addGroup** -> **addPolicyToGroup**;
	* **delGroup** -> **delPolicyFromGroup**;
	* **funcAccess** -> **setFuncAccess**;
	* **checkAccess** -> **checkFuncAccess**;
	* **install** -> **installPolicy**.

9) Some methods were moved from their classes to another classes:

* **Logman->newLogRecord** to **ServiceMethods->newLogRecord**;
* **Policy->checkFuncAccess** to **ServiceMethods->checkFuncAccess**;
* **Policy->addPolicyToGroup** to **UserMan->addPolicyToGroup**;
* **Policy->delPolicyFromGroup** to **UserMan->delPolicyFromGroup**.

10) Changes in module *extclass.php*:

* new method **outputFormat**, it sets the way of data output as string *json* or as object *DOMDocument*;
* in all methods of classes of all core modules which had data output as object *DOMDocument* was provided output as string *json*.
* ability to display information about errors on the page was realize in method **setError**. Constant **MECCANO_SHOW_ERRORS** was added to configuration file *conf.php* to manage displaying of information about errors on screen.

11) Changes in module *files.php*:

* ability to display information about errors on screen  was realize in method **setError**.

12) Changes in module *plugins.php*:

* operations over plug-ins are being blocked during execution of methods **unpack**, **delUnpacked**, **install** и **delInstalled**.

13) Strings which are stored in database are no longer validated by function **htmlspecialchars**.
