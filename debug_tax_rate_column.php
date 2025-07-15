<?php
/**
 * Debug script to check tax rate column display
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPressの読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

echo "=== KTPWP 税率列デバッグ ===\n";
echo "開始時刻: " . date( 'Y-m-d H:i:s' ) . "\n\n";

try {
    // 受注書データを取得
    global $wpdb;
    $order_table = $wpdb->prefix . 'ktp_order';
    $invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
    
    // 最新の受注書を取得
    $latest_order = $wpdb->get_row(
        "SELECT * FROM {$order_table} ORDER BY id DESC LIMIT 1"
    );
    
    if ( ! $latest_order ) {
        echo "❌ 受注書が見つかりません。\n";
        exit;
    }
    
    echo "✅ 受注書ID: " . $latest_order->id . "\n";
    echo "プロジェクト名: " . $latest_order->project_name . "\n\n";
    
    // 請求項目を取得
    $invoice_items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$invoice_items_table} WHERE order_id = %d ORDER BY sort_order ASC, id ASC",
            $latest_order->id
        )
    );
    
    if ( empty( $invoice_items ) ) {
        echo "❌ 請求項目が見つかりません。\n";
        exit;
    }
    
    echo "✅ 請求項目数: " . count( $invoice_items ) . "\n\n";
    
    // 各項目の税率を確認
    echo "=== 請求項目の税率確認 ===\n";
    foreach ( $invoice_items as $index => $item ) {
        echo "項目 " . ( $index + 1 ) . ":\n";
        echo "  - 項目名: " . $item->product_name . "\n";
        echo "  - 単価: " . $item->price . "\n";
        echo "  - 数量: " . $item->quantity . "\n";
        echo "  - 金額: " . $item->amount . "\n";
        echo "  - 税率: " . ( isset( $item->tax_rate ) ? $item->tax_rate : '未設定' ) . "%\n";
        echo "  - 備考: " . $item->remarks . "\n";
        echo "\n";
    }
    
    // プレビューHTMLを生成して税率列が含まれているか確認
    echo "=== プレビューHTML生成テスト ===\n";
    
    // Kntan_Order_Classをインスタンス化
    if ( class_exists( 'Kntan_Order_Class' ) ) {
        $order_class = new Kntan_Order_Class();
        
        // プライベートメソッドを呼び出すためにリフレクションを使用
        $reflection = new ReflectionClass( $order_class );
        $method = $reflection->getMethod( 'Generate_Invoice_Items_For_Preview' );
        $method->setAccessible( true );
        
        $result = $method->invoke( $order_class, $latest_order->id );
        
        if ( isset( $result['html'] ) ) {
            $html = $result['html'];
            
            // 税率列が含まれているかチェック
            if ( strpos( $html, '税率' ) !== false ) {
                echo "✅ 税率列がHTMLに含まれています。\n";
            } else {
                echo "❌ 税率列がHTMLに含まれていません。\n";
            }
            
            if ( strpos( $html, '%</div>' ) !== false ) {
                echo "✅ 税率の表示（X%）がHTMLに含まれています。\n";
            } else {
                echo "❌ 税率の表示（X%）がHTMLに含まれていません。\n";
            }
            
            // HTMLの一部を表示（デバッグ用）
            echo "\n=== HTMLの一部（ヘッダー行周辺） ===\n";
            $lines = explode( "\n", $html );
            foreach ( $lines as $line ) {
                if ( strpos( $line, '税率' ) !== false || strpos( $line, '%</div>' ) !== false ) {
                    echo "  " . htmlspecialchars( $line ) . "\n";
                }
            }
            
        } else {
            echo "❌ プレビューHTMLの生成に失敗しました。\n";
        }
    } else {
        echo "❌ Kntan_Order_Classが見つかりません。\n";
    }
    
} catch ( Exception $e ) {
    echo "❌ エラーが発生しました: " . $e->getMessage() . "\n";
}

echo "\n終了時刻: " . date( 'Y-m-d H:i:s' ) . "\n";
echo "=== デバッグ完了 ===\n"; 