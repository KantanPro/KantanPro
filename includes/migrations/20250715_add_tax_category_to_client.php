<?php
/**
 * Migration: Add tax_category column to client table
 *
 * This migration adds tax_category column to support internal/external tax display.
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
 * Migration class for adding tax_category column to client table
 */
class KTPWP_Migration_20250715_Add_Tax_Category_To_Client {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;
        
        $client_table = $wpdb->prefix . 'ktp_client';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $client_table 
            ) 
        ) === $client_table;
        
        if ( ! $table_exists ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Table ' . $client_table . ' does not exist. Skipping tax_category column addition.' );
            }
            return false;
        }
        
        // Check if the column already exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$client_table}` LIKE %s", 
                'tax_category' 
            ) 
        );
        
        if ( $column_exists ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Column tax_category already exists in ' . $client_table . '. Skipping.' );
            }
            return true;
        }
        
        // Add the tax_category column
        $sql = "ALTER TABLE `{$client_table}` ADD COLUMN `tax_category` ENUM('内税', '外税') NOT NULL DEFAULT '内税' COMMENT '税区分（内税・外税）' AFTER `email`";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Failed to add tax_category column to ' . $client_table . '. Error: ' . $wpdb->last_error );
            }
            return false;
        }
        
        // Update existing clients to have default tax_category
        $update_result = $wpdb->query( 
            "UPDATE `{$client_table}` SET `tax_category` = '内税' WHERE `tax_category` IS NULL OR `tax_category` = ''" 
        );
        
        if ( $update_result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Updated ' . $update_result . ' existing clients with default tax_category' );
            }
        }
        
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Successfully added tax_category column to ' . $client_table );
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
        
        $client_table = $wpdb->prefix . 'ktp_client';
        
        // Check if the column exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$client_table}` LIKE %s", 
                'tax_category' 
            ) 
        );
        
        if ( ! $column_exists ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Column tax_category does not exist in ' . $client_table . '. Nothing to remove.' );
            }
            return true;
        }
        
        // Remove the tax_category column
        $sql = "ALTER TABLE `{$client_table}` DROP COLUMN `tax_category`";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Failed to remove tax_category column from ' . $client_table . '. Error: ' . $wpdb->last_error );
            }
            return false;
        }
        
        // Log success
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Successfully removed tax_category column from ' . $client_table );
        }
        
        return true;
    }
    
    /**
     * Check if migration is needed
     *
     * @return bool True if migration is needed, false otherwise
     */
    public static function needs_migration() {
        global $wpdb;
        
        $client_table = $wpdb->prefix . 'ktp_client';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $client_table 
            ) 
        ) === $client_table;
        
        if ( ! $table_exists ) {
            return false;
        }
        
        // Check if column exists
        $column_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$client_table}` LIKE %s", 
                'tax_category' 
            ) 
        );
        
        return ! $column_exists;
    }
}

// マイグレーションクラスのみ定義（自動実行はしない）
// プラグインの有効化時に ktpwp_run_auto_migrations() 関数で実行される 