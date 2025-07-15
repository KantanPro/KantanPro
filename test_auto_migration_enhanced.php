<?php
/**
 * 強化された自動マイグレーション機能のテスト
 *
 * @package KTPWP
 * @since 1.0.0
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 強化された自動マイグレーション機能のテストクラス
 */
class KTPWP_Auto_Migration_Enhanced_Test {

    /**
     * テストを実行
     */
    public static function run_tests() {
        echo "<h2>強化された自動マイグレーション機能テスト</h2>\n";
        
        // テストケース1: マイグレーション状態のチェック
        self::test_migration_status_check();
        
        // テストケース2: 適格請求書ナンバー機能のマイグレーション
        self::test_qualified_invoice_migration();
        
        // テストケース3: プラグイン有効化時のマイグレーション
        self::test_plugin_activation_migration();
        
        // テストケース4: プラグイン更新時のマイグレーション
        self::test_plugin_upgrade_migration();
        
        // テストケース5: データベース整合性チェック
        self::test_database_integrity();
        
        echo "<h3>テスト完了</h3>\n";
    }

    /**
     * マイグレーション状態のチェックテスト
     */
    private static function test_migration_status_check() {
        echo "<h3>テストケース1: マイグレーション状態のチェック</h3>\n";
        
        if ( function_exists( 'ktpwp_check_migration_status' ) ) {
            $status = ktpwp_check_migration_status();
            
            echo "<p><strong>現在のDBバージョン:</strong> " . esc_html($status['current_db_version']) . "</p>\n";
            echo "<p><strong>プラグインバージョン:</strong> " . esc_html($status['plugin_version']) . "</p>\n";
            echo "<p><strong>マイグレーション必要:</strong> " . ($status['needs_migration'] ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>最終マイグレーション:</strong> " . esc_html($status['last_migration']) . "</p>\n";
            echo "<p><strong>有効化完了:</strong> " . ($status['activation_completed'] ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>アップデート完了:</strong> " . ($status['upgrade_completed'] ? 'はい' : 'いいえ') . "</p>\n";
            
            // 適格請求書ナンバー機能の状態
            $qualified_invoice = $status['qualified_invoice'];
            echo "<p><strong>適格請求書機能マイグレーション済み:</strong> " . ($qualified_invoice['migrated'] ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>適格請求書機能バージョン:</strong> " . esc_html($qualified_invoice['version']) . "</p>\n";
            echo "<p><strong>適格請求書機能有効:</strong> " . ($qualified_invoice['enabled'] ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>適格請求書機能最終更新:</strong> " . esc_html($qualified_invoice['timestamp']) . "</p>\n";
            
            if ( $status['migration_error'] ) {
                echo "<p><strong>マイグレーションエラー:</strong> " . esc_html($status['migration_error']) . "</p>\n";
            }
        } else {
            echo "<p><strong>エラー:</strong> ktpwp_check_migration_status関数が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }

    /**
     * 適格請求書ナンバー機能のマイグレーションテスト
     */
    private static function test_qualified_invoice_migration() {
        echo "<h3>テストケース2: 適格請求書ナンバー機能のマイグレーション</h3>\n";
        
        if ( function_exists( 'ktpwp_run_qualified_invoice_migration' ) ) {
            echo "<p><strong>適格請求書ナンバー機能のマイグレーションを実行中...</strong></p>\n";
            
            $result = ktpwp_run_qualified_invoice_migration();
            
            if ( $result ) {
                echo "<p><strong>結果:</strong> 成功</p>\n";
                
                // マイグレーション後の状態をチェック
                $migrated = get_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', false );
                $version = get_option( 'ktpwp_qualified_invoice_profit_calculation_version', '0.0.0' );
                $enabled = get_option( 'ktpwp_qualified_invoice_enabled', false );
                
                echo "<p><strong>マイグレーション完了フラグ:</strong> " . ($migrated ? 'はい' : 'いいえ') . "</p>\n";
                echo "<p><strong>機能バージョン:</strong> " . esc_html($version) . "</p>\n";
                echo "<p><strong>機能有効化:</strong> " . ($enabled ? 'はい' : 'いいえ') . "</p>\n";
            } else {
                echo "<p><strong>結果:</strong> 失敗</p>\n";
            }
        } else {
            echo "<p><strong>エラー:</strong> ktpwp_run_qualified_invoice_migration関数が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }

    /**
     * プラグイン有効化時のマイグレーションテスト
     */
    private static function test_plugin_activation_migration() {
        echo "<h3>テストケース3: プラグイン有効化時のマイグレーション</h3>\n";
        
        if ( function_exists( 'ktpwp_plugin_activation' ) ) {
            echo "<p><strong>プラグイン有効化処理をシミュレート中...</strong></p>\n";
            
            // 現在の状態を保存
            $current_version = get_option( 'ktpwp_version', '0.0.0' );
            $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            
            // 有効化処理を実行
            ktpwp_plugin_activation();
            
            // 処理後の状態をチェック
            $new_version = get_option( 'ktpwp_version', '0.0.0' );
            $new_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            $activation_completed = get_option( 'ktpwp_activation_completed', false );
            $qualified_invoice_migrated = get_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', false );
            
            echo "<p><strong>処理前バージョン:</strong> " . esc_html($current_version) . "</p>\n";
            echo "<p><strong>処理後バージョン:</strong> " . esc_html($new_version) . "</p>\n";
            echo "<p><strong>処理前DBバージョン:</strong> " . esc_html($current_db_version) . "</p>\n";
            echo "<p><strong>処理後DBバージョン:</strong> " . esc_html($new_db_version) . "</p>\n";
            echo "<p><strong>有効化完了:</strong> " . ($activation_completed ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>適格請求書機能マイグレーション:</strong> " . ($qualified_invoice_migrated ? 'はい' : 'いいえ') . "</p>\n";
            
        } else {
            echo "<p><strong>エラー:</strong> ktpwp_plugin_activation関数が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }

    /**
     * プラグイン更新時のマイグレーションテスト
     */
    private static function test_plugin_upgrade_migration() {
        echo "<h3>テストケース4: プラグイン更新時のマイグレーション</h3>\n";
        
        if ( function_exists( 'ktpwp_plugin_upgrade_migration' ) ) {
            echo "<p><strong>プラグイン更新処理をシミュレート中...</strong></p>\n";
            
            // 更新前の状態を保存
            $current_version = get_option( 'ktpwp_version', '0.0.0' );
            $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            
            // 更新処理をシミュレート
            $hook_extra = array( 'plugin' => 'ktpwp/ktpwp.php' );
            $upgrader = null; // 実際のアップグレーダーオブジェクトは不要
            
            ktpwp_plugin_upgrade_migration( $upgrader, $hook_extra );
            
            // 処理後の状態をチェック
            $new_version = get_option( 'ktpwp_version', '0.0.0' );
            $new_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            $upgrade_completed = get_option( 'ktpwp_upgrade_completed', false );
            $previous_version = get_option( 'ktpwp_previous_version', '0.0.0' );
            
            echo "<p><strong>処理前バージョン:</strong> " . esc_html($current_version) . "</p>\n";
            echo "<p><strong>処理後バージョン:</strong> " . esc_html($new_version) . "</p>\n";
            echo "<p><strong>処理前DBバージョン:</strong> " . esc_html($current_db_version) . "</p>\n";
            echo "<p><strong>処理後DBバージョン:</strong> " . esc_html($new_db_version) . "</p>\n";
            echo "<p><strong>更新完了:</strong> " . ($upgrade_completed ? 'はい' : 'いいえ') . "</p>\n";
            echo "<p><strong>前回バージョン:</strong> " . esc_html($previous_version) . "</p>\n";
            
        } else {
            echo "<p><strong>エラー:</strong> ktpwp_plugin_upgrade_migration関数が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }

    /**
     * データベース整合性チェックテスト
     */
    private static function test_database_integrity() {
        echo "<h3>テストケース5: データベース整合性チェック</h3>\n";
        
        if ( function_exists( 'ktpwp_check_database_integrity' ) ) {
            echo "<p><strong>データベース整合性をチェック中...</strong></p>\n";
            
            ktpwp_check_database_integrity();
            
            // チェック後の状態を確認
            $integrity_checked = get_transient( 'ktpwp_db_integrity_checked' );
            
            echo "<p><strong>整合性チェック完了:</strong> " . ($integrity_checked ? 'はい' : 'いいえ') . "</p>\n";
            
            // 主要テーブルの存在確認
            global $wpdb;
            
            $tables_to_check = array(
                'ktp_supplier' => '協力会社テーブル',
                'ktp_order_cost_items' => 'コスト項目テーブル',
                'ktp_order_invoice_items' => '請求項目テーブル',
                'ktp_client' => '顧客テーブル',
                'ktp_department' => '部署テーブル'
            );
            
            echo "<h4>テーブル存在確認:</h4>\n";
            foreach ( $tables_to_check as $table => $description ) {
                $table_name = $wpdb->prefix . $table;
                $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
                echo "<p><strong>{$description}:</strong> " . ($exists ? '存在' : '不存在') . "</p>\n";
            }
            
            // 適格請求書ナンバーカラムの確認
            $supplier_table = $wpdb->prefix . 'ktp_supplier';
            $qualified_invoice_column = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$supplier_table}` LIKE %s", 
                    'qualified_invoice_number' 
                ) 
            );
            
            echo "<p><strong>適格請求書ナンバーカラム:</strong> " . ($qualified_invoice_column ? '存在' : '不存在') . "</p>\n";
            
            // supplier_idカラムの確認
            $cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';
            $supplier_id_column = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SHOW COLUMNS FROM `{$cost_items_table}` LIKE %s", 
                    'supplier_id' 
                ) 
            );
            
            echo "<p><strong>supplier_idカラム:</strong> " . ($supplier_id_column ? '存在' : '不存在') . "</p>\n";
            
        } else {
            echo "<p><strong>エラー:</strong> ktpwp_check_database_integrity関数が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }
}

// テスト実行
if (isset($_GET['run_auto_migration_enhanced_test']) && current_user_can('manage_options')) {
    KTPWP_Auto_Migration_Enhanced_Test::run_tests();
}
?> 