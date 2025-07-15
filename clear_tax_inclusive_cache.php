<?php
/**
 * Script to clear tax_inclusive setting cache
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPressの読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

echo "=== KTPWP 税込/税抜き表示設定キャッシュクリア ===\n";
echo "開始時刻: " . date( 'Y-m-d H:i:s' ) . "\n\n";

try {
    // 一般設定を取得
    $general_settings = get_option( 'ktp_general_settings', array() );
    
    echo "現在の一般設定:\n";
    print_r( $general_settings );
    echo "\n";
    
    // tax_inclusiveが存在するかチェック
    if ( isset( $general_settings['tax_inclusive'] ) ) {
        echo "tax_inclusive設定を削除中...\n";
        unset( $general_settings['tax_inclusive'] );
        
        // 設定を更新
        $result = update_option( 'ktp_general_settings', $general_settings );
        
        if ( $result ) {
            echo "✅ tax_inclusive設定を削除しました。\n";
        } else {
            echo "❌ tax_inclusive設定の削除に失敗しました。\n";
        }
    } else {
        echo "ℹ️ tax_inclusive設定は既に存在しません。\n";
    }
    
    // 更新後の設定を確認
    $updated_settings = get_option( 'ktp_general_settings', array() );
    echo "\n更新後の一般設定:\n";
    print_r( $updated_settings );
    
    // WordPressのオプションキャッシュをクリア
    wp_cache_flush();
    echo "\n✅ WordPressキャッシュをクリアしました。\n";
    
} catch ( Exception $e ) {
    echo "❌ エラーが発生しました: " . $e->getMessage() . "\n";
}

echo "\n終了時刻: " . date( 'Y-m-d H:i:s' ) . "\n";
echo "=== キャッシュクリア完了 ===\n"; 