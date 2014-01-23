<?php
/**
 * @package VirtualPiggy.Services.Implementations
 */
class MerchantPaymentServiceConfiguration implements IPaymentServiceConfiguration
{
    public function GetServiceConfiguration()
    {
        $config = new dtoPaymentGatewayConfiguration();
        /* ================================
         * Define all SOAP variables to be used for client soap call
        ================================ */
        $config->HeaderNamespace = "vp";
        $config->propMerchantIdentifier  = "MerchantIdentifier";
        $config->propApiKey = "APIkey";
        /* ================================
         * URLs for Virtual Piggy webservice & WSDL
        ================================ */        
        $config->TransactionServiceEndpointAddress = "https://development.virtualpiggy.com/Services/TransactionService.svc";
        $config->TransactionServiceEndpointAddressWsdl = "https://development.virtualpiggy.com/services/TransactionService.svc?wsdl";
        /* ================================
         * API Keys required for all webservice calls
        ================================ */
        $config->MerchantIdentifier = "03d081e1-2d57-4c98-8f1b-bbb83d4ab14a";
        $config->APIkey  = "gadgetboom123";        
        /* ================================
         * Default parameters for Virtual Piggy transaction
        ================================ */        
        $config->Currency = "USD";
        $config->DefaultShipmentMethod = "Delivery";
        return $config;
    }
}   
?>
