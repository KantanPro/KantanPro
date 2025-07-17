<?php
/**
 * Simple AJAX Test with WordPress hooks
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Simulate being logged in as admin
wp_set_current_user(1);

// Get current user info
$current_user = wp_get_current_user();
echo "Current user: " . $current_user->user_login . " (ID: " . $current_user->ID . ")\n";
echo "User capabilities: edit_posts=" . (current_user_can('edit_posts') ? 'yes' : 'no') . "\n";

// Check if AJAX hooks are registered
global $wp_filter;
$ajax_hook = 'wp_ajax_ktp_get_supplier_qualified_invoice_number';
if (isset($wp_filter[$ajax_hook])) {
    echo "AJAX hook '$ajax_hook' is registered\n";
    
    // Get the callbacks
    $callbacks = $wp_filter[$ajax_hook]->callbacks;
    foreach ($callbacks as $priority => $callback_group) {
        foreach ($callback_group as $callback) {
            echo "  - Callback: " . print_r($callback['function'], true) . "\n";
        }
    }
} else {
    echo "AJAX hook '$ajax_hook' is NOT registered\n";
}

// Check if handler class exists
if (class_exists('KTPWP_Ajax')) {
    echo "KTPWP_Ajax class exists\n";
    $ajax_instance = KTPWP_Ajax::get_instance();
    if ($ajax_instance) {
        echo "KTPWP_Ajax instance created\n";
        if (method_exists($ajax_instance, 'ajax_get_supplier_qualified_invoice_number')) {
            echo "Handler method exists\n";
        }
    }
} else {
    echo "KTPWP_Ajax class does NOT exist\n";
}

// Test by triggering the WordPress action
echo "\nTesting by triggering WordPress action...\n";

// Set POST data
$_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
$_POST['supplier_id'] = '1';
$_POST['_wpnonce'] = wp_create_nonce('ktp_ajax_nonce');

// Capture output
ob_start();
do_action('wp_ajax_ktp_get_supplier_qualified_invoice_number');
$output = ob_get_clean();

echo "Action output: " . ($output ?: 'No output') . "\n";

// Check for any errors
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo "WP_DEBUG is enabled\n";
}
?>
