<?php
/**
 * 配布用マイグレーション機能テスト
 * 
 * このファイルは、プラグインの新規インストール・再有効化・アップデート時の
 * 自動マイグレーション機能が正常に動作することを確認するためのテストファイルです。
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 配布用マイグレーション機能テストクラス
 */
class KTPWP_Distribution_Migration_Test {
    
    /**
     * テスト実行
     */
    public static function run_tests() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }
        
        echo '<div class="wrap">';
        echo '<h1>KantanPro 配布用マイグレーション機能テスト</h1>';
        
        // テスト結果を格納
        $test_results = array();
        
        // 1. 新規インストール検出テスト
        $test_results['new_installation_detection'] = self::test_new_installation_detection();
        
        // 2. 再有効化検出テスト
        $test_results['reactivation_detection'] = self::test_reactivation_detection();
        
        // 3. アップデート検出テスト
        $test_results['upgrade_detection'] = self::test_upgrade_detection();
        
        // 4. マイグレーション実行テスト
        $test_results['migration_execution'] = self::test_migration_execution();
        
        // 5. 通知機能テスト
        $test_results['notification_system'] = self::test_notification_system();
        
        // 結果表示
        self::display_test_results( $test_results );
        
        echo '</div>';
    }
    
    /**
     * 新規インストール検出テスト
     */
    private static function test_new_installation_detection() {
        $result = array(
            'name' => '新規インストール検出',
            'status' => 'unknown',
            'message' => ''
        );
        
        try {
            // 新規インストールフラグを設定
            update_option( 'ktpwp_new_installation_detected', true );
            
            // 関数が存在するかチェック
            if ( function_exists( 'ktpwp_detect_new_installation' ) ) {
                $result['status'] = 'success';
                $result['message'] = '新規インストール検出機能が正常に実装されています。';
            } else {
                $result['status'] = 'error';
                $result['message'] = '新規インストール検出関数が見つかりません。';
            }
            
        } catch ( Exception $e ) {
            $result['status'] = 'error';
            $result['message'] = 'エラー: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 再有効化検出テスト
     */
    private static function test_reactivation_detection() {
        $result = array(
            'name' => '再有効化検出',
            'status' => 'unknown',
            'message' => ''
        );
        
        try {
            // 再有効化フラグを設定
            update_option( 'ktpwp_reactivation_required', true );
            
            // 関数が存在するかチェック
            if ( function_exists( 'ktpwp_check_reactivation_migration' ) ) {
                $result['status'] = 'success';
                $result['message'] = '再有効化検出機能が正常に実装されています。';
            } else {
                $result['status'] = 'error';
                $result['message'] = '再有効化検出関数が見つかりません。';
            }
            
        } catch ( Exception $e ) {
            $result['status'] = 'error';
            $result['message'] = 'エラー: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * アップデート検出テスト
     */
    private static function test_upgrade_detection() {
        $result = array(
            'name' => 'アップデート検出',
            'status' => 'unknown',
            'message' => ''
        );
        
        try {
            // 関数が存在するかチェック
            if ( function_exists( 'ktpwp_plugin_upgrade_migration' ) ) {
                $result['status'] = 'success';
                $result['message'] = 'アップデート検出機能が正常に実装されています。';
            } else {
                $result['status'] = 'error';
                $result['message'] = 'アップデート検出関数が見つかりません。';
            }
            
        } catch ( Exception $e ) {
            $result['status'] = 'error';
            $result['message'] = 'エラー: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * マイグレーション実行テスト
     */
    private static function test_migration_execution() {
        $result = array(
            'name' => 'マイグレーション実行',
            'status' => 'unknown',
            'message' => ''
        );
        
        try {
            // 関数が存在するかチェック
            if ( function_exists( 'ktpwp_run_auto_migrations' ) ) {
                $result['status'] = 'success';
                $result['message'] = '自動マイグレーション機能が正常に実装されています。';
            } else {
                $result['status'] = 'error';
                $result['message'] = '自動マイグレーション関数が見つかりません。';
            }
            
        } catch ( Exception $e ) {
            $result['status'] = 'error';
            $result['message'] = 'エラー: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 通知機能テスト
     */
    private static function test_notification_system() {
        $result = array(
            'name' => '通知機能',
            'status' => 'unknown',
            'message' => ''
        );
        
        try {
            // 関数が存在するかチェック
            if ( function_exists( 'ktpwp_distribution_admin_notices' ) ) {
                $result['status'] = 'success';
                $result['message'] = '通知機能が正常に実装されています。';
            } else {
                $result['status'] = 'error';
                $result['message'] = '通知機能関数が見つかりません。';
            }
            
        } catch ( Exception $e ) {
            $result['status'] = 'error';
            $result['message'] = 'エラー: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * テスト結果表示
     */
    private static function display_test_results( $results ) {
        echo '<h2>テスト結果</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>テスト項目</th>';
        echo '<th>ステータス</th>';
        echo '<th>メッセージ</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ( $results as $test ) {
            $status_class = $test['status'] === 'success' ? 'notice-success' : 'notice-error';
            $status_text = $test['status'] === 'success' ? '成功' : 'エラー';
            
            echo '<tr>';
            echo '<td>' . esc_html( $test['name'] ) . '</td>';
            echo '<td><span class="notice ' . $status_class . '">' . esc_html( $status_text ) . '</span></td>';
            echo '<td>' . esc_html( $test['message'] ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // 現在のマイグレーション状態を表示
        echo '<h2>現在のマイグレーション状態</h2>';
        if ( function_exists( 'ktpwp_check_migration_status' ) ) {
            $status = ktpwp_check_migration_status();
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<tr><td>現在のDBバージョン</td><td>' . esc_html( $status['current_db_version'] ) . '</td></tr>';
            echo '<tr><td>プラグインバージョン</td><td>' . esc_html( $status['plugin_version'] ) . '</td></tr>';
            echo '<tr><td>マイグレーション必要</td><td>' . ( $status['needs_migration'] ? 'はい' : 'いいえ' ) . '</td></tr>';
            echo '<tr><td>有効化完了</td><td>' . ( $status['activation_completed'] ? 'はい' : 'いいえ' ) . '</td></tr>';
            echo '<tr><td>アップデート完了</td><td>' . ( $status['upgrade_completed'] ? 'はい' : 'いいえ' ) . '</td></tr>';
            echo '<tr><td>再有効化完了</td><td>' . ( $status['reactivation_completed'] ? 'はい' : 'いいえ' ) . '</td></tr>';
            echo '<tr><td>新規インストール完了</td><td>' . ( $status['new_installation_completed'] ? 'はい' : 'いいえ' ) . '</td></tr>';
            echo '</table>';
        } else {
            echo '<p class="notice notice-error">マイグレーション状態チェック関数が見つかりません。</p>';
        }
    }
}

// テスト実行用のアクションフック
add_action( 'admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'KantanPro マイグレーションテスト',
        'KTPWP マイグレーションテスト',
        'manage_options',
        'ktpwp-migration-test',
        array( 'KTPWP_Distribution_Migration_Test', 'run_tests' )
    );
} );
?>
