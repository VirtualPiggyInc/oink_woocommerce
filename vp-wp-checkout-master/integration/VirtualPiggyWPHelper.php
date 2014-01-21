<?php
class VirtualPiggyWPHelper {
    public static function getAssetURL() {
        return 'vp-wp-checkout/assets/';
    }

    public static function addJS($scriptName) {
        wp_enqueue_script(
            'vp-checkout',
            plugins_url(self::getAssetURL() . "js/$scriptName.js"),
            array('jquery'),
            null,
            true
        );

        wp_localize_script('vp-checkout', 'VPParams', array(
            'baseURL' => get_site_url(),
            'version' => VP_PLUGIN_VERSION
        ));
    }

    public static function addCSS($styleName) {
        wp_enqueue_style(
            'vp-checkout',
            plugins_url(self::getAssetURL() . "css/$styleName.css", '')
        );
    }

    public static function debug() {
        echo '<pre>' . PHP_EOL;

        foreach (func_get_args() as $arg) {
            if (is_bool($arg)) {
                echo $arg ? 'true' : 'false';
            } else {
                print_r($arg);
            }

            echo '<br />' . PHP_EOL;
        }

        echo '</pre>' . PHP_EOL;
    }

    public static function log($data, $tag = '') {
        $text = date('Y-m-d H:i:s') . " [$tag]" . '====================' . PHP_EOL;
        ob_start();
        print_r($data);
        $text .= ob_get_clean();
        $text .= PHP_EOL;

        $filePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'callback.log';

        @chmod($filePath, 0777);

        if(@file_exists($filePath) && filesize($filePath) > 1024 * 200) {
            @file_put_contents('', $filePath);
        }

        @file_put_contents($filePath, $text, FILE_APPEND);
    }
}
