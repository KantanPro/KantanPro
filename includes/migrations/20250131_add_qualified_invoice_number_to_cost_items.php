<?php
/**
 * コスト項目テーブルに適格請求書番号カラムを追加するマイグレーション
 * 
 * このマイグレーションは、ktp_order_cost_itemsテーブルに
 * qualified_invoice_numberカラムを追加します。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KTPWP_Migration_20250131_Add_Qualified_Invoice_Number_To_Cost_Items {

    /**
     * マイグレーションを実行
     */
    public static function up() {
        global $wpdb;

        try {
            $cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';

            // qualified_invoice_numberカラムが存在するかチェック
            $qualified_invoice_number_exists = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$cost_items_table}` LIKE %s", 
                    'qualified_invoice_number' 
                ) 
            );
            
            if ( ! $qualified_invoice_number_exists ) {
                // qualified_invoice_numberカラムが存在しない場合は追加
                $sql = "ALTER TABLE `{$cost_items_table}` ADD COLUMN `qualified_invoice_number` VARCHAR(255) DEFAULT NULL AFTER `purchase`";
                $result = $wpdb->query( $sql );
                
                if ( $result === false ) {
                    error_log( 'KTPWP Migration: Failed to add qualified_invoice_number column to cost items table. Error: ' . $wpdb->last_error );
                    return false;
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: Successfully added qualified_invoice_number column to cost items table' );
                }
            } else {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: qualified_invoice_number column already exists in cost items table' );
                }
            }

            // マイグレーション完了フラグを設定
            update_option( 'ktpwp_qualified_invoice_number_cost_items_migrated', true );
            update_option( 'ktpwp_qualified_invoice_number_cost_items_timestamp', current_time( 'mysql' ) );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration: Successfully completed qualified invoice number to cost items migration' );
            }

            return true;

        } catch ( Exception $e ) {
            error_log( 'KTPWP Migration Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * マイグレーションをロールバック
     */
    public static function down() {
        global $wpdb;

        try {
            $cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';

            // qualified_invoice_numberカラムが存在するかチェック
            $qualified_invoice_number_exists = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$cost_items_table}` LIKE %s", 
                    'qualified_invoice_number' 
                ) 
            );
            
            if ( $qualified_invoice_number_exists ) {
                // qualified_invoice_numberカラムが存在する場合は削除
                $sql = "ALTER TABLE `{$cost_items_table}` DROP COLUMN `qualified_invoice_number`";
                $result = $wpdb->query( $sql );
                
                if ( $result === false ) {
                    error_log( 'KTPWP Migration: Failed to drop qualified_invoice_number column from cost items table. Error: ' . $wpdb->last_error );
                    return false;
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Migration: Successfully dropped qualified_invoice_number column from cost items table' );
                }
            }

            // マイグレーション完了フラグを削除
            delete_option( 'ktpwp_qualified_invoice_number_cost_items_migrated' );
            delete_option( 'ktpwp_qualified_invoice_number_cost_items_timestamp' );

            return true;

        } catch ( Exception $e ) {
            error_log( 'KTPWP Migration Error: ' . $e->getMessage() );
            return false;
        }
    }
} 