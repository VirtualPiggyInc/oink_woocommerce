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

    return file_exists($path);
}

function vp_shopp_gateway_install() {
    $shoppGatewayDir = vp_get_shopp_gateway_dir();

    if (!file_exists($shoppGatewayDir)) {
        mkdir($shoppGatewayDir);
    }

    $source = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'shopp/VirtualPiggyGateway.php';
    $dest = $shoppGatewayDir . DIRECTORY_SEPARATOR . 'VirtualPiggyGateway.php';

    @copy($source, $dest);
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

    public function getVP() {
        if (!$this->vp) {
            $gateway = new VirtualPiggyGateway;
            $this->vp = new VirtualPiggy($gateway->settings);
        }

        return $this->vp;
    }

    public function handleServices() {
        $action = '_action_' . $_REQUEST['vp_action'];

        if (method_exists($this, $action)) {
            $this->{$action}($_REQUEST);
            die();
        }
    }

    private function _action_login() {
        $result = null;
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

    private function _action_logout() {
        $this->getVP()->logout();
    }

    private function _action_destroy_session() {
        @session_destroy();
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

    private function _action_get_shipping_details() {
        if ($this->vp->isParent())
            $result = $this->vp->getShippingDetailsBySelectedChild();
        else
            $result = $this->vp->getCurrentChildShippingDetails();

        $data = array(
            'Address' => $result->Address,
            'City' => $result->City,
            'State' => $result->State,
            'Zip' => $result->Zip,
            'Country' => $result->Country,
            'Phone' => $result->Phone,
            'ParentEmail' => $result->ParentEmail,
            'Name' => $result->ChildName,
            'ParentName' => $result->ParentName,
            'ErrorMessage' => $result->ErrorMessage
        );
        if (empty($data['ErrorMessage']))
            $status = true;
        else {
            $status = false;
            wc_add_notice($data['ErrorMessage'], 'error');
        }
        $this->sendJSON($status, $data['ErrorMessage'], $data);
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
        if ($data->Status == VP_STATUS_REJECTED) {
            $this->cancelOrderByTransactionId($data->id);
        } else {
            $this->approveOrderByTransactionId($data->id, $data);
        }
    }

    private function _action_d() {
        pr(vp_shopp_gateway_exists());
    }

    private function _action_a() {
        $id = $_GET['id'];

        if (isset($_GET['status']) && $_GET['status'] == VP_STATUS_REJECTED) {
            $this->cancelOrderByTransactionId($id);
        } else {
            $transaction = new dtoTransactionStatus();
            $transaction->id = $id;
            $this->approveOrderByTransactionId($id, $transaction);
        }
    }

    private function _action_session() {
        pr($_SESSION);
    }

    private function cancelOrderByTransactionId($transactionId) {
        $purchase = new Purchase($transactionId);

        $this->markPurchaseVoided($purchase, $transactionId);
    }

    private function approveOrderByTransactionId($transactionId, dtoTransactionStatus $orderInfo) {
        $purchase = new Purchase($transactionId);

        $this->markPurchaseAuthed($purchase, $transactionId);
    }

    private function markPurchaseAuthed(Purchase $purchase, $transactionId) {
        global $wpdb;

        $purchaseTable = $purchase->_table;

        $wpdb->query("UPDATE $purchaseTable SET txnstatus='authed' WHERE txnid = '$transactionId'");
    }

    private function markPurchaseVoided(Purchase $purchase, $transactionId) {
        global $wpdb;

        $purchaseTable = $purchase->_table;

        $wpdb->query("UPDATE $purchaseTable SET txnstatus='voided' WHERE txnid = '$transactionId'");
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

    shopp_enqueue_script('vpc', $checkoutScript);
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

add_action('init', 'vp_init',0);
add_action('parse_request', 'vp_parse_request');