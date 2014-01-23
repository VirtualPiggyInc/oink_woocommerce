<?php
class VirtualPiggy {
    const USER_TYPE_PARENT = 'Parent';
    const USER_TYPE_CHILD = 'Child';
    const SESSION_FIELD = '_vp_user';

    private $config;
    private $user;

    public function __construct($config = array()) {
        require_once 'VirtualPiggyWPHelper.php';

        $this->load('Data/dtos');
        $this->load('Services/Interfaces/IPaymentService');
        $this->load('Services/Implementations/XmlToArray');
        $this->load('Services/Implementations/VirtualPiggyException');
        $this->load('Services/Implementations/VirtualPiggySoapClient');
        $this->load('Services/Implementations/VirtualPiggyPaymentService');

        $this->config = (object)$config;
        $this->config->HeaderNamespace = 'vp';
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION[self::SESSION_FIELD])) {
            $this->user = $_SESSION[self::SESSION_FIELD];
        }
    }

    private function load($path) {
        $path = preg_replace('[^a-z]', DIRECTORY_SEPARATOR, $path);

        $path = 'VirtualPiggy' . DIRECTORY_SEPARATOR . $path . '.php';

        require_once $path;
    }

    private function getPaymentService() {
        static $service;
        
        if (!$service) {
            $service = new VirtualPiggyPaymentService($this->config);
        }

        return $service;
    }

    public function login($username, $password) {
        $this->logout();
        $userDTO = new stdClass();

        $user = $this->getPaymentService()->AuthenticateUser($username, $password);

        if ($user->ErrorMessage) {
            throw new ErrorException($user->ErrorMessage);
        }

        $userDTO->username = $username;
        $userDTO->Token = $user->Token;
        $userDTO->UserType = $user->UserType;

        if ($userDTO->UserType == VirtualPiggy::USER_TYPE_PARENT) {
            $userDTO->childs = $this->getAllChilds($user->Token);
            $userDTO->payment = $this->getAllPaymentAccounts($user->Token);
        }

        $this->user = $_SESSION[self::SESSION_FIELD] = $userDTO;

        return true;
    }

    public function logout() {
        $this->user = $_SESSION[self::SESSION_FIELD] = null;
    }

    public function set($key, $value) {
        $this->config->{$key} = $value;
    }

    public function get($key) {
        return isset($this->config->{$key}) ? $this->config->{$key} : null;
    }

    private function getAllChilds($token) {
        return $this->getPaymentService()->GetAllChildren($token);
    }

    private function getAllPaymentAccounts($token) {
        return $this->getPaymentService()->GetPaymentAccounts($token);
    }

    public function getUserData() {
        $user = (array)$this->user;
        $dto = array(
            'name' => $user['username']
        );

        if (!count($user)) {
            return null;
        }

        if ($this->isParent()) {
            $dto['role'] = self::USER_TYPE_PARENT;
        } else {
            $dto['role'] = self::USER_TYPE_CHILD;
        }

        if ($this->isParent() && isset($user['childs']) && is_array($user['childs'])) {
            foreach ($user['childs'] as $child) {
                $child = (array)$child;
                $dto['childs'][] = $child['Name'];
            }
        }

        if ($this->isParent() && isset($user['payment']) && is_array($user['payment'])) {
            foreach ($user['payment'] as $payment) {
                $payment = (array)$payment;
                $dto['payment'][$payment['Type']] = $payment['Url'];
            }
        }

        return (object)$dto;
    }

    public function isParent() {
        return isset($this->user->UserType) && $this->user->UserType == VirtualPiggy::USER_TYPE_PARENT;
    }

    private function getCurrentUserToken() {
        return isset($this->user->Token) ? $this->user->Token : null;
    }

    private function getChildShippingDetails($childIdentifier) {
        if ($this->isParent()) {
            return $this->getPaymentService()->GetParentChildAddress(
                $this->getCurrentUserToken(),
                $childIdentifier
            );
        }

        return null;
    }

    public function getShippingDetailsByChildName($childName) {
        return $this->getChildShippingDetails(
            $this->getChildIdentifier($childName)
        );
    }

    public function getShippingDetailsBySelectedChild() {
        return $this->getChildShippingDetails(
            $this->getSelectedChild()
        );
    }

    private function getChildIdentifier($childName) {
        if (!isset($this->user->childs) || !is_array($this->user->childs)) {
            return null;
        }

        foreach ($this->user->childs as $child) {
            $child = (array)$child;
            if ($child['Name'] === $childName) {
                return $child['Token'];
            }
        }

        return null;
    }

    private function getPaymentIdentifier($paymentName) {
        if (!isset($this->user->payment) || !is_array($this->user->payment)) {
            return null;
        }

        foreach ($this->user->payment as $payment) {
            $payment = (array)$payment;
            if ($payment['Type'] === $paymentName) {
                return $payment['Token'];
            }
        }

        return null;
    }

    private function getCartDTOByWooCommerceOrder(WC_Order $order) {
        $cartDTO = new dtoCart();

        foreach ($order->get_items() as $item) {
            $itemDto = new dtoCartItem();

            $itemDto->Name = $item['name'];
            $itemDto->Description = $item['name'];
            $itemDto->Price = $item['line_total'];
            $itemDto->Quantity = $item['qty'];
            $itemDto->Total = $item['line_total'];

            $cartDTO->Items[] = $itemDto;
        }

        $cartDTO->Currency = $this->config->Currency;
        $cartDTO->Cost = $order->get_shipping();
        $cartDTO->Total = $order->get_total();
        $cartDTO->ShippmentTotal = $order->get_total();
        $cartDTO->Discount = $order->get_total_discount();
        $cartDTO->Tax = $order->get_total_tax();
        $cartDTO->ShipmentAddress = $this->getShippingAddressDTOByWooCommerceOrder($order);

        return $cartDTO;
    }

    private function getCartDTOByShoppOrder(Order $order, Purchase $purchase) {
        $cartDTO = new dtoCart();

        /**
         * @var Cart $cart
         */
        $cart = $order->Cart;

        /**
         * @var Item $item
         */
        foreach ($cart->contents as $item) {
            $itemDto = new dtoCartItem();

            $itemDto->Name = $item->name;
            $itemDto->Description = $item->description;
            $itemDto->Price = $item->priced;
            $itemDto->Quantity = $item->quantity;
            $itemDto->Total = $item->total;

            $cartDTO->Items[] = $itemDto;
        }

        $cartDTO->Currency = $this->config->Currency;
        $cartDTO->Cost = $purchase->shipping;
        $cartDTO->Total = $purchase->total;
        $cartDTO->ShippmentTotal = $purchase->total;
        $cartDTO->Discount = $purchase->discount;
        $cartDTO->Tax = $purchase->tax;
        $cartDTO->ShipmentAddress = $this->getShippingAddressDTOByShoppPurchase($purchase);

        return $cartDTO;
    }

    private function processParentPayment(dtoCart $cartDTO) {
        return $this->getPaymentService()->ProcessParentTransaction(
            $this->getCurrentUserToken(),
            $cartDTO->toEscapedXml(),
            '',
            $this->getSelectedChild(),
            $this->getSelectedPaymentMethod()
        );
    }

    private function processChildrenPayment(dtoCart $cartDTO) {
        return $this->getPaymentService()->ProcessTransaction(
            $cartDTO->toEscapedXml(),
            $this->getCurrentUserToken(),
            ''
        );
    }

    public function processPaymentByWooCommerceOrder(WC_Order $order) {
        $cartDTO = $this->getCartDTOByWooCommerceOrder($order);

        if ($this->isParent()) {
            $result = $this->processParentPayment($cartDTO);
        } else {
            $result = $this->processChildrenPayment($cartDTO);
        }

        if (!isset($result->Status) || !$result->Status) {
            throw new ErrorException($result->ErrorMessage);
        }

        @VirtualPiggyWPHelper::log($result, 'SYNC');

        return $result;
    }

    public function processPaymentByShoppOrder(Order $order, Purchase $purchase) {
        $cartDTO = $this->getCartDTOByShoppOrder($order, $purchase);

        if ($this->isParent()) {
            $result = $this->processParentPayment($cartDTO);
        } else {
            $result = $this->processChildrenPayment($cartDTO);
        }

        if (!$result->Status) {
            throw new ErrorException($result->ErrorMessage);
        }

        return $result->TransactionIdentifier;
    }

    private function getShippingAddressDTOByWooCommerceOrder(WC_Order $order) {
        $shippingAddressDTO = new dtoAddress();

        $shippingAddressDTO->Address = $order->shipping_address_1;
        $shippingAddressDTO->City = $order->shipping_city;
        $shippingAddressDTO->State = $order->shipping_state;
        $shippingAddressDTO->Zip = $order->shipping_postcode;
        $shippingAddressDTO->Country = $order->shipping_country;
        $shippingAddressDTO->Phone = $order->billing_phone;
        $shippingAddressDTO->ParentEmail = $order->billing_email;
        $shippingAddressDTO->ChildName = $this->user->selectedChildName;
        $shippingAddressDTO->ParentName = 'John Doe';

        return $shippingAddressDTO;
    }

    private function getShippingAddressDTOByShoppPurchase(Purchase $purchase) {
        $shippingAddressDTO = new dtoAddress();

        $shippingAddressDTO->Address = $purchase->address;
        $shippingAddressDTO->City = $purchase->city;
        $shippingAddressDTO->State = $purchase->state;
        $shippingAddressDTO->Zip = $purchase->postcode;
        $shippingAddressDTO->Country = $purchase->country;
        $shippingAddressDTO->Phone = $purchase->phone;
        $shippingAddressDTO->ParentEmail = $purchase->email;
        $shippingAddressDTO->ChildName = $this->user->selectedChildName;
        $shippingAddressDTO->ParentName = 'John Doe';

        return $shippingAddressDTO;
    }

    public function setSelectedPaymentMethod($paymentName) {
        $this->user->selectedPaymentMethod = $this->getPaymentIdentifier($paymentName);
    }

    private function getSelectedPaymentMethod() {
        return $this->user->selectedPaymentMethod;
    }

    public function setSelectedChild($childName) {
        $this->user->selectedChildName = $childName;
        $this->user->selectedChild = $this->getChildIdentifier($childName);
    }

    private function getSelectedChild() {
        return $this->user->selectedChild;
    }

    private function getSelectedChildName() {
        return $this->user->selectedChildName;
    }

    public function getCurrentChildShippingDetails() {
        return $this->getPaymentService()->GetChildAddress($this->getCurrentUserToken());
    }
}