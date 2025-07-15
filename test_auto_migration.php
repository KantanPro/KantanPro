<?php
/**
 * KantanPro 自動マイグレーションシステム テストスクリプト
 * 
 * このスクリプトは、プラグインの有効化・更新時に自動マイグレーションが
 * 正しく動作するかをテストするためのものです。
 * 
 * 使用方法:
 * 1. このファイルをプラグインのルートディレクトリに配置
 * 2. ブラウザで直接アクセスしてテスト実行
 * 3. または、WP-CLIで実行: wp eval-file test_auto_migration.php
 */

// WordPressの初期化
if ( ! defined( 'ABSPATH' ) ) {
    // 現在のディレクトリがWordPressのルートディレクトリかチェック
    if ( file_exists( __DIR__ . '/../../../../wp-config.php' ) ) {
        require_once __DIR__ . '/../../../../wp-config.php';
    } elseif ( file_exists( __DIR__ . '/../../../wp-config.php' ) ) {
        require_once __DIR__ . '/../../../wp-config.php';
    } else {
        echo "WordPressのルートディレクトリが見つかりません。\n";
        echo "現在のディレクトリ: " . __DIR__ . "\n";
        exit( 1 );
    }
}

// プラグインの読み込み
if ( ! function_exists( 'ktpwp_check_migration_status' ) ) {
    echo "KantanProプラグインが読み込まれていません。\n";
    exit( 1 );
}

echo "=== KantanPro 自動マイグレーションシステム テスト ===\n\n";

// 1. 現在のマイグレーション状態を確認
echo "1. 現在のマイグレーション状態:\n";
$status = ktpwp_check_migration_status();
echo "   - 現在のDBバージョン: " . $status['current_db_version'] . "\n";
echo "   - プラグインバージョン: " . $status['plugin_version'] . "\n";
echo "   - マイグレーション必要: " . ( $status['needs_migration'] ? 'はい' : 'いいえ' ) . "\n";
echo "   - 最終マイグレーション: " . $status['last_migration'] . "\n";
echo "   - 有効化完了: " . ( $status['activation_completed'] ? 'はい' : 'いいえ' ) . "\n";
echo "   - アップデート完了: " . ( $status['upgrade_completed'] ? 'はい' : 'いいえ' ) . "\n";

if ( $status['migration_error'] ) {
    echo "   - マイグレーションエラー: " . $status['migration_error'] . "\n";
}

echo "\n";

// 2. マイグレーションファイルの確認
echo "2. マイグレーションファイルの確認:\n";
$migrations_dir = __DIR__ . '/includes/migrations';
if ( is_dir( $migrations_dir ) ) {
    $files = glob( $migrations_dir . '/*.php' );
    if ( $files ) {
        sort( $files );
        foreach ( $files as $file ) {
            $filename = basename( $file, '.php' );
            $migration_key = 'ktpwp_migration_' . $filename . '_completed';
            $completed = get_option( $migration_key, false );
            echo "   - " . $filename . ": " . ( $completed ? '完了' : '未実行' ) . "\n";
        }
    } else {
        echo "   - マイグレーションファイルが見つかりません\n";
    }
} else {
    echo "   - マイグレーションディレクトリが見つかりません: " . $migrations_dir . "\n";
}

echo "\n";

// 3. データベーステーブルの確認
echo "3. データベーステーブルの確認:\n";
global $wpdb;

$required_tables = array(
    $wpdb->prefix . 'ktp_order',
    $wpdb->prefix . 'ktp_supplier',
    $wpdb->prefix . 'ktp_client',
    $wpdb->prefix . 'ktp_service',
    $wpdb->prefix . 'ktp_department',
    $wpdb->prefix . 'ktp_order_invoice_items',
    $wpdb->prefix . 'ktp_order_cost_items',
    $wpdb->prefix . 'ktp_order_staff_chat',
    $wpdb->prefix . 'ktp_terms_of_service'
);

foreach ( $required_tables as $table ) {
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    echo "   - " . basename( $table ) . ": " . ( $exists ? '存在' : '不存在' ) . "\n";
}

echo "\n";

// 4. プラグイン設定の確認
echo "4. プラグイン設定の確認:\n";
$plugin_options = array(
    'ktpwp_version',
    'ktpwp_db_version',
    'ktpwp_activation_completed',
    'ktpwp_upgrade_completed',
    'ktpwp_last_migration_timestamp',
    'ktpwp_migration_error'
);

foreach ( $plugin_options as $option ) {
    $value = get_option( $option, '未設定' );
    echo "   - " . $option . ": " . $value . "\n";
}

echo "\n";

// 5. マイグレーション実行テスト（オプション）
if ( isset( $_GET['run_migration'] ) && $_GET['run_migration'] === 'yes' ) {
    echo "5. マイグレーション実行テスト:\n";
    
    try {
        echo "   - マイグレーションを開始...\n";
        ktpwp_run_auto_migrations();
        
        $new_status = ktpwp_check_migration_status();
        echo "   - マイグレーション完了\n";
        echo "   - 新しいDBバージョン: " . $new_status['current_db_version'] . "\n";
        
        if ( $new_status['migration_error'] ) {
            echo "   - エラー: " . $new_status['migration_error'] . "\n";
        } else {
            echo "   - エラーなし\n";
        }
        
    } catch ( Exception $e ) {
        echo "   - エラーが発生しました: " . $e->getMessage() . "\n";
    }
} else {
    echo "5. マイグレーション実行テスト:\n";
    echo "   - 実行するには ?run_migration=yes をURLに追加してください\n";
}

echo "\n";

// 6. 推奨事項
echo "6. 推奨事項:\n";
if ( $status['needs_migration'] ) {
    echo "   - マイグレーションが必要です。プラグインを無効化・再有効化してください。\n";
} else {
    echo "   - マイグレーションは最新です。\n";
}

if ( ! $status['activation_completed'] ) {
    echo "   - プラグインの有効化が完了していません。プラグインを無効化・再有効化してください。\n";
}

if ( $status['migration_error'] ) {
    echo "   - マイグレーションエラーが発生しています。ログを確認してください。\n";
}

echo "\n";

// 7. WP-CLIコマンドの案内
echo "7. WP-CLIコマンド:\n";
echo "   - マイグレーション状態確認: wp ktpwp status\n";
echo "   - マイグレーション実行: wp ktpwp migration\n";
echo "   - マイグレーションリセット: wp ktpwp reset-migration --confirm=yes\n";

echo "\n=== テスト完了 ===\n";

// ブラウザ表示用のHTML
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    echo "<br><br>";
    echo "<h3>テスト結果</h3>";
    echo "<p>上記の結果を確認してください。</p>";
    echo "<p><a href='?run_migration=yes'>マイグレーションを実行する</a></p>";
    echo "<p><a href='" . admin_url() . "'>管理画面に戻る</a></p>";
} 