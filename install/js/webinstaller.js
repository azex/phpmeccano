/*
 *     phpMeccano v0.0.1. Web-framework written with php programming language. Component of the web installer.
 *     Copyright (C) 2015  Alexei Muzarov
 * 
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 * 
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 * 
 *     You should have received a copy of the GNU General Public License along
 *     with this program; if not, write to the Free Software Foundation, Inc.,
 *     51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 *     e-mail: azexmail@gmail.com
 *     e-mail: azexmail@mail.ru
 */

"use strict";

var WebInstaller = {};

WebInstaller.enableSubmitByConf = 0;
WebInstaller.enableSubmitByUser = 0;
WebInstaller.inputConfirm = {
    "groupname" : false,
    "username" : false,
    "passw" : false,
    "repassw" : false,
    "email" : false
};
WebInstaller.rid = Math.random();

WebInstaller.xhrObject = function() {
    var request;
    try {
    request = new ActiveXObject("Msxml2.XMLHTTP");
    }
    catch (e) {
        try {
            request = new ActiveXObject("Microsoft.XMLHTTP");
        }
        catch (E) {
            request = false;
        }
  }
  if (!request && typeof XMLHttpRequest!=='undefined') {
    request = new XMLHttpRequest();
  }
  return request;
} ;

WebInstaller.sendRequest = function(dataURL, execFunc) {
    var request = WebInstaller.xhrObject();
    request.open('GET', dataURL, true);
    request.onreadystatechange = function() {
        if (request.readyState === 4) {
            if (request.status === 200) {
                var response = request.responseText;
                execFunc(response);
            }
        }
    }
    request.send(null);
} ;

WebInstaller.submitForm = function (dataURL, form, execFunc) {
    var sendData = "";
    var elements = form.elements;
    var request = WebInstaller.xhrObject();
    request.open("POST", dataURL, true);
    request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    for (var i = 0; i < elements.length; i++) {
        sendData += encodeURIComponent(elements[i].name) + "=" + encodeURIComponent(elements[i].value) + "&";
    }
    sendData = sendData.substring(0, (sendData.length - 1));
    request.onreadystatechange = function() {
        if (request.readyState === 4) {
            if (request.status === 200) {
                var response = request.responseText;
                execFunc(response);
            }
        }
    }
    request.send(sendData);
} ;

WebInstaller.validateConf = function(respData) {
    var i, key, span;
    var respJSON = JSON.parse(respData);
    var respKeys = Object.keys(respJSON);
    var doEnabled = 1;
    for (i = 0; i < respKeys.length; ++i) {
        key = respKeys[i];
        span = document.getElementById(key);
        if (respJSON[key][0]) {
            span.setAttribute("class", "true");
        }
        else {
            span.setAttribute("class", "false");
            doEnabled = 0;
        }
        span.innerHTML = respJSON[key][1];
    }
    WebInstaller.enableSubmitByConf = doEnabled;
    WebInstaller.enableSubmit();
    window.setTimeout("WebInstaller.sendRequest('valconf.php?' + Math.random(), WebInstaller.validateConf)", 2000);
} ;

WebInstaller.validateForm = function() {
    var i, key;
    var doEnabled = 1;
    var formKeys = ["groupname", "username", "passw", "repassw", "email"];
    for (i = 0; i < formKeys.length; ++i) {
        key = formKeys[i];
        if (!WebInstaller.inputConfirm[key]) {
            doEnabled = 0;
        }
    }
    WebInstaller.enableSubmitByUser = doEnabled;
    WebInstaller.enableSubmit();
}

WebInstaller.enableSubmit = function() {
    var submit = document.forms.userconf.elements.submit;
    if (WebInstaller.enableSubmitByConf && WebInstaller.enableSubmitByUser) {
        submit.removeAttribute("disabled");
    }
    else {
        submit.setAttribute("disabled", "");
    }
} ;

WebInstaller.showMeForm = function(respData) {
    WebInstaller.validateConf(respData);
    document.getElementById("progress").setAttribute("class", "hidden");
    document.getElementById("loading").setAttribute("class", "hidden");
    document.getElementById("settings").removeAttribute("class");
} ;

WebInstaller.pregGroupName = function(value) {
    var regEx = /^.{1,50}$/;
    if(regEx.test(document.forms.userconf.elements.groupname.value) && document.forms.userconf.elements.groupname.value.replace(/[\s\n\r\t]+/, "")) {
        WebInstaller.inputConfirm.groupname = true;
        document.forms.userconf.elements.groupname.setAttribute("class", "true");
    }
    else {
        document.forms.userconf.elements.groupname.setAttribute("class", "false");
        WebInstaller.inputConfirm.groupname = false;
    }
    WebInstaller.validateForm();
} ;

WebInstaller.pregUserName = function(value) {
    var regEx = /^[a-zA-Z\d]{3,20}$/;
    if(regEx.test(document.forms.userconf.elements.username.value)) {
        document.forms.userconf.elements.username.setAttribute("class", "true");
        WebInstaller.inputConfirm.username = true;
    }
    else {
        document.forms.userconf.elements.username.setAttribute("class", "false");
        WebInstaller.inputConfirm.username = false;
    }
    WebInstaller.validateForm();
} ;

