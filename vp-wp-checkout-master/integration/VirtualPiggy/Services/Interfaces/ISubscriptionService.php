<?php
/**
 * @package VirtualPiggy.Services.Interfaces
 */
    interface ISubscriptionService {
        /**
         * Processes a subscription initiated by a child.
         * @return Object containing a reference to the subscription
        */		
        public function ProcessSubscription($token, $subscriptionData, $checkOutData, $description);
        /**
         * Processes a subscription that a parent makes on a child's behalf
         * @return Object containing a reference to the subscription
        */		        
        public function ProcessParentSubscription($token, $subscriptionData, $checkOutData, $description,$childIdentifier,$paymentAccountIdentifier);
        /**
         * Allows a merchant to cancel a subscription with a reference id returned by the ProcessSubscription or ProcessParentSubscription methods
         * @return Object containing the initial reference id. If the cancelation does not work an exception will be returned
        */		        
        public function MerchantCancelSubscription($Identifier);
        /**
         * Allows a merchant to cancel a subscription with a reference id returned by the ProcessSubscription or ProcessParentSubscription methods
         * @return Object containing the initial reference id. If the cancelation does not work an exception will be returned
        */		                
        public function MerchantCancelSubscriptionByExternalRef($ExternalRefIdentifier);
        /**
         * Allows a merchant to cancel a subscription with a reference id returned by the ProcessSubscription or ProcessParentSubscription methods
         * @return Object containing the initial reference id. If the cancelation does not work an exception will be returned
        */		                
        public function GetSubscriptionTransactionsByRef($externalIdentifier);
        /**
         * Allows a merchant to obtain all transactions that have been processed in association with a subscription.
         * @return Array containing a list of transactions
        */		        
        public function GetSubscriptionTransactions($subscriptionIdentifier);
    }
?>