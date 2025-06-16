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
            'version' => get_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION )
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

        // Clear all reference cache
        $sections = array( 'overview', 'shortcodes', 'templates', 'customize', 'faq', 'advanced', 'support' );
        
        foreach ( $sections as $section ) {
            delete_transient( "ktpwp_reference_content_{$section}" );
        }
        
        // Clear main cache
        delete_transient( 'ktpwp_reference_cache' );
        
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
            . '<h3>KTPWPプラグイン リファレンス</h3>'
            . '<p>KTPWPは、WordPress上で動作する包括的なビジネス管理プラグインです。受注処理、顧客管理、サービス管理、仕入先管理、レポート機能、各種設定までを一元管理できます。</p>'
            . '<h4>主な機能</h4>'
            . '<ul>'
            . '<li><b>7つの管理タブで完全なワークフロー管理</b></li>'
            . '<li>受注案件の進捗管理とステータス追跡</li>'
            . '<li>受注書作成・編集・印刷・請求項目管理</li>'
            . '<li>顧客情報管理・注文履歴表示・印刷テンプレート</li>'
            . '<li>サービス・商品マスター管理・価格設定</li>'
            . '<li>仕入先・外注先情報管理・支払条件設定</li>'
            . '<li>売上分析・進捗状況・データ集計</li>'
            . '<li>宛名印刷テンプレート・システム設定</li>'
            . '</ul>'
            . '<h4>主要機能詳細</h4>'
            . '<ul>'
            . '<li><b>受注管理機能</b><br>6段階の進捗管理（受付中→見積中→作成中→完成未請求→請求済→入金済）、プロジェクト名・顧客情報・担当者・金額・進捗の一元管理、受注書の作成・編集・プレビュー・印刷機能、請求項目とコスト項目の詳細管理、スタッフ間のチャット機能</li>'
            . '<li><b>顧客管理機能</b><br>会社名・担当者・連絡先・住所情報の管理、締め日・支払月・支払日・支払方法の設定、顧客別注文履歴の表示、宛名印刷テンプレートの活用、対象・対象外ステータス管理</li>'
            . '<li><b>サービス管理機能</b><br>サービス名・価格・単位・カテゴリーの管理、頻度データによる利用状況の把握、カテゴリー別分類とソート機能、受注書への簡単なサービス追加</li>'
            . '<li><b>仕入先管理機能</b><br>協力会社・外注先の詳細情報管理、支払条件・税区分の設定、代表者名・連絡先・住所情報、カテゴリー分類と検索機能</li>'
            . '<li><b>レポート機能</b><br>売上推移グラフ、進捗別受注件数の集計、顧客別売上分析、月次・年次レポート</li>'
            . '<li><b>印刷・テンプレート機能</b><br>受注書の印刷レイアウト、宛名印刷テンプレート（郵便番号、住所、会社名等の置換機能）、プレビュー機能で印刷前確認、カスタマイズ可能なテンプレート</li>'
            . '</ul>'
            . '<h4>セキュリティ対策</h4>'
            . '<ul>'
            . '<li>SQLインジェクション防止</li>'
            . '<li>XSS（クロスサイトスクリプティング）保護</li>'
            . '<li>CSRF（クロスサイトリクエストフォージェリ）対策</li>'
            . '<li>ファイルアップロードの検証</li>'
            . '<li>ユーザー権限の適切な制御</li>'
            . '<li>データベースアクセスの安全な処理</li>'
            . '</ul>'
            . '<h4>操作性・利便性</h4>'
            . '<ul>'
            . '<li>直感的なタブ型インターフェース</li>'
            . '<li>ページネーション機能で大量データも快適</li>'
            . '<li>ソート機能（ID、名前、日付、カテゴリー、頻度等）</li>'
            . '<li>検索・フィルタリング機能</li>'
            . '<li>リアルタイムプレビュー</li>'
            . '<li>レスポンシブデザイン対応</li>'
            . '</ul>'
            . '<h4>インストール</h4>'
            . '<ol>'
            . '<li>プラグインファイルを <code>/wp-content/plugins/</code> ディレクトリにアップロード、または管理画面「プラグイン」→「新規追加」からインストール</li>'
            . '<li>WordPressの「プラグイン」画面でKTPWPを有効化</li>'
            . '<li>新しい固定ページを作成し、<code>[ktpwp_all_tab]</code> または <code>[kantanAllTab]</code> を挿入</li>'
            . '<li>管理画面「KTPWP設定」から基本設定</li>'
            . '</ol>'
            . '<h4>使い方</h4>'
            . '<ol>'
            . '<li>固定ページに <code>[ktpwp_all_tab]</code> を挿入すると、7つのタブが表示されます</li>'
            . '<li>「得意先」タブから顧客情報を登録</li>'
            . '<li>「サービス」タブからサービス・商品を登録</li>'
            . '<li>「伝票処理」タブから新規受注書を作成し、顧客とサービスを選択</li>'
            . '<li>「仕事リスト」タブで案件の進捗を管理</li>'
            . '</ol>'
            . '<h4>進捗ステータス</h4>'
            . '<ol>'
            . '<li>受付中 - 新規受注、内容確認中</li>'
            . '<li>見積中 - 見積作成・提案中</li>'
            . '<li>作成中 - 作業実行中</li>'
            . '<li>完成未請求 - 作業完了、請求書未発行</li>'
            . '<li>請求済 - 請求書発行済み</li>'
            . '<li>入金済 - 支払い完了</li>'
            . '</ol>'
            . '<h4>印刷機能</h4>'
            . '<ul>'
            . '<li>受注書印刷：「伝票処理」タブでプレビュー・印刷が可能</li>'
            . '<li>宛名印刷：「設定」タブでテンプレートを設定し、顧客情報を自動置換</li>'
            . '</ul>'
            . '<h4>データの管理</h4>'
            . '<ul>'
            . '<li>ソート機能：各リストはID、名前、日付、カテゴリー等でソート可能</li>'
            . '<li>検索機能：各タブで条件検索が可能</li>'
            . '<li>ページネーション：大量データも快適に閲覧</li>'
            . '<li>削除管理：対象外設定で論理削除による安全な管理</li>'
            . '</ul>'
            . '<h4>よくある質問</h4>'
            . '<ul>'
            . '<li><b>Q: このプラグインは安全ですか？</b><br>A: はい。SQLインジェクション防止、XSS保護、CSRF対策、ファイルアップロード検証など、包括的なセキュリティ対策が実装されています。</li>'
            . '<li><b>Q: 既存のWordPressテーマに影響しますか？</b><br>A: いいえ。プラグイン専用のクラス名とCSSを使用しており、テーマとの競合を避ける設計になっています。</li>'
            . '<li><b>Q: データのバックアップは必要ですか？</b><br>A: はい。重要なビジネスデータを扱うため、定期的なWordPressデータベースのバックアップを推奨します。</li>'
            . '<li><b>Q: モバイルデバイスで使用できますか？</b><br>A: はい。レスポンシブデザインに対応しており、スマートフォンやタブレットでも使用できます。</li>'
            . '<li><b>Q: 複数のユーザーで使用できますか？</b><br>A: はい。WordPressのユーザー権限機能を活用し、適切な権限を持つユーザーのみがアクセスできます。</li>'
            . '<li><b>Q: データのエクスポートは可能ですか？</b><br>A: レポート機能で集計データの出力が可能です。詳細なエクスポート機能は今後のアップデートで提供予定です。</li>'
            . '<li><b>Q: 受注書のレイアウトはカスタマイズできますか？</b><br>A: はい。設定タブでテンプレートの編集が可能です。HTML/CSSの知識があればより詳細なカスタマイズができます。</li>'
            . '<li><b>Q: 税込み・税抜きの計算はどうなりますか？</b><br>A: 仕入先には税区分の設定があり、適切な税計算が行われます。詳細は設定画面で確認してください。</li>'
            . '</ul>'
            . '<h4>サポート・システム要件</h4>'
            . '<ul>'
            . '<li>公式サイト: <a href="https://www.kantanpro.com/" target="_blank">https://www.kantanpro.com/</a></li>'
            . '<li>WordPress 5.0 以上 / PHP 7.4 以上 / MySQL 5.6 以上 または MariaDB 10.0 以上 / 推奨メモリ: 256MB 以上</li>'
            . '</ul>'
            . '<h4>アップグレード通知</h4>'
            . '<ul>'
            . '<li>1.0.7 (beta): ベータ版リリース。UI/UXを改善しました。</li>'
            . '<li>1.0.0: ベータ版リリース。プラグインリファレンス機能が追加されました。セキュリティとパフォーマンスが大幅に向上。全機能が本番環境で安定稼働。新機能のヘルプ・リファレンスモーダルを活用してください。</li>'
            . '<li>beta: 最初の本格リリース版。全機能が利用可能。本番環境での使用に最適化。</li>'
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
    . '<p>KTPWPは7つのタブで構成されており、ビジネスプロセス全体をカバーします。</p>'
    . '<div class="ktpwp-tabs-explanation">'
    . '<div class="ktpwp-tab-item"><h4>1. 仕事リスト</h4><ul>'
    . '<li>受注案件の進捗管理とステータス追跡</li>'
    . '<li>6段階の進捗（受付中→見積中→作成中→完成未請求→請求済→入金済）</li>'
    . '<li>プロジェクト名・顧客情報・担当者・金額・進捗の一元管理</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>2. 伝票処理</h4><ul>'
    . '<li>受注書作成・編集・印刷・請求項目管理</li>'
    . '<li>受注書のプレビュー・印刷機能</li>'
    . '<li>請求項目とコスト項目の詳細管理</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>3. 得意先</h4><ul>'
    . '<li>顧客情報管理・注文履歴表示・印刷テンプレート</li>'
    . '<li>会社名・担当者・連絡先・住所情報の管理</li>'
    . '<li>締め日・支払月・支払日・支払方法の設定</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>4. サービス</h4><ul>'
    . '<li>サービス・商品マスター管理・価格設定</li>'
    . '<li>サービス名・価格・単位・カテゴリーの管理</li>'
    . '<li>頻度データによる利用状況の把握</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>5. 協力会社</h4><ul>'
    . '<li>仕入先・外注先情報管理・支払条件設定</li>'
    . '<li>協力会社・外注先の詳細情報管理</li>'
    . '<li>支払条件・税区分の設定</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>6. レポート</h4><ul>'
    . '<li>売上分析・進捗状況・データ集計</li>'
    . '<li>売上推移グラフ・進捗別受注件数の集計</li>'
    . '<li>顧客別売上分析・月次・年次レポート</li>'
    . '</ul></div>'
    . '<div class="ktpwp-tab-item"><h4>7. 設定</h4><ul>'
    . '<li>宛名印刷テンプレート・システム設定</li>'
    . '<li>印刷テンプレートのカスタマイズ</li>'
    . '<li>会社情報・税率・メール設定</li>'
    . '</ul></div>'
    . '</div>'
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
            . '<h3>' . esc_html__( 'ショートコード使用方法', 'ktpwp' ) . '</h3>'
            . '<div class="ktpwp-shortcode-explanation">'
            . '<h4>' . esc_html__( '基本ショートコード', 'ktpwp' ) . '</h4>'
            . '<p>' . esc_html__( 'メインのプラグイン機能を表示するには、以下のショートコードを使用してください：', 'ktpwp' ) . '</p>'
            . '<code style="background: #f5f5f5; padding: 12px; border-radius: 4px; display: block; margin: 12px 0; font-size: 16px;">[ktpwp_all_tab]</code>'
            . '<h4>' . esc_html__( '設置方法', 'ktpwp' ) . '</h4>'
            . '<ol>'
            . '<li>' . esc_html__( 'WordPress管理画面で「固定ページ」→「新規追加」をクリック', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ページタイトルを入力（例：「ワークフロー管理」）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'エディタに [ktpwp_all_tab] を挿入', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '「公開」または「更新」をクリック', 'ktpwp' ) . '</li>'
            . '</ol>'
            . '<h4>' . esc_html__( '注意事項', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'ログインユーザーのみがプラグイン機能にアクセス可能です', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ページのパーマリンクは覚えやすいものに設定することをお勧めします', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ページのテンプレートは「デフォルト」または「全幅」を推奨します', 'ktpwp' ) . '</li>'
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
            . '<h3>' . esc_html__( '設定ガイド', 'ktpwp' ) . '</h3>'
            . '<div class="ktpwp-settings-guide">'
            . '<p>' . esc_html__( 'プラグインの設定は管理画面から行えます。', 'ktpwp' ) . '</p>'
            . '<p><a href="' . esc_url( $settings_url ) . '" target="_blank" style="color: #0073aa;">' . esc_html__( '→ 設定ページを開く', 'ktpwp' ) . '</a></p>'
            . '<h4>' . esc_html__( '一般設定', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( '会社情報の登録', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '表示件数の設定', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '<h4>' . esc_html__( 'メール・SMTP設定', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'メール送信者アドレス設定', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'SMTP サーバー設定', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'テストメール送信機能', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '<h4>' . esc_html__( 'ライセンス設定', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'アクティベーションキーの入力', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '有償機能の有効化', 'ktpwp' ) . '</li>'
            . '</ul>'
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
            . '<h3>' . esc_html__( 'セキュリティ機能', 'ktpwp' ) . '</h3>'
            . '<div class="ktpwp-security-features">'
            . '<p>' . esc_html__( 'KTPWPプラグインは以下のセキュリティ対策を実装しています：', 'ktpwp' ) . '</p>'
            . '<h4>' . esc_html__( '実装済みセキュリティ機能', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'SQLインジェクション防止（準備文・バインド変数使用）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'XSS攻撃防止（データサニタイズ・エスケープ処理）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'CSRF攻撃防止（WordPressノンス検証）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ファイルアップロード検証', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ログインユーザー限定アクセス', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'REST API アクセス制限', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'HTTPセキュリティヘッダー設定', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '<h4>' . esc_html__( 'セキュリティのベストプラクティス', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'WordPress本体を最新版に保つ', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '強固なパスワードを使用する', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '不要なユーザーアカウントを削除する', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '定期的にバックアップを取る', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'SSL証明書を導入する', 'ktpwp' ) . '</li>'
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
            . '<h3>' . esc_html__( 'トラブルシューティング', 'ktpwp' ) . '</h3>'
            . '<div class="ktpwp-troubleshooting">'
            . '<h4>' . esc_html__( 'よくある問題と解決方法', 'ktpwp' ) . '</h4>'
            . '<div class="ktpwp-faq-item">'
            . '<h5>' . esc_html__( 'Q: ショートコードを挿入してもプラグインが表示されない', 'ktpwp' ) . '</h5>'
            . '<p><strong>' . esc_html__( 'A:', 'ktpwp' ) . '</strong></p>'
            . '<ul>'
            . '<li>' . esc_html__( 'ログインしているかを確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'プラグインが有効化されているかを確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ショートコードが正しく記述されているかを確認してください：[ktpwp_all_tab]', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '</div>'
            . '<div class="ktpwp-faq-item">'
            . '<h5>' . esc_html__( 'Q: データが保存されない', 'ktpwp' ) . '</h5>'
            . '<p><strong>' . esc_html__( 'A:', 'ktpwp' ) . '</strong></p>'
            . '<ul>'
            . '<li>' . esc_html__( 'データベースの権限を確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'PHPのメモリ制限を確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'プラグインを一度無効化して再有効化してください', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '</div>'
            . '<div class="ktpwp-faq-item">'
            . '<h5>' . esc_html__( 'Q: PDF出力ができない', 'ktpwp' ) . '</h5>'
            . '<p><strong>' . esc_html__( 'A:', 'ktpwp' ) . '</strong></p>'
            . '<ul>'
            . '<li>' . esc_html__( 'サーバーのPHP拡張機能を確認してください（mbstring, gd等）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'ブラウザのポップアップブロックを無効にしてください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '会社情報の設定が完了しているかを確認してください', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '</div>'
            . '<div class="ktpwp-faq-item">'
            . '<h5>' . esc_html__( 'Q: タブが正しく表示されない', 'ktpwp' ) . '</h5>'
            . '<p><strong>' . esc_html__( 'A:', 'ktpwp' ) . '</strong></p>'
            . '<ul>'
            . '<li>' . esc_html__( 'ブラウザのキャッシュをクリアしてください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'テーマとの競合がないかを確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( '他のプラグインとの競合がないかを確認してください', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '</div>'
            . '<div class="ktpwp-faq-item">'
            . '<h5>' . esc_html__( 'Q: メール送信ができない', 'ktpwp' ) . '</h5>'
            . '<p><strong>' . esc_html__( 'A:', 'ktpwp' ) . '</strong></p>'
            . '<ul>'
            . '<li>' . esc_html__( 'SMTP設定を確認してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'テストメール送信機能を使用してください', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'レンタルサーバーのメール送信制限を確認してください', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '</div>'
            . '<h4>' . esc_html__( 'システム要件の確認', 'ktpwp' ) . '</h4>'
            . '<ul>'
            . '<li>' . esc_html__( 'WordPress 5.0以上', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'PHP 7.4以上（8.0以上推奨）', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'MySQL 5.6以上', 'ktpwp' ) . '</li>'
            . '<li>' . esc_html__( 'メモリ制限: 128MB以上', 'ktpwp' ) . '</li>'
            . '</ul>'
            . '<h4>' . esc_html__( 'デバッグモード', 'ktpwp' ) . '</h4>'
            . '<p>' . esc_html__( '問題が解決しない場合は、wp-config.phpでデバッグモードを有効にしてエラーログを確認してください：', 'ktpwp' ) . '</p>'
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
            . '<h3>' . esc_html__( 'KTPWPプラグインリファレンス', 'ktpwp' ) . '</h3>'
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
        // Clear any cached reference data
        delete_transient( 'ktpwp_reference_cache' );
        
        // Update plugin reference metadata
        update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
        update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
        
        // Log activation event for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグインリファレンスが有効化時に更新されました。' );
        }
        
        // Force regeneration of reference content on next load
        update_option( 'ktpwp_reference_needs_refresh', true );
    }
}

// Initialize the plugin reference only after init (not on plugin load)
add_action('init', function() {
    KTPWP_Plugin_Reference::get_instance();
});

} // End if class_exists
