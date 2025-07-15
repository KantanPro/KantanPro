<?php
/**
 * Migration: Add tax_rate column to service table
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add tax_rate column to service table
 */
function ktpwp_add_tax_rate_to_service_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_service';
    
    // Check if tax_rate column already exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'tax_rate'",
            DB_NAME,
            $table_name
        )
    );
    
    if ( empty( $column_exists ) ) {
        // Add tax_rate column
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 NOT NULL AFTER `price`";
        $result = $wpdb->query( $sql );
        
        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Successfully added tax_rate column to {$table_name} table" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Error adding tax_rate column to {$table_name} table: " . $wpdb->last_error );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: tax_rate column already exists in {$table_name} table" );
        }
    }
}

// Run migration if called directly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    ktpwp_add_tax_rate_to_service_table();
} 