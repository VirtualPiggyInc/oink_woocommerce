<?php

/**
 * @package VirtualPiggy.Services.Implementations
 */
class VirtualPiggySubscriptionService implements ISubscriptionService {

    var $config;

    function __construct($config) {
        $this->config = $config;
    }
    private function GetVPSoapClient(){
        $client = new VirtualPiggySoapClient($this->config);
        return $client;
    }
    /**
     * Default method to generate soap client with WCF security headers
     * set properly
     */
    private function GetNativeSoapClient() {
        $client = new SOAPClient($this->config->TransactionServiceEndpointAddressWsdl, array("trace" => 1));
        $headers = array();

        $headers[] = new SoapHeader($this->config->HeaderNamespace,
                        $this->config->propMerchantIdentifier,
                        $this->config->MerchantIdentifier);

        $headers[] = new SoapHeader($this->config->HeaderNamespace,
                        $this->config->propApiKey,
                        $this->config->APIkey);
        $client->__setSoapHeaders($headers);
        
        return $client;
    }
    
    private function GetSoapClient(){
        if(class_exists("SOAPClient")){
            return $this->GetNativeSoapClient();
        }else{
            return $this->GetVPSoapClient();
        }
    }
    /**
    * Processes a subscription initiated by a child.
    * @return Object containing a reference to the subscription
    */		
    public function ProcessSubscription($token, $subscriptionData, $checkOutData, $description) {
        $description = (empty($description) || strlen($description) == 0) ? 'Subscription' : $description;
        $result_dto = new dtoSubscriptionResult();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'token' => $token,
                'subscriptionData' => $subscriptionData,
                'checkOutData' => $checkOutData,
                'description' => $description,
            );
            $result = $client->ProcessSubscription($params);
            $result_dto->Xml = $client->__getLastResponse();
            $result_dto->ErrorMessage = $result->ProcessSubscriptionResult->ErrorMessage;
            $result_dto->Status = $result->ProcessSubscriptionResult->Status;
            $result_dto->SubscriptionIdentifier = $result->ProcessSubscriptionResult->Identifier;
            $result_dto->Name = $result->ProcessSubscriptionResult->Name;
            $result_dto->Type = $result->ProcessSubscriptionResult->Type;
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;    
        }
        return $result_dto;
    }
    /**
    * Processes a subscription that a parent makes on a child's behalf
    * @return Object containing a reference to the subscription
    */		        
    public function ProcessParentSubscription($token, $subscriptionData, $checkOutData, $description, $childIdentifier, $paymentAccountIdentifier) {
        $description = (empty($description) || strlen($description) == 0) ? 'Subscription' : $description;
        $result_dto = new dtoSubscriptionResult();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'token' => $token,
                'subscriptionData' => $subscriptionData,
                'checkOutData' => $checkOutData,
                'transactionDescription' => $description,
                'childIdentifier' => $childIdentifier,
                'paymentIdentifier' => $paymentAccountIdentifier,
            );
            $result = $client->ProcessParentSubscription($params);
            $result_dto->Xml = $client->__getLastResponse();
            $result_dto->ErrorMessage = $result->ProcessParentSubscriptionResult->ErrorMessage;
            $result_dto->Status = $result->ProcessParentSubscriptionResult->Status;
            $result_dto->SubscriptionIdentifier = $result->ProcessParentSubscriptionResult->Identifier;
            $result_dto->Name = $result->ProcessParentSubscriptionResult->Name;
            $result_dto->Type = $result->ProcessParentSubscriptionResult->Type;
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;
        }
        return $result_dto;
    }
    /**
    * Allows a merchant to cancel a subscription with a reference id returned by the ProcessSubscription or ProcessParentSubscription methods
    * @return Object containing the initial reference id. If the cancelation does not work an exception will be returned
    */		            
    public function MerchantCancelSubscription($Identifier){
        $result_dto = new dtoResultObject();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'Identifier' => $Identifier,
            );
            $result = $client->MerchantCancelSubscription($params);
            $result_dto->Xml = $client->__getLastResponse();
            $result_dto->ErrorMessage = $result->MerchantCancelSubscriptionResult->ErrorMessage;
            $result_dto->Status = $result->MerchantCancelSubscriptionResult->Status;
            $result_dto->Token = $result->MerchantCancelSubscriptionResult->scalar;
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;
            return $result_dto;
        }
        return $result_dto;
    }
    /**
    * Allows a merchant to obtain all transactions that have been processed in association with a subscription.
    * @return Array containing a list of transactions
    */		            
    public function GetSubscriptionTransactions($subscriptionIdentifier){
        $result_dto = new dtoResultObject();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'subscriptionIdentifier' => $subscriptionIdentifier,
            );
            $result = $client->GetSubscriptionTransactions($params);
            $transactions=array();
            foreach($result->GetSubscriptionTransactionsResult->Transaction as $transaction){

                $dtoTransaction=new dtoTransactionStatus();
                $dtoTransaction->Address=$transaction["Address"];
                $dtoTransaction->Amount=$transaction["Amount"];
                $dtoTransaction->ExpiryDate=$transaction["ExpirationDate"];
                $dtoTransaction->Zip=$transaction["Zip"];
                $dtoTransaction->City=$transaction["City"];
                $dtoTransaction->Country=$transaction["Country"];
                $dtoTransaction->Data=$transaction["DataXML"];
                $dtoTransaction->Description=$transaction["Description"];
                $dtoTransaction->Status=$transaction["Status"];
                $dtoTransaction->Id=$transaction["Id"];
                $dtoTransaction->Url=$transaction["Url"];
                $dtoTransaction->State=$transaction["State"];
                $dtoTransaction->MerchantIdentifier=$transaction["MerchantIdentifier"];
                $dtoTransaction->TransactionIdentifier=$transaction["TransactionIdentifier"];
                $transactions[]=$dtoTransaction;
            }
            return $transactions;
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;
            return $result_dto;    
        }
        return $result_dto;
    }
    public function MerchantCancelSubscriptionByExternalRef($ExternalRefIdentifier){
        $result_dto = new dtoResultObject();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'ExternalRefIdentifier' => $ExternalRefIdentifier,
            );
            $result = $client->ApproveSubscription($params);
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;
            return $result_dto;    
        }
        return $result;
    }

    
    public function GetSubscriptionTransactionsByRef($externalIdentifier){
        $result_dto = new dtoResultObject();
        $result_dto->Status = false;
        $result_dto->ErrorMessage = "SOAP call not executed.";
        try {
            $client = $this->GetSoapClient();
            $params = array(
                'ExternalRefIdentifier' => $externalRefIdentifier,
            );
            $result = $client->ApproveSubscription($params);
        } catch (Exception $e) {
            $result_dto->ErrorMessage = "Exception occured: " . $e;
            return $result_dto;    
        }
        return $result;
    }    
}

?>
