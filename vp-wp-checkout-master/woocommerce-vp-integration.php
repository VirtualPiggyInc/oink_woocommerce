<?php
if (!defined('ABSPATH')) {
    die();
}

define('VP_ACTIVE_ORDER', 'shop_order');
require_once 'integration' . DIRECTORY_SEPARATOR . 'VirtualPiggy.php';
require_once 'integration' . DIRECTORY_SEPARATOR . 'VirtualPiggyWPHelper.php';

/**
 * @return vp_payment_wc
 */
function get_vp_payment() {
    static $instance = null;

    if (!$instance) {
        $instance = new vp_payment_wc();
    }

    return $instance;
}

class vp_payment_wc extends woocommerce_payment_gateway {
    const LOGIN_ERROR_MESSAGE = 'An error occurred. Please try again.';

    function __construct() {
        $this->id = "virtual-piggy";
        $this->icon = "";
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        // VirtualPiggy Configuration
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->HeaderNamespace = $this->settings['HeaderNamespace'];
        $this->propMerchantIdentifier = $this->settings['propMerchantIdentifier'];
        $this->propApiKey = $this->settings['propApiKey'];
        $this->TransactionServiceEndpointAddress = $this->settings['TransactionServiceEndpointAddress'];
        $this->TransactionServiceEndpointAddressWsdl = $this->settings['TransactionServiceEndpointAddressWsdl'];
        $this->ParentServiceEndpointAddress = $this->settings['ParentServiceEndpointAddress'];
        $this->ParentServiceEndpointAddressWsdl = $this->settings['ParentServiceEndpointAddressWsdl'];
        $this->MerchantIdentifier = $this->settings['MerchantIdentifier'];
        $this->APIkey = $this->settings['APIkey'];
        $this->Currency = $this->settings['Currency'];
        $this->DefaultShipmentMethod = $this->settings['DefaultShipmentMethod'];
        $this->order_expiration_time = $this->settings['order_expiration_time'];

        $this->vp = new VirtualPiggy($this->settings);

        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        add_action('virtualpiggy_callback', array(&$this, 'handleCallback'), 10, 1);

        listen_virtual_piggy_callbacks();
    }

    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woothemes'),
                'type' => 'checkbox',
                'label' => __('Enable VirtualPiggy', 'woothemes'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woothemes'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                'default' => __('VirtualPiggy', 'woothemes')
            ),
            'description' => array(
                'title' => __('Customer Message', 'woothemes'),
                'type' => 'textarea',
                'default' => 'Checkout with VirtualPiggy.',
                'description' => 'VirtualPiggy checkout description.'
            ),
            'HeaderNamespace' => array(
                'title' => __('Header Namespace', 'woothemes'),
                'type' => 'text',
                'description' => __('Header Namespace.', 'woothemes'),
                'default' => __('vp', 'woothemes'),
                'style' => 'width:300px'
            ),
            'TransactionServiceEndpointAddress' => array(
                'title' => __('Transaction Service Endpoint Address', 'woothemes'),
                'type' => 'textarea',
                'description' => __('Transaction Service Endpoint Address.', 'woothemes'),
                'default' => __('https://development.virtualpiggy.com/Services/TransactionService.svc', 'woothemes')
            ),
            'TransactionServiceEndpointAddressWsdl' => array(
                'title' => __('Transaction Service Endpoint Address WSDL', 'woothemes'),
                'type' => 'textarea',
                'description' => __('Transaction Service Endpoint Address.', 'woothemes'),
                'default' => __('https://development.virtualpiggy.com/services/TransactionService.svc?wsdl', 'woothemes')
            ),
            'ParentServiceEndpointAddress' => array(
                'title' => __('Parent Service Endpoint Address WSDL', 'woothemes'),
                'type' => 'textarea',
                'description' => __('Parent Service Endpoint Address.', 'woothemes'),
                'default' => __('https://development.virtualpiggy.com/services/JSON/ParentService.svc', 'woothemes')
            ),
            'ParentServiceEndpointAddressWsdl' => array(
                'title' => __('Parent Service Endpoint Address WSDL', 'woothemes'),
                'type' => 'textarea',
                'description' => __('Parent Service Endpoint Address.', 'woothemes'),
                'default' => __('https://development.virtualpiggy.com/services/JSON/ParentService.svc?wsdl', 'woothemes')
            ),
            'MerchantIdentifier' => array(
                'title' => __('Unique Merchant Identifier', 'woothemes'),
                'type' => 'textarea',
                'description' => __('Unique Merchant Identifier.', 'woothemes'),
                'default' => __('a1d2e935-7f4d-4c70-8dfb-f0e6cace5774', 'woothemes')
            ),
            'APIkey' => array(
                'title' => __('API Key', 'woothemes'),
                'type' => 'text',
                'description' => __('Virtual Piggy API Key.', 'woothemes'),
                'default' => __('gadgetboom123', 'woothemes')
            ),
            'Currency' => array(
                'title' => __('Currency', 'woothemes'),
                'type' => 'text',
                'description' => __('Currency.', 'woothemes'),
                'default' => __('USD', 'woothemes')
            )
        );
    }

    public function admin_options() {
        ?>
    <h3><?php _e('VirtualPiggy', 'woothemes'); ?></h3>
    <table class="form-table">
        <?php
        $this->generate_settings_html();
        ?>
    </table>
    <?php
    }

    function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    function process_payment($order_id) {
        global $woocommerce;

        ignore_order_mails();

        $order = &new woocommerce_order($order_id);

        try {
            $result = $this->vp->processPaymentByWooCommerceOrder($order);
            $transactionId = $result->TransactionIdentifier;

            $order->add_order_note('Transaction Identifier: ' . $transactionId);
        } catch (Exception $e) {
            $order->update_status('failed', $e->getMessage());
            $woocommerce->add_error(__('VirtualPiggy error:', 'woocommerce') . $e->getMessage());
            return array(
                'result' => 'fail'
            );
        }

        virtual_piggy_persist_transaction($transactionId, $order_id);

        if (virtual_piggy_is_transaction_approved($transactionId)) {
            $this->approveOrder($order, $order_id, $transactionId);
        } else if (virtual_piggy_is_transaction_rejected($transactionId)) {
            $this->cancelOrderByTransactionId($transactionId);
        } else {
            // We must wait the callback
            $this->freezeOrder($order, $order_id, $transactionId);
            $order->update_status('on-hold', __('Awaiting payment confirmation', 'woothemes'));
        }

        virtual_piggy_shopp_persist_transaction_id($transactionId, $order_id);

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Empty awaiting payment session
        unset($_SESSION['order_awaiting_payment']);

        $this->vp->logout();
        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))))
        );
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

    private function _action_login() {
        $result = null;
        $user = null;
        $message = '';

        try {
            $result = $this->vp->login(
                $_REQUEST['username'],
                $_REQUEST['password']
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        $user = $this->vp->getUserData();

        $this->sendJSON($result, $message, $user);
    }

    private function _action_logout() {
        $this->vp->logout();
    }

    private function _action_destroy_session() {
        @session_destroy();
    }

    private function _action_get_data() {
        $this->sendJSON(true, '', $this->vp->getUserData());
    }

    private function _action_set_options() {
        $childName = $_REQUEST['child'];
        $paymentName = $_REQUEST['payment'];

        $this->vp->setSelectedChild($childName);
        $this->vp->setSelectedPaymentMethod($paymentName);
    }

    private function _action_get_shipping_details() {
        $result = $this->vp->getShippingDetails();

        $name = preg_replace('/([a-z]+)\s.+/i', '$1', $result->ParentName);
        $lastname = preg_replace('/.+\s(.+)$/i', '$1', $result->ParentName);

        $this->sendJSON(true, '', array(
            'Address' => $result->Address,
            'City' => $result->City,
            'State' => $result->State,
            'Zip' => $result->Zip,
            'Country' => $result->Country,
            'Phone' => $result->Phone,
            'ParentEmail' => $result->ParentEmail,
            'ParentName' => $name,
            'ParentLastName' => $lastname
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
    public function handleCallback($data) {
        if (defined('VP_CALLBACK_HANDLED')) {
            return;
        }

        define('VP_CALLBACK_HANDLED', true);

        $transactionId = $data->id;

        if ($data->Status == VP_STATUS_REJECTED) {
            // Updates the VP table
            virtual_piggy_reject_transaction($transactionId);

            if (virtual_piggy_transaction_has_order($transactionId)) {
                // Updates the WooCommerce order
                $this->cancelOrderByTransactionId($transactionId);
            }
        } else {
            // Updates the VP table
            virtual_piggy_approve_transaction($transactionId);

            if (virtual_piggy_transaction_has_order($transactionId)) {
                $order = $this->getOrderIdByTransactionId($transactionId);

                // Updates the WooCommerce Order
                $this->approveOrder($order, $order->id, $transactionId);
            }
        }
    }

    private function _action_session() {
        pr($_SESSION);
    }

    private function cancelOrderByTransactionId($transactionId) {
        $orderId = $this->getOrderIdByTransactionId($transactionId);

        if ($orderId) {
            wp_delete_post($orderId, true);
        }
    }

    private function approveOrder($order, $order_id, $transactionId) {
        $orderId = $this->getOrderIdByTransactionId($transactionId);

        $this->unfreezeOrderByOrderId($orderId);

        require_once 'woocommerce' . DIRECTORY_SEPARATOR . 'class-wc-order.php';

        $order = new VP_WC_Order($orderId);

        // Reduce stock levels
        $order->reduce_order_stock();

        global $woocommerce;

        /**
         * @var WC_Email $mailer
         */
        $mailer = $woocommerce->mailer();

        $mailer->customer_completed_order($orderId);

        do_action('woocommerce_order_status_completed_notification');

        $order->update_status('processing');
    }

    private function unfreezeOrderByTransactionId($transactionId) {
        $this->unfreezeOrderByOrderId(
            $this->getOrderIdByTransactionId($transactionId)
        );
    }

    private function unfreezeOrderByOrderId($id) {
        set_post_type($id, VP_ACTIVE_ORDER);
    }

    private function freezeOrderByOrderId($id) {
        set_post_type($id, VP_FROZEN_ORDER);
    }

    private function freezeOrder(WC_Order $order) {
        $this->freezeOrderByOrderId($order->id);
    }

    /**
     * A frozen order has a comment with the transaction id
     *
     * @param $transactionId
     * @return int|null
     */
    private function getOrderIdByTransactionId($transactionId) {
        global $wpdb;

        $query = "SELECT comment_post_ID AS ORDER_ID FROM $wpdb->comments WHERE comment_content LIKE '%$transactionId%'";

        $result = $wpdb->get_results($query);

        if (isset($result[0]->ORDER_ID)) {
            return $result[0]->ORDER_ID;
        }

        return null;
    }

    /*
   Array
   (
       [id] => 2b0828b7-d05e-483d-8764-bbd09a0bc8fd
       [Status] => Processed
       [Url] => https://www.gadgetboom.com/
       [errorMessage] =>
       [MerchantIdentifier] => a1d2e935-7f4d-4c70-8dfb-f0e6cace5774
       [TransactionIdentifier] => 2b0828b7-d05e-483d-8764-bbd09a0bc8fd
       [Description] => Gadget Boom order
       [Amount] => 61.50
       [ExpiryDate] =>
       [Data] =>

       [Address] => 15 West Highland Avenue
       [City] => Philadelphia
       [Zip] => 19118
       [State] => PA
       [Country] => US
   )
    */
    private function getProcessedOrderNote($orderInfo) {
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

    if (is_checkout()) {
        VirtualPiggyWPHelper::addJS('checkout');
        VirtualPiggyWPHelper::addJS('custom');
    }
}

function vp_parse_request() {
    if ($_GET['vp_action']) {
        get_vp_payment()->handleServices();
    }
}


function ignore_order_mails() {
    global $woocommerce;

    // Need to be sure that the mailer is already instanced
    $woocommerce->mailer();

    $email_actions = array(
        'woocommerce_low_stock',
        'woocommerce_no_stock',
        'woocommerce_product_on_backorder',
        'woocommerce_order_status_pending_to_processing',
        'woocommerce_order_status_pending_to_completed',
        'woocommerce_order_status_pending_to_on-hold',
        'woocommerce_order_status_failed_to_processing',
        'woocommerce_order_status_failed_to_completed',
        'woocommerce_order_status_pending_to_processing',
        'woocommerce_order_status_pending_to_on-hold',
        'woocommerce_order_status_completed',
        'woocommerce_new_customer_note'
    );

    foreach ($email_actions as $action) {
        remove_action($action, array(&$woocommerce, 'send_transactional_email'));
    }
}

function vp_handle_callback($data) {
    get_vp_payment()->handleCallback($data);
}

add_action('wp_head', 'vp_init', 25);
add_filter('woocommerce_payment_gateways', 'add_vp_gateway');
add_action('parse_request', 'vp_parse_request');
add_action('virtualpiggy_callback', 'vp_handle_callback', 10, 1);