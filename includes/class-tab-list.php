<?php
/**
 * List class for KTPWP plugin
 *
 * Handles order list display, filtering, and management.
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

if ( ! class_exists( 'Kantan_List_Class' ) ) {

/**
 * List class for managing order lists
 *
 * @since 1.0.0
 */
class Kantan_List_Class {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Constructor initialization
    }
    
    /**
     * Display list tab view
     *
     * @since 1.0.0
     * @param string $tab_name Tab name
     * @return void
     */
    public function List_Tab_View( $tab_name ) {
        // Check user capabilities
        // if ( ! current_user_can( 'manage_options' ) ) {
        //     wp_die( __( 'You do not have sufficient permissions to access this page.', 'ktpwp' ) );
        // }

        if ( empty( $tab_name ) ) {
            error_log( 'KTPWP: Empty tab_name provided to List_Tab_View method' );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_order';

        $content = '';

        // Controller container display at top
        $content .= '<div class="controller">';
        
        // Print button with proper escaping
        $content .= '<button title="' . esc_attr__( 'Print', 'ktpwp' ) . '" onclick="alert(\'' . esc_js( __( 'Print function placeholder', 'ktpwp' ) ) . '\')" style="padding: 6px 10px; font-size: 12px;">';
        $content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'Print', 'ktpwp' ) . '">print</span>';
        $content .= '</button>';

        // Progress status buttons
        $progress_labels = array(
            1 => __( '受付中', 'ktpwp' ),
            2 => __( '見積中', 'ktpwp' ),
            3 => __( '作成中', 'ktpwp' ),
            4 => __( '完成未請求', 'ktpwp' ),
            5 => __( '請求済', 'ktpwp' ),
            6 => __( '入金済', 'ktpwp' )
        );
        
        $selected_progress = isset( $_GET['progress'] ) ? absint( $_GET['progress'] ) : 1;

        // Get count for each progress status with prepared statements
        $progress_counts = array();
        foreach ( $progress_labels as $num => $label ) {
            $count = $wpdb->get_var( $wpdb->prepare( 
                "SELECT COUNT(*) FROM `%1s` WHERE progress = %d", 
                $table_name, 
                $num 
            ) );
            $progress_counts[ $num ] = (int) $count;
        }

        $content .= '</div>'; // .controller end

        // Workflow area to display progress buttons in full width
        $content .= '<div class="workflow" style="width:100%;margin:0px 0 0px 0;">';
        $content .= '<div class="progress-filter" style="display:flex;gap:8px;width:100%;justify-content:center;">';
        
        foreach ( $progress_labels as $num => $label ) {
            $active = ( $selected_progress === $num ) ? 'style="font-weight:bold;background:#1976d2;color:#fff;"' : '';
            $btn_label = esc_html( $label ) . ' (' . $progress_counts[ $num ] . ')';
            // $content .= '<a href="?tab_name=' . urlencode($tab_name) . '&progress=' . $num . '" class="progress-btn" '.$active.'>' . $btn_label . '</a>';
            $content .= '<a href="' . add_query_arg(array('tab_name' => $tab_name, 'progress' => $num)) . '" class="progress-btn" '.$active.'>' . $btn_label . '</a>';
        }
        $content .= '</div>';
        $content .= '</div>';

        // 受注書リスト表示
        // $content .= '<h3>■ 受注書リスト</h3>';

        // ページネーション設定
        // 一般設定から表示件数を取得（設定クラスが利用可能な場合）
        if (class_exists('KTP_Settings')) {
            $query_limit = KTP_Settings::get_work_list_range();
        } else {
            $query_limit = 20; // フォールバック値
        }
        $page_stage = isset($_GET['page_stage']) ? $_GET['page_stage'] : '';
        $page_start = isset($_GET['page_start']) ? intval($_GET['page_start']) : 0;
        $flg = isset($_GET['flg']) ? $_GET['flg'] : '';
        $selected_progress = isset($_GET['progress']) ? intval($_GET['progress']) : 1;
        if ($page_stage == '') {
            $page_start = 0;
        }
        // 総件数取得
        $total_query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE progress = %d", $selected_progress);
        $total_rows = $wpdb->get_var($total_query);
        $total_pages = ceil($total_rows / $query_limit);
        $current_page = floor($page_start / $query_limit) + 1;
        // データ取得
        $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE progress = %d ORDER BY time DESC LIMIT %d, %d", $selected_progress, $page_start, $query_limit);
        $order_list = $wpdb->get_results($query);

        // --- ここからラッパー追加 ---
        $content .= '<div class="ktp_work_list_box">';
        if ($order_list) {
            // 進捗ラベル
        $progress_labels = [
            1 => '受付中',
            2 => '見積中',
            3 => '作成中',
            4 => '完成未請求',
            5 => '請求済',
            6 => '入金済'
        ];
            $content .= '<ul>';
            foreach ($order_list as $order) {
                $order_id = esc_html($order->id);
                $customer_name = esc_html($order->customer_name);
                $user_name = esc_html($order->user_name);
                $project_name = isset($order->project_name) ? esc_html($order->project_name) : '';
                
                // 納期フィールドの値を取得（希望納期は削除、納品予定日のみ）
                $expected_delivery_date = isset($order->expected_delivery_date) ? $order->expected_delivery_date : '';
                
                // 日時フォーマット変換
                $raw_time = $order->time;
                $formatted_time = '';
                if (!empty($raw_time)) {
                    // UNIXタイムスタンプかMySQL日付か判定
                    if (is_numeric($raw_time) && strlen($raw_time) >= 10) {
                        // UNIXタイムスタンプ（秒単位）
                        $timestamp = (int)$raw_time;
                        $dt = new DateTime('@' . $timestamp);
                        $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
                    } else {
                        // MySQL DATETIME形式
                        $dt = date_create($raw_time, new DateTimeZone('Asia/Tokyo'));
                    }
                    if ($dt) {
                        $week = ['日','月','火','水','木','金','土'];
                        $w = $dt->format('w');
                        $formatted_time = $dt->format('n/j') . '（' . $week[$w] . '）' . $dt->format(' H:i');
                    }
                }
                $time = esc_html($formatted_time);
                $progress = intval($order->progress);
                
                // シンプルなURL生成（パーマリンク設定に依存しない）
                // $detail_url = '?tab_name=order&order_id=' . $order_id;
                $detail_url = add_query_arg(array('tab_name' => 'order', 'order_id' => $order_id));

                // プルダウンフォーム
                $content .= "<li class='ktp_work_list_item'>";
                $content .= "<a href='{$detail_url}'>ID: {$order_id} - {$customer_name} ({$user_name})";
                if ($project_name !== '') {
                    $content .= " - <span class='project_name'>{$project_name}</span>";
                }
                $content .= " - {$time}</a>";
                
                // 納期フィールドを追加（希望納期は削除、納品予定日のみ）
                $content .= "<div class='delivery-dates-container'>";
                $content .= "<span class='delivery-label'>納期</span>";
                $content .= "<input type='date' name='expected_delivery_date_{$order_id}' value='{$expected_delivery_date}' class='delivery-date-input' data-order-id='{$order_id}' data-field='expected_delivery_date' placeholder='納品予定日' title='納品予定日'>";
                $content .= "</div>";
                
                $content .= "<form method='post' action='' style='margin: 0px 0 0px 0;display:inline;'>";
                $content .= "<input type='hidden' name='update_progress_id' value='{$order_id}' />";
                $content .= "<select name='update_progress' class='progress-select status-{$progress}' onchange='this.form.submit()'>";
                foreach ($progress_labels as $num => $label) {
                    $selected = ($progress === $num) ? 'selected' : '';
                    $content .= "<option value='{$num}' {$selected}>{$label}</option>";
                }
                $content .= "</select>";
                $content .= "</form>";
                $content .= "</li>";
            }
            $content .= '</ul>';
        } else {
            $content .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
                . '<span class="material-symbols-outlined" aria-label="データなし">search_off</span>'
                . '<span style="font-size: 1em; font-weight: 600;">' . esc_html__('受注書データがありません。', 'ktpwp') . '</span>'
                . '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__('得意先タブで顧客情報を入力し受注書を作成してください', 'ktpwp') . '</span>'
                . '</div>';
        }
        // 進捗更新処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress_id'], $_POST['update_progress'])) {
            $update_id = intval($_POST['update_progress_id']);
            $update_progress = intval($_POST['update_progress']);
            if ($update_id > 0 && $update_progress >= 1 && $update_progress <= 6) {
                $wpdb->update($table_name, ['progress' => $update_progress], ['id' => $update_id]);
                // リダイレクトで再読み込み（POSTリダブミット防止）
                wp_redirect(esc_url_raw($_SERVER['REQUEST_URI']));
                exit;
            }
        }
        // --- ページネーション ---
        if ($total_pages > 1) {
            // 統一されたページネーションデザインを使用
            $content .= $this->render_pagination($current_page, $total_pages, $query_limit, $tab_name, $flg, $selected_progress, $total_rows);
        }
        $content .= '</div>'; // .ktp_work_list_box 終了
        // --- ここまでラッパー追加 ---

        // 納期フィールドのJavaScriptファイルを読み込み
        wp_enqueue_script('ktp-delivery-dates');

        return $content;
    }

    /**
     * 統一されたページネーションデザインをレンダリング
     *
     * @param int $current_page 現在のページ
     * @param int $total_pages 総ページ数
     * @param int $query_limit 1ページあたりの表示件数
     * @param string $tab_name タブ名
     * @param string $flg フラグ
     * @param int $selected_progress 選択された進捗
     * @param int $total_rows 総データ数
     * @return string ページネーションHTML
     */
    private function render_pagination($current_page, $total_pages, $query_limit, $tab_name, $flg, $selected_progress, $total_rows) {
        if ($total_pages <= 1) {
            return '';
        }

        $pagination_html = '<div class="pagination" style="text-align: center; margin: 20px 0; padding: 20px 0;">';
        
        // 1行目：ページ情報表示
        $pagination_html .= '<div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">';
        $pagination_html .= esc_html($current_page) . ' / ' . esc_html($total_pages) . ' ページ（全 ' . esc_html($total_rows) . ' 件）';
        $pagination_html .= '</div>';
        
        // 2行目：ページネーションボタン
        $pagination_html .= '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: center; width: 100%;">';
        
        // ページネーションボタンのスタイル（正円ボタン）
        $button_style = 'display: inline-block; width: 36px; height: 36px; padding: 0; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 50%; color: #333; background: #fff; transition: all 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 34px; text-align: center; vertical-align: middle; font-size: 14px;';
        $current_style = 'background: #1976d2; color: white; border-color: #1976d2; font-weight: bold; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
        $hover_effect = 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"';

        // 前のページボタン
        if ($current_page > 1) {
            $prev_args = array(
                'tab_name' => $tab_name,
                'page_start' => ($current_page - 2) * $query_limit,
                'page_stage' => 2,
                'flg' => $flg,
                'progress' => $selected_progress
            );
            $prev_url = esc_url(add_query_arg($prev_args));
            $pagination_html .= "<a href=\"{$prev_url}\" style=\"{$button_style}\" {$hover_effect}>‹</a>";
        }

        // ページ番号ボタン（省略表示対応）
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        // 最初のページを表示
        if ($start_page > 1) {
            $first_args = array(
                'tab_name' => $tab_name,
                'page_start' => 0,
                'page_stage' => 2,
                'flg' => $flg,
                'progress' => $selected_progress
            );
            $first_url = esc_url(add_query_arg($first_args));
            $pagination_html .= "<a href=\"{$first_url}\" style=\"{$button_style}\" {$hover_effect}>1</a>";
            
            if ($start_page > 2) {
                $pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
            }
        }

        // 中央のページ番号
        for ($i = $start_page; $i <= $end_page; $i++) {
            $page_args = array(
                'tab_name' => $tab_name,
                'page_start' => ($i - 1) * $query_limit,
                'page_stage' => 2,
                'flg' => $flg,
                'progress' => $selected_progress
            );
            $page_url = esc_url(add_query_arg($page_args));
            
            if ($i == $current_page) {
                $pagination_html .= "<span style=\"{$button_style} {$current_style}\">{$i}</span>";
            } else {
                $pagination_html .= "<a href=\"{$page_url}\" style=\"{$button_style}\" {$hover_effect}>{$i}</a>";
            }
        }

        // 最後のページを表示
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
            }
            
            $last_args = array(
                'tab_name' => $tab_name,
                'page_start' => ($total_pages - 1) * $query_limit,
                'page_stage' => 2,
                'flg' => $flg,
                'progress' => $selected_progress
            );
            $last_url = esc_url(add_query_arg($last_args));
            $pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
        }

        // 次のページボタン
        if ($current_page < $total_pages) {
            $next_args = array(
                'tab_name' => $tab_name,
                'page_start' => $current_page * $query_limit,
                'page_stage' => 2,
                'flg' => $flg,
                'progress' => $selected_progress
            );
            $next_url = esc_url(add_query_arg($next_args));
            $pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
        }

        // 2行目のボタン部分の終了
        $pagination_html .= '</div>';

        $pagination_html .= '</div>';
        
        return $pagination_html;
    }

}
} // class_exists