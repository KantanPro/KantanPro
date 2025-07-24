<?php
/**
 * Migration: Add qualified_invoice_number column to supplier table
 *
 * This migration adds the qualified_invoice_number column to the ktp_supplier table
 * to support qualified invoice number functionality.
 *
 * @package KTPWP
 * @subpackage Migrations
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Migration class for adding qualified_invoice_number column to supplier table
 */
class KTPWP_Migration_20250130_Add_Qualified_Invoice_Number_To_Supplier {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_supplier';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) 
        ) === $table_name;
        
        if ( ! $table_exists ) {
            error_log( 'KTPWP Migration: Table ' . $table_name . ' does not exist. Skipping qualified_invoice_number column addition.' );
            return false;
        }
        
        // Check if the column already exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 
                'qualified_invoice_number' 
            ) 
        );
        
        if ( $column_exists ) {
            error_log( 'KTPWP Migration: Column qualified_invoice_number already exists in ' . $table_name . '. Skipping.' );
            return true;
        }
        
        // Add the qualified_invoice_number column
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `qualified_invoice_number` VARCHAR(100) NOT NULL DEFAULT '' AFTER `memo`";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'KTPWP Migration: Failed to add qualified_invoice_number column to ' . $table_name . '. Error: ' . $wpdb->last_error );
            return false;
        }
        
        // Log success
        error_log( 'KTPWP Migration: Successfully added qualified_invoice_number column to ' . $table_name );
        
        return true;
    }
    
    /**
     * Rollback the migration
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_supplier';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) 
        ) === $table_name;
        
        if ( ! $table_exists ) {
            error_log( 'KTPWP Migration: Table ' . $table_name . ' does not exist. Skipping qualified_invoice_number column removal.' );
            return false;
        }
        
        // Check if the column exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 
                'qualified_invoice_number' 
            ) 
        );
        
        if ( ! $column_exists ) {
            error_log( 'KTPWP Migration: Column qualified_invoice_number does not exist in ' . $table_name . '. Skipping.' );
            return true;
        }
        
        // Remove the qualified_invoice_number column
        $sql = "ALTER TABLE `{$table_name}` DROP COLUMN `qualified_invoice_number`";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'KTPWP Migration: Failed to remove qualified_invoice_number column from ' . $table_name . '. Error: ' . $wpdb->last_error );
            return false;
        }
        
        // Log success
        error_log( 'KTPWP Migration: Successfully removed qualified_invoice_number column from ' . $table_name );
        
        return true;
    }
}

// Run the migration if this file is executed directly
if ( ! function_exists( 'add_action' ) ) {
    // CLI execution
    KTPWP_Migration_20250130_Add_Qualified_Invoice_Number_To_Supplier::up();
} 