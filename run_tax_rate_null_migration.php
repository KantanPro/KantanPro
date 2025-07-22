<?php
/**
 * 税率NULL対応マイグレーション実行スクリプト
 * 
 * 使用方法:
 * 1. このファイルをWordPressのルートディレクトリに配置
 * 2. ブラウザで http://your-site.com/run_tax_rate_null_migration.php にアクセス
 * 3. 実行完了後、このファイルを削除
 */

// WordPressを読み込み
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( '管理者権限が必要です。' );
}

echo '<h1>税率NULL対応マイグレーション</h1>';

// マイグレーションファイルを実行
$migration_file = dirname( __FILE__ ) . '/wp-content/plugins/KantanPro/includes/migrations/20250122_allow_null_tax_rate.php';

if ( file_exists( $migration_file ) ) {
    echo '<p>マイグレーションファイルを実行中...</p>';
    
    // マイグレーションファイルを実行
    include_once( $migration_file );
    
    echo '<p style="color: green;">マイグレーションが完了しました。</p>';
    echo '<p>税率フィールドがNULL値を許可するように修正されました。</p>';
} else {
    echo '<p style="color: red;">マイグレーションファイルが見つかりません: ' . $migration_file . '</p>';
}

echo '<p><strong>注意:</strong> このファイルは実行完了後、セキュリティのため削除してください。</p>'; 