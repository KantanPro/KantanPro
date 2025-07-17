<?php
/**
 * Test AJAX with complete WordPress initialization
 */

// Set up WordPress environment properly
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost:8081';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php';

// Set up POST data
$_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
$_POST['supplier_id'] = '1';
$_POST['_wpnonce'] = 'test_nonce';

// Set up fake user cookie for authentication
$_COOKIE['wordpress_logged_in_' . md5('localhost:8081' . '/wp-content')] = 'kantan|' . (time() + 3600) . '|' . md5('test');

// Initialize WordPress
define('WP_USE_THEMES', false);
define('DOING_AJAX', true);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Set user
wp_set_current_user(2);

// Generate valid nonce
$nonce = wp_create_nonce('ktp_ajax_nonce');
$_POST['_wpnonce'] = $nonce;

echo "Testing AJAX with complete WordPress initialization...\n";
echo "Current user: " . get_current_user_id() . "\n";
echo "Nonce: " . $nonce . "\n";

// Load admin-ajax.php
require_once(ABSPATH . 'wp-admin/admin-ajax.php');
?>
