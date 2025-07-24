<?php
/**
 * Migration: Allow NULL tax_rate in supplier skills table
 * 
 * This migration modifies the tax_rate column in the ktp_supplier_skills table
 * to allow NULL values, which is useful for cases where tax rate is not applicable
 * or not yet determined.
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
 * Migration class for allowing NULL tax_rate in supplier skills
 */
class KTPWP_Migration_Allow_Null_Tax_Rate_In_Supplier_Skills {

    /**
     * Migration version
     */
    const VERSION = '3.4.0';

    /**
     * Option name for tracking migration status
     */
    const OPTION_NAME = 'ktp_supplier_skills_table_version';

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function run() {
        global $wpdb;

        // Check if migration is already completed
        $current_version = get_option( self::OPTION_NAME );
        if ( version_compare( $current_version, self::VERSION, '>=' ) ) {
            return true;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( ! $table_exists ) {
            error_log( 'KTPWP: Supplier skills table does not exist. Skipping migration.' );
            return false;
        }

        // Check if tax_rate column exists
        $column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'tax_rate'" );
        if ( ! $column_exists ) {
            error_log( 'KTPWP: tax_rate column does not exist in supplier skills table. Skipping migration.' );
            return false;
        }

        // Get current column definition
        $current_definition = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'tax_rate'" );
        
        // Check if column already allows NULL
        if ( $current_definition->Null === 'YES' ) {
            error_log( 'KTPWP: tax_rate column already allows NULL values. Migration not needed.' );
            update_option( self::OPTION_NAME, self::VERSION );
            return true;
        }

        // Modify column to allow NULL values
        $sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT 10.00 COMMENT '税率'";
        
        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to modify tax_rate column to allow NULL values. Error: ' . $wpdb->last_error );
            return false;
        }

        // Update version
        update_option( self::OPTION_NAME, self::VERSION );

        error_log( 'KTPWP: Successfully modified tax_rate column to allow NULL values in supplier skills table.' );
        return true;
    }

    /**
     * Rollback the migration (if needed)
     *
     * @return bool True on success, false on failure
     */
    public static function rollback() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( ! $table_exists ) {
            return false;
        }

        // Check if tax_rate column exists
        $column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'tax_rate'" );
        if ( ! $column_exists ) {
            return false;
        }

        // Get current column definition
        $current_definition = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'tax_rate'" );
        
        // Check if column already does not allow NULL
        if ( $current_definition->Null === 'NO' ) {
            return true;
        }

        // Modify column to not allow NULL values
        $sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT '税率'";
        
        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to rollback tax_rate column modification. Error: ' . $wpdb->last_error );
            return false;
        }

        // Revert version
        update_option( self::OPTION_NAME, '3.3.0' );

        error_log( 'KTPWP: Successfully rolled back tax_rate column modification.' );
        return true;
    }
}

// Auto-run migration if this file is accessed directly
if ( defined( 'WP_CLI' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
    // Only run if accessed directly or in debug mode
    if ( ! defined( 'ABSPATH' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        $result = KTPWP_Migration_Allow_Null_Tax_Rate_In_Supplier_Skills::run();
        if ( $result ) {
            echo "Migration completed successfully.\n";
        } else {
            echo "Migration failed.\n";
        }
    }
} 