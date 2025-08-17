<?php
/**
 * Sales Ledger PDF Generation Ajax Handler
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajax handler for sales ledger PDF generation
 */
add_action( 'wp_ajax_ktp_generate_sales_ledger_pdf', 'ktp_handle_sales_ledger_pdf_ajax' );

function ktp_handle_sales_ledger_pdf_ajax() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ktpwp_ajax_nonce' ) ) {
        wp_send_json_error( 'セキュリティチェックに失敗しました。' );
        return;
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
        wp_send_json_error( 'このページにアクセスする権限がありません。' );
        return;
    }

    // ライセンスチェック
    $license_manager = KTPWP_License_Manager::get_instance();
    if ( ! $license_manager->is_license_valid() ) {
        wp_send_json_error( 'この機能を利用するにはライセンスが必要です。' );
        return;
    }

    $year = intval( $_POST['year'] ?? date('Y') );
    
    if ( $year < 2000 || $year > date('Y') + 10 ) {
        wp_send_json_error( '無効な年度が指定されました。' );
        return;
    }

    try {
        // 売上台帳データを取得
        $sales_data = ktp_get_sales_ledger_data_for_pdf( $year );
        
        // PDF用HTMLを生成
        $pdf_html = ktp_generate_sales_ledger_pdf_html( $sales_data, $year );
        
        wp_send_json_success( array(
            'pdf_html' => $pdf_html,
            'filename' => "売上台帳_{$year}年_" . date('Ymd'),
            'year' => $year,
            'total_records' => count( $sales_data ),
            'total_amount' => array_sum( array_column( $sales_data, 'total_amount' ) )
        ) );

    } catch ( Exception $e ) {
        error_log( 'KTPWP Sales Ledger PDF Error: ' . $e->getMessage() );
        wp_send_json_error( 'PDF生成中にエラーが発生しました: ' . $e->getMessage() );
    }
}

/**
 * Get sales ledger data for PDF generation
 *
 * @param int $year Target year
 * @return array Sales data
 */
function ktp_get_sales_ledger_data_for_pdf( $year ) {
    global $wpdb;

    // 売上台帳用のデータを取得（請求済以降の進捗状況の案件のみ）
    $query = "SELECT 
        o.id,
        o.project_name as order_title,
        o.created_at as date,
        o.progress,
        o.customer_name as client_name,
        COALESCE(SUM(ii.amount), 0) as total_amount,
        GROUP_CONCAT(ii.product_name SEPARATOR ', ') as products
    FROM {$wpdb->prefix}ktp_order o
    LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id
    WHERE YEAR(o.created_at) = %d
    AND o.progress >= 5
    AND o.progress != 7
    AND ii.amount IS NOT NULL
    GROUP BY o.id
    ORDER BY o.created_at ASC";

    $results = $wpdb->get_results( $wpdb->prepare( $query, $year ), ARRAY_A );

    // データを整形
    $sales_data = array();
    foreach ( $results as $row ) {
        $sales_data[] = array(
            'id' => $row['id'],
            'date' => date( 'Y年m月d日', strtotime( $row['date'] ) ),
            'client_name' => $row['client_name'] ?: '未設定',
            'client_address' => '',
            'order_title' => $row['order_title'] ?: '無題',
            'products' => $row['products'] ?: '',
            'total_amount' => floatval( $row['total_amount'] ),
            'progress' => intval( $row['progress'] )
        );
    }

    return $sales_data;
}

/**
 * Generate sales ledger PDF HTML
 *
 * @param array $sales_data Sales data
 * @param int $year Target year
 * @return string PDF HTML content
 */
