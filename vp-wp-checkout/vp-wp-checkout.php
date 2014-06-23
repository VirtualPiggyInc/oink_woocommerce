<?php
/*
Plugin Name: Oink
Plugin URI: https://github.com/VirtualPiggyInc/oink_woocommerce
Description: Now children and teens can shop safely with the Oink payment gateway for Woocommerce and Shopp.
Author: Oink Integration Team
Author URI: http://oink.com
Version: 1.0.0
*/

if (!defined('ABSPATH')) {
    die();
}

add_action('plugins_loaded', 'init_vp_payment', 0);

add_action('woocommerce_after_checkout_form', 'oink_checkout_hidden_inputs_view', 0);

add_action('woocommerce_after_cart_contents', 'oink_cart_button_view', 0);

add_action('woocommerce_after_cart_totals', 'vp_cart_init');

function vp_cart_init() {
    VirtualPiggyWPHelper::addCSS('virtualpiggy');

    $checkoutScript = plugins_url(VirtualPiggyWPHelper::getAssetURL() . "js/cart.js");

    wp_enqueue_script('vpc', $checkoutScript, array(), '1.0.0', true);
    wp_localize_script('vpc', 'VPParams', array(
        'baseURL' => get_site_url()
    ));
}

function oink_checkout_hidden_inputs_view() {
    $oinkRadioEnabled = false;
    $oinkButtonEnabled = false;
    $vpGateway = get_vp_payment();
    if(isset($vpGateway) && $vpGateway->isButtonShown() === true)
        $oinkButtonEnabled = true;

    if(isset($vpGateway) && $vpGateway->isRadioShown() === true)
        $oinkRadioEnabled = true;
    echo '<input type="hidden" id="oink_radio_enabled" value="'.($oinkRadioEnabled?'1':'0').'"/>';
    echo '<input type="hidden" id="oink_button_enabled" value="'.($oinkButtonEnabled?'1':'0').'"/>';
}

function oink_cart_button_view() {
    $vpGateway = get_vp_payment();
    if(isset($vpGateway) && $vpGateway->isButtonShown() === true) {
        echo '<tr><td colspan="6"><img src="https://cdn.virtualpiggy.com/public/images/accepting-150x49.png" class="virtualpiggy-button" style="width:150px; height: 49px; margin: 0;"></td></tr>';
    }
}

function init_vp_payment() {

    //if (class_exists('woocommerce_payment_gateway')) {
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        define('VP_IS_SHOPP', false);
        define('VP_IS_WOOCOMMERCE', true);
        require 'woocommerce-vp-integration.php';
    } else if(defined('SHOPP_VERSION')) {
        define('VP_IS_SHOPP', true);
        define('VP_IS_WOOCOMMERCE', false);
        require 'shopp-vp-integration.php';
    } else {
        return;
    }

    define('VP_FROZEN_ORDER', '__vp_frozen_order');
    define('VP_STATUS_REJECTED', 'Rejected');
    define('VP_STATUS_PROCESSED', 'Processed');
    define('VP_PLUGIN_VERSION', '0.0.8');

    listen_virtual_piggy_callbacks();
}

function virtual_piggy_approve_transaction($transactionId) {
    virtual_piggy_set_transaction_status($transactionId, 'APPROVED');
}

function virtual_piggy_get_order_id_by_transaction_id($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT * FROM $table WHERE transactionId = '$transactionId'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['orderId']) ? $result[0]['orderId'] : null;
}

function virtual_piggy_transaction_has_order($transactionId) {
    return !!virtual_piggy_get_order_id_by_transaction_id($transactionId);
}

function listen_virtual_piggy_callbacks() {
    require_once 'listener.php';
}

function get_virtual_piggy_callbak_data() {
    require_once 'integration/VirtualPiggy/Data/dtos.php';
    require_once 'integration/VirtualPiggy/Services/Interfaces/ICallbackService.php';
    require_once 'integration/VirtualPiggy/Services/Implementations/VirtualPiggyCallbackService.php';

    $service = new VirtualPiggyCallbackService();
    return $service->GetCallbackTransactionStatus();
}

function virtual_piggy_get_transaction_table_name() {
    global $wpdb;

    return $wpdb->prefix . "vp_transactions";
}

function virtual_piggy_transaction_id_exists($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT COUNT(*) AS c FROM $table WHERE transactionId = '$transactionId'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['c']) && $result[0]['c'];
}

function virtual_piggy_persist_transaction($transactionId, $orderId = null, $status = null) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    if (!virtual_piggy_transaction_id_exists($transactionId)) {
        if($status == "Processed") {
            $insert = array(
                'transactionId' => $transactionId,
                'status' => "APPROVED"
            );
        } elseif($status != "LimitsExceeded") {
            $insert = array(
                'transactionId' => $transactionId
            );
        }

        if($status != "LimitsExceeded") {
            if (!is_null($orderId)) {
                $insert['orderId'] = $orderId;
            }

            return $wpdb->insert(
                $table,
                $insert
            );
        } else {
            return null;
        }
    } else {
        $update = array(
            'orderId' => $orderId
        );

        return $wpdb->update(
            $table,
            $update,
            array('transactionId' => $transactionId)
        );
    }
}

function virtual_piggy_set_transaction_status($transactionId, $status) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    if (!virtual_piggy_transaction_id_exists($transactionId)) {
        $insert = array(
            'transactionId' => $transactionId,
            'status' => $status
        );

        $wpdb->insert(
            $table,
            $insert
        );
    } else {
        $update = array(
            'status' => $status
        );

        $wpdb->update(
            $table,
            $update,
            array('transactionId' => $transactionId)
        );
    }
}

function virtual_piggy_reject_transaction($transactionId) {
    virtual_piggy_set_transaction_status($transactionId, 'REJECTED');
}

function virtual_piggy_is_transaction_rejected($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT COUNT(*) AS c FROM $table WHERE transactionId = '$transactionId' AND status = 'REJECTED'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['c']) && $result[0]['c'];
}

function virtual_piggy_is_transaction_approved($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT COUNT(*) AS c FROM $table WHERE transactionId = '$transactionId' AND status = 'APPROVED'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['c']) && $result[0]['c'];
}

function virtual_piggy_install_db() {
    $table_name = virtual_piggy_get_transaction_table_name();

    $sql = "
CREATE TABLE IF NOT EXISTS $table_name (
id mediumint(9) NOT NULL AUTO_INCREMENT,
transactionId varchar(55) NOT NULL DEFAULT '',
orderId varchar(55) DEFAULT NULL,
status varchar(55) DEFAULT NULL,
UNIQUE KEY id (id)
);";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option("vp_db_version", '3');
}

function pr($a) {
    echo '<pre>';
    is_bool($a) ? var_dump($a): print_r($a);
    echo '</pre><br/>';
}

register_activation_hook(__FILE__, 'virtual_piggy_install_db');