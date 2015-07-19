<?php

namespace core;

interface intRemove {
    public function __construct(\mysqli $dblink, $id, $keepData = TRUE);
    public function errId();
    public function errExp();
    public function prerm();
    public function postrm();
}

class Remove implements intRemove {
    
    private $errid = 0; // error code
    private $errexp = ''; // error description
    private $dbLink; // mysqli object
    private $id; // identifier of the installed plugin
    private $keepData; // 

    public function __construct(\mysqli $dblink, $id, $keepData = TRUE) {
        $this->dbLink = $dblink;
        $this->id = $id;
        $this->keepData = $keepData;
    }
    
    private function setError($id, $exp) {
        $this->errid = $id;
        $this->errexp = $exp;
    }
    
    public function errId() {
        return $this->errid;
    }
    
    public function errExp() {
        return $this->errexp;
    }
    
    public function prerm() {
        // put your code here
        return TRUE;
    }
    
    public function postrm() {
        // put your code here
        return TRUE;
    }

}
