<?php
class WC_Crezco_Log {
    private static $fileName = 'crezco.log';


    public static function add($data, $title = null) {
        $settings = WC_Admin_Settings::get_option('woocommerce_crezco_settings');

        if (!empty($settings) && $settings['debug']) {
            if (!file_exists(plugin_dir_path(__FILE__) . 'logs')) {
                mkdir(plugin_dir_path(__FILE__) . 'logs', 0777, true);
            }
            file_put_contents(plugin_dir_path(__FILE__) . 'logs/'. self::$fileName, 'Crezco debug (' . $title . '): ' . json_encode($data), FILE_APPEND);
        }
    }
}