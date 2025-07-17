<?php
/**
 * Direct AJAX Test without WordPress user session
 * 
 * This script tests AJAX handlers by simulating POST request
 */

// Define WordPress path
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Simulate being logged in as admin (for testing purposes)
wp_set_current_user(1); // Assuming user ID 1 is admin

echo "Testing AJAX handler directly...\n";

// Simulate POST data
$_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
$_POST['supplier_id'] = '1';
$_POST['_wpnonce'] = wp_create_nonce('ktp_ajax_nonce');
$_POST['nonce'] = wp_create_nonce('ktp_ajax_nonce');
$_POST['security'] = wp_create_nonce('ktp_ajax_nonce');

echo "POST data set: " . print_r($_POST, true) . "\n";

// Get AJAX instance
global $ktpwp_ajax;
if ($ktpwp_ajax && method_exists($ktpwp_ajax, 'ajax_get_supplier_qualified_invoice_number')) {
    echo "Calling AJAX handler...\n";
    
    // Capture all output
    ob_start();
    try {
        $ktpwp_ajax->ajax_get_supplier_qualified_invoice_number();
    } catch (Exception $e) {
        echo "Exception caught: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    
    echo "Handler output: " . $output . "\n";
} else {
    echo "AJAX handler not available\n";
}

// Check debug log for any new entries
echo "\nRecent debug log entries:\n";
$debug_log = '/Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/debug.log';
if (file_exists($debug_log)) {
    $command = 'tail -20 ' . escapeshellarg($debug_log) . ' | grep "KTPWP Ajax"';
    $recent_lines = shell_exec($command);
    echo $recent_lines ?: "No recent KTPWP Ajax entries found\n";
}
?>