function ktp_generate_sales_ledger_pdf_html( $sales_data, $year ) {
    $total_amount = array_sum( array_column( $sales_data, 'total_amount' ) );
    $total_records = count( $sales_data );
    $current_date = date( 'Y年m月d日' );
    
    // 月別集計を計算
    $monthly_totals = array();
    foreach ( $sales_data as $row ) {
        $month = date( 'n', strtotime( str_replace( array('年', '月', '日'), array('-', '-', ''), $row['date'] ) ) );
        if ( ! isset( $monthly_totals[ $month ] ) ) {
            $monthly_totals[ $month ] = 0;
        }
        $monthly_totals[ $month ] += $row['total_amount'];
    }

    $html = '
    <div class="sales-ledger-pdf">
        <div class="header" style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
            <h1 style="font-size: 24px; margin: 0 0 10px 0; font-weight: bold;">売上台帳</h1>
            <div style="font-size: 18px; margin-bottom: 10px;">' . esc_html( $year ) . '年度</div>
            <div style="font-size: 14px; color: #666;">作成日：' . esc_html( $current_date ) . '</div>
        </div>

        <div class="summary" style="margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">年間売上サマリー</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">年間売上合計</div>
                    <div style="font-size: 20px; font-weight: bold; color: #1976d2;">¥' . number_format( $total_amount ) . '</div>
                </div>
                <div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">売上件数</div>
                    <div style="font-size: 20px; font-weight: bold; color: #4caf50;">' . number_format( $total_records ) . '件</div>
                </div>
            </div>
        </div>';

    // 月別売上サマリー
    if ( ! empty( $monthly_totals ) ) {
        $html .= '
        <div class="monthly-summary" style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">月別売上サマリー</h2>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 12px;">
                <div style="font-weight: bold; padding: 8px; background: #e3f2fd; text-align: center;">月</div>
                <div style="font-weight: bold; padding: 8px; background: #e3f2fd; text-align: center;">売上金額</div>
                <div style="font-weight: bold; padding: 8px; background: #e3f2fd; text-align: center;">月</div>
                <div style="font-weight: bold; padding: 8px; background: #e3f2fd; text-align: center;">売上金額</div>';
        
        for ( $i = 1; $i <= 12; $i += 2 ) {
            $amount1 = isset( $monthly_totals[ $i ] ) ? $monthly_totals[ $i ] : 0;
            $amount2 = isset( $monthly_totals[ $i + 1 ] ) ? $monthly_totals[ $i + 1 ] : 0;
            
            $html .= '
                <div style="padding: 6px; text-align: center; border: 1px solid #ddd;">' . $i . '月</div>
                <div style="padding: 6px; text-align: right; border: 1px solid #ddd;">¥' . number_format( $amount1 ) . '</div>';
            
            if ( $i + 1 <= 12 ) {
                $html .= '
                <div style="padding: 6px; text-align: center; border: 1px solid #ddd;">' . ($i + 1) . '月</div>
                <div style="padding: 6px; text-align: right; border: 1px solid #ddd;">¥' . number_format( $amount2 ) . '</div>';
            } else {
                $html .= '<div></div><div></div>';
            }
        }
        
        $html .= '
            </div>
        </div>';
    }

    // 売上明細テーブル
    $html .= '
        <div class="sales-details">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">売上明細</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">No.</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">日付</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">顧客名</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">案件名</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">商品・サービス</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold;">売上金額</th>
                    </tr>
                </thead>
                <tbody>';

    if ( ! empty( $sales_data ) ) {
        $row_number = 1;
        foreach ( $sales_data as $row ) {
            $html .= '
                    <tr>
                        <td style="border: 1px solid #333; padding: 6px; text-align: center;">' . $row_number . '</td>
                        <td style="border: 1px solid #333; padding: 6px; text-align: center;">' . esc_html( $row['date'] ) . '</td>
                        <td style="border: 1px solid #333; padding: 6px;">' . esc_html( $row['client_name'] ) . '</td>
                        <td style="border: 1px solid #333; padding: 6px;">' . esc_html( $row['order_title'] ) . '</td>
                        <td style="border: 1px solid #333; padding: 6px; font-size: 10px;">' . esc_html( mb_strimwidth( $row['products'], 0, 50, '...' ) ) . '</td>
                        <td style="border: 1px solid #333; padding: 6px; text-align: right; font-weight: bold;">¥' . number_format( $row['total_amount'] ) . '</td>
                    </tr>';
            $row_number++;
        }
    } else {
        $html .= '
                    <tr>
                        <td colspan="6" style="border: 1px solid #333; padding: 20px; text-align: center; color: #666;">
                            対象年度の売上データがありません。
                        </td>
                    </tr>';
    }

    $html .= '
                </tbody>
                <tfoot>
                    <tr style="background: #f0f8ff; font-weight: bold;">
                        <td colspan="5" style="border: 1px solid #333; padding: 8px; text-align: right;">合計</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: right; font-size: 14px;">¥' . number_format( $total_amount ) . '</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666;">
            <div>この売上台帳は確定申告用として作成されました。</div>
            <div style="margin-top: 5px;">KantanPro 業務管理システム</div>
        </div>
    </div>';

    return $html;
}