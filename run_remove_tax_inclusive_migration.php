<?php
/**
 * Script to run the migration to remove tax_inclusive setting
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPressの読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// プラグインの読み込み
require_once( dirname( __FILE__ ) . '/includes/migrations/20250131_remove_tax_inclusive_setting.php' );

echo "=== KTPWP 税込/税抜き表示設定削除マイグレーション ===\n";
echo "開始時刻: " . date( 'Y-m-d H:i:s' ) . "\n\n";

try {
    // マイグレーション実行
    $result = KTPWP_Migration_20250131_Remove_Tax_Inclusive_Setting::up();
    
    if ( $result ) {
        echo "✅ マイグレーションが正常に完了しました。\n";
        echo "税込/税抜き表示設定が削除されました。\n";
    } else {
        echo "❌ マイグレーションが失敗しました。\n";
        echo "エラーログを確認してください。\n";
    }
    
} catch ( Exception $e ) {
    echo "❌ エラーが発生しました: " . $e->getMessage() . "\n";
}

echo "\n終了時刻: " . date( 'Y-m-d H:i:s' ) . "\n";
echo "=== マイグレーション完了 ===\n"; 