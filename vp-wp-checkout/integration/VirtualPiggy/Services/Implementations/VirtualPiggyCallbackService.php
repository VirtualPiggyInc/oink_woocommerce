<?php

/**
 * @package VirtualPiggy.Services.Implementations
 */
class VirtualPiggyCallbackService implements ICallbackService {
    /**
     * This should implements the method used by the callbacks
     *
     * @param $name
     * @return null
     */
    private function fetch($name) {
        return isset($_REQUEST[$name]) ? $_REQUEST[$name] : null ;
    }

    public function GetCallbackAddressInformation()
    {
        $address = new dtoAddressRequest();
        
        if ($this->fetch('MerchantIdentifier'))        
        {
            $address->MerchantIdentifier = $this->fetch('MerchantIdentifier');
        }
        if ($this->fetch('Token'))        
        {
            $address->Token = $this->fetch('Token');
        }
        if ($this->fetch('Email'))        
        {
            $address->Email = $this->fetch('Email');
        }
        if ($this->fetch('FirstName'))        
        {
            $address->FirstName = $this->fetch('FirstName');
        }
        if ($this->fetch('LastName'))        
        {
            $address->LastName = $this->fetch('LastName');
        }
        if ($this->fetch('Address'))        
        {
            $address->Address = $this->fetch('Address');
        }
        if ($this->fetch('Zip'))        
        {
            $address->Zip = $this->fetch('Zip');
        }
        if ($this->fetch('State'))        
        {
            $address->State = $this->fetch('State');
        }

        return $address;
         
    }
    
    public function GetCallbackTransactionStatus() {
        $status = new dtoTransactionStatus();
        if ($this->fetch('id'))
            $status->id = $this->fetch('id');
        if ($this->fetch('Status'))
            $status->Status = $this->fetch('Status');
        if ($this->fetch('Url'))
            $status->Url = $this->fetch('Url');
        if ($this->fetch('errorMessage'))
            $status->errorMessage = $this->fetch('errorMessage');
        if ($this->fetch('MerchantIdentifier'))
            $status->MerchantIdentifier = $this->fetch('MerchantIdentifier');
        if ($this->fetch('TransactionIdentifier'))
            $status->TransactionIdentifier = $this->fetch('TransactionIdentifier');
        if ($this->fetch('Description'))
            $status->Description = $this->fetch('Description');
        if ($this->fetch('Amount'))
            $status->Amount = $this->fetch('Amount');
        if ($this->fetch('ExpiryDate'))
            $status->ExpiryDate = $this->fetch('ExpiryDate');
        if ($this->fetch('Data'))
            $status->Data = $this->fetch('Data');
        if ($this->fetch('Address'))
            $status->Address = $this->fetch('Address');
        if ($this->fetch('City'))
            $status->City = $this->fetch('City');
        if ($this->fetch('Zip'))
            $status->Zip = $this->fetch('Zip');
        if ($this->fetch('Email'))
            $status->Email = $this->fetch('Email');
        if ($this->fetch('State'))
            $status->State = $this->fetch('State');
        if ($this->fetch('Country'))
            $status->Country = $this->fetch('Country');
        return $status;
    }

}

?>
