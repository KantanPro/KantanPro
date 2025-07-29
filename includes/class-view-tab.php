<?php

class view_tabs_Class {

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

        // タブの位置を取得
        $position = $_GET['tab_name'] ?? 'list';

        // タブの内容を配列で定義
        $tabs = array(
			'list' => '仕事リスト',
			'order' => '受注書',
			'client' => '顧客',
			'service' => 'サービス',
			'supplier' => '協力会社',
			'report' => 'レポート',
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
				'multiple_results',
				'no_results',
				'flg',
				'sort_by',
				'sort_order',
				'order_sort_by',
				'order_sort_order',
				'chat_open',
				'message_sent',  // チャット関連パラメータも除去
            ),
            $current_url
        );

        foreach ( $tabs as $key => $value ) {
			$checked = $position === $key ? ' checked' : '';
			$active_class = $position === $key ? ' active' : '';
			// クリーンなベースURLにタブ名のみを追加
			$tab_url = add_query_arg( 'tab_name', $key, $clean_base_url );
			$view .= "<input id=\"$key\" type=\"radio\" name=\"tab_item\"$checked>";
			$view .= '<a href="' . esc_url( $tab_url ) . "\" class=\"tab_item$active_class\">$value</a>";
        }

        $view .= <<<EOF
              <div class="tab_content" id="list_content">
              <br />
              </div>
EOF;
        // タブ外に各タブ本体を出す
        $view .= $list_content;
        $view .= $order_content;
        $view .= $client_content;
        $view .= $service_content;
        $view .= $supplier_content;
        $view .= $report_content;
        $view .= <<<EOF
              <div class="tab_content" id="order_content">
              <br />
              </div>
              <div class="tab_content" id="client_content">
              <br />
              </div>
              <div class="tab_content" id="service_content">
              <br />
              </div>
              <div class="tab_content" id="supplier_content">
              <br />
              </div>
              <div class="tab_content" id="report_content">
              <br />
              </div>
            </div>
            EOF;

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
