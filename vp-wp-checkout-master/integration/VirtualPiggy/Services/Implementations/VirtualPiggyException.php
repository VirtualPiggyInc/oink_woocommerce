<?php
class VirtualPiggyException extends Exception{
    
    public function __toString() {
        return $this->getMessage();
    }
    
}
?>
