<?php
/**
 * Plugin Reference class for KTPWP plugin
 *
 * Handles plugin reference/help documentation display
 * with real-time updates and user-friendly interface.
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

if ( ! class_exists( 'KTPWP_Plugin_Reference' ) ) {

/**
 * Plugin Reference class for managing help documentation
 *
 * @since 1.0.0
 */
class KTPWP_Plugin_Reference {

    /**
     * Single instance of the class
     *
     * @var KTPWP_Plugin_Reference
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTPWP_Plugin_Reference
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
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
        add_action( 'wp_ajax_ktpwp_get_reference', array( $this, 'ajax_get_reference' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_get_reference', array( $this, 'ajax_get_reference' ) );
        add_action( 'wp_ajax_ktpwp_clear_reference_cache', array( $this, 'ajax_clear_reference_cache' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_clear_reference_cache', array( $this, 'ajax_clear_reference_cache' ) );
        add_action( 'wp_footer', array( $this, 'add_modal_html' ) );
    }

    /**
     * Enqueue scripts and styles for reference modal
     * 
     * Note: This method is no longer used as scripts are loaded in main ktpwp.php
     * Kept for backward compatibility
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_reference_scripts() {
        // Scripts are now loaded in main ktpwp.php file
        // This method is kept for backward compatibility
    }

    /**
     * Generate reference link for header
     *
     * @since 1.0.0
     * @return string HTML for reference link
     */
    public function get_reference_link() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $reference_icon = '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">help</span>';
        
        // デバッグ用：リファレンスリンクが生成されることをコンソールに記録
        $debug_script = '<script>console.log("KTPWP Reference: Link generated");</script>';
        
