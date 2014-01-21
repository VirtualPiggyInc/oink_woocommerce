<?php
/**
 * virtualpiggy.com
 * @class VirtualPiggyGateway
 * @since 1.2
 * @version 0.2
 * @subpackage VirtualPiggyGateway
 **/
class VirtualPiggyGateway extends GatewayFramework implements GatewayModule {

    var $secure = false; // SSL not required
    var $multi = true; // Support multiple methods
    var $captures = true; // Supports Auth-only
    var $refunds = true; // Supports refunds

    var $methods = array(); // List of active OfflinePayment payment methods

    function __construct() {
        parent::__construct();

        $this->setDefaults();

        add_action('shopp_virtualpiggygateway_sale', array(&$this, 'auth')); // process sales as auth-only
        add_action('shopp_virtualpiggygateway_auth', array(&$this, 'auth'));
        add_action('shopp_virtualpiggygateway_capture', array(&$this, 'capture'));
        add_action('shopp_virtualpiggygateway_refund', array(&$this, 'refund'));
        add_action('shopp_virtualpiggygateway_void', array(&$this, 'void'));
    }

    function actions() {
    }

    private function getPurchaseByOrder(Order $order) {
        $purchaseId = $order->inprogress;
        return new Purchase($purchaseId);
    }

    /**
     * Process the order
     *
     * Process the order but leave it in PENDING status.
     *
     * @author Jonathan Davis
     * @since 1.1
     *
     * @return void
     **/
    function auth($Event) {
        set_time_limit(0);

        $order = $this->Order;
        $OrderTotals = $order->Cart->Totals;
        $Paymethod = $order->paymethod();

        if (!function_exists('get_vp_payment')) {
            throw new ErrorException('This payment method is not allowed.');
        }

        /**
         * @var VirtualPiggy $vp
         */
        $vp = get_vp_payment()->getVP();

        $purchase = $this->getPurchaseByOrder($order);
        $result = $vp->processPaymentByShoppOrder($order, $purchase);
        $transactionId = $result->TransactionIdentifier;
        shopp_add_order_event($Event->order, 'capture', array(
            'txnid' => $transactionId,
            'amount' => $OrderTotals->total,
            'gateway' => $Paymethod->processor,
            'user' => 0
        ));

        $persistResult = virtual_piggy_persist_transaction($transactionId, $purchase->id);

        if (class_exists('VirtualPiggyWPHelper')) {
            VirtualPiggyWPHelper::log(array(
                'transactionId' => $transactionId,
                'purchaseId' => $purchase->id,
                'persistResult' => $persistResult,
                'order' => $order
            ), 'POST_SHOPP_AUTH');
        }

        if (virtual_piggy_is_transaction_approved($transactionId)) {
            $this->approvePurchase($order, $purchase, $transactionId);
        } else if (virtual_piggy_is_transaction_rejected($transactionId)) {
            $this->rejectPurchase($order, $purchase, $transactionId);
        } else {
            // We must wait the callback
            $this->freezePurchase($order, $purchase, $transactionId);
        }

        // This forces the txnid in the purchase
        virtual_piggy_shopp_persist_transaction_id($transactionId, $purchase->id);

        Shopping::instance()->reset();
        shopp_redirect(shoppurl(false, 'thanks'));
    }

    function capture($Event) {

    }

    function refund($Event) {

    }

    function void($Event) {

    }

    function settings() {
        $this->ui->text(0, array(
            'name' => 'MerchantIdentifier',
            'size' => 40,
            'value' => $this->settings['MerchantIdentifier'],
            'label' => __('Unique Merchant Identifier.', 'Shopp')
        ));

        $this->ui->text(0, array(
            'name' => 'APIkey',
            'size' => 40,
            'value' => $this->settings['APIkey'],
            'label' => __('Virtual Piggy API Key.', 'Shopp')
        ));

        $this->ui->text(1, array(
            'name' => 'TransactionServiceEndpointAddress',
            'size' => 70,
            'value' => $this->settings['TransactionServiceEndpointAddress'],
            'label' => __('Transaction Service Endpoint Address.', 'Shopp')
        ));

        $this->ui->text(1, array(
            'name' => 'TransactionServiceEndpointAddressWsdl',
            'size' => 70,
            'value' => $this->settings['TransactionServiceEndpointAddressWsdl'],
            'label' => __('Transaction Service Endpoint Address (WSDL).', 'Shopp')
        ));

        $this->ui->text(0, array(
            'name' => 'ParentServiceEndpointAddress',
            'size' => 70,
            'value' => $this->settings['ParentServiceEndpointAddress'],
            'label' => __('Parent Service Endpoint Address.', 'Shopp')
        ));

        $this->ui->text(0, array(
            'name' => 'ParentServiceEndpointAddressWsdl',
            'size' => 70,
            'value' => $this->settings['ParentServiceEndpointAddressWsdl'],
            'label' => __('Parent Service Endpoint Address (WSDL).', 'Shopp')
        ));
    }

    private function setDefaults() {
        $defaults = array();
        $defaults['MerchantIdentifier'] = 'a1d2e935-7f4d-4c70-8dfb-f0e6cace5774';
        $defaults['APIkey'] = 'gadgetboom123';
        $defaults['TransactionServiceEndpointAddress'] = 'https://development.virtualpiggy.com/services/TransactionService.svc';
        $defaults['TransactionServiceEndpointAddressWsdl'] = 'https://development.virtualpiggy.com/services/TransactionService.svc?wsdl';
        $defaults['ParentServiceEndpointAddress'] = 'https://development.virtualpiggy.com/services/JSON/ParentService.svc';
        $defaults['ParentServiceEndpointAddressWsdl'] = 'https://development.virtualpiggy.com/services/JSON/ParentService.svc?wsdl';
        $defaults['Currency'] = 'USD';

        $settings = $this->settings;

        if (count($settings)) {
            $settings = array_shift($settings);
        } else {
            $settings = array();
        }

        foreach ($defaults as $name => $value) {
            if (!isset($settings[$name]) || empty($settings[$name])) {
                $settings[$name] = $defaults[$name];
            }
        }

        if (function_exists('get_vp_payment')) {
            get_vp_payment()->getVP()->setSettings($settings);
        }
    }

    private function updatePurchase(Order $order, Purchase $purchase, $transactionId, $status) {
        global $wpdb;

        $purchaseTable = $purchase->_table;
        $purchaseId = $purchase->id;

        $query = "
            UPDATE
                $purchaseTable
            SET
                txnstatus='$status',
                txnid='$transactionId'
            WHERE
                id = $purchaseId
        ";

        $wpdb->query($query);
    }

    private function approvePurchase(Order $order, Purchase $purchase, $transactionId) {
        $this->updatePurchase($order, $purchase, $transactionId, 'captured');

        virtual_piggy_shopp_persist_event_authed($transactionId, $purchase->id);
    }


    private function rejectPurchase(Order $order, Purchase $purchase, $transactionId) {
        $this->updatePurchase($order, $purchase, $transactionId, 'voided');
    }

    private function freezePurchase(Order $order, Purchase $purchase, $transactionId) {
        $this->updatePurchase($order, $purchase, $transactionId, 'capture');
    }

    private function insertTransactionId($purchase, $transactionId) {
        global $wpdb;

        $vpTable = $wpdb->prefix . 'vp_shopp_transactions';

        $wpdb->insert($vpTable, array('id' => $purchase->id, 'transactionId' => $transactionId));
    }
}