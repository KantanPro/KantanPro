<?php
/**
 * Create donations table migration
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
 * Migration: Create donations table
 */
class KTP_Migration_20250127_Create_Donation_Table {
    
    /**
     * Execute the migration
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'JPY',
            donor_name varchar(255) DEFAULT '',
            donor_email varchar(255) DEFAULT '',
            donor_message text,
            stripe_payment_intent_id varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY donor_email (donor_email)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // Migration completed successfully
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Created ktp_donations table' );
        }
        
        return array(
            'success' => true,
            'message' => 'Created donations table successfully'
        );
    }
    
    /**
     * Rollback the migration
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Dropped ktp_donations table' );
        }
        
        return array(
            'success' => true,
            'message' => 'Dropped donations table successfully'
        );
    }
} 