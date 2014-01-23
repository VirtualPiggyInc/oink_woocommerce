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
        $order = $this->Order;
        $OrderTotals = $order->Cart->Totals;
        $Billing = $order->Billing;
        $Paymethod = $order->paymethod();

        /**
         * @var VirtualPiggy $vp
         */
        $vp = get_vp_payment()->getVP();

        $transactionId = $vp->processPaymentByShoppOrder($order, $this->getPurchaseByOrder($order));

        shopp_add_order_event($Event->order, 'capture', array(
            'txnorigin' => $transactionId,
            'txnid' => $transactionId,
            'amount' => $OrderTotals->total,
            'fees' => 0,
            'gateway' => $Paymethod->processor,
            'paymethod' => $Paymethod->label,
            'paytype' => $Billing->cardtype,
            'payid' => $Billing->card,
            'user' => 0
        ));

        //$this->freezePurchase($order, $this->getPurchaseByOrder($order));

        Shopping::instance()->reset();
        $this->markPurchase($order, $this->getPurchaseByOrder($order), $transactionId);
        shopp_redirect( shoppurl(false,'thanks') );
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

        foreach ($defaults as $name => $value) {
            if (!isset($this->settings[$name]) || !$this->settings[$name]) {
                $this->settings[$name] = $defaults[$name];
            }
        }
    }

    /**
     * @deprecated
     * @param Order $order
     * @param Purchase $purchase
     */
    private function freezePurchase(Order $order, Purchase $purchase) {
        global $wpdb;

        $purchaseId = $purchase->id;
        $purchaseTable = $purchase->_table;
        $frozenTable = $wpdb->prefix . "vp_shopp_order";

//        $wpdb->query("DELETE FROM $frozenTable WHERE id = $purchaseId");
        $wpdb->query("INSERT INTO $frozenTable (SELECT * FROM $purchaseTable WHERE id = $purchaseId)");
        $wpdb->query("DELETE FROM $purchaseTable WHERE id = $purchaseId");

    }

    private function markPurchase(Order $order, Purchase $purchase, $transactionId) {
        global $wpdb;

        $purchaseId = $order->inprogress;
        $purchaseTable = $purchase->_table;

        $wpdb->query("UPDATE $purchaseTable SET txnid = '$transactionId', txnstatus='purchase' WHERE id = $purchaseId");
    }
}