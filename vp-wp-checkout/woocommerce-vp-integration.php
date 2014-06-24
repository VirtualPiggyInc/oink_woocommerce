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

//class vp_payment_wc extends woocommerce_payment_gateways {
class vp_payment_wc extends WC_Payment_Gateway {
    const LOGIN_ERROR_MESSAGE = 'An error occurred. Please try again.';
    public static $paymentMethodShownForOptions = array('none'=>'None','all'=>'All Users','registered'=>'Only Registered Users','guest'=>'Only Guest Users');
    public static $checkoutTypeOptions = array('button_and_radio'=>'Checkout Button and Radio Select', 'button_only'=>'Checkout Button Only', 'radio_only'=>'Radio Select Only');

    function __construct() {
        $this->id = "virtual-piggy";
        $this->method_title = __( 'Oink', 'woocommerce' );
        $this->icon = "";
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();
        $this->settings['enabled'] = 'yes'; //making this setting always true so the checkmark shows up in payment gateways.

        // VirtualPiggy Configuration
        $this->title = $this->settings['title'];


        $this->description = $this->settings['description'];
        $this->HeaderNamespace = $this->settings['HeaderNamespace'];
        $this->propMerchantIdentifier = $this->settings['propMerchantIdentifier'];
        $this->propApiKey = $this->settings['propApiKey'];
        $this->transactionServiceURL = $this->settings['transactionServiceURL'];
        //$this->TransactionServiceEndpointAddressWsdl = $this->settings['TransactionServiceEndpointAddressWsdl'];
        //$this->ParentServiceEndpointAddress = $this->settings['ParentServiceEndpointAddress'];
        //$this->ParentServiceEndpointAddressWsdl = $this->settings['ParentServiceEndpointAddressWsdl'];
        $this->MerchantIdentifier = $this->settings['merchantIdentifier'];
        $this->APIkey = $this->settings['apiKey'];
        $this->Currency = $this->settings['Currency'];
        $this->DefaultShipmentMethod = $this->settings['DefaultShipmentMethod']; // TODO: remove, is this even used?
        $this->order_expiration_time = $this->settings['order_expiration_time'];

        $this->vp = new VirtualPiggy($this->settings);

        //   add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
        add_action('virtualpiggy_callback', array(&$this, 'handleCallback'), 10, 1);

        listen_virtual_piggy_callbacks();

    }

    /*
     * Overriding this function from the parent class for a double does of awesome!
     */
    function is_available() {
        return $this->isOinkActionShown('radio');
    }

    function isOinkActionShown($actionType) {
        $result = false;
        if(isset($this->settings['checkoutType']) && isset($this->settings['paymentMethodShownFor'])) {
            $isLoggedIn = is_user_logged_in();
            if($this->settings['paymentMethodShownFor'] == 'all' ||
                ($this->settings['paymentMethodShownFor'] == 'guest' && $isLoggedIn === false) ||
                ($this->settings['paymentMethodShownFor'] == 'registered' && $isLoggedIn === true)) {
                if($this->settings['checkoutType'] == 'button_and_radio' || $this->settings['checkoutType'] == $actionType.'_only') {
                    $result = true;
                }
            }
        }
        return $result;
    }

    function isButtonShown() {
        return $this->isOinkActionShown('button');
    }

    function isRadioShown() {
        return $this->isOinkActionShown('radio');
    }