WebInstaller.pregPassw = function(value) {
    var regEx = /^[-+=_a-zA-Z\d@.,?!;:"'~`|#№*$%&^\][(){}<>\/\\]{8,50}$/;
    if(regEx.test(document.forms.userconf.elements.passw.value)) {
        document.forms.userconf.elements.passw.setAttribute("class", "true");
        WebInstaller.inputConfirm.passw = true;
    }
    else {
        document.forms.userconf.elements.passw.setAttribute("class", "false");
        WebInstaller.inputConfirm.passw = false;
    }
    WebInstaller.pregRePassw(value);
} ;

WebInstaller.pregRePassw = function(value) {
    var regEx = /^[-+=_a-zA-Z\d@.,?!;:"'~`|#№*$%&^\][(){}<>\/\\]{8,50}$/;
    if(regEx.test(document.forms.userconf.elements.repassw.value) && (document.forms.userconf.elements.passw.value === document.forms.userconf.elements.repassw.value)) {
        document.forms.userconf.elements.repassw.setAttribute("class", "true");
        WebInstaller.inputConfirm.repassw = true;
    }
    else {
        document.forms.userconf.elements.repassw.setAttribute("class", "false");
        WebInstaller.inputConfirm.repassw = false;
    }
    WebInstaller.validateForm();
} ;

WebInstaller.pregMail = function(value) {
    // regex from Blink engine
    // https://chromium.googlesource.com/chromium/blink/+/master/Source/core/html/forms/EmailInputType.cpp
    // line 48
    var regEx = /^[a-z0-9!#$%&'*+/=?^_`{|}~.-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/;
    if(regEx.test(document.forms.userconf.elements.email.value)) {
        document.forms.userconf.elements.email.setAttribute("class", "true");
        WebInstaller.inputConfirm.email = true;
    }
    else {
        document.forms.userconf.elements.email.setAttribute("class", "false");
        WebInstaller.inputConfirm.email = false;
    }
    WebInstaller.validateForm();
} ;

WebInstaller.errorGears = function() {
    if (window.gears.stepAngle > 0) {
        window.gears.stepAngle = -1;
    }
    else {
        window.gears.stepAngle = 1;
    }
    window.setTimeout("WebInstaller.errorGears()", 500);
} ;

WebInstaller.makeInstall = function(respData) {
    var error = document.getElementById("error");
    error.setAttribute("class", "hidden");
    var respJSON = JSON.parse(respData);
    if (!respJSON["response"]) {
        if (typeof respJSON["response"] == "boolean") {
            WebInstaller.errorGears();
        }
        var errexp = document.getElementById("errexp");
        errexp.innerHTML = respJSON["error"];
        error.setAttribute("class", "center");
        window.scrollTo(0, 0);
    }
    else {
        switch (respJSON["response"]) {
            case 1:
                document.getElementById("instprogress").removeAttribute("class");
                document.getElementById("creatingdb").removeAttribute("class");
                document.getElementById("settings").setAttribute("class", "hidden");
                document.getElementById("progress").setAttribute("class", "center");
                window.setTimeout("WebInstaller.submitForm('makeinstall.php?' + Math.random(), document.forms.userconf, WebInstaller.makeInstall)", 25);
                break;
            case 2:
                window.gears.stepAngle = 4;
                document.getElementById("creatingdb").setAttribute("class", "true");
                document.getElementById("instpack").removeAttribute("class");
                window.setTimeout("WebInstaller.submitForm('makeinstall.php?' + Math.random(), document.forms.userconf, WebInstaller.makeInstall)", 25);
                break;
            case 3:
                window.gears.stepAngle = 12;
                document.getElementById("instpack").setAttribute("class", "true");
                document.getElementById("creatingroot").removeAttribute("class");
                window.setTimeout("WebInstaller.submitForm('makeinstall.php?' + Math.random(), document.forms.userconf, WebInstaller.makeInstall)", 25);
                break;
            case 4:
                window.gears.stepAngle = 19;
                document.getElementById("creatingroot").setAttribute("class", "true");
                document.getElementById("instprogress").setAttribute("class", "hidden");
                document.getElementById("completed").removeAttribute("class");
                document.getElementById("selfremove").setAttribute("class", "center false");
                WebInstaller.rid = respJSON["rid"];
                break;
        }
    }
} ;

WebInstaller.selfRemove = function(value) {
    document.getElementById("trash").setAttribute("class", "hidden");
    document.getElementById("waitgear").removeAttribute("class");
    window.setTimeout('WebInstaller.sendRequest("selfremove.php?rid=" + WebInstaller.rid + Math.random(), WebInstaller.selfRemoved)', 2000);
}

WebInstaller.selfRemoved = function(respData) {
    var respJSON = JSON.parse(respData);
    if (respJSON["response"]) {
        document.getElementById("selfremove").setAttribute("class", "hidden");
        document.getElementById("removed").setAttribute("class", "center true");
    }
    else {
        var error = document.getElementById("error");
        var errexp = document.getElementById("errexp");
        errexp.innerHTML = respJSON["error"];
        error.setAttribute("class", "center");
        window.scrollTo(0, 0);
    }
}
