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
        wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
        return;
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
        wp_send_json_error( __( 'このページにアクセスする権限がありません。', 'ktpwp' ) );
        return;
    }

    $year = intval( $_POST['year'] ?? date('Y') );
    
    if ( $year < 2000 || $year > date('Y') + 10 ) {
        wp_send_json_error( __( '無効な年度が指定されました。', 'ktpwp' ) );
        return;
    }

    try {
        // 売上台帳データを取得
        $sales_data = ktp_get_sales_ledger_data_for_pdf( $year );
        
        // PDF用HTMLを生成
        $pdf_html = ktp_generate_sales_ledger_pdf_html( $sales_data, $year );
        
        wp_send_json_success( array(
            'pdf_html' => $pdf_html,
            'filename' => sprintf( __( '売上台帳_%s年_%s', 'ktpwp' ), $year, date('Ymd') ),
            'year' => $year,
            'total_records' => count( $sales_data ),
            'total_amount' => array_sum( array_column( $sales_data, 'total_amount' ) )
        ) );

    } catch ( Exception $e ) {
        error_log( 'KTPWP Sales Ledger PDF Error: ' . $e->getMessage() );
        wp_send_json_error( __( 'PDF生成中にエラーが発生しました: ', 'ktpwp' ) . $e->getMessage() );
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
        o.completion_date as date,
        o.progress,
        o.customer_name as client_name,
        COALESCE(SUM(ii.amount), 0) as total_amount,
        GROUP_CONCAT(ii.product_name SEPARATOR ', ') as products
    FROM {$wpdb->prefix}ktp_order o
    LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id
    WHERE YEAR(o.completion_date) = %d
    AND o.progress >= 5
    AND o.progress != 7
    AND ii.amount IS NOT NULL
    AND o.completion_date IS NOT NULL
    GROUP BY o.id
    ORDER BY o.completion_date ASC";

    $results = $wpdb->get_results( $wpdb->prepare( $query, $year ), ARRAY_A );

    // データを整形
    $sales_data = array();
    foreach ( $results as $row ) {
        $sales_data[] = array(
            'id' => $row['id'],
            'date' => wp_date( __( 'Y年m月d日', 'ktpwp' ), strtotime( $row['date'] ) ),
            'client_name' => $row['client_name'] ?: __( '未設定', 'ktpwp' ),
            'client_address' => '',
            'order_title' => $row['order_title'] ?: __( '無題', 'ktpwp' ),
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
    $current_date = wp_date( __( 'Y年m月d日', 'ktpwp' ) );

    // 自社名を取得（単体書類でもどの会社のものか分かるようにする。メールアドレスは含めない）
    $company_info = class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::get_company_info() : '';
    $company_name = ! empty( $company_info ) ? wp_strip_all_tags( $company_info ) : get_bloginfo( 'name' );
    $company_name = preg_replace( '/\S+@\S+\.\S+/', '', $company_name );
    $company_name = preg_replace( '/\s+/', ' ', trim( $company_name ) );
    $company_name = trim( $company_name ) !== '' ? $company_name : __( '（自社名未設定）', 'ktpwp' );
    
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
            <div style="font-size: 14px; color: #333; margin-bottom: 8px; font-weight: bold;">' . nl2br( esc_html( $company_name ) ) . '</div>
            <h1 style="font-size: 24px; margin: 0 0 10px 0; font-weight: bold;">' . esc_html__( '売上台帳', 'ktpwp' ) . '</h1>
            <div style="font-size: 18px; margin-bottom: 10px;">' . esc_html( sprintf( __( '%s年度', 'ktpwp' ), $year ) ) . '</div>
            <div style="font-size: 14px; color: #666;">' . esc_html( sprintf( __( '作成日：%s', 'ktpwp' ), $current_date ) ) . '</div>
        </div>

        <div class="summary" style="margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">' . esc_html__( '年間売上サマリー', 'ktpwp' ) . '</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">' . esc_html__( '年間売上合計', 'ktpwp' ) . '</div>
                    <div style="font-size: 20px; font-weight: bold; color: #1976d2;">' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $total_amount ) : number_format( $total_amount ) ) . '</div>
                </div>
                <div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">' . esc_html__( '売上件数', 'ktpwp' ) . '</div>
                    <div style="font-size: 20px; font-weight: bold; color: #4caf50;">' . esc_html( sprintf( __( '%s件', 'ktpwp' ), number_format( $total_records ) ) ) . '</div>
                </div>
            </div>
        </div>';

    // 月別売上サマリー（1〜12月を縦並び、6ヶ月ブロック×2で左右表示）
    if ( ! empty( $monthly_totals ) ) {
        $html .= '
        <div class="monthly-summary" style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">' . esc_html__( '月別売上サマリー', 'ktpwp' ) . '</h2>
            <div style="display: flex; gap: 24px; flex-wrap: wrap;">';

        // 左ブロック：1月〜6月
        $html .= '
            <div style="flex: 1; min-width: 200px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 2px solid #333;">
                    <thead>
                        <tr style="background: #e3f2fd;">
                            <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '月', 'ktpwp' ) . '</th>
                            <th style="border: 2px solid #333; padding: 8px; text-align: right; font-weight: bold;">' . esc_html__( '売上金額', 'ktpwp' ) . '</th>
                        </tr>
                    </thead>
                    <tbody>';
        for ( $i = 1; $i <= 6; $i++ ) {
            $amount = isset( $monthly_totals[ $i ] ) ? $monthly_totals[ $i ] : 0;
            $html .= '
                        <tr>
                            <td style="border: 2px solid #333; padding: 6px; text-align: center;">' . esc_html( sprintf( __( '%d月', 'ktpwp' ), $i ) ) . '</td>
                            <td style="border: 2px solid #333; padding: 6px; text-align: right;">' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $amount ) : number_format( $amount ) ) . '</td>
                        </tr>';
        }
        $html .= '
                    </tbody>
                </table>
            </div>';

        // 右ブロック：7月〜12月
        $html .= '
            <div style="flex: 1; min-width: 200px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 2px solid #333;">
                    <thead>
                        <tr style="background: #e3f2fd;">
                            <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '月', 'ktpwp' ) . '</th>
                            <th style="border: 2px solid #333; padding: 8px; text-align: right; font-weight: bold;">' . esc_html__( '売上金額', 'ktpwp' ) . '</th>
                        </tr>
                    </thead>
                    <tbody>';
        for ( $i = 7; $i <= 12; $i++ ) {
            $amount = isset( $monthly_totals[ $i ] ) ? $monthly_totals[ $i ] : 0;
            $html .= '
                        <tr>
                            <td style="border: 2px solid #333; padding: 6px; text-align: center;">' . esc_html( sprintf( __( '%d月', 'ktpwp' ), $i ) ) . '</td>
                            <td style="border: 2px solid #333; padding: 6px; text-align: right;">' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $amount ) : number_format( $amount ) ) . '</td>
                        </tr>';
        }
        $html .= '
                    </tbody>
                </table>
            </div>
            </div>
        </div>';
    }

    // 売上明細テーブル（罫線は月別サマリーと合わせて2px）
    $html .= '
        <div class="sales-details">
            <h2 style="font-size: 18px; margin: 0 0 15px 0; color: #333;">' . esc_html__( '売上明細', 'ktpwp' ) . '</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; border: 2px solid #333;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">No.</th>
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '日付', 'ktpwp' ) . '</th>
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '顧客名', 'ktpwp' ) . '</th>
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '案件名', 'ktpwp' ) . '</th>
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '商品・サービス', 'ktpwp' ) . '</th>
                        <th style="border: 2px solid #333; padding: 8px; text-align: center; font-weight: bold;">' . esc_html__( '売上金額', 'ktpwp' ) . '</th>
                    </tr>
                </thead>
                <tbody>';

    if ( ! empty( $sales_data ) ) {
        $row_number = 1;
        foreach ( $sales_data as $row ) {
            $html .= '
                    <tr>
                        <td style="border: 2px solid #333; padding: 6px; text-align: center;">' . $row_number . '</td>
                        <td style="border: 2px solid #333; padding: 6px; text-align: center;">' . esc_html( $row['date'] ) . '</td>
                        <td style="border: 2px solid #333; padding: 6px;">' . esc_html( $row['client_name'] ) . '</td>
                        <td style="border: 2px solid #333; padding: 6px;">' . esc_html( $row['order_title'] ) . '</td>
                        <td style="border: 2px solid #333; padding: 6px; font-size: 10px;">' . esc_html( mb_strimwidth( $row['products'], 0, 50, '...' ) ) . '</td>
                        <td style="border: 2px solid #333; padding: 6px; text-align: right; font-weight: bold;">' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $row['total_amount'] ) : number_format( $row['total_amount'] ) ) . '</td>
                    </tr>';
            $row_number++;
        }
    } else {
        $html .= '
                    <tr>
                        <td colspan="6" style="border: 2px solid #333; padding: 20px; text-align: center; color: #666;">
                            ' . esc_html__( '対象年度の売上データがありません。', 'ktpwp' ) . '
                        </td>
                    </tr>';
    }

    $html .= '
                </tbody>
                <tfoot>
                    <tr style="background: #f0f8ff; font-weight: bold;">
                        <td colspan="5" style="border: 2px solid #333; padding: 8px; text-align: right;">' . esc_html__( '合計', 'ktpwp' ) . '</td>
                        <td style="border: 2px solid #333; padding: 8px; text-align: right; font-size: 14px;">' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $total_amount ) : number_format( $total_amount ) ) . '</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>';

    return $html;
}