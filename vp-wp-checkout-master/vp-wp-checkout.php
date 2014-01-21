<?php
/*
Plugin Name: VirtualPiggy payment gateway
Plugin URI:
Description: VirtualPiggy payment gateway for Woocommerce/Shopp
Author: Summa Solutions
Author URI: http://summasolutions.net
Version: 0.1.4
*/
if (!defined('ABSPATH')) {
    die();
}

function init_vp_payment() {
    require 'actions.php';

    if (class_exists('woocommerce_payment_gateway')) {
        define('VP_IS_SHOPP', false);
        define('VP_IS_WOOCOMMERCE', true);
        require 'woocommerce-vp-integration.php';
    } else if (defined('SHOPP_VERSION')) {
        define('VP_IS_SHOPP', true);
        define('VP_IS_WOOCOMMERCE', false);
        require 'shopp-vp-integration.php';
    } else {
        return;
    }

    define('VP_FROZEN_ORDER', '__vp_frozen_order');
    define('VP_STATUS_REJECTED', 'Rejected');
    define('VP_STATUS_PROCESSED', 'Processed');
    define('VP_PLUGIN_VERSION', '0.1.4');

    listen_virtual_piggy_callbacks();
}

function virtual_piggy_deactivate() {
    if (defined('SHOPP_VERSION')) {
        define('VP_IS_SHOPP', true);
        define('VP_IS_WOOCOMMERCE', false);

        vp_shopp_gateway_uninstall();
    }
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

function virtual_piggy_install_db() {
    $table_name = virtual_piggy_get_transaction_table_name();

    $sql = "
        CREATE TABLE  $table_name (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          transactionId varchar(55) NOT NULL DEFAULT '',
          orderId varchar(55) DEFAULT NULL,
          status varchar(55) DEFAULT NULL,
          UNIQUE KEY id (id)
        )
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option("vp_db_version", '3');
}

function virtual_piggy_persist_transaction($transactionId, $orderId = null) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    if (!virtual_piggy_transaction_id_exists($transactionId)) {
        $insert = array(
            'transactionId' => $transactionId
        );

        if (!is_null($orderId)) {
            $insert['orderId'] = $orderId;
        }

        return $wpdb->insert(
            $table,
            $insert
        );
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

function virtual_piggy_approve_transaction($transactionId) {
    virtual_piggy_set_transaction_status($transactionId, 'APPROVED');
}

function virtual_piggy_reject_transaction($transactionId) {
    virtual_piggy_set_transaction_status($transactionId, 'REJECTED');
}

function virtual_piggy_get_transactions() {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT * FROM $table";

    return $wpdb->get_results($query, ARRAY_A);
}

function virtual_piggy_transaction_id_exists($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT COUNT(*) AS c FROM $table WHERE transactionId = '$transactionId'";

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

function virtual_piggy_is_transaction_rejected($transactionId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT COUNT(*) AS c FROM $table WHERE transactionId = '$transactionId' AND status = 'REJECTED'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['c']) && $result[0]['c'];
}

function virtual_piggy_get_transaction_id_by_order_id($orderId) {
    global $wpdb;

    $table = virtual_piggy_get_transaction_table_name();

    $query = "SELECT transactionId FROM $table WHERE orderId = '$orderId'";

    $result = $wpdb->get_results($query, ARRAY_A);

    return isset($result[0]['transactionId']) ? $result[0]['transactionId'] : null;
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

function virtual_piggy_shopp_persist_transaction_id($transactionId, $purchaseId) {
    global $wpdb;

    $wpdb->update(
        $wpdb->prefix . 'shopp_purchase',
        array('txnid' => $transactionId),
        array('id' => $purchaseId)
    );
}

function virtual_piggy_shopp_persist_event_authed($transactionId, $purchaseId) {
    global $wpdb;

    $table = $wpdb->prefix . 'shopp_meta';

    $serialized = new stdClass();

    $serialized->txnid = $transactionId;
    $serialized->gateway = 'VirtualPiggyGateway';
    $serialized->paymethod = 'Virtual Piggy';
    $serialized->amount = 0;
    $serialized->user = 0;

    $insert = array(
        'parent' => $purchaseId,
        'context' => 'purchase',
        'type' => 'event',
        'name' => 'authed',
        'value' => serialize($serialized),
        'numeral' => '1',
        'sortorder' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s')
    );

    $result = $wpdb->insert(
        $table,
        $insert
    );

    return $result;
}

function virtual_piggy_compute_dir_checksum($dir) {
    $md5 = array();

    $handle = dir($dir);

    while (false !== ($entry = $handle->read())) {
        if (preg_match('/^\./', $entry)) {
            continue;
        }

        $file = $dir . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($file)) {
            $md5[] = virtual_piggy_compute_dir_checksum($file);
        } else {
            // Avoid README.md and log files
            if (!virtual_piggy_is_file_computable($entry)) {
                continue;
            }

            $md5[] = md5_file($file);
        }
    }

    $handle->close();

    return md5(implode('', $md5));
}

function virtual_piggy_is_file_computable($name) {
    $files = array(
        'README.md',
        'readme.txt',
        'callback.log',
        'custom.js',
    );

    return !in_array($name, $files);
}

function virtual_piggy_compute_checksum() {
    $dir = dirname(__FILE__);
    return virtual_piggy_compute_dir_checksum($dir);
}

function virtual_piggy_require_dev_credentials() {
}

function pr($a) {
    echo '<pre>';
    is_bool($a) ? var_dump($a) : print_r($a);
    echo '</pre><br/>';
}

register_deactivation_hook(__FILE__, 'virtual_piggy_deactivate');
register_activation_hook(__FILE__, 'virtual_piggy_install_db');
add_action('plugins_loaded', 'init_vp_payment', 0);