<?php

class KTPWP_View_Tabs_Class {

    public function __construct() {
    }

    // 指定された内容でタブを表示するメソッド
    function TabsView(
        $list_content,
        $order_content,
        $client_content,
        $service_content,
        $supplier_content,
        $report_content
    ) {

        // AJAX設定を確実に出力（編集者権限がある場合のみ）
        if ( current_user_can( 'edit_posts' ) ) {
            $this->output_staff_chat_ajax_config();
        }

        // タブの位置（ショートコード側のコンテンツ生成と同様に POST の tab_name を優先）
        $position = 'list';
        if ( isset( $_POST['tab_name'] ) && is_string( $_POST['tab_name'] ) ) {
            $position = sanitize_text_field( wp_unslash( $_POST['tab_name'] ) );
        } elseif ( isset( $_GET['tab_name'] ) ) {
            $position = sanitize_text_field( wp_unslash( $_GET['tab_name'] ) );
        }
        $allowed_positions = array( 'list', 'order', 'client', 'service', 'supplier', 'report' );
        if ( ! in_array( $position, $allowed_positions, true ) ) {
            $position = 'list';
        }

        $lock_icon = '<span class="ktp-lock-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4V7Zm3 9.73V18h-2v-1.27a2 2 0 1 1 2 0Z"/></svg></span>';
        $report_locked = function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'report' );

        // タブの内容を配列で定義
        $tabs = array(
			'list' => esc_html__( '仕事リスト', 'kantanpro' ),
			'order' => esc_html__( '受注書', 'kantanpro' ),
			'client' => esc_html__( '顧客', 'kantanpro' ),
			'service' => esc_html__( 'サービス', 'kantanpro' ),
			'supplier' => esc_html__( '協力会社', 'kantanpro' ),
			'report' => ( $report_locked ? $lock_icon : '' ) . esc_html__( 'レポート', 'kantanpro' ),
        );

        // タブの内容を作成（プラグインコンテナクラスを追加してテーマとの競合を防止）
        $view = '<div class="tabs ktp_plugin_container">';
        // 現在のURL情報を取得
        $current_url = add_query_arg( null, null );

        // 各タブ用のクリーンなベースURLを作成（KTPWPパラメータを全て除去）
        $clean_base_url = remove_query_arg(
            array(
				'tab_name',
				'from_client',
				'customer_name',
				'user_name',
				'client_id',
				'order_id',
				'delete_order',
				'data_id',
				'view_mode',
				'query_post',
				'page_start',
				'page_stage',
				'message',
				'search_query',
				'list_search',
				'multiple_results',
				'search_service_name',
				'search_category',
				'no_results',
				'flg',
				'sort_by',
				'sort_order',
				'order_sort_by',
				'order_sort_order',
				'skills_sort_by',
				'skills_sort_order',
				'skills_page',
				'report_type',
				'period',
				'tax_year',
				'list_type',
				'progress',
				'chat_open',
				'message_sent',  // チャット関連パラメータも除去
            ),
            $current_url
        );

        foreach ( $tabs as $key => $value ) {
			$checked = $position === $key ? ' checked' : '';
			$active_class = $position === $key ? ' active' : '';
			$tab_args = array_merge(
				array( 'tab_name' => $key ),
				$this->get_saved_tab_state( $key )
			);
			$tab_url = add_query_arg( $tab_args, $clean_base_url );
			$view .= "<input id=\"$key\" type=\"radio\" name=\"tab_item\"$checked>";
			$view .= '<a href="' . esc_url( $tab_url ) . "\" class=\"tab_item$active_class\">$value</a>";
        }

        // 各タブ本体を #list_content 等に置く。親は .tabs の直下にし、ラジオ（#list 等）の「一般兄弟」になること
        // （#list:checked ~ #list_content の ~ は兄弟セレクタのため、中間にラッパー div を挟まない）
        $view .= '<div class="ktp-tab-panels-clearfix" style="clear:both;width:100%;height:0;"></div>';
        $panels = array(
            'list' => $list_content,
            'order' => $order_content,
            'client' => $client_content,
            'service' => $service_content,
            'supplier' => $supplier_content,
            'report' => $report_content,
        );
        foreach ( $panels as $panel_id => $panel_html ) {
			$is_active    = ( $position === $panel_id );
			$active_class = $is_active ? ' tab_content--active' : '';
			$aria_hidden  = $is_active ? 'false' : 'true';
			$view        .= '<div class="tab_content' . esc_attr( $active_class ) . '" id="' . esc_attr( $panel_id ) . '_content" aria-hidden="' . esc_attr( $aria_hidden ) . '">' . $panel_html . '</div>';
        }
        $view .= '</div>';

        // フッターエリアを追加
        $plugin_name = esc_html( KANTANPRO_PLUGIN_NAME );
        $plugin_version = esc_html( KANTANPRO_PLUGIN_VERSION );
        $terms_url = admin_url( 'admin.php?page=ktp-terms&view=public' );

        $view .= '<div class="ktp-footer">';
        $view .= '<div class="ktp-footer-content">';
        $view .= '<span class="ktp-footer-text">' . $plugin_name . ' v' . $plugin_version . '</span>';
        $view .= ' <a href="' . esc_url($terms_url) . '" target="_blank" style="margin-left:10px;font-size:12px;color:#666;text-decoration:none;">利用規約</a>';
        $view .= '</div>';
        $view .= '</div>';

		return $view;
    }

    /**
     * Cookie に保存されたタブ表示状態を取得する。
     *
     * @param string $tab_name タブ名。
     * @return array<string, string>
     */
    private function get_saved_tab_state( $tab_name ) {
        // タブ状態は localStorage で管理（Cookie 肥大化による 431 回避）。
        return array();
    }

    /**
     * スタッフチャット用AJAX設定を出力
     */
    private function output_staff_chat_ajax_config() {
        // 編集者権限チェック - 権限がない場合は何も出力しない
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        // Ajaxリクエスト中は、JavaScriptの出力を抑制する
        if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        static $output_done = false;

        // 重複出力を防止
        if ( $output_done ) {
            return;
        }

        // 統一ナンス管理システムを使用
        $nonce_manager = KTPWP_Nonce_Manager::getInstance();
        $ajax_data = $nonce_manager->get_unified_ajax_config();

        echo '<script type="text/javascript">';
        echo 'window.ktpwp_ajax = ' . json_encode( $ajax_data ) . ';';
        echo 'window.ktp_ajax_object = ' . json_encode( $ajax_data ) . ';';
        echo 'window.ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';';
        echo 'console.log("TabView: 統一AJAX設定を出力", window.ktpwp_ajax);';
        echo '</script>';

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // error_log('KTPWP TabView: Unified AJAX config output: ' . json_encode($ajax_data));
        }

        $output_done = true;
    }
}
