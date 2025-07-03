<?php
/**
 * Plugin Reference class for KTPWP plugin
 *
 * KantanPro（KTPWP）の公式リファレンス・ヘルプを提供。
 * - モバイル対応UI、PDF出力、サービス選択ポップアップ、アバター、ヘルプボタン、セキュリティ強化など最新機能を網羅。
 * - 管理タブ・伝票処理・顧客・サービス・協力会社・レポート・チャット等の使い方を解説。
 * - バージョンアップ履歴・トラブルシューティングも掲載。
 * - 最新バージョン: 1.3.0(beta)
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.2.2
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
        $version = KANTANPRO_PLUGIN_VERSION;
        
        return '<div class="ktpwp-reference-section">
            <h2>KantanPro プラグイン概要</h2>
            <p><strong>バージョン:</strong> ' . esc_html($version) . '</p>
            
            <h3>🎯 プラグインの目的</h3>
            <p>KantanProは、WordPress上で業務管理・受注進捗・請求・顧客・サービス・協力会社・レポート・スタッフチャットまで一元管理できる多機能プラグインです。</p>
            
            <h3>🚀 主要機能</h3>
            <ul>
                <li><strong>6つの管理タブ</strong>：仕事リスト・伝票処理・得意先・サービス・協力会社・レポート</li>
                <li><strong>受注案件の進捗管理</strong>：7段階（受注→進行中→完了→請求→支払い→ボツ）</li>
                <li><strong>PDF出力機能</strong>：受注書・請求書の個別・一括出力</li>
                <li><strong>スタッフチャット</strong>：リアルタイムコミュニケーション</li>
                <li><strong>モバイル対応UI</strong>：レスポンシブデザイン・アバター表示</li>
                <li><strong>セキュリティ機能</strong>：XSS/CSRF/SQLi対策・権限管理</li>
                <li><strong>自動マイグレーション</strong>：DB構造の自動更新</li>
                <li><strong>WP-CLI対応</strong>：コマンドラインでの管理</li>
            </ul>
            
            <h3>📱 モバイル対応</h3>
            <p>iOS/Android実機でも崩れない直感的UIを提供。gap→margin対応により、モバイルデバイスでの操作性を大幅に改善しています。</p>
            
            <h3>🔒 セキュリティ</h3>
            <p>SQLインジェクション防止、XSS・CSRF対策、ファイルアップロード検証など、強固なセキュリティ機能を実装しています。</p>
            
            <h3>🔄 自動化機能</h3>
            <p>データベース構造の自動マイグレーション、WP-CLIベースの管理機能により、運用負荷を軽減しています。</p>
        </div>';
    }

    /**
     * Get tabs content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_tabs_content() {
        return '<div class="ktpwp-reference-section">
            <h2>管理タブの使い方</h2>
            
            <h3>📋 仕事リストタブ</h3>
            <p><strong>機能：</strong>受注案件の一覧表示・進捗管理</p>
            <ul>
                <li>受注案件の検索・ソート・ページネーション</li>
                <li>進捗状況の変更（7段階）</li>
                <li>納期管理・警告マーク表示</li>
                <li>PDF出力（個別・一括）</li>
            </ul>
            
            <h3>📄 伝票処理タブ</h3>
            <p><strong>機能：</strong>受注書・請求書の詳細編集</p>
            <ul>
                <li>受注書・請求書の作成・編集</li>
                <li>サービス選択ポップアップ</li>
                <li>明細項目の追加・編集・削除</li>
                <li>スタッフチャット機能</li>
                <li>PDF出力・保存</li>
            </ul>
            
            <h3>👥 得意先タブ</h3>
            <p><strong>機能：</strong>顧客情報のマスター管理</p>
            <ul>
                <li>顧客情報の登録・編集・削除</li>
                <li>カテゴリー別管理</li>
                <li>検索・ソート・ページネーション</li>
                <li>注文履歴の表示</li>
            </ul>
            
            <h3>🛠️ サービスタブ</h3>
            <p><strong>機能：</strong>商品・サービスのマスター管理</p>
            <ul>
                <li>商品・サービスの登録・編集・削除</li>
                <li>カテゴリー・価格・単位管理</li>
                <li>画像アップロード機能</li>
                <li>検索・ソート・ページネーション</li>
            </ul>
            
            <h3>🤝 協力会社タブ</h3>
            <p><strong>機能：</strong>協力会社・外注先の管理</p>
            <ul>
                <li>協力会社情報の登録・編集・削除</li>
                <li>職能・スキル管理</li>
                <li>原価管理機能</li>
                <li>検索・ソート・ページネーション</li>
            </ul>
            
            <h3>📊 レポートタブ</h3>
            <p><strong>機能：</strong>売上・進捗の分析・レポート</p>
            <ul>
                <li>売上レポート</li>
                <li>進捗状況の分析</li>
                <li>グラフ表示機能</li>
                <li>データエクスポート</li>
            </ul>
        </div>';
    }

    /**
     * Get shortcode content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_shortcode_content() {
        return '<div class="ktpwp-reference-section">
            <h2>ショートコードの使い方</h2>
            
            <h3>🎯 メインショートコード</h3>
            <p><strong>使用方法：</strong></p>
            <code>[ktpwp_all_tab]</code>
            
            <p><strong>説明：</strong></p>
            <ul>
                <li>6つの管理タブを表示するメインショートコード</li>
                <li>固定ページに挿入して使用</li>
                <li>ログインユーザーのみアクセス可能</li>
                <li>権限に応じて機能が制限される</li>
            </ul>
            
            <h3>📝 設置手順</h3>
            <ol>
                <li>WordPress管理画面で「固定ページ」→「新規追加」</li>
                <li>ページタイトルを入力（例：「業務管理」）</li>
                <li>本文に <code>[ktpwp_all_tab]</code> を挿入</li>
                <li>「公開」ボタンをクリック</li>
                <li>公開されたページにアクセスして動作確認</li>
            </ol>
            
            <h3>🔧 カスタマイズ</h3>
            <p>ショートコードは現在、パラメータなしの基本形式のみ対応しています。</p>
            
            <h3>⚠️ 注意事項</h3>
            <ul>
                <li>ログインが必要です</li>
                <li>適切な権限を持つユーザーのみアクセス可能</li>
                <li>テーマとの競合を避けるため、専用のCSSクラスを使用</li>
            </ul>
        </div>';
    }

    /**
     * Get settings content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_settings_content() {
        return '<div class="ktpwp-reference-section">
            <h2>設定・管理</h2>
            
            <h3>⚙️ 基本設定</h3>
            <p><strong>アクセス方法：</strong>WordPress管理画面 → 「KantanPro」</p>
            
            <h3>🔐 権限管理</h3>
            <ul>
                <li><strong>管理者</strong>：全機能アクセス可能</li>
                <li><strong>編集者</strong>：編集・管理機能アクセス可能</li>
                <li><strong>投稿者</strong>：閲覧・基本編集機能</li>
                <li><strong>購読者</strong>：閲覧のみ</li>
            </ul>
            
            <h3>🗄️ データベース管理</h3>
            <ul>
                <li><strong>自動マイグレーション</strong>：プラグイン有効化時に自動実行</li>
                <li><strong>WP-CLI管理</strong>：コマンドラインでのDB構造管理</li>
                <li><strong>バックアップ</strong>：定期的なデータバックアップを推奨</li>
            </ul>
            
            <h3>📊 パフォーマンス設定</h3>
            <ul>
                <li><strong>ページネーション</strong>：大量データの効率的表示</li>
                <li><strong>キャッシュ</strong>：リファレンス情報のキャッシュ</li>
                <li><strong>画像最適化</strong>：アップロード画像の自動最適化</li>
            </ul>
        </div>';
    }

    /**
     * Get security content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_security_content() {
        return '<div class="ktpwp-reference-section">
            <h2>セキュリティ機能</h2>
            
            <h3>🛡️ 実装済みセキュリティ対策</h3>
            
            <h4>SQLインジェクション防止</h4>
            <ul>
                <li>prepare文の使用</li>
                <li>バインド変数による安全なクエリ実行</li>
                <li>WordPress標準のデータベース関数の活用</li>
            </ul>
            
            <h4>XSS・CSRF対策</h4>
            <ul>
                <li>データのサニタイズ・エスケープ</li>
                <li>ノンス（nonce）によるフォーム保護</li>
                <li>CSRFトークンの検証</li>
            </ul>
            
            <h4>ファイルアップロード検証</h4>
            <ul>
                <li>MIME型の検証</li>
                <li>ファイルサイズの制限</li>
                <li>危険なファイル形式の除外</li>
            </ul>
            
            <h4>権限管理</h4>
            <ul>
                <li>ロールベースアクセス制御</li>
                <li>機能別権限チェック</li>
                <li>安全なデータベースアクセス</li>
            </ul>
            
            <h3>🔒 推奨セキュリティ設定</h3>
            <ul>
                <li>強力なパスワードの使用</li>
                <li>定期的なパスワード変更</li>
                <li>不要なユーザーアカウントの削除</li>
                <li>WordPress本体・プラグインの最新版維持</li>
                <li>SSL証明書の導入</li>
            </ul>
        </div>';
    }

    /**
     * Get troubleshooting content
     *
     * @since 1.0.0
     * @return string HTML content
     */
    private function get_troubleshooting_content() {
        return '<div class="ktpwp-reference-section">
            <h2>トラブルシューティング</h2>
            
            <h3>❓ よくある問題と解決方法</h3>
            
            <h4>Q: ショートコードが表示されない</h4>
            <p><strong>A:</strong></p>
            <ul>
                <li>ログインしているか確認してください</li>
                <li>適切な権限があるか確認してください</li>
                <li>プラグインが有効化されているか確認してください</li>
                <li>テーマとの競合がないか確認してください</li>
            </ul>
            
            <h4>Q: PDF出力ができない</h4>
            <p><strong>A:</strong></p>
            <ul>
                <li>PHP拡張「GD」が有効か確認してください</li>
                <li>サーバーのメモリ制限を確認してください</li>
                <li>一時ファイルの書き込み権限を確認してください</li>
            </ul>
            
            <h4>Q: モバイルで表示が崩れる</h4>
            <p><strong>A:</strong></p>
            <ul>
                <li>ブラウザのキャッシュをクリアしてください</li>
                <li>CSSの競合がないか確認してください</li>
                <li>最新版のプラグインを使用してください</li>
            </ul>
            
            <h4>Q: データベースエラーが発生する</h4>
            <p><strong>A:</strong></p>
            <ul>
                <li>WP-CLIでマイグレーションを実行してください</li>
                <li>データベースの権限を確認してください</li>
                <li>WordPressのデバッグモードを有効にして詳細を確認してください</li>
            </ul>
            
            <h3>🔧 デバッグ方法</h3>
            <ol>
                <li>WordPressのデバッグモードを有効化</li>
                <li>ブラウザの開発者ツールでエラーを確認</li>
                <li>サーバーのエラーログを確認</li>
                <li>プラグインのキャッシュをクリア</li>
            </ol>
            
            <h3>📞 サポート</h3>
            <p>問題が解決しない場合は、以下までご連絡ください：</p>
            <ul>
                <li><strong>公式サイト</strong>: <a href="https://www.kantanpro.com/" target="_blank">https://www.kantanpro.com/</a></li>
                <li><strong>開発者プロフィール</strong>: <a href="https://www.kantanpro.com/developer-profile/" target="_blank">https://www.kantanpro.com/developer-profile/</a></li>
            </ul>
        </div>';
    }

    /**
     * Render modal HTML
     *
     * @since 1.0.0
     * @return string HTML content
     */
    public static function render_modal() {
        $nonce = wp_create_nonce( 'ktpwp_reference_nonce' );
        
        return '<div id="ktpwp-reference-modal" class="ktpwp-modal" style="display: none;">
            <div class="ktpwp-modal-content">
                <div class="ktpwp-modal-header">
                    <h2>KantanPro ヘルプ・リファレンス</h2>
                    <span class="ktpwp-modal-close">&times;</span>
                </div>
                <div class="ktpwp-modal-body">
                    <div class="ktpwp-reference-nav">
                        <button class="ktpwp-nav-btn active" data-section="overview">概要</button>
                        <button class="ktpwp-nav-btn" data-section="tabs">管理タブ</button>
                        <button class="ktpwp-nav-btn" data-section="shortcodes">ショートコード</button>
                        <button class="ktpwp-nav-btn" data-section="settings">設定</button>
                        <button class="ktpwp-nav-btn" data-section="security">セキュリティ</button>
                        <button class="ktpwp-nav-btn" data-section="troubleshooting">トラブルシューティング</button>
                    </div>
                    <div id="ktpwp-reference-content">
                        <!-- コンテンツがここに動的に読み込まれます -->
                    </div>
                </div>
                <div class="ktpwp-modal-footer">
                    <button id="ktpwp-clear-cache" class="ktpwp-btn">キャッシュクリア</button>
                    <span class="ktpwp-version">v' . esc_html(KANTANPRO_PLUGIN_VERSION) . '</span>
                </div>
            </div>
        </div>';
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
     * Plugin activation hook
     *
     * @since 1.0.0
     * @return void
     */
    public static function on_plugin_activation() {
        // Set flag to refresh reference content
        update_option( 'ktpwp_reference_needs_refresh', true );
        
        // Set initial metadata
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Reference: Plugin activation - reference cache marked for refresh' );
        }
    }

    /**
     * Clear all reference cache
     *
     * @since 1.0.0
     * @return void
     */
    public static function clear_all_cache() {
        $sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting' );
        
        foreach ( $sections as $section ) {
            delete_transient( "ktpwp_reference_content_{$section}" );
        }
        
        delete_transient( 'ktpwp_reference_cache' );
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Reference: All cache cleared' );
        }
    }
}

} // End if class_exists check
