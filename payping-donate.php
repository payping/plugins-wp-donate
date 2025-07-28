<?php
/*
Plugin Name: افزونه حمایت مالی پی‌پینگ برای وردپرس
Version: 2.0.0
Description: افزونه حمایت مالی از وبسایت ها -- برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [PayPingDonate]
Plugin URI: https://github.com/payping/plugins-wp-donate
Author: PayPing Team
Author URI: https://payping.ir/
*/

defined('ABSPATH') || exit;

define('PP_DONATE_VERSION', '2.0.0');
define('PP_DONATE_DB_VERSION', '1.1'); // Current database version
define('PP_DONATE_PATH', plugin_dir_path(__FILE__));
define('PP_DONATE_URL', plugin_dir_url(__FILE__));


require_once PP_DONATE_PATH . 'includes/class-database.php';
require_once PP_DONATE_PATH . 'includes/class-admin.php';
require_once PP_DONATE_PATH . 'includes/class-form-handler.php';

add_action('plugins_loaded', function(){
    $current_db_version = get_option('pp_donate_db_version', '1.0');
    
    // Compare versions and update if needed
    if (version_compare($current_db_version, PP_DONATE_DB_VERSION, '<')) {
        PP_Donate_DB::install();
        update_option('pp_donate_db_version', PP_DONATE_DB_VERSION);
    }
    
    new PP_Donate_Admin();
    new PP_Donate_Form_Handler();
});

function dd($data){
    echo "<pre dir='ltr'>"; var_dump($data); die();
}