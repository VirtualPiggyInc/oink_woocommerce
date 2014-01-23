<?php
if (!defined('ABSPATH')) {
    die(':(');
}

if(isset($_REQUEST['MerchantIdentifier'])) {
    $data = get_virtual_piggy_callbak_data();

    @VirtualPiggyWPHelper::log($data, 'ASYNC');

    do_action('virtualpiggy_callback', $data);

    die('Virtual Piggy (c)' . PHP_EOL);
}