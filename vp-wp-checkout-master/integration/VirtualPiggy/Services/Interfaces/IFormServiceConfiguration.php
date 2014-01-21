<?php
/**
 * @package VirtualPiggy.Services.Interfaces
 */
    interface IFormServiceConfiguration
    {
        public function GetAddressRequestFormConfiguration();
        public function GetCheckoutFormConfiguration();
        public function GetPreAuthorizedCheckoutFormConfiguration();
    }
?>
