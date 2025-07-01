<?php
/**
 * Order UI management class for KTPWP plugin
 *
 * Handles UI display functionality for orders, including HTML table generation.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Order_UI' ) ) {

/**
 * Order UI management class
 *
 * @since 1.0.0
 */
class KTPWP_Order_UI {

    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var KTPWP_Order_UI
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTPWP_Order_UI
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Private constructor for singleton
    }

    /**
     * Generate HTML table for invoice items
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return string HTML table content
     */
    public function generate_invoice_items_table( $order_id ) {
        $order_items = KTPWP_Order_Items::get_instance();
        $items = $order_items->get_invoice_items( $order_id );

        // sort_orderの昇順でソート
        usort($items, function($a, $b) {
            $a_order = isset($a['sort_order']) ? intval($a['sort_order']) : 0;
            $b_order = isset($b['sort_order']) ? intval($b['sort_order']) : 0;
            // 昇順
            return $a_order <=> $b_order;
        });

        // If no items or empty array, create one empty row for display
        if ( empty( $items ) ) {
            $items = array(
                array(
                    'id' => 0,
                    'order_id' => $order_id,
                    'product_name' => '',
                    'price' => 0,
                    'unit' => '式',
                    'quantity' => 1,
                    'amount' => 0,
                    'remarks' => '',
                    'sort_order' => 1
                )
            );
        }

        // Calculate total amount
        $total_amount = 0;
        foreach ( $items as $item ) {
            $total_amount += isset($item['amount']) ? floatval($item['amount']) : 0;
        }

        $html = '<div class="invoice-items-container">';
        $html .= '<form method="post" action="" class="invoice-items-form">';
        $html .= '<input type="hidden" name="order_id" value="' . intval( $order_id ) . '" />';
        $html .= '<input type="hidden" name="save_invoice_items" value="1" />';
        $html .= wp_nonce_field( 'save_invoice_items_action', 'invoice_items_nonce', true, false );
        $html .= '<div class="invoice-items-scroll-wrapper">';
        $html .= '<table class="invoice-items-table" id="invoice-items-table-' . intval( $order_id ) . '">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="actions-column">' . esc_html__( '操作', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( 'サービス', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '単価', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '数量', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( '単位', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '金額', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( '備考', 'ktpwp' ) . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ( $items as $index => $item ) {
            $row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
            $html .= '<tr class="invoice-item-row" data-row-id="' . $row_id . '">';
            // Actions column with drag handle and buttons
            $html .= '<td class="actions-column">';
            $html .= '<span class="drag-handle" title="' . esc_attr__( 'ドラッグして並び替え', 'ktpwp' ) . '">&#9776;</span>';
            $html .= '<button type="button" class="btn-add-row" title="' . esc_attr__( '行を追加', 'ktpwp' ) . '">+</button>';
            // --- ここを修正: 1行目でも常に削除ボタンを出力 ---
            $html .= '<button type="button" class="btn-delete-row" title="' . esc_attr__( '行を削除', 'ktpwp' ) . '">×</button>';
            $html .= '<button type="button" class="btn-move-row" title="' . esc_attr__( 'サービス選択', 'ktpwp' ) . '">></button>';
            $html .= '</td>';

            // Product name
            $html .= '<td>';
            $html .= '<input type="text" name="invoice_items[' . $index . '][product_name]" ';
            $html .= 'value="' . esc_attr( $item['product_name'] ) . '" ';
            $html .= 'class="invoice-item-input product-name" />';
            $html .= '<input type="hidden" name="invoice_items[' . $index . '][id]" value="' . $row_id . '" />';
            $html .= '</td>';

            // Price
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="invoice_items[' . $index . '][price]" ';
            $html .= 'value="' . esc_attr( $item['price'] ) . '" ';
            $html .= 'class="invoice-item-input price" step="0.01" min="0" style="text-align:left;" />';
            $html .= '</td>';

            // Quantity
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="invoice_items[' . $index . '][quantity]" ';
            $html .= 'value="' . esc_attr( $item['quantity'] ) . '" ';
            $html .= 'class="invoice-item-input quantity" step="0.01" min="0" style="text-align:left;" />';
            $html .= '</td>';

            // Unit
            $html .= '<td>';
            $html .= '<input type="text" name="invoice_items[' . $index . '][unit]" ';
            $html .= 'value="' . esc_attr( $item['unit'] ) . '" ';
            $html .= 'class="invoice-item-input unit" />';
            $html .= '</td>';

            // Amount
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="invoice_items[' . $index . '][amount]" ';
            $html .= 'value="' . esc_attr( $item['amount'] ) . '" ';
            $html .= 'class="invoice-item-input amount" step="1" readonly style="text-align:left;" />';
            $html .= '</td>';

            // Remarks
            $html .= '<td>';
            $html .= '<input type="text" name="invoice_items[' . $index . '][remarks]" ';
            $html .= 'value="' . esc_attr( $item['remarks'] ) . '" ';
            $html .= 'class="invoice-item-input remarks" />';
            $html .= '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>'; // invoice-items-scroll-wrapper

        // Total amount display (rounded up)
        $total_amount_ceiled = ceil($total_amount);
        $html .= '<div class="invoice-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
        $html .= esc_html__( '合計金額', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
        $html .= '</div>';

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate HTML table for cost items
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return string HTML table content
     */
    public function generate_cost_items_table( $order_id ) {
        // supplier_idカラムがなければ自動追加
        if (class_exists('KTPWP_Order_Items')) {
            $order_items = KTPWP_Order_Items::get_instance();
            $order_items->add_supplier_id_column_if_missing();
        }

        $order_items = KTPWP_Order_Items::get_instance();
        $items = $order_items->get_cost_items( $order_id );

        // sort_orderの昇順でソート
        usort($items, function($a, $b) {
            $a_order = isset($a['sort_order']) ? intval($a['sort_order']) : 0;
            $b_order = isset($b['sort_order']) ? intval($b['sort_order']) : 0;
            // 昇順
            return $a_order <=> $b_order;
        });

        // If no items or empty array, create one empty row for display
        if ( empty( $items ) ) {
            $items = array(
                array(
                    'id' => 0,
                    'order_id' => $order_id,
                    'product_name' => '',
                    'price' => 0,
                    'unit' => '式',
                    'quantity' => 1,
                    'amount' => 0,
                    'remarks' => '',
                    'sort_order' => 1
                )
            );
        }

        // Calculate total amount
        $total_amount = 0;
        foreach ( $items as $item ) {
            $total_amount += isset($item['amount']) ? floatval($item['amount']) : 0;
        }

        $html = '<div class="cost-items-container">';
        $html .= '<form method="post" action="" class="cost-items-form">';
        $html .= '<input type="hidden" name="order_id" value="' . intval( $order_id ) . '" />';
        $html .= '<input type="hidden" name="save_cost_items" value="1" />';
        $html .= wp_nonce_field( 'save_cost_items_action', 'cost_items_nonce', true, false );
        $html .= '<div class="cost-items-scroll-wrapper">';
        $html .= '<table class="cost-items-table" id="cost-items-table-' . intval( $order_id ) . '">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="actions-column">' . esc_html__( '操作', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( 'サービス', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '単価', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '数量', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( '単位', 'ktpwp' ) . '</th>';
        $html .= '<th style="text-align:left;">' . esc_html__( '金額', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( '備考', 'ktpwp' ) . '</th>';
        $html .= '<th>' . esc_html__( '仕入', 'ktpwp' ) . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ( $items as $index => $item ) {
            $row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
            $html .= '<tr class="cost-item-row" data-row-id="' . $row_id . '">';
            // Actions column with drag handle and buttons
            $html .= '<td class="actions-column">';
            $html .= '<span class="drag-handle" title="' . esc_attr__( 'ドラッグして並び替え', 'ktpwp' ) . '">&#9776;</span>';
            $html .= '<button type="button" class="btn-add-row" title="' . esc_attr__( '行を追加', 'ktpwp' ) . '">+</button>';
            // --- ここを修正: 1行目でも常に削除ボタンを出力 ---
            $html .= '<button type="button" class="btn-delete-row" title="' . esc_attr__( '行を削除', 'ktpwp' ) . '">×</button>';
            $html .= '<button type="button" class="btn-move-row" title="' . esc_attr__( '行を移動', 'ktpwp' ) . '">></button>';
            $html .= '</td>';

            // Product name
            $html .= '<td>';
            $html .= '<input type="text" name="cost_items[' . $index . '][product_name]" ';
            $html .= 'value="' . esc_attr( $item['product_name'] ) . '" ';
            $html .= 'class="cost-item-input product-name" />';
            $html .= '<input type="hidden" name="cost_items[' . $index . '][id]" value="' . $row_id . '" />';
            $html .= '</td>';

            // Price
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="cost_items[' . $index . '][price]" ';
            $html .= 'value="' . esc_attr( $item['price'] ) . '" ';
            $html .= 'class="cost-item-input price" step="0.01" min="0" style="text-align:left;" />';
            $html .= '</td>';

            // Quantity
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="cost_items[' . $index . '][quantity]" ';
            $html .= 'value="' . esc_attr( $item['quantity'] ) . '" ';
            $html .= 'class="cost-item-input quantity" step="0.01" min="0" style="text-align:left;" />';
            $html .= '</td>';

            // Unit
            $html .= '<td>';
            $html .= '<input type="text" name="cost_items[' . $index . '][unit]" ';
            $html .= 'value="' . esc_attr( $item['unit'] ) . '" ';
            $html .= 'class="cost-item-input unit" />';
            $html .= '</td>';

            // Amount
            $html .= '<td style="text-align:left;">';
            $html .= '<input type="number" name="cost_items[' . $index . '][amount]" ';
            $html .= 'value="' . esc_attr( $item['amount'] ) . '" ';
            $html .= 'class="cost-item-input amount" step="1" readonly style="text-align:left;" />';
            $html .= '</td>';

            // Remarks
            $html .= '<td>';
            $html .= '<input type="text" name="cost_items[' . $index . '][remarks]" ';
            $html .= 'value="' . esc_attr( $item['remarks'] ) . '" ';
            $html .= 'class="cost-item-input remarks" />';
            $html .= '</td>';

            // Purchase (仕入)
            $html .= '<td>';
            $html .= '<input type="text" name="cost_items[' . $index . '][purchase]" ';
            $html .= 'value="' . esc_attr( isset($item['purchase']) ? $item['purchase'] : '' ) . '" ';
            $html .= 'class="cost-item-input purchase" />';
            $html .= '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>'; // cost-items-scroll-wrapper

        // Profit calculation similar to invoice items
        $invoice_items = $order_items->get_invoice_items( $order_id );
        $invoice_total = 0;
        foreach ( $invoice_items as $invoice_item ) {
            $invoice_total += isset($invoice_item['amount']) ? floatval($invoice_item['amount']) : 0;
        }
        
        // Round up processing
        $invoice_total_ceiled = ceil($invoice_total);
        $total_amount_ceiled = ceil($total_amount);
        $profit = $invoice_total_ceiled - $total_amount_ceiled;

        // Total amount display (rounded up)
        $html .= '<div class="cost-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
        $html .= esc_html__( '合計金額', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
        $html .= '</div>';

        // Profit display (using rounded up values)
        $profit_color = $profit >= 0 ? '#28a745' : '#dc3545';  // Green for profit, red for loss
        $html .= '<div class="profit-display" style="text-align:right;margin-top:8px;font-weight:bold;color:' . $profit_color . ';">';
        $html .= esc_html__( '利益', 'ktpwp' ) . ' : ' . esc_html( number_format( $profit ) ) . esc_html__( '円', 'ktpwp' );
        $html .= '</div>';

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate email content for different order statuses
     *
     * @since 1.0.0
     * @param object $order Order object
     * @param string $my_company Company name
     * @return array Email subject and body
     */
    public function generate_email_content( $order, $my_company ) {
        if ( ! $order || ! is_object( $order ) ) {
            return array(
                'subject' => '',
                'body' => ''
            );
        }

        $order_items = KTPWP_Order_Items::get_instance();
        
        // Get invoice items list and amount from actual database
        $invoice_items_from_db = $order_items->get_invoice_items( $order->id );
        $amount = 0;
        $invoice_list = '';
        
        if (!empty($invoice_items_from_db)) {
            // Actual invoice item data exists
            $invoice_list = "\n";
            $max_length = 0;
            $item_lines = array();
            
            foreach ($invoice_items_from_db as $item) {
                $product_name = isset($item['product_name']) ? sanitize_text_field($item['product_name']) : '';
                $item_amount = isset($item['amount']) ? floatval($item['amount']) : 0;
                $price = isset($item['price']) ? floatval($item['price']) : 0;
                $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 0;
                $unit = isset($item['unit']) ? sanitize_text_field($item['unit']) : '';
                $remarks = isset($item['remarks']) ? sanitize_text_field($item['remarks']) : '';
                
                $amount += $item_amount;
                
                if ($product_name) {
                    $line = sprintf('%s  %s円 x %s%s = %s円', 
                        $product_name, 
                        number_format($price), 
                        number_format($quantity), 
                        $unit, 
                        number_format($item_amount)
                    );
                    if ($remarks) {
                        $line .= ' (' . $remarks . ')';
                    }
                    $item_lines[] = $line;
                    $max_length = max($max_length, mb_strlen($line));
                }
            }
            
            $invoice_list .= implode("\n", $item_lines);
            $invoice_list .= "\n" . str_repeat('-', $max_length);
            $invoice_list .= "\n合計：" . number_format(ceil($amount)) . '円';
        } else {
            // No invoice item data, try JSON data (old format)
            $invoice_items_json = $order->invoice_items ? sanitize_textarea_field( $order->invoice_items ) : '';
            if ($invoice_items_json) {
                $items = @json_decode($invoice_items_json, true);
                if (is_array($items)) {
                    $invoice_list = "\n";
                    foreach ($items as $item) {
                        $amount += isset($item['amount']) ? floatval( $item['amount'] ) : 0;
                        $product_name = isset($item['name']) ? sanitize_text_field( $item['name'] ) : '';
                        $price = isset($item['price']) ? floatval( $item['price'] ) : 0;
                        $quantity = isset($item['quantity']) ? floatval( $item['quantity'] ) : 1;
                        $unit = isset($item['unit']) ? sanitize_text_field( $item['unit'] ) : '';
                        $remarks = isset($item['remarks']) ? sanitize_text_field( $item['remarks'] ) : '';
                        
                        if ($product_name) {
                            $invoice_list .= sprintf('%s  %s円 x %s%s = %s円', 
                                $product_name, 
                                number_format($price), 
                                number_format($quantity), 
                                $unit, 
                                number_format($price * $quantity)
                            );
                            if ($remarks) {
                                $invoice_list .= ' (' . $remarks . ')';
                            }
                            $invoice_list .= "\n";
                        }
                    }
                    $invoice_list .= "合計：" . number_format(ceil($amount)) . '円';
                }
            }
        }

        // Company and admin name
        $my_name = '';

        // Generate subject and body by progress status
        $progress = absint( $order->progress );
        $project_name = $order->project_name ? sanitize_text_field( $order->project_name ) : '';
        $customer_name = sanitize_text_field( $order->customer_name );
        $user_name = sanitize_text_field( $order->user_name );
        
        // 進捗に応じた帳票タイトルと件名を設定
        $document_titles = [
            1 => '見積り書',
            2 => '注文受書',
            3 => '納品書',
            4 => '請求書',
            5 => '領収書',
            6 => '案件完了'
        ];
        
        $document_messages = [
            1 => "につきましてお見積りいたします。",
            2 => "につきましてご注文をお受けしました。",
            3 => "につきまして完了しました。",
            4 => "につきまして請求申し上げます。",
            5 => "につきましてお支払いを確認しました。",
            6 => "につきましては全て完了しています。"
        ];
        
        $document_title = isset($document_titles[$progress]) ? $document_titles[$progress] : '受注書';
        $document_message = isset($document_messages[$progress]) ? $document_messages[$progress] : '';
        
        // 日付フォーマット
        $order_date = date('Y年m月d日', $order->time);
        
        // 件名と本文の統一フォーマット
        $subject = "{$document_title}：{$project_name}";
        $body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞ ID: {$order->id} [{$order_date}]\n「{$project_name}」{$document_message}\n\n請求項目\n{$invoice_list}\n\n--\n{$my_company}";

        return array(
            'subject' => $subject,
            'body' => $body
        );
    }

} // End of KTPWP_Order_UI class

} // class_exists check
