<?php

namespace core;

// it generates and returns unique password salt
function makeSalt($prefix = '') {
    return substr(base64_encode(sha1(uniqid().microtime(TRUE).rand(1, 10e9).uniqid($prefix, TRUE))), 0, 22);
}