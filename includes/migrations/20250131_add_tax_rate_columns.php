<?php
/**
 * Migration: Add tax_rate columns to invoice_items, cost_items, and service tables
 *
 * This migration adds tax_rate columns to support consumption tax functionality.
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
 * Migration class for adding tax_rate columns
 */
class KTPWP_Migration_20250131_Add_Tax_Rate_Columns {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;
        
        $success = true;
        
        // Add tax_rate column to ktp_order_invoice_items table
        $invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
        if ( self::add_tax_rate_column( $invoice_table, 'invoice_items' ) === false ) {
            $success = false;
        }
        
        // Add tax_rate column to ktp_order_cost_items table
        $cost_table = $wpdb->prefix . 'ktp_order_cost_items';
        if ( self::add_tax_rate_column( $cost_table, 'cost_items' ) === false ) {
            $success = false;
        }
        
        // Add tax_rate column to ktp_service table
        $service_table = $wpdb->prefix . 'ktp_service';
        if ( self::add_tax_rate_column( $service_table, 'service' ) === false ) {
            $success = false;
        }
        
        // Add tax settings to general settings
        if ( self::add_tax_settings() === false ) {
            $success = false;
        }
        
        return $success;
    }
    
    /**
     * Add tax_rate column to a table
     *
     * @param string $table_name Table name
     * @param string $table_type Table type for logging
     * @return bool True on success, false on failure
     */
    private static function add_tax_rate_column( $table_name, $table_type ) {
        global $wpdb;
        
        // Check if the table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) 
        ) === $table_name;
        
        if ( ! $table_exists ) {
            error_log( 'KTPWP Migration: Table ' . $table_name . ' does not exist. Skipping tax_rate column addition.' );
            return false;
        }
        
        // Check if the column already exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 
                'tax_rate' 
            ) 
        );
        
        if ( $column_exists ) {
            error_log( 'KTPWP Migration: Column tax_rate already exists in ' . $table_name . '. Skipping.' );
            return true;
        }
        
        // Add the tax_rate column
        // テーブルによってカラムの位置を調整
        if ( $table_name === $wpdb->prefix . 'ktp_service' ) {
            $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT '税率（%）' AFTER `price`";
        } else {
            $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT '税率（%）' AFTER `amount`";
        }
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'KTPWP Migration: Failed to add tax_rate column to ' . $table_name . '. Error: ' . $wpdb->last_error );
            return false;
        }
        
        // Log success
        error_log( 'KTPWP Migration: Successfully added tax_rate column to ' . $table_name );
        
        return true;
    }
    
    /**
     * Add tax settings to general settings
     *
     * @return bool True on success, false on failure
     */
    private static function add_tax_settings() {
        $general_settings = get_option( 'ktp_general_settings', array() );
        
        // Add default tax settings if they don't exist
        $tax_settings_added = false;
        
        if ( ! isset( $general_settings['default_tax_rate'] ) ) {
            $general_settings['default_tax_rate'] = 10.00;
            $tax_settings_added = true;
        }
        
        if ( ! isset( $general_settings['reduced_tax_rate'] ) ) {
            $general_settings['reduced_tax_rate'] = 8.00;
            $tax_settings_added = true;
        }
        

        
        if ( $tax_settings_added ) {
            $result = update_option( 'ktp_general_settings', $general_settings );
            if ( $result ) {
                error_log( 'KTPWP Migration: Successfully added tax settings to general settings' );
                return true;
            } else {
                error_log( 'KTPWP Migration: Failed to add tax settings to general settings' );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rollback the migration
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;
        
        $success = true;
        
        // Remove tax_rate column from ktp_order_invoice_items table
        $invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
        if ( self::remove_tax_rate_column( $invoice_table, 'invoice_items' ) === false ) {
            $success = false;
        }
        
        // Remove tax_rate column from ktp_order_cost_items table
        $cost_table = $wpdb->prefix . 'ktp_order_cost_items';
        if ( self::remove_tax_rate_column( $cost_table, 'cost_items' ) === false ) {
            $success = false;
        }
        
        // Remove tax_rate column from ktp_service table
        $service_table = $wpdb->prefix . 'ktp_service';
        if ( self::remove_tax_rate_column( $service_table, 'service' ) === false ) {
            $success = false;
        }
        
        // Remove tax settings from general settings
        if ( self::remove_tax_settings() === false ) {
            $success = false;
        }
        
        return $success;
    }
    
    /**
     * Remove tax_rate column from a table
     *
     * @param string $table_name Table name
     * @param string $table_type Table type for logging
     * @return bool True on success, false on failure
     */
    private static function remove_tax_rate_column( $table_name, $table_type ) {
        global $wpdb;
        
        // Check if the table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) 
        ) === $table_name;
        
        if ( ! $table_exists ) {
            error_log( 'KTPWP Migration: Table ' . $table_name . ' does not exist. Skipping tax_rate column removal.' );
            return false;
        }
        
        // Check if the column exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 
                'tax_rate' 
            ) 
        );
        
        if ( ! $column_exists ) {
            error_log( 'KTPWP Migration: Column tax_rate does not exist in ' . $table_name . '. Skipping.' );
            return true;
        }
        
        // Remove the tax_rate column
        $sql = "ALTER TABLE `{$table_name}` DROP COLUMN `tax_rate`";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'KTPWP Migration: Failed to remove tax_rate column from ' . $table_name . '. Error: ' . $wpdb->last_error );
            return false;
        }
        
        // Log success
        error_log( 'KTPWP Migration: Successfully removed tax_rate column from ' . $table_name );
        
        return true;
    }
    
    /**
     * Remove tax settings from general settings
     *
     * @return bool True on success, false on failure
     */
    private static function remove_tax_settings() {
        $general_settings = get_option( 'ktp_general_settings', array() );
        
        // Remove tax settings if they exist
        $tax_settings_removed = false;
        
        if ( isset( $general_settings['default_tax_rate'] ) ) {
            unset( $general_settings['default_tax_rate'] );
            $tax_settings_removed = true;
        }
        
        if ( isset( $general_settings['reduced_tax_rate'] ) ) {
            unset( $general_settings['reduced_tax_rate'] );
            $tax_settings_removed = true;
        }
        

        
        if ( $tax_settings_removed ) {
            $result = update_option( 'ktp_general_settings', $general_settings );
            if ( $result ) {
                error_log( 'KTPWP Migration: Successfully removed tax settings from general settings' );
                return true;
            } else {
                error_log( 'KTPWP Migration: Failed to remove tax settings from general settings' );
                return false;
            }
        }
        
        return true;
    }
}

// Run the migration if this file is executed directly
if ( ! function_exists( 'add_action' ) ) {
    // CLI execution
    KTPWP_Migration_20250131_Add_Tax_Rate_Columns::up();
} 