    function init_form_fields() {
        $this->form_fields = array(
            'transactionServiceURL' => array(
                'title' => __('Transaction Service URL', 'woothemes'),
                'type' => 'text',
                'description' => __('This is the oink endpoint URI which will be used.'),
                'default' => __('https://integration.virtualpiggy.com/Services/TransactionService.svc', 'woothemes'),
            ),
            'merchantIdentifier' => array(
                'title' => __('Merchant Identifier', 'woothemes'),
                'type' => 'text',
                'description' => __('Unique Merchant Identifier.', 'woothemes'),
                'default' => __('71af1ad0-e24d-4e9a-8173-7b6c7ba4d475', 'woothemes'),
            ),
            'apiKey' => array(
                'title' => __('API Key', 'woothemes'),
                'type' => 'text',
                'description' => __('Oink API Key.', 'woothemes'),
                'default' => __('Eddy123', 'woothemes')
            ),
            'isTwoStepAuthEnabled' => array(
                'title' => __('Two Step Authorization', 'woothemes'),
                'type' => 'checkbox',
                'label' => __('Use two step authorization?', 'woothemes'),
                'default' => 'no'
            ),
            'Currency' => array(
                'title' => __('Currency', 'woothemes'),
                'type' => 'text',
                'description' => __('Currency.', 'woothemes'),
                'default' => __('USD', 'woothemes')
            ),
            'paymentMethodShownFor' => array(
                'title' => __('Show payment method for', 'woothemes'),
                'type' => 'select',
                'options' => static::$paymentMethodShownForOptions,
                'description' => __('Which users see oink as a checkout option?', 'woothemes'),
                'default' => 'none',
            ),
            'checkoutType' => array(
                'title' => __('Checkout Type', 'woothemes'),
                'type' => 'select',
                'options' => static::$checkoutTypeOptions,
                'description' => __('Where do users see Oink as a checkout option?','woothemes'),
                'default' => 'button_and_radio',
            ),
            'enabled' => array(
                'type' => 'hidden',
                'default' => 'yes',
                'display' => false,
            ),
            'title' => array(
                'title' => __('Title', 'woothemes'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                'default' => __('Oink', 'woothemes')
            ),
            'description' => array(
                'title' => __('Customer Message', 'woothemes'),
                'type' => 'textarea',
                'default' => 'Checkout with Oink.',
                'description' => 'Oink checkout description.'
            ),
            'HeaderNamespace' => array(
                'title' => __('Header Namespace', 'woothemes'),
                'type' => 'text',
                'description' => __('Header Namespace.', 'woothemes'),
                'default' => __('vp', 'woothemes'),
                'style' => 'width:300px'
            ),
        );
    }

    public function admin_options() {
        VirtualPiggyWPHelper::addCSS('virtualpiggy');
        ?>
        <h3><?php _e('Oink', 'woothemes'); ?></h3>
        <table class="form-table">
            <?php
            $this->generate_settings_html();
            ?>
        </table>
    <?php
    }

    public function generate_settings_html() {
        ob_start();
        parent::generate_settings_html();
        $settingsHtml = ob_get_contents();
        ob_end_clean();
        $settingsRows = explode('<tr valign="top">', $settingsHtml);
        foreach($settingsRows as $settingsRowK=>$settingsRow) {
            if($settingsRowK == 4) {
                echo $this->generate_test_connection_html();
            }
            echo '<tr valign="top">'.$settingsRow;
        }
    }

    public function generate_test_connection_html() {
        $oinkSettingsScript = plugins_url(VirtualPiggyWPHelper::getAssetURL() . "js/oink_settings.js");
        $oinkLoadingImgSrc = plugins_url(VirtualPiggyWPHelper::getAssetURL() . "images/virtualpiggy/ajax-loader-tr.gif");
        $html = '<tr valign="top"><th scope="row" class="titledesc"><!-- ---></th><td class="forminp"><button class="oink_button" id="oink_test_connection_btn"><span>Test Connection</span></button>';

        $html .= '<input type="hidden" id="oink_ajax_spinner_img_url" value="'.$oinkLoadingImgSrc.'"/>';
        $html .= '<input type="hidden" id="oink_ajax_action_test_connection_url" value="'.get_site_url().'/?vp_action=test_connection"/>';
        $html .= '<script type="text/javascript" src="'.$oinkSettingsScript.'"></script>';
        $html .= '</td></tr>';
        return $html;
    }

    function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    function email_instructions($order, $sent_to_admin) {

        if ($sent_to_admin) {
            return;
        }

        if ($order->status !== 'on-hold') {
            return;
        }

        if ($order->payment_method !== 'bankgiro-postgiro') {
            return;
        }

        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        ?><h2><?php _e('Betalning', 'woothemes') ?></h2>
        <ul class="order_details bankgiro-postgiro_details"><?php

        $fields = array(
            'bankgironr' => __('Bankgironummer', 'woothemes'),
            'postgironr' => __('Postgironummern', 'woothemes'),
        );

        foreach ($fields as $key => $value) :
            if (!empty($this->$key)) :
                echo '<li class="' . $key . '">' . $value . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
            endif;
        endforeach;

        ?></ul><?php
    }


    function process_payment($order_id) {
        global $woocommerce;

        ignore_order_mails();
        $order = new WC_Order( $order_id );

        try {
            $result = $this->vp->processPaymentByWooCommerceOrder($order);
            $transactionIdentifier = $result->TransactionIdentifier;

            $order->add_order_note('Transaction Identifier: ' . $transactionIdentifier);
        } catch (Exception $e) {
            $order->update_status('failed', $e->getMessage());
            if(function_exists(wc_add_notice))
                wc_add_notice($e->getMessage(),'error');
            else
<<<<<<< HEAD
                $woocommerce->add_error(__('Payment error:', 'woothemes') . $e->getMessage());
=======
                $woocommerce->add_error(__('Oink error:', 'woocommerce') . $e->getMessage());
>>>>>>> origin/woocomm-and-shopp-fixes

            return array(
                'result' => 'fail'
            );
        }

        virtual_piggy_persist_transaction($transactionIdentifier, $order_id, $result->TransactionStatus);

        if (virtual_piggy_is_transaction_approved($transactionIdentifier)) {
            $this->approveOrder($order, $order_id, $transactionIdentifier);
        } else if (virtual_piggy_is_transaction_rejected($transactionIdentifier)) {
            $this->cancelOrderByTransactionId($transactionIdentifier);
        } elseif($result->TransactionStatus == "ApprovalPending") {
            // We must wait the callback
            //$this->freezeOrder($order, $order_id, $transactionId);
            //$order->update_status('pending', __('Awaiting payment confirmation', 'woothemes'));
            $order->add_order_note('Awaiting approval confirmation');
        }

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Empty awaiting payment session
        unset($_SESSION['order_awaiting_payment']);

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => add_query_arg('key', $order->order_key,
                add_query_arg('order', $order_id, $this->get_return_url($this->order)))
        );
    }

