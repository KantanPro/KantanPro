<?php
/**
 * Tax Category Labels Migration Runner
 * 
 * This script updates tax category labels from "税込|税抜" to "内税|外税"
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// WordPress環境を読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'この操作を実行する権限がありません。' );
}

// マイグレーション実行
require_once( dirname( __FILE__ ) . '/includes/migrations/20250131_update_tax_category_labels.php' );

echo '<h2>税区分ラベル更新マイグレーション</h2>';

if ( ktpwp_update_tax_category_labels() ) {
    echo '<p style="color: green;">✓ 税区分ラベルの更新が完了しました。</p>';
    echo '<p>「税込」→「内税」、「税抜」→「外税」に更新されました。</p>';
} else {
    echo '<p style="color: red;">✗ 税区分ラベルの更新に失敗しました。</p>';
    echo '<p>エラーログを確認してください。</p>';
}

echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>'; 