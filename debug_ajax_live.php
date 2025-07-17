<?php
/**
 * Live AJAX Debug Test
 * 
 * This script tests AJAX handlers with valid WordPress context
 */

// Define WordPress path
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    echo "User not logged in. Please log in to WordPress first.\n";
    exit;
}

// Get current user
$current_user = wp_get_current_user();
echo "Current user: " . $current_user->user_login . " (ID: " . $current_user->ID . ")\n";

// Check if AJAX actions are registered
$wp_actions = $GLOBALS['wp_filter']['wp_ajax_ktp_get_supplier_qualified_invoice_number'] ?? null;
echo "AJAX action 'ktp_get_supplier_qualified_invoice_number' registered: " . ($wp_actions ? 'YES' : 'NO') . "\n";

$wp_actions2 = $GLOBALS['wp_filter']['wp_ajax_ktp_get_supplier_tax_category'] ?? null;
echo "AJAX action 'ktp_get_supplier_tax_category' registered: " . ($wp_actions2 ? 'YES' : 'NO') . "\n";

// Generate valid nonce
$nonce = wp_create_nonce('ktp_ajax_nonce');
echo "Generated nonce: " . $nonce . "\n";

// Test AJAX handler directly
if (function_exists('verify_ajax_referer')) {
    echo "verify_ajax_referer function exists\n";
}

// Test direct handler call
if (class_exists('KTPWP_Ajax')) {
    echo "KTPWP_Ajax class exists\n";
    
    // Get instance
    global $ktpwp_ajax;
    if ($ktpwp_ajax) {
        echo "KTPWP_Ajax instance available\n";
        
        // Simulate AJAX request
        $_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
        $_POST['supplier_id'] = '1';
        $_POST['_wpnonce'] = $nonce;
        $_POST['nonce'] = $nonce;
        $_POST['security'] = $nonce;
        
        // Call handler method directly (be careful with output)
        if (method_exists($ktpwp_ajax, 'ajax_get_supplier_qualified_invoice_number')) {
            echo "Handler method exists\n";
            
            // Capture output
            ob_start();
            try {
                $ktpwp_ajax->ajax_get_supplier_qualified_invoice_number();
            } catch (Exception $e) {
                echo "Exception: " . $e->getMessage() . "\n";
            }
            $output = ob_get_clean();
            
            echo "Handler output: " . $output . "\n";
        } else {
            echo "Handler method does not exist\n";
        }
    } else {
        echo "KTPWP_Ajax instance not available\n";
    }
} else {
    echo "KTPWP_Ajax class does not exist\n";
}
?>