    private function rejectOrder($id) {
        $db_connection = $this->createConnection();

        $query = 'update wp_term_relationships set term_taxonomy_id=12 where object_id='.$id;
        mysql_query($query, $db_connection);
    }

    private function createConnection() {
        $connection = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
        mysql_select_db(DB_NAME, $connection);

        return $connection;
    }

    private function addRejectComment($id) {
        $db_connection = $this->createConnection();

        $query = 'insert into wp_comments (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent, comment_type, comment_parent, user_id) values ('.$id.', "WooCommerce", "'.date("Y-m-d h:i:s").'", "'.date("Y-m-d h:i:s").'", "Order changed from pending to cancelled", 0, "1", "WooCommerce", "order_note", 0, 0)';
        mysql_query($query, $db_connection);
    }

    private function approveOrderMySql($id) {
        $db_connection = $this->createConnection();
        $query = 'update wp_term_relationships set term_taxonomy_id=9 where object_id='.$id;
        mysql_query($query, $db_connection);
    }

    private function addApproveComment($id) {
        $db_connection = $this->createConnection();
        $query = 'insert into wp_comments (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent, comment_type, comment_parent, user_id) values ('.$id.', "WooCommerce", "'.date("Y-m-d h:i:s").'", "'.date("Y-m-d h:i:s").'", "Order changed from pending to processing", 0, "1", "WooCommerce", "order_note", 0, 0)';
        mysql_query($query, $db_connection);
    }

    private function approveOrder($order, $order_id, $transactionId) {
        $orderId = $this->getOrderIdByTransactionId($transactionId);

        //$this->unfreezeOrderByOrderId($orderId);

        require_once 'woocommerce' . DIRECTORY_SEPARATOR . 'class-wc-order.php';

        $order = new VP_WC_Order($orderId);

        // Reduce stock levels and approve order in woo tables
        $order->reduce_order_stock();
        $this->approveOrderMySql($orderId);
        $this->addApproveComment($orderId);

        global $woocommerce;

        /**
         * @var WC_Email $mailer
         */
        $mailer = $woocommerce->mailer();

        //$mailer->customer_completed_order($orderId);



        do_action('woocommerce_order_status_completed_notification');

    }

    public function handleServices() {
        $action = '_action_' . $_REQUEST['vp_action'];

        if (method_exists($this, $action)) {
            $this->{$action}($_REQUEST);
            die();
        }
    }

    public function _action_test_connection() {
        $result = $this->vp->getPingHeaders(
            $_REQUEST['transactionServiceURL'],
            $_REQUEST['merchantIdentifier'],
            $_REQUEST['apiKey']
        );
        $this->sendJSON($result);
    }

    private function _action_login() {
        $result = true;
        $user = null;
        $message = '';

        try {
            $result = $this->vp->login(
                $_REQUEST['username'],
                $_REQUEST['password']
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
            $result = false;
        }
        $user = $this->vp->getUserData();
        if ($user->errorMessage) {
            $result = false;
            $message = $user->errorMessage;
        }
        $this->sendJSON($result, $message, $user);
    }

    private function _action_set_logged_in_true() {
        $_SESSION['vp_logged_in'] = true;
    }

    private function _action_logout() {
        $this->vp->logout();
    }

    private function _action_destroy_session() {
        @session_destroy();
    }

    private function _action_get_data() {
        $data = $this->vp->getUserData();
        if ($data->errorMessage)
            $this->sendJSON(false, $data->errorMessage, $data);
        else
            $this->sendJSON(true, '', $data);
    }

    private function _action_set_options() {
        $childName = $_REQUEST['child'];
        $paymentName = $_REQUEST['payment'];

        $this->vp->setSelectedChild($childName);
        $this->vp->setSelectedPaymentMethod($paymentName);
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
            global $woocommerce;
            if(function_exists(wc_add_notice))
                wc_add_notice($data['ErrorMessage'],'error');
            else
<<<<<<< HEAD
                $woocommerce->add_error(__('Payment error:', 'woothemes') . $data['ErrorMessage']);
=======
                $woocommerce->add_error(__('Oink error:', 'woocommerce') . $data['ErrorMessage']);
>>>>>>> origin/woocomm-and-shopp-fixes
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

    private function _action_n() {
        global $woocommerce;
        $woocommerce->mailer();
        do_action('woocommerce_order_status_completed_notification', 42);

        echo 'Mail test';
    }

    private function _action_session() {
        pr($_SESSION);
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

    private function cancelOrderByTransactionId($transactionId) {
        $orderId = $this->getOrderIdByTransactionId($transactionId);

        if ($orderId) {
            $this->rejectOrder($orderId);
            $this->addRejectComment($orderId);
        }
    }

    private function approveOrderByTransactionId($transactionId, $orderInfo = null) {
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

        //$mailer->customer_completed_order($orderId);

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
        return "Oink: This order has been approved on $time. [ID:$id]";
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