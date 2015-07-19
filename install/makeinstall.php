<?php

//header('Content-type: text/plain; charset=utf-8');

require_once 'conf.php';
require_once 'webinstaller.php';

$webinst = new \core\WebInstaller();

if (isset($_SESSION['webinstaller_step'])) {
    // make step
    if ($_SESSION['webinstaller_step'] == 1) {
        $webinst->createDbTables();
    }
    elseif ($_SESSION['webinstaller_step'] == 2) {
        $webinst->installPackage();
    }
    elseif ($_SESSION['webinstaller_step'] == 3) {
        $webinst->groupUsers($_SESSION['user_param']);
    }
    // check step
    if (!$webinst->errId()) {
        $_SESSION['webinstaller_step'] += 1;
        $removeId = core\makeIdent();
        echo json_encode(array("response" => $_SESSION['webinstaller_step'], "removeId" => $removeId));
        if ($_SESSION['webinstaller_step'] == 4) {
            $_SESSION = array();
            $_SESSION['removeId'] = $removeId;
        }
    }
    else {
        echo json_encode(array("response" => FALSE, "error" => $webinst->errExp()));
        $_SESSION = array();
    }
}
else {
    if ($webinst->revalidateAll($_POST)) {
        $_SESSION['webinstaller_step'] = 1;
        $_SESSION['user_param'] = $_POST;
        echo json_encode(array("response" => $_SESSION['webinstaller_step']));
    }
    else {
        echo json_encode(array("response" => 0, "error" => $webinst->errExp()));
    }
}
