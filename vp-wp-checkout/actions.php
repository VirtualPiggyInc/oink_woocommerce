<?php
if (!defined('ABSPATH')) {
    die(':(');
}

function virtual_piggy_service($service, $arguments = array()) {
    $action = 'virtual_piggy_action_' . $service;

    if (function_exists($action)) {
        call_user_func_array($action, $arguments);
        die();
    }
}

function virtual_piggy_action_install() {
    echo vp_shopp_gateway_install() ? 'OK' : 'ERROR';
}


function virtual_piggy_action_session() {
    virtual_piggy_require_dev_credentials();
    pr($_SESSION);
}


function virtual_piggy_action_checksum() {
    virtual_piggy_require_dev_credentials();
    echo virtual_piggy_compute_checksum();
}