        return $debug_script . '<a href="#" id="ktpwp-reference-trigger" class="ktpwp-reference-link" '
            . 'title="' . esc_attr__( 'プラグインの使い方を確認', 'ktpwp' ) . '" '
            . 'style="color: #0073aa; text-decoration: none; margin-left: 8px; display: inline-flex; align-items: center; gap: 4px;">'
            . $reference_icon
            . '<span>' . esc_html__( 'ヘルプ', 'ktpwp' ) . '</span>'
            . '</a>';
    }

    /**
     * Ajax handler for getting reference content
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_reference() {
        // Security check
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_reference_nonce' ) ) {
            wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'ログインが必要です。', 'ktpwp' ) );
        }

        $section = isset( $_POST['section'] ) ? sanitize_text_field( $_POST['section'] ) : 'overview';
        
        // Check if reference needs refresh after activation
        if ( get_option( 'ktpwp_reference_needs_refresh', false ) ) {
            delete_option( 'ktpwp_reference_needs_refresh' );
            delete_transient( 'ktpwp_reference_cache' );
        }
        
        $content = $this->get_reference_content( $section );
        
        wp_send_json_success( array(
            'content' => $content,
            'section' => $section,
            'last_updated' => get_option( 'ktpwp_reference_last_updated', time() ),
            'version' => KANTANPRO_PLUGIN_VERSION // 常に最新の定数値を使用
        ) );
    }

    /**
     * Ajax handler for clearing reference cache
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_reference_cache() {
        // Security check
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_reference_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'セキュリティチェックに失敗しました。', 'ktpwp' ) ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => esc_html__( 'ログインが必要です。', 'ktpwp' ) ) );
        }

        // Clear all reference cache (including individual section caches)
        $sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting' );
        
        foreach ( $sections as $section ) {
            delete_transient( "ktpwp_reference_content_{$section}" );
        }
        
        // Clear main cache
        delete_transient( 'ktpwp_reference_cache' );
        
        // Update metadata
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
        
        // Update last cleared timestamp
        update_option( 'ktpwp_reference_last_cleared', time() );
        
        wp_send_json_success( array( 
            'message' => esc_html__( 'キャッシュをクリアしました。', 'ktpwp' ),
            'cleared_at' => current_time( 'mysql' )
        ) );
    }

    /**
     * Get reference content by section
     *
     * @since 1.0.0
     * @param string $section Reference section
     * @return string HTML content
     */
    private function get_reference_content( $section ) {
        // Check cache first (unless refresh is needed)
        $cache_key = "ktpwp_reference_content_{$section}";
        $cached_content = get_transient( $cache_key );
        
        if ( $cached_content !== false && ! get_option( 'ktpwp_reference_needs_refresh', false ) ) {
            return $cached_content;
        }
        
        $content = '';
        
        switch ( $section ) {
            case 'overview':
                $content = $this->get_overview_content();
                break;
            case 'tabs':
                $content = $this->get_tabs_content();
                break;
            case 'shortcodes':
                $content = $this->get_shortcode_content();
                break;
            case 'settings':
                $content = $this->get_settings_content();
                break;
            case 'security':
                $content = $this->get_security_content();
                break;
            case 'troubleshooting':
                $content = $this->get_troubleshooting_content();
                break;
            default:
                $content = $this->get_overview_content();
                break;
        }
        
        // Cache the content for 1 hour
        if ( ! empty( $content ) ) {
            set_transient( $cache_key, $content, HOUR_IN_SECONDS );
        }
        
        return $content;
    }

    /**
     * Get overview content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_overview_content() {
        return '<div class="ktpwp-reference-content">'
            . '<h3>KantanProプラグイン リファレンス</h3>'
            . '<p>KantanPro (KTPWP) は、WordPressで動作する総合ビジネス管理プラグインです。受注進捗・伝票・顧客・サービス・仕入先・レポート・スタッフチャットまで一元管理できます。</p>'
            . '<h4>主な機能</h4>'
            . '<ul>'
            . '<li>6つの管理タブ（仕事リスト・伝票処理・得意先・サービス・協力会社・レポート）</li>'
            . '<li>受注案件の進捗管理（7段階）</li>'
            . '<li>受注書の作成・編集・印刷・プレビュー</li>'
            . '<li>顧客・サービス・仕入先のマスター管理</li>'
            . '<li>協力会社商品管理（商品名・単価・数量・単位の詳細管理）</li>'
            . '<li>スタッフ間チャット（自動スクロール・削除連動）</li>'
            . '<li>売上・進捗・顧客別レポート</li>'
            . '<li>直感的なタブUI・ページネーション・ソート・検索</li>'
            . '<li>案件名インライン編集（リアルタイム更新）</li>'
            . '<li>レスポンシブデザイン</li>'
            . '<li>納期管理機能</li>'
            . '</ul>'
            . '<h4>インストール</h4>'
            . '<ol>'
            . '<li>プラグインを <code>/wp-content/plugins/</code> にアップロード、または管理画面からインストール</li>'
            . '<li>プラグインを有効化</li>'
            . '<li>固定ページに <code>[ktpwp_all_tab]</code> を挿入</li>'
            . '<li>管理画面「KantanPro」から基本設定（一般設定・メール/SMTP・デザイン・ライセンス・スタッフ管理）を行う</li>'
            . '</ol>'
            . '<h4>システム要件</h4>'
            . '<ul>'
            . '<li>WordPress 5.0 以上</li>'
            . '<li>PHP 7.4 以上</li>'
            . '<li>MySQL 5.6 以上 または MariaDB 10.0 以上</li>'
            . '<li>推奨メモリ: 256MB 以上</li>'
            . '</ul>'
            . '<h4>進捗ステータス</h4>'
            . '<ul>'
            . '<li><strong>受付中</strong> - 新規受注、内容確認中</li>'
            . '<li><strong>見積中</strong> - 見積作成・提案中</li>'
            . '<li><strong>受注</strong> - 作業実行中</li>'
            . '<li><strong>完了</strong> - 作業完了、請求書未発行</li>'
            . '<li><strong>請求済</strong> - 請求書発行済み</li>'
            . '<li><strong>入金済</strong> - 支払い完了</li>'
            . '<li><strong>ボツ</strong> - 復活可能なボツ案件</li>'
            . '</ul>'
            . '<h4>変更履歴</h4>'
            . '<ul>'
            . '<li><strong>1.2.1(beta)</strong>: モバイル表示の改善、進捗状況にボツ追加、納期管理機能を実装、得意先タブの構造変更</li>'
            . '<li><strong>1.2.0(beta)</strong>: 設定タブの廃止、伝票処理タブの最終実装、サービス・協力会社ポップアップの強化、行要素数値の最適化</li>'
            . '<li><strong>1.1.1(beta)</strong>: 協力会社機能の大幅拡張、ページネーション機能の強化</li>'
            . '<li><strong>1.1.0(beta)</strong>: 職能管理機能の拡張、Ajaxインライン編集、データマイグレーション等</li>'
            . '<li><strong>1.0.9(beta)</strong> 以前: UI/UX改善、セキュリティ強化、スタッフチャット自動スクロール等</li>'
            . '</ul>'
            . '</div>';
    }

    /**
     * Get tabs content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_tabs_content() {
        return '<div class="ktpwp-reference-content">'
            . '<h3>タブ機能説明</h3>'
            . '<p>KantanProは6つのタブで構成され、ビジネスプロセス全体をカバーします。</p>'
            . '<div class="ktpwp-tabs-explanation">'
            . '<div class="ktpwp-tab-item"><h4>1. 仕事リスト</h4><ul>'
            . '<li>受注案件の進捗管理とステータス追跡（7段階）</li>'
            . '<li>プロジェクト名・顧客情報・担当者・金額・進捗の一元管理</li>'
            . '<li>案件名インライン編集機能</li>'
            . '<li>納期管理機能</li>'
            . '</ul></div>'
            . '<div class="ktpwp-tab-item"><h4>2. 伝票処理</h4><ul>'
            . '<li>受注書作成・編集・印刷・請求項目管理</li>'
            . '<li>プレビュー・印刷機能</li>'
            . '</ul></div>'
            . '<div class="ktpwp-tab-item"><h4>3. 得意先</h4><ul>'
            . '<li>顧客情報管理・注文明細履歴表示・印刷テンプレート</li>'
            . '<li>会社名・担当者・連絡先・住所情報の管理</li>'
            . '<li>構造変更により、より使いやすいインターフェース</li>'
            . '</ul></div>'
            . '<div class="ktpwp-tab-item"><h4>4. サービス</h4><ul>'
            . '<li>サービス・商品マスター管理・価格設定</li>'
            . '<li>サービス名・価格・単位・カテゴリ管理</li>'
            . '</ul></div>'
            . '<div class="ktpwp-tab-item"><h4>5. 協力会社</h4><ul>'
            . '<li>仕入先・外注先情報管理・支払条件設定</li>'
            . '<li>商品管理機能（商品名・単価・数量・単位の詳細管理）</li>'
            . '</ul></div>'
            . '<div class="ktpwp-tab-item"><h4>6. レポート</h4><ul>'
            . '<li>売上分析・進捗状況・データ集計</li>'
            . '<li>顧客別売上分析・月次・年次レポート</li>'
            . '</ul></div>'
            . '</div>'
            . '<h4>進捗ステータス詳細</h4>'
            . '<ul>'
            . '<li><strong>受付中</strong> - 新規受注、内容確認中</li>'
            . '<li><strong>見積中</strong> - 見積作成・提案中</li>'
            . '<li><strong>受注</strong> - 作業実行中</li>'
            . '<li><strong>完了</strong> - 作業完了、請求書未発行</li>'
            . '<li><strong>請求済</strong> - 請求書発行済み</li>'
            . '<li><strong>入金済</strong> - 支払い完了</li>'
            . '<li><strong>ボツ</strong> - 復活可能なボツ案件</li>'
            . '</ul>'
            . '</div>';
    }

    /**
     * Get shortcode content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_shortcode_content() {
        return '<div class="ktpwp-reference-content">'
            . '<h3>ショートコード使用方法</h3>'
            . '<div class="ktpwp-shortcode-explanation">'
            . '<h4>基本ショートコード</h4>'
            . '<p>メインのプラグイン機能を表示するには、以下のショートコードを使用してください：</p>'
            . '<code style="background: #f5f5f5; padding: 12px; border-radius: 4px; display: block; margin: 12px 0; font-size: 16px;">[ktpwp_all_tab]</code>'
            . '<h4>設置方法</h4>'
            . '<ol>'
            . '<li>WordPress管理画面で「固定ページ」→「新規追加」をクリック</li>'
            . '<li>ページタイトルを入力（例：「ワークフロー管理」など）</li>'
            . '<li>エディタに <code>[ktpwp_all_tab]</code> を挿入</li>'
            . '<li>「公開」または「更新」をクリック</li>'
            . '</ol>'
            . '<h4>注意事項</h4>'
            . '<ul>'
            . '<li>ログインユーザーのみがプラグイン機能にアクセス可能です</li>'
            . '<li>ページのパーマリンクは分かりやすいものに設定することを推奨します</li>'
            . '<li>ページのテンプレートは「デフォルト」または「全幅」を推奨します</li>'
            . '</ul>'
            . '</div>'
            . '</div>';
    }

    /**
     * Get settings content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_settings_content() {
        $settings_url = admin_url( 'admin.php?page=ktp-settings' );
        return '<div class="ktpwp-reference-content">'
            . '<h3>管理画面・設定ガイド</h3>'
            . '<div class="ktpwp-settings-guide">'
            . '<p>プラグインの設定は管理画面「KantanPro」メニューから行えます。</p>'
            . '<ul>'
            . '<li><b>一般設定</b>: プラグインの基本設定</li>'
            . '<li><b>メール・SMTP設定</b>: メール送信に関する基本設定</li>'
            . '<li><b>デザイン</b>: プラグインの外観とデザイン設定</li>'
            . '<li><b>ライセンス設定</b>: アクティベーションキー登録</li>'
            . '<li><b>スタッフ管理</b>: スタッフの追加・削除</li>'
            . '</ul>'
            . '<p><a href="' . esc_url( $settings_url ) . '" target="_blank" style="color: #0073aa;">→ 設定ページを開く</a></p>'
            . '</div>'
            . '</div>';
    }

    /**
     * Get security content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_security_content() {
        return '<div class="ktpwp-reference-content">'
            . '<h3>セキュリティ機能</h3>'
            . '<div class="ktpwp-security-features">'
            . '<p>KantanProプラグインは以下のセキュリティ対策を実装しています：</p>'
            . '<ul>'
            . '<li>SQLインジェクション防止（prepare文・バインド変数使用）</li>'
            . '<li>XSS攻撃防止（データサニタイズ・エスケープ処理）</li>'
            . '<li>CSRF攻撃防止（WordPressノンス検証）</li>'
            . '<li>ファイルアップロード検証</li>'
            . '<li>ユーザー権限の適切な制御</li>'
            . '<li>データベースアクセスの安全な処理</li>'
            . '</ul>'
            . '</div>'
            . '</div>';
    }

    /**
     * Get troubleshooting content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_troubleshooting_content() {
        return '<div class="ktpwp-reference-content">'
            . '<h3>トラブルシューティング</h3>'
            . '<div class="ktpwp-troubleshooting">'
            . '<h4>よくある質問と解決方法</h4>'
            . '<div class="ktpwp-faq-item"><h5>Q: ショートコードを挿入してもプラグインが表示されない</h5><p><strong>A:</strong></p><ul><li>ログインしているかを確認してください</li><li>プラグインが有効化されているかを確認してください</li><li>ショートコードが正しく記述されているかを確認してください：[ktpwp_all_tab]</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: データが保存されない</h5><p><strong>A:</strong></p><ul><li>データベースの権限を確認してください</li><li>PHPのメモリ制限を確認してください</li><li>プラグインを一度無効化して再有効化してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: 商品管理機能が表示されない</h5><p><strong>A:</strong></p><ul><li>データベースのマイグレーションが完了しているかを確認してください</li><li>プラグインを再有効化してみてください</li><li>ブラウザキャッシュをクリアしてください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: インライン編集が動作しない</h5><p><strong>A:</strong></p><ul><li>JavaScriptが有効になっているかを確認してください</li><li>ログイン権限があることを確認してください</li><li>ブラウザのコンソールでエラーがないかを確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: ページネーションが正しく動作しない</h5><p><strong>A:</strong></p><ul><li>一般設定で表示件数が正しく設定されているかを確認してください</li><li>Ajax通信がブロックされていないかを確認してください</li><li>ネットワーク接続が安定しているかを確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: PDF出力ができない</h5><p><strong>A:</strong></p><ul><li>サーバーのPHP拡張機能（mbstring, gd等）を確認してください</li><li>ブラウザのポップアップブロックを無効にしてください</li><li>会社情報の設定が完了しているかを確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: タブが正しく表示されない</h5><p><strong>A:</strong></p><ul><li>ブラウザのキャッシュをクリアしてください</li><li>テーマとの競合がないかを確認してください</li><li>他のプラグインとの競合がないかを確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: メール送信ができない</h5><p><strong>A:</strong></p><ul><li>SMTP設定を確認してください</li><li>テストメール送信機能を使用してください</li><li>レンタルサーバーのメール送信制限を確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: 納期管理機能が表示されない</h5><p><strong>A:</strong></p><ul><li>プラグインを最新バージョン（1.2.1以上）に更新してください</li><li>データベースのマイグレーションが完了しているかを確認してください</li><li>ブラウザキャッシュをクリアしてください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: モバイル表示が正しく表示されない</h5><p><strong>A:</strong></p><ul><li>ブラウザのキャッシュをクリアしてください</li><li>CSSファイルが正しく読み込まれているかを確認してください</li><li>テーマのレスポンシブ設定を確認してください</li></ul></div>'
            . '<div class="ktpwp-faq-item"><h5>Q: ボツ機能が動作しない</h5><p><strong>A:</strong></p><ul><li>プラグインを最新バージョン（1.2.1以上）に更新してください</li><li>進捗ステータスの設定を確認してください</li><li>権限設定を確認してください</li></ul></div>'
            . '<h4>システム要件の確認</h4>'
            . '<ul>'
            . '<li>WordPress 5.0以上</li>'
            . '<li>PHP 7.4以上</li>'
            . '<li>MySQL 5.6以上 または MariaDB 10.0以上</li>'
            . '<li>推奨メモリ: 256MB以上</li>'
            . '</ul>'
            . '<h4>デバッグモード</h4>'
            . '<p>問題が解決しない場合は、wp-config.phpでデバッグモードを有効にしてエラーログを確認してください：</p>'
            . '<code style="background: #f5f5f5; padding: 8px; border-radius: 4px; display: block; margin: 8px 0;">define(\'WP_DEBUG\', true);<br>define(\'WP_DEBUG_LOG\', true);</code>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render reference modal HTML
     *
     * @since 1.0.0
     * @return string Modal HTML
     */
    public static function render_modal() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        return '<div id="ktpwp-reference-modal" class="ktpwp-modal" style="display: none;">'
            . '<div class="ktpwp-modal-overlay">'
            . '<div class="ktpwp-modal-content">'
            . '<div class="ktpwp-modal-header">'
            . '<h3>' . esc_html__( 'KantanProプラグインリファレンス', 'ktpwp' ) . '</h3>'
            . '<div class="ktpwp-modal-header-actions">'
            . '<button class="ktpwp-clear-cache-btn" type="button" title="' . esc_attr__( 'キャッシュをクリア', 'ktpwp' ) . '">'
            . esc_html__( 'キャッシュクリア', 'ktpwp' ) . '</button>'
            . '<button class="ktpwp-modal-close" type="button">&times;</button>'
            . '</div>'
            . '</div>'
            . '<div class="ktpwp-modal-body">'
            . '<div class="ktpwp-reference-sidebar">'
            . '<ul class="ktpwp-reference-nav">'
            . '<li><a href="#" data-section="overview" class="active">' . esc_html__( '概要', 'ktpwp' ) . '</a></li>'
            . '<li><a href="#" data-section="tabs">' . esc_html__( 'タブ機能', 'ktpwp' ) . '</a></li>'
            . '<li><a href="#" data-section="shortcodes">' . esc_html__( 'ショートコード', 'ktpwp' ) . '</a></li>'
            . '<li><a href="#" data-section="settings">' . esc_html__( '設定', 'ktpwp' ) . '</a></li>'
            . '<li><a href="#" data-section="security">' . esc_html__( 'セキュリティ', 'ktpwp' ) . '</a></li>'
            . '<li><a href="#" data-section="troubleshooting">' . esc_html__( 'トラブルシューティング', 'ktpwp' ) . '</a></li>'
            . '</ul>'
            . '</div>'
            . '<div class="ktpwp-reference-content">'
            . '<div id="ktpwp-reference-loading" style="display: none;">' . esc_html__( '読み込み中...', 'ktpwp' ) . '</div>'
            . '<div id="ktpwp-reference-text"></div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Add modal HTML to footer
     *
     * @since 1.0.0
     * @return void
     */
    public function add_modal_html() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        echo self::render_modal();
    }

    /**
     * Plugin activation hook for reference updates
     *
     * This method is called during plugin activation to ensure
     * reference documentation is properly initialized and updated.
     *
     * @since 1.0.0
     * @return void
     */
    public static function on_plugin_activation() {
        // Clear ALL cached reference data (individual section caches)
        $sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting' );
        foreach ( $sections as $section ) {
            delete_transient( "ktpwp_reference_content_{$section}" );
        }
        
        // Clear main cache
        delete_transient( 'ktpwp_reference_cache' );
        
        // Clear any existing options that might store cached data
        delete_option( 'ktpwp_reference_last_cleared' );
        
        // Update plugin reference metadata with current version
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
        
        // Log activation event for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグインリファレンスが有効化時に更新されました。バージョン: ' . KANTANPRO_PLUGIN_VERSION . ' (1.2.1 beta)');
        }
        
        // Force regeneration of reference content on next load
        update_option( 'ktpwp_reference_needs_refresh', true );
    }

    /**
     * Manual cache clear function for troubleshooting
     * 
     * @since 1.0.9
     * @return bool Success status
     */
    public static function clear_all_cache() {
        // Clear all individual section caches
        $sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting' );
        $cleared_count = 0;
        
        foreach ( $sections as $section ) {
            if ( delete_transient( "ktpwp_reference_content_{$section}" ) ) {
                $cleared_count++;
            }
        }
        
        // Clear main cache
        delete_transient( 'ktpwp_reference_cache' );
        
        // Update metadata to current version
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
        update_option( 'ktpwp_reference_last_cleared', time() );
        update_option( 'ktpwp_reference_needs_refresh', true );
        
        // Log the action
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP: Manual cache clear completed. Cleared {$cleared_count} section caches. Version: " . KANTANPRO_PLUGIN_VERSION . " (1.2.1 beta)");
        }
        
        return true;
    }
}

