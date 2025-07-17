<?php
/**
 * Debug AJAX handler registration before admin-ajax.php
 */

// Set up WordPress environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost:8081';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php';

// Set up POST data
$_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
$_POST['supplier_id'] = '1';

// Initialize WordPress
define('WP_USE_THEMES', false);
define('DOING_AJAX', true);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Set user
wp_set_current_user(2);

// Generate valid nonce
$nonce = wp_create_nonce('ktp_ajax_nonce');
$_POST['_wpnonce'] = $nonce;

echo "Debug AJAX handler registration...\n";
echo "Current user: " . get_current_user_id() . "\n";
echo "User can edit_posts: " . (current_user_can('edit_posts') ? 'yes' : 'no') . "\n";
echo "Nonce: " . $nonce . "\n";

// Check if hooks are registered
global $wp_filter;

$hooks_to_check = [
    'wp_ajax_ktp_get_supplier_qualified_invoice_number',
    'wp_ajax_nopriv_ktp_get_supplier_qualified_invoice_number',
    'init',
    'wp_loaded'
];

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        echo "✓ Hook '$hook' is registered with " . count($wp_filter[$hook]->callbacks) . " priority levels\n";
    } else {
        echo "✗ Hook '$hook' is NOT registered\n";
    }
}

// Check if the AJAX class has been initialized
if (class_exists('KTPWP_Ajax')) {
    echo "✓ KTPWP_Ajax class exists\n";
    
    $ajax_instance = KTPWP_Ajax::get_instance();
    if ($ajax_instance) {
        echo "✓ KTPWP_Ajax instance created\n";
    } else {
        echo "✗ KTPWP_Ajax instance NOT created\n";
    }
} else {
    echo "✗ KTPWP_Ajax class does NOT exist\n";
}

// Check if init has been called
echo "Init action called: " . did_action('init') . " times\n";
echo "wp_loaded action called: " . did_action('wp_loaded') . " times\n";

// Force register ajax handlers if they're not registered
if (!isset($wp_filter['wp_ajax_ktp_get_supplier_qualified_invoice_number'])) {
    echo "Forcing AJAX handler registration...\n";
    if (class_exists('KTPWP_Ajax')) {
        $ajax_instance = KTPWP_Ajax::get_instance();
        if (method_exists($ajax_instance, 'register_ajax_handlers')) {
            $ajax_instance->register_ajax_handlers();
            echo "AJAX handlers registered manually\n";
        }
    }
}

// Check again
if (isset($wp_filter['wp_ajax_ktp_get_supplier_qualified_invoice_number'])) {
    echo "✓ Hook 'wp_ajax_ktp_get_supplier_qualified_invoice_number' is now registered\n";
} else {
    echo "✗ Hook 'wp_ajax_ktp_get_supplier_qualified_invoice_number' is still NOT registered\n";
}

// Show available ajax actions
echo "\nAvailable AJAX actions:\n";
$ajax_actions = array_keys($wp_filter);
$ajax_actions = array_filter($ajax_actions, function($action) {
    return strpos($action, 'wp_ajax_') === 0;
});
sort($ajax_actions);
foreach (array_slice($ajax_actions, 0, 10) as $action) {
    echo "  - $action\n";
}
if (count($ajax_actions) > 10) {
    echo "  ... and " . (count($ajax_actions) - 10) . " more\n";
}

echo "\nTest completed.\n";
?>
