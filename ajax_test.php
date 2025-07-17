<?php
/**
 * AJAX Test Page
 * 
 * This page tests AJAX functionality in a web context
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Check if it's an AJAX request
if (isset($_POST['test_ajax'])) {
    // Simulate AJAX request
    $_POST['action'] = 'ktp_get_supplier_qualified_invoice_number';
    $_POST['supplier_id'] = '1';
    $_POST['_wpnonce'] = wp_create_nonce('ktp_ajax_nonce');
    
    // Call the WordPress AJAX handler
    $response = wp_remote_post(admin_url('admin-ajax.php'), array(
        'body' => $_POST,
        'timeout' => 15,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(),
        'cookies' => array(),
    ));
    
    if (is_wp_error($response)) {
        echo 'Error: ' . $response->get_error_message();
    } else {
        echo 'Response: ' . wp_remote_retrieve_body($response);
    }
    exit;
}

// Get current user
$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html>
<head>
    <title>AJAX Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>AJAX Test Page</h1>
    
    <p>Current user: <?php echo $current_user->user_login ?: 'Not logged in'; ?> (ID: <?php echo $current_user->ID; ?>)</p>
    <p>User capabilities: edit_posts=<?php echo current_user_can('edit_posts') ? 'yes' : 'no'; ?></p>
    
    <h2>Test AJAX Handler</h2>
    <button id="test-ajax">Test AJAX</button>
    <div id="ajax-result"></div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-ajax').click(function() {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ktp_get_supplier_qualified_invoice_number',
                    supplier_id: '1',
                    _wpnonce: '<?php echo wp_create_nonce('ktp_ajax_nonce'); ?>'
                },
                success: function(response) {
                    $('#ajax-result').html('Success: ' + response);
                },
                error: function(xhr, status, error) {
                    $('#ajax-result').html('Error: ' + xhr.status + ' - ' + error + '<br>Response: ' + xhr.responseText);
                }
            });
        });
    });
    </script>
</body>
</html>
