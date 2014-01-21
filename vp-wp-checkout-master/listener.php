<?php
if (!defined('ABSPATH')) {
    die(':(');
}
/**
 * All callbacks* have a MerchantIdentifier attribute
 *
 * The Reject callback doesn't
 */
if(isset($_REQUEST['MerchantIdentifier']) || isset($_REQUEST['ReferenceId'])) {
    $data = get_virtual_piggy_callbak_data();

    @VirtualPiggyWPHelper::log($data, 'ASYNC');

    do_action('virtualpiggy_callback', $data);

    die('Virtual Piggy (c)' . PHP_EOL);
}