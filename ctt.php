<?php
/**
 * @package INDIGIT
 * @version 1.0.0
 */
/*
Plugin Name: WooCommerce CTT by INDIGIT
Plugin URI: https://indigit.pt
Description: This connects WooCommerce to CTT
Author: INDIGIT®
Version: 1.0.0
Author URI: https://indigit.pt
*/

define('INDIGIT_PLG_DIR', WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)));
define('INDIGIT_PLG_DIR_LOGS', INDIGIT_PLG_DIR . '/logs');

define('INDIGIT_CTT_ENABLED', 'indigit_ctt_enabled');
define('INDIGIT_CTT_PRODUCTION', 'indigit_ctt_production');
define('INDIGIT_CTT_AUTHENTICATION_ID', 'indigit_ctt_authentication_id');
define('INDIGIT_CTT_CLIENT_ID', 'indigit_ctt_client_id');
define('INDIGIT_CTT_USER_ID', 'indigit_ctt_user_id');
define('INDIGIT_CTT_CONTRACT_ID', 'indigit_ctt_contract_id');
define('INDIGIT_CTT_DISTRIBUTION_CHANNEL_ID', 'indigit_ctt_distribution_channel_id');
define('INDIGIT_CTT_SUB_PRODUCT_ID', 'indigit_ctt_sub_product_id');
define('INDIGIT_CTT_REFERENCE_PREFIX', 'indigit_ctt_reference_prefix');
define('INDIGIT_CTT_PHONE_NUMBER', 'indigit_ctt_phone_number');


$composer_autoloader = __DIR__ . '/vendor/autoload.php';
if (is_readable($composer_autoloader)) {
    /** @noinspection PhpIncludeInspection */
    require $composer_autoloader;
}


/**
 * Get INDIGIT plugin
 *
 * @return \INDIGIT\Plugin
 */
function indigit()
{
    return \INDIGIT\Plugin::instance();
}

// Bind
indigit()->registerAdminMenu()->registerListeners();


// Debug & Helpers
/**
 * Log debug message
 *
 * @param string prefix
 * @param string|object $message
 * @param array $data
 */
if(!function_exists('indigit_log')){
    function indigit_log($prefix = 'info', $message = '', $data = [])
    {

        if ($message instanceof \Exception) {
            $data = array_merge($data, [
                '_file' => $message->getFile(),
                '_line' => $message->getLine(),
                '_trace' => $message->getTraceAsString()
            ]);
            $message = $message->getMessage();
        }
        $dateTime = new DateTime();
        $logStr = strtoupper($prefix) . ': ';
        $logStr .= $message . ' ';
        $logStr .= json_encode($data);
        $logStr .= "\n\n";

        try {
            file_put_contents(INDIGIT_PLG_DIR_LOGS . '/log-' . $dateTime->format('Y-m-d') . '.log', $logStr, FILE_APPEND);
        } catch (\Exception $e) {
        }
    }

}
