<?php
/**
 * Migration script to update tax category labels from "税込|税抜" to "内税|外税"
 * 
 * @package KTPWP
 * @subpackage Migrations
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Update tax category labels in client table
 */
function ktpwp_update_tax_category_labels() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_client';
    
    // Check if table exists
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
    if ( $table_exists !== $table_name ) {
        error_log( 'KTPWP Migration: Client table does not exist' );
        return false;
    }
    
    // Check if tax_category column exists
    $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'tax_category' ) );
    if ( empty( $column_exists ) ) {
        error_log( 'KTPWP Migration: tax_category column does not exist' );
        return false;
    }
    
    // Update "税込" to "内税"
    $update_inclusive = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_name} SET tax_category = %s WHERE tax_category = %s",
            '内税',
            '税込'
        )
    );
    
    // Update "税抜" to "外税"
    $update_exclusive = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_name} SET tax_category = %s WHERE tax_category = %s",
            '外税',
            '税抜'
        )
    );
    
    if ( $update_inclusive === false || $update_exclusive === false ) {
        error_log( 'KTPWP Migration: Failed to update tax category labels' );
        return false;
    }
    
    // Log the results
    $inclusive_count = $wpdb->rows_affected;
    $wpdb->flush();
    
    $exclusive_count = $wpdb->rows_affected;
    
    error_log( "KTPWP Migration: Updated {$inclusive_count} records from '税込' to '内税'" );
    error_log( "KTPWP Migration: Updated {$exclusive_count} records from '税抜' to '外税'" );
    
    // Set migration flag
    update_option( 'ktpwp_tax_category_labels_updated', true );
    
    return true;
}

// Run migration if not already done
if ( ! get_option( 'ktpwp_tax_category_labels_updated' ) ) {
    ktpwp_update_tax_category_labels();
} 