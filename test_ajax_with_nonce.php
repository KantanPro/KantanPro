<?php
/**
 * Get valid nonce for AJAX testing
 */
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Simulate being logged in as the admin user
wp_set_current_user(2); // User ID 2 is 'kantan'

// Generate valid nonce
$nonce = wp_create_nonce('ktp_ajax_nonce');

// Check if we can verify the nonce
$verify_result = wp_verify_nonce($nonce, 'ktp_ajax_nonce');

echo "Generated nonce: " . $nonce . "\n";
echo "Verification result: " . ($verify_result ? 'Valid' : 'Invalid') . "\n";

// Test AJAX request with valid nonce
$ajax_url = admin_url('admin-ajax.php');
echo "AJAX URL: " . $ajax_url . "\n";

// Force the correct URL for testing
$ajax_url = 'http://localhost:8081/wp-admin/admin-ajax.php';
echo "Corrected AJAX URL: " . $ajax_url . "\n";

$post_data = array(
    'action' => 'ktp_get_supplier_qualified_invoice_number',
    'supplier_id' => '1',
    '_wpnonce' => $nonce
);

$response = wp_remote_post($ajax_url, array(
    'body' => $post_data,
    'timeout' => 15,
    'cookies' => array(), // No cookies needed for testing
));

if (is_wp_error($response)) {
    echo "Error: " . $response->get_error_message() . "\n";
} else {
    echo "Response code: " . wp_remote_retrieve_response_code($response) . "\n";
    echo "Response body: " . wp_remote_retrieve_body($response) . "\n";
}
?>
