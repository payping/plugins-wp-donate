<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;
$table = $wpdb->prefix . 'payping_donations';

$wpdb->query("DROP TABLE IF EXISTS $table");

delete_option('pp_donate_token');
delete_option('pp_donate_success_text');
delete_option('pp_donate_error_text');
delete_option('pp_donate_custom_style');
