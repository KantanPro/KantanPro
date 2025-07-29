<?php
/**
 * Migration: Add qualified invoice profit calculation functionality
 *
 * This migration adds the qualified invoice profit calculation functionality
 * to the KantanPro plugin.
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
 * Migration class for adding qualified invoice profit calculation functionality
 */
class KTPWP_Migration_20250131_Add_Qualified_Invoice_Profit_Calculation {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Starting qualified invoice profit calculation migration' );
        }

        try {
            // 1. 協力会社テーブルの適格請求書ナンバーカラム確認
            $supplier_table = $wpdb->prefix . 'ktp_supplier';
            $column_exists = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$supplier_table}` LIKE %s", 
                    'qualified_invoice_number' 
                ) 
            );
            
            if ( ! $column_exists ) {
                // 適格請求書ナンバーカラムが存在しない場合は追加
                $sql = "ALTER TABLE `{$supplier_table}` ADD COLUMN `qualified_invoice_number` VARCHAR(100) NOT NULL DEFAULT '' AFTER `memo`";
                $result = $wpdb->query( $sql );
                
                if ( $result === false ) {
                    error_log( 'KTPWP Migration: Failed to add qualified_invoice_number column to supplier table. Error: ' . $wpdb->last_error );
                    return false;
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: Successfully added qualified_invoice_number column to supplier table' );
                }
            }

            // 2. コスト項目テーブルのsupplier_idカラム確認
            $cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';
            $supplier_id_exists = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$cost_items_table}` LIKE %s", 
                    'supplier_id' 
                ) 
            );
            
            if ( ! $supplier_id_exists ) {
                // supplier_idカラムが存在しない場合は追加
                $sql = "ALTER TABLE `{$cost_items_table}` ADD COLUMN `supplier_id` INT(11) DEFAULT NULL AFTER `order_id`";
                $result = $wpdb->query( $sql );
                
                if ( $result === false ) {
                    error_log( 'KTPWP Migration: Failed to add supplier_id column to cost items table. Error: ' . $wpdb->last_error );
                    return false;
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: Successfully added supplier_id column to cost items table' );
                }
            }

            // 3. 既存のコスト項目データのsupplier_idを更新（可能な場合）
            self::update_existing_cost_items_supplier_id();

            // 4. 設定オプションの追加
            self::add_qualified_invoice_settings();

            // 5. マイグレーション完了フラグを設定
            update_option( 'ktpwp_qualified_invoice_profit_calculation_version', '1.0.0' );
            update_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', true );
            update_option( 'ktpwp_qualified_invoice_profit_calculation_timestamp', current_time( 'mysql' ) );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Successfully completed qualified invoice profit calculation migration' );
            }

            return true;

        } catch ( Exception $e ) {
            error_log( 'KTPWP Migration Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Update existing cost items with supplier_id where possible
     */
    private static function update_existing_cost_items_supplier_id() {
        global $wpdb;
        
        $cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';
        $supplier_table = $wpdb->prefix . 'ktp_supplier';

        // purchaseカラムが存在するかチェック
        $purchase_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `{$cost_items_table}` LIKE %s", 
                'purchase' 
            ) 
        );

        if ( $purchase_exists ) {
            // purchaseカラムの値を使ってsupplier_idを更新
            $sql = "
                UPDATE `{$cost_items_table}` ci
                INNER JOIN `{$supplier_table}` s ON ci.purchase = s.company_name
                SET ci.supplier_id = s.id
                WHERE ci.supplier_id IS NULL AND ci.purchase != ''
            ";
            
            $result = $wpdb->query( $sql );
            
            if ( $result !== false ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: Updated ' . $result . ' cost items with supplier_id based on purchase field' );
                }
            }
        }
    }

    /**
     * Add qualified invoice related settings
     */
    private static function add_qualified_invoice_settings() {
        // 適格請求書ナンバー機能の有効化設定
        if ( ! get_option( 'ktpwp_qualified_invoice_enabled' ) ) {
            add_option( 'ktpwp_qualified_invoice_enabled', true );
        }

        // 適格請求書ナンバー機能の説明設定
        if ( ! get_option( 'ktpwp_qualified_invoice_description' ) ) {
            add_option( 'ktpwp_qualified_invoice_description', '適格請求書ナンバーの有無による仕入税額控除の可否を考慮した利益計算機能' );
        }

        // デバッグモード設定
        if ( ! get_option( 'ktpwp_qualified_invoice_debug_mode' ) ) {
            add_option( 'ktpwp_qualified_invoice_debug_mode', false );
        }
    }

    /**
     * Rollback the migration
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: Rolling back qualified invoice profit calculation migration' );
        }

        try {
            // 設定オプションを削除
            delete_option( 'ktpwp_qualified_invoice_enabled' );
            delete_option( 'ktpwp_qualified_invoice_description' );
            delete_option( 'ktpwp_qualified_invoice_debug_mode' );
            delete_option( 'ktpwp_qualified_invoice_profit_calculation_version' );
            delete_option( 'ktpwp_qualified_invoice_profit_calculation_migrated' );
            delete_option( 'ktpwp_qualified_invoice_profit_calculation_timestamp' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Successfully rolled back qualified invoice profit calculation migration' );
            }

            return true;

        } catch ( Exception $e ) {
            error_log( 'KTPWP Migration Rollback Error: ' . $e->getMessage() );
            return false;
        }
    }
}

// Run the migration if this file is executed directly
if ( ! function_exists( 'add_action' ) ) {
    // CLI execution
    KTPWP_Migration_20250131_Add_Qualified_Invoice_Profit_Calculation::up();
} 