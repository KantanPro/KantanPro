<?php
/**
 * Standalone test for AJAX handler logic without WordPress database
 * This tests the core logic of the AJAX handlers to identify the issue
 */

// Mock WordPress functions
function wp_verify_nonce($nonce, $action) {
    // Simulate successful nonce verification for testing
    return true;
}

function wp_die($message = '', $title = '', $args = array()) {
    echo "wp_die called: $message\n";
    exit;
}

function wp_send_json_error($data = null) {
    echo "ERROR: " . json_encode($data) . "\n";
    exit;
}

function wp_send_json_success($data = null) {
    echo "SUCCESS: " . json_encode($data) . "\n";
    exit;
}

function current_user_can($capability) {
    return true; // Simulate admin user
}

function debug_log($message) {
    echo "LOG: $message\n";
}

// Mock global variables
$_POST = array(
    'supplier_id' => '123',
    'nonce' => 'test_nonce',
    '_wpnonce' => 'test_nonce',
    '_ajax_nonce' => 'test_nonce',
    'ktp_ajax_nonce' => 'test_nonce',
    'security' => 'test_nonce'
);

// Mock database class
class MockWPDB {
    public function prepare($query, ...$args) {
        return sprintf($query, ...$args);
    }
    
    public function get_var($query) {
        // Simulate database result
        if (strpos($query, 'qualified_invoice_number') !== false) {
            return 'QI-2024-001'; // Mock qualified invoice number
        }
        if (strpos($query, 'tax_category') !== false) {
            return 'standard'; // Mock tax category
        }
        return null;
    }
}

$wpdb = new MockWPDB();

// Include the AJAX handler logic (simplified version)
class Test_KTPWP_AJAX {
    
    public function ajax_get_supplier_qualified_invoice_number() {
        debug_log('=== ajax_get_supplier_qualified_invoice_number called ===');
        
        // Get supplier ID
        $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
        debug_log('Supplier ID: ' . $supplier_id);
        
        if (!$supplier_id) {
            debug_log('No supplier_id provided');
            wp_send_json_error('No supplier_id provided');
            return;
        }
        
        // Check nonce (multiple keys)
        $nonce_keys = array('nonce', '_wpnonce', '_ajax_nonce', 'ktp_ajax_nonce', 'security');
        $nonce_verified = false;
        
        foreach ($nonce_keys as $key) {
            if (isset($_POST[$key])) {
                $nonce = $_POST[$key];
                debug_log("Checking nonce with key '$key': $nonce");
                if (wp_verify_nonce($nonce, 'ktp_ajax_nonce')) {
                    $nonce_verified = true;
                    debug_log("Nonce verified with key: $key");
                    break;
                }
            }
        }
        
        if (!$nonce_verified && !current_user_can('manage_options')) {
            debug_log('Nonce verification failed');
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        // Mock database query
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_supplier';
        $qualified_invoice_number = $wpdb->get_var($wpdb->prepare(
            "SELECT qualified_invoice_number FROM {$table_name} WHERE id = %d",
            $supplier_id
        ));
        
        debug_log('Qualified invoice number: ' . ($qualified_invoice_number ? $qualified_invoice_number : 'NULL'));
        
        wp_send_json_success(array(
            'qualified_invoice_number' => $qualified_invoice_number ? $qualified_invoice_number : ''
        ));
    }
    
    public function ajax_get_supplier_tax_category() {
        debug_log('=== ajax_get_supplier_tax_category called ===');
        
        // Get supplier ID
        $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
        debug_log('Supplier ID: ' . $supplier_id);
        
        if (!$supplier_id) {
            debug_log('No supplier_id provided');
            wp_send_json_error('No supplier_id provided');
            return;
        }
        
        // Check nonce (multiple keys)
        $nonce_keys = array('nonce', '_wpnonce', '_ajax_nonce', 'ktp_ajax_nonce', 'security');
        $nonce_verified = false;
        
        foreach ($nonce_keys as $key) {
            if (isset($_POST[$key])) {
                $nonce = $_POST[$key];
                debug_log("Checking nonce with key '$key': $nonce");
                if (wp_verify_nonce($nonce, 'ktp_ajax_nonce')) {
                    $nonce_verified = true;
                    debug_log("Nonce verified with key: $key");
                    break;
                }
            }
        }
        
        if (!$nonce_verified && !current_user_can('manage_options')) {
            debug_log('Nonce verification failed');
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        // Mock database query
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_supplier';
        $tax_category = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_category FROM {$table_name} WHERE id = %d",
            $supplier_id
        ));
        
        debug_log('Tax category: ' . ($tax_category ? $tax_category : 'NULL'));
        
        wp_send_json_success(array(
            'tax_category' => $tax_category ? $tax_category : 'standard'
        ));
    }
}

// Test the handlers
echo "=== Testing AJAX Handlers ===\n";

$ajax_handler = new Test_KTPWP_AJAX();

echo "\n--- Testing qualified invoice number handler ---\n";
$ajax_handler->ajax_get_supplier_qualified_invoice_number();

echo "\n--- Testing tax category handler ---\n";
$ajax_handler->ajax_get_supplier_tax_category();

echo "\n=== Test Complete ===\n";
