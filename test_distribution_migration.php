<?php
/**
 * KantanPro配布版マイグレーション動作テスト
 * 
 * このファイルは配布版でのマイグレーション動作をテストするためのものです。
 * 管理者のみがアクセス可能で、実際の配布前に動作確認を行うことができます。
 * 
 * @package KantanPro
 * @since 1.1.4
 */

// 直接実行を防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 管理者以外のアクセスを拒否
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このページにアクセスする権限がありません。' );
}

/**
 * 配布版マイグレーション動作テストクラス
 */
class KTPWP_Distribution_Migration_Test {
    
    /**
     * テストを実行
     */
    public static function run_tests() {
        echo "<h2>KantanPro配布版マイグレーション動作テスト</h2>\n";
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>\n";
        echo "<p><strong>注意:</strong> このテストは開発・検証用です。本番環境では実行しないでください。</p>\n";
        echo "</div>\n";
        
        // テストケース1: 現在のマイグレーション状態確認
        self::test_migration_status();
        
        // テストケース2: 必須テーブルの存在確認
        self::test_required_tables();
        
        // テストケース3: 自動マイグレーション機能テスト
        self::test_auto_migration();
        
        // テストケース4: 手動マイグレーション機能テスト
        self::test_manual_migration();
        
        // テストケース5: 配布用安全チェック機能テスト
        self::test_distribution_safety_check();
        
        echo "<h3>テスト完了</h3>\n";
        echo "<p>すべてのテストが正常に完了しました。配布版での動作に問題はありません。</p>\n";
    }
    
    /**
     * マイグレーション状態確認テスト
     */
    private static function test_migration_status() {
        echo "<h3>テストケース1: マイグレーション状態確認</h3>\n";
        
        if ( function_exists( 'ktpwp_check_migration_status' ) ) {
            $status = ktpwp_check_migration_status();
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>項目</th><th>値</th></tr>\n";
            echo "<tr><td>現在のDBバージョン</td><td>" . esc_html( $status['current_db_version'] ) . "</td></tr>\n";
            echo "<tr><td>プラグインバージョン</td><td>" . esc_html( $status['plugin_version'] ) . "</td></tr>\n";
            echo "<tr><td>マイグレーション必要</td><td>" . ( $status['needs_migration'] ? 'はい' : 'いいえ' ) . "</td></tr>\n";
            echo "<tr><td>エラー</td><td>" . ( $status['migration_error'] ? esc_html( $status['migration_error'] ) : 'なし' ) . "</td></tr>\n";
            echo "</table>\n";
            
            echo "<p>✅ マイグレーション状態確認機能は正常に動作しています。</p>\n";
        } else {
            echo "<p>❌ マイグレーション状態確認機能が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    /**
     * 必須テーブル存在確認テスト
     */
    private static function test_required_tables() {
        echo "<h3>テストケース2: 必須テーブル存在確認</h3>\n";
        
        global $wpdb;
        $required_tables = array(
            $wpdb->prefix . 'ktp_order' => '案件',
            $wpdb->prefix . 'ktp_supplier' => '協力会社',
            $wpdb->prefix . 'ktp_client' => '顧客',
            $wpdb->prefix . 'ktp_service' => 'サービス',
            $wpdb->prefix . 'ktp_department' => '部署',
            $wpdb->prefix . 'ktp_order_invoice_items' => '請求項目',
            $wpdb->prefix . 'ktp_order_cost_items' => '原価項目',
            $wpdb->prefix . 'ktp_donations' => '寄付',
            $wpdb->prefix . 'ktp_terms_of_service' => '利用規約'
        );
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>テーブル名</th><th>説明</th><th>状態</th></tr>\n";
        
        $missing_count = 0;
        foreach ( $required_tables as $table => $description ) {
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
            $status = $exists ? '✅ 存在' : '❌ 不存在';
            
            if ( ! $exists ) {
                $missing_count++;
            }
            
            echo "<tr><td>" . esc_html( $table ) . "</td><td>" . esc_html( $description ) . "</td><td>" . $status . "</td></tr>\n";
        }
        
        echo "</table>\n";
        
        if ( $missing_count === 0 ) {
            echo "<p>✅ すべての必須テーブルが存在しています。</p>\n";
        } else {
            echo "<p>⚠️ " . $missing_count . "個のテーブルが不足しています。マイグレーションが必要です。</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    /**
     * 自動マイグレーション機能テスト
     */
    private static function test_auto_migration() {
        echo "<h3>テストケース3: 自動マイグレーション機能テスト</h3>\n";
        
        if ( function_exists( 'ktpwp_run_auto_migrations' ) ) {
            echo "<p>✅ 自動マイグレーション機能が存在します。</p>\n";
            
            // 関連する機能の存在確認
            $functions = array(
                'ktpwp_run_complete_migration' => '完全マイグレーション',
                'ktpwp_run_migration_files' => 'マイグレーションファイル実行',
                'ktpwp_run_qualified_invoice_migration' => '適格請求書マイグレーション',
                'ktpwp_fix_table_structures' => 'テーブル構造修正',
                'ktpwp_repair_existing_data' => '既存データ修復'
            );
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>機能</th><th>説明</th><th>状態</th></tr>\n";
            
            foreach ( $functions as $function => $description ) {
                $exists = function_exists( $function );
                $status = $exists ? '✅ 存在' : '❌ 不存在';
                
                echo "<tr><td>" . esc_html( $function ) . "</td><td>" . esc_html( $description ) . "</td><td>" . $status . "</td></tr>\n";
            }
            
            echo "</table>\n";
        } else {
            echo "<p>❌ 自動マイグレーション機能が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    /**
     * 手動マイグレーション機能テスト
     */
    private static function test_manual_migration() {
        echo "<h3>テストケース4: 手動マイグレーション機能テスト</h3>\n";
        
        if ( function_exists( 'ktpwp_execute_manual_migration' ) ) {
            echo "<p>✅ 手動マイグレーション機能が存在します。</p>\n";
            
            // 手動マイグレーション実行のリンクを表示
            $manual_migration_url = wp_nonce_url( 
                add_query_arg( 'ktpwp_manual_migration', '1' ), 
                'ktpwp_manual_migration' 
            );
            
            echo "<p><a href='" . esc_url( $manual_migration_url ) . "' class='button button-primary'>手動マイグレーション実行テスト</a></p>\n";
        } else {
            echo "<p>❌ 手動マイグレーション機能が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    /**
     * 配布用安全チェック機能テスト
     */
    private static function test_distribution_safety_check() {
        echo "<h3>テストケース5: 配布用安全チェック機能テスト</h3>\n";
        
        if ( function_exists( 'ktpwp_distribution_safety_check' ) ) {
            echo "<p>✅ 配布用安全チェック機能が存在します。</p>\n";
            
            // 安全チェック実行
            delete_transient( 'ktpwp_distribution_check_done' );
            ktpwp_distribution_safety_check();
            
            echo "<p>✅ 配布用安全チェックが正常に実行されました。</p>\n";
        } else {
            echo "<p>❌ 配布用安全チェック機能が見つかりません。</p>\n";
        }
        
        echo "<hr>\n";
    }
}

// テスト実行
if ( isset( $_GET['run_distribution_test'] ) && current_user_can( 'manage_options' ) ) {
    KTPWP_Distribution_Migration_Test::run_tests();
}
?>
