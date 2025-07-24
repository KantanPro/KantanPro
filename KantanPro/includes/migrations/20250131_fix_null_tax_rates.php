<?php
/**
 * 税率がNULLまたは空のレコードを修正するマイグレーション
 * 2025年1月31日
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class FixNullTaxRatesMigration {
    
    public static function run() {
        global $wpdb;
        
        $invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
        
        // 税率がNULLまたは空または0のレコードを10%に更新
        $result = $wpdb->query("
            UPDATE `{$invoice_items_table}` 
            SET tax_rate = 10.00 
            WHERE tax_rate IS NULL 
               OR tax_rate = '' 
               OR tax_rate = 0 
               OR tax_rate < 0
        ");
        
        if ($result !== false) {
            return array(
                'success' => true,
                'message' => "税率を修正しました。更新件数: {$result}件",
                'updated_count' => $result
            );
        } else {
            return array(
                'success' => false,
                'message' => '税率の修正に失敗しました',
                'error' => $wpdb->last_error
            );
        }
    }
    
    public static function check() {
        global $wpdb;
        
        $invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
        
        // 税率がNULLまたは空または0のレコード数を確認
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM `{$invoice_items_table}` 
            WHERE tax_rate IS NULL 
               OR tax_rate = '' 
               OR tax_rate = 0 
               OR tax_rate < 0
        ");
        
        return array(
            'has_issues' => $count > 0,
            'issue_count' => $count,
            'message' => $count > 0 ? "税率が設定されていないレコードが{$count}件あります" : "税率の問題はありません"
        );
    }
}

// マイグレーション実行用の関数
function ktpwp_fix_null_tax_rates() {
    return FixNullTaxRatesMigration::run();
}

// チェック用の関数
function ktpwp_check_null_tax_rates() {
    return FixNullTaxRatesMigration::check();
} 