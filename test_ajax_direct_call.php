<?php
/**
 * Test AJAX with WordPress's built-in AJAX mechanism
 */

define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

echo "Testing WordPress AJAX mechanism...\n";

// Simulate being logged in
wp_set_current_user(2);

// Test with a simple built-in AJAX action first
echo "Testing with built-in heartbeat action...\n";

$_POST['action'] = 'heartbeat';
$_POST['data'] = array('test' => 'data');

// Try to call the action
if (has_action('wp_ajax_heartbeat')) {
    echo "✓ Built-in heartbeat action exists\n";
} else {
    echo "✗ Built-in heartbeat action does not exist\n";
}

// Test our custom action
echo "Testing our custom action...\n";

if (has_action('wp_ajax_ktp_get_supplier_qualified_invoice_number')) {
    echo "✓ Our custom action exists\n";
} else {
    echo "✗ Our custom action does not exist\n";
}

// Check if the class method exists
if (class_exists('KTPWP_Ajax')) {
    $ajax_instance = KTPWP_Ajax::get_instance();
    if (method_exists($ajax_instance, 'ajax_get_supplier_qualified_invoice_number')) {
        echo "✓ Handler method exists\n";
    } else {
        echo "✗ Handler method does not exist\n";
    }
} else {
    echo "✗ KTPWP_Ajax class does not exist\n";
}

// Test the method directly
echo "Testing method directly...\n";

if (class_exists('KTPWP_Ajax')) {
    $ajax_instance = KTPWP_Ajax::get_instance();
    
    // Set up POST data
    $_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
    $_POST['supplier_id'] = '1';
    $_POST['_wpnonce'] = wp_create_nonce('ktp_ajax_nonce');
    
    echo "POST data: " . print_r($_POST, true) . "\n";
    
    // Call the method directly
    try {
        ob_start();
        $ajax_instance->ajax_get_supplier_qualified_invoice_number();
        $output = ob_get_clean();
        echo "Direct method output: " . ($output ?: 'No output') . "\n";
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}

echo "Test completed.\n";
?>