// Initialize the plugin reference only after init (not on plugin load)
add_action('init', function() {
    KTPWP_Plugin_Reference::get_instance();
});

// Add admin action to manually clear reference cache
add_action('wp_ajax_ktpwp_manual_cache_clear', function() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }
    
    if (class_exists('KTPWP_Plugin_Reference')) {
        KTPWP_Plugin_Reference::clear_all_cache();
        wp_die('プラグインリファレンスキャッシュをクリアしました。バージョン: ' . KANTANPRO_PLUGIN_VERSION . ' (1.2.1 beta)');
    } else {
        wp_die('プラグインリファレンスクラスが見つかりません');
    }
});

// Add admin menu for cache clearing (for debugging)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_menu', function() {
        add_submenu_page(
            null, // 非表示メニュー
            'Clear Reference Cache',
            'Clear Reference Cache',
            'manage_options',
            'ktpwp-clear-cache',
            function() {
                if (isset($_GET['action']) && $_GET['action'] === 'clear') {
                    if (class_exists('KTPWP_Plugin_Reference')) {
                        KTPWP_Plugin_Reference::clear_all_cache();
                        echo '<div class="notice notice-success"><p>キャッシュをクリアしました。バージョン: ' . KANTANPRO_PLUGIN_VERSION . ' (1.2.1 beta)</p></div>';
                    }
                }
                echo '<div class="wrap">';
                echo '<h1>KantanPro リファレンスキャッシュクリア</h1>';
                echo '<p><a href="?page=ktpwp-clear-cache&action=clear" class="button button-primary">キャッシュをクリア</a></p>';
                echo '<p>現在のバージョン: ' . KANTANPRO_PLUGIN_VERSION . ' (1.2.1 beta)</p>';
                echo '</div>';
            }
        );
    });
}

} // End if class_exists
