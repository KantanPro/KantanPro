<?php
/**
 * Check WordPress AJAX Hooks Registration
 */

define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/Desktop/ktpwp/wordpress/wp-load.php');

// Check if hooks are registered
global $wp_filter;

echo "Checking WordPress AJAX hooks registration...\n";

// Check for our specific AJAX actions
$ajax_actions = [
    'wp_ajax_ktp_get_supplier_qualified_invoice_number',
    'wp_ajax_ktp_get_supplier_tax_category',
    'wp_ajax_nopriv_ktp_get_supplier_qualified_invoice_number',
    'wp_ajax_nopriv_ktp_get_supplier_tax_category',
];

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "✓ Hook '$action' is registered\n";
        
        // Show callbacks
        foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function'])) {
                    $func = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                } else {
                    $func = $callback['function'];
                }
                echo "  - Priority $priority: $func\n";
            }
        }
    } else {
        echo "✗ Hook '$action' is NOT registered\n";
    }
}

// Check KTPWP_Ajax class
echo "\nChecking KTPWP_Ajax class...\n";

if (class_exists('KTPWP_Ajax')) {
    echo "✓ KTPWP_Ajax class exists\n";
    
    $ajax_instance = KTPWP_Ajax::get_instance();
    if ($ajax_instance) {
        echo "✓ KTPWP_Ajax instance created\n";
        
        // Check for the method
        if (method_exists($ajax_instance, 'ajax_get_supplier_qualified_invoice_number')) {
            echo "✓ Method ajax_get_supplier_qualified_invoice_number exists\n";
        } else {
            echo "✗ Method ajax_get_supplier_qualified_invoice_number does NOT exist\n";
        }
        
        if (method_exists($ajax_instance, 'register_ajax_handlers')) {
            echo "✓ Method register_ajax_handlers exists\n";
        } else {
            echo "✗ Method register_ajax_handlers does NOT exist\n";
        }
        
        // Check if registered_handlers property exists
        if (property_exists($ajax_instance, 'registered_handlers')) {
            echo "✓ Property registered_handlers exists\n";
            // Cannot access private property directly
            echo "  (registered_handlers is private - cannot access directly)\n";
        } else {
            echo "✗ Property registered_handlers does NOT exist\n";
        }
    } else {
        echo "✗ KTPWP_Ajax instance could not be created\n";
    }
} else {
    echo "✗ KTPWP_Ajax class does NOT exist\n";
}

// Check if init action has been called
echo "\nChecking WordPress init actions...\n";

if (did_action('init')) {
    echo "✓ WordPress 'init' action has been called " . did_action('init') . " times\n";
} else {
    echo "✗ WordPress 'init' action has NOT been called\n";
}

// Force trigger init to ensure hooks are registered
echo "\nForcing WordPress init to ensure hooks are registered...\n";
do_action('init');

// Check again
echo "Checking hooks again after forced init...\n";
if (isset($wp_filter['wp_ajax_ktp_get_supplier_qualified_invoice_number'])) {
    echo "✓ Hook 'wp_ajax_ktp_get_supplier_qualified_invoice_number' is now registered\n";
} else {
    echo "✗ Hook 'wp_ajax_ktp_get_supplier_qualified_invoice_number' is still NOT registered\n";
}
?>
