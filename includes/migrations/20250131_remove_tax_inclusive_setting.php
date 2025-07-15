<?php
/**
 * Migration to remove tax_inclusive setting from general settings
 *
 * @package KTPWP
 * @subpackage Migrations
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Migration class to remove tax_inclusive setting
 */
class KTPWP_Migration_20250131_Remove_Tax_Inclusive_Setting {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        error_log( 'KTPWP Migration: Starting removal of tax_inclusive setting' );
        
        $success = self::remove_tax_inclusive_setting();
        
        if ( $success ) {
            error_log( 'KTPWP Migration: Successfully removed tax_inclusive setting' );
        } else {
            error_log( 'KTPWP Migration: Failed to remove tax_inclusive setting' );
        }
        
        return $success;
    }
    
    /**
     * Remove tax_inclusive setting from general settings
     *
     * @return bool True on success, false on failure
     */
    private static function remove_tax_inclusive_setting() {
        $general_settings = get_option( 'ktp_general_settings', array() );
        
        // Remove tax_inclusive setting if it exists
        if ( isset( $general_settings['tax_inclusive'] ) ) {
            unset( $general_settings['tax_inclusive'] );
            
            $result = update_option( 'ktp_general_settings', $general_settings );
            if ( $result ) {
                error_log( 'KTPWP Migration: Successfully removed tax_inclusive setting from general settings' );
                return true;
            } else {
                error_log( 'KTPWP Migration: Failed to remove tax_inclusive setting from general settings' );
                return false;
            }
        } else {
            error_log( 'KTPWP Migration: tax_inclusive setting does not exist. Nothing to remove.' );
            return true;
        }
    }
    
    /**
     * Rollback the migration (add back the setting)
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        error_log( 'KTPWP Migration: Rolling back removal of tax_inclusive setting' );
        
        $general_settings = get_option( 'ktp_general_settings', array() );
        
        // Add back tax_inclusive setting
        if ( ! isset( $general_settings['tax_inclusive'] ) ) {
            $general_settings['tax_inclusive'] = false; // 税抜き表示がデフォルト
            
            $result = update_option( 'ktp_general_settings', $general_settings );
            if ( $result ) {
                error_log( 'KTPWP Migration: Successfully restored tax_inclusive setting' );
                return true;
            } else {
                error_log( 'KTPWP Migration: Failed to restore tax_inclusive setting' );
                return false;
            }
        } else {
            error_log( 'KTPWP Migration: tax_inclusive setting already exists. Nothing to restore.' );
            return true;
        }
    }
}

// Run the migration if this file is executed directly
if ( ! function_exists( 'add_action' ) ) {
    // CLI execution
    KTPWP_Migration_20250131_Remove_Tax_Inclusive_Setting::up();
} 