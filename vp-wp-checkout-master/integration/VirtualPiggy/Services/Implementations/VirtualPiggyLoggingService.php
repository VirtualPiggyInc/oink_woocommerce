<?php

class VirtualPiggyLoggingService implements ILoggingService
{
    public function Log($result)
    {
            $logfile = $_SERVER['DOCUMENT_ROOT'].'/VirtualPiggy.'.date("YmdHis").'.log';
            // writing response to external file
            $f = fopen($logfile, 'w');
            ob_start();
            print_r($result);
            $return = ob_get_contents();
            ob_end_clean();
            fwrite($f, $return);
            fclose($f);
    }
}
?>
