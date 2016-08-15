#Changelog#

##v0.1.0##

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

9) Organization of relations between classes of core modules of the framework was made by extending, it makes easier creation of objects;

10) Changes in module *extclass.php*:

* new method **outputFormat**, it sets the way of data output as string *json* or as object *DOMDocument*;
* in all methods of classes of all core modules which had data output as object *DOMDocument* was provided output as string *json*.
* ability to display information about errors on screen  was realize in method **setError**. Constant **MECCANO_SHOW_ERRORS** was added to configuration file *conf.php* to manage displaying of information about errors on screen.

11) Changes in module *files.php*:

* ability to display information about errors on screen  was realize in method **setError**.

12) Changes in module *plugins.php*:

* operations over plug-ins are being blocked during execution of methods **unpack**, **delUnpacked**, **install** и **delInstalled**.

13) Strings which are stored in database are no longer validated by function **htmlspecialchars**.
