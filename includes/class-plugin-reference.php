<?php
/**
 * Plugin Reference class for KTPWP plugin
 *
 * KantanPro（KTPWP）の公式リファレンス・ヘルプを提供。
 * - モバイル対応UI、PDF出力、サービス選択ポップアップ、アバター、ヘルプボタン、セキュリティ強化など最新機能を網羅。
 * - 管理タブ・伝票処理・顧客・サービス・協力会社・レポート・チャット等の使い方を解説。
 * - 部署管理機能・利用規約管理機能・自動更新機能の詳細説明を追加。
 * - 動的更新履歴システム・セッション管理最適化などの技術的改善を反映。
 * - バージョンアップ履歴・トラブルシューティングも掲載。
 * - 最新バージョン: 1.0.0(preview)
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

			wp_send_json_success(
                array(
					'content' => $content,
					'section' => $section,
					'last_updated' => get_option( 'ktpwp_reference_last_updated', time() ),
					'version' => KANTANPRO_PLUGIN_VERSION, // 常に最新の定数値を使用
                )
            );
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
			$sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting', 'departments', 'terms', 'changelog' );

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

			wp_send_json_success(
                array(
					'message' => esc_html__( 'キャッシュをクリアしました。', 'ktpwp' ),
					'cleared_at' => current_time( 'mysql' ),
                )
            );
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
				case 'departments':
					$content = $this->get_departments_content();
					break;
				case 'terms':
					$content = $this->get_terms_content();
					break;
				case 'changelog':
					$content = $this->get_changelog_content();
					break;
				default:
					$content = $this->get_overview_content();
					break;
			}

			// Cache the content for 1 hour
			set_transient( $cache_key, $content, HOUR_IN_SECONDS );

			return $content;
		}

		/**
		 * Get overview content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_overview_content() {
			return '<h2>KantanPro プラグイン概要</h2>
			
			<h3>プラグインの特徴</h3>
			<p>KantanProは、WordPress上で以下の業務を一元管理できる多機能プラグインです。</p>
			
			<h4>主要機能</h4>
			<ul>
				<li><strong>6つの管理タブ</strong>（仕事リスト・伝票処理・得意先・サービス・協力会社・レポート）</li>
				<li><strong>受注案件の進捗管理</strong>（7段階：受注→進行中→完了→請求→支払い→ボツ）</li>
				<li><strong>受注書・請求書の作成・編集・PDF保存</strong>（個別・一括出力対応）</li>
				<li><strong>顧客・サービス・協力会社のマスター管理</strong>（検索・ソート・ページネーション）</li>
				<li><strong>スタッフチャット</strong>（自動スクロール・削除連動・安定化）</li>
				<li><strong>モバイル対応UI</strong>（gap→margin対応、iOS/Android実機対応）</li>
				<li><strong>部署管理機能</strong>（顧客ごとの部署・担当者管理）</li>
				<li><strong>利用規約管理機能</strong>（同意ダイアログ・管理画面）</li>
				<li><strong>自動更新機能</strong>（GitHub連携による最新版の自動配信）</li>
				<li><strong>動的更新履歴システム</strong>（データベースベースの管理）</li>
				<li><strong>セキュリティ機能</strong>（XSS/CSRF/SQLi/権限管理/ファイル検証/ノンス/prepare文）</li>
				<li><strong>セッション管理最適化</strong>（REST API・AJAX・内部リクエスト対応）</li>
			</ul>
			
			<h3>システム要件</h3>
			<ul>
				<li>WordPress 5.0 以上</li>
				<li>PHP 7.4 以上</li>
				<li>MySQL 5.6 以上 または MariaDB 10.0 以上</li>
				<li>推奨メモリ: 256MB 以上</li>
				<li>推奨PHP拡張: GD（画像処理用）</li>
			</ul>
			
			<h3>インストール方法</h3>
			<ol>
				<li>プラグインを `/wp-content/plugins/` にアップロード、または管理画面からインストール</li>
				<li>プラグインを有効化（自動マイグレーションが実行されます）</li>
				<li>固定ページに `[ktpwp_all_tab]` を挿入</li>
				<li>管理画面「KantanPro」から基本設定を行ってください</li>
			</ol>';
		}

		/**
		 * Get tabs content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_tabs_content() {
			return '<h2>管理タブの使い方</h2>
			
			<h3>1. 仕事リストタブ</h3>
			<p>受注案件の一覧表示・検索・フィルタリング機能</p>
			<ul>
				<li>進捗状況による絞り込み</li>
				<li>顧客名・案件名での検索</li>
				<li>納期・金額でのソート</li>
				<li>ページネーション機能</li>
			</ul>
			
			<h3>2. 伝票処理タブ</h3>
			<p>受注書・請求書の作成・編集・PDF出力</p>
			<ul>
				<li>サービス選択ポップアップで商品追加</li>
				<li>個別・一括PDF出力</li>
				<li>メール送信機能（ファイル添付対応）</li>
				<li>進捗状況の更新</li>
			</ul>
			
			<h3>3. 得意先タブ</h3>
			<p>顧客情報の管理・部署・担当者管理</p>
			<ul>
				<li>顧客情報の登録・編集・削除</li>
				<li>部署・担当者の追加・管理</li>
				<li>選択された部署の情報を請求書に反映</li>
				<li>顧客ごとのメールアドレス管理</li>
			</ul>
			
			<h3>4. サービスタブ</h3>
			<p>商品・サービスのマスター管理</p>
			<ul>
				<li>商品・サービスの登録・編集・削除</li>
				<li>カテゴリー・価格・単位管理</li>
				<li>検索・ソート・ページネーション</li>
			</ul>
			
			<h3>5. 協力会社タブ</h3>
			<p>協力会社・仕入先の管理</p>
			<ul>
				<li>協力会社情報の登録・編集・削除</li>
				<li>支払い条件の管理</li>
				<li>連絡先情報の管理</li>
			</ul>
			
			<h3>6. レポートタブ</h3>
			<p>売上・進捗のレポート表示</p>
			<ul>
				<li>月別売上レポート</li>
				<li>進捗状況の統計</li>
				<li>グラフ表示機能</li>
			</ul>';
		}

		/**
		 * Get shortcode content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_shortcode_content() {
			return '<h2>ショートコードの使い方</h2>
			
			<h3>メインショートコード</h3>
			<p><code>[ktpwp_all_tab]</code></p>
			<p>6つの管理タブをすべて表示します。固定ページに挿入して使用してください。</p>
			
			<h3>使用例</h3>
			<pre><code>// 固定ページの本文に挿入
[ktpwp_all_tab]</code></pre>
			
			<h3>注意事項</h3>
			<ul>
				<li>ログインが必要です</li>
				<li>適切な権限が必要です</li>
				<li>モバイル対応のレスポンシブデザイン</li>
			</ul>';
		}

		/**
		 * Get settings content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_settings_content() {
			return '<h2>設定・管理機能</h2>
			
			<h3>管理画面での設定</h3>
			<p>WordPress管理画面の「KantanPro」メニューから各種設定が可能です。</p>
			
			<h4>利用可能な設定項目</h4>
			<ul>
				<li><strong>一般設定</strong>：基本設定・システム情報</li>
				<li><strong>メール・SMTP設定</strong>：メール送信設定</li>
				<li><strong>デザイン</strong>：UI・UX設定</li>
				<li><strong>スタッフ管理</strong>：スタッフ情報・権限管理</li>
				<li><strong>ライセンス設定</strong>：ライセンス情報</li>
				<li><strong>利用規約管理</strong>：利用規約の編集・管理</li>
			</ul>
			
			<h3>自動更新機能</h3>
			<p>GitHub連携による最新版の自動配信機能が利用できます。</p>
			<ul>
				<li>プラグイン詳細情報の表示</li>
				<li>セキュリティを重視した更新プロセス</li>
				<li>更新通知の管理画面表示</li>
			</ul>';
		}

		/**
		 * Get security content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_security_content() {
			return '<h2>セキュリティ機能</h2>
			
			<h3>実装されているセキュリティ対策</h3>
			<ul>
				<li>SQLインジェクション防止（prepare文・バインド変数）</li>
				<li>XSS・CSRF対策（サニタイズ・エスケープ・ノンス）</li>
				<li>ファイルアップロード検証（MIME型・サイズ制限）</li>
				<li>権限管理・安全なDBアクセス（ロールベースアクセス制御）</li>
				<li>REST API制限（ログインユーザーのみアクセス可能）</li>
				<li>セッション管理最適化（内部リクエスト・API呼び出し時の自動クローズ）</li>
				<li>gap→margin対応によるUI崩れ防止（iOS/Android実機対応）</li>
			</ul>';
		}

		/**
		 * Get troubleshooting content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_troubleshooting_content() {
			return '<h2>トラブルシューティング</h2>
			
			<h3>よくある問題と解決方法</h3>
			
			<h4>1. プラグインが表示されない</h4>
			<ul>
				<li>ショートコード <code>[ktpwp_all_tab]</code> が正しく挿入されているか確認</li>
				<li>ログイン状態を確認</li>
				<li>適切な権限があるか確認</li>
			</ul>
			
			<h4>2. PDF出力ができない</h4>
			<ul>
				<li>PHP拡張GDが有効になっているか確認</li>
				<li>メモリ制限を確認（推奨：256MB以上）</li>
				<li>一時ファイルの書き込み権限を確認</li>
			</ul>
			
			<h4>3. モバイルで表示が崩れる</h4>
			<ul>
				<li>ブラウザのキャッシュをクリア</li>
				<li>CSSファイルが正しく読み込まれているか確認</li>
				<li>gap→margin対応が適用されているか確認</li>
			</ul>
			
			<h4>4. データベースエラーが発生する</h4>
			<ul>
				<li>プラグインを無効化してから再度有効化</li>
				<li>データベースの権限を確認</li>
				<li>WordPressのデバッグモードを有効化して詳細を確認</li>
			</ul>';
		}

		/**
		 * Get departments content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_departments_content() {
			return '<h2>部署管理機能</h2>
			
			<h3>概要</h3>
			<p>顧客ごとに複数の部署・担当者を管理できる機能です。請求書に部署情報を反映できます。</p>
			
			<h3>使い方</h3>
			
			<h4>1. 部署の追加</h4>
			<ol>
				<li>得意先タブで顧客を選択・編集</li>
				<li>部署管理セクションで部署名・担当者名・メールアドレスを入力</li>
				<li>「追加」ボタンをクリック</li>
			</ol>
			
			<h4>2. 部署の選択</h4>
			<ol>
				<li>部署一覧のチェックボックスをクリック</li>
				<li>選択された部署の情報が請求書に反映されます</li>
				<li>一度に1つの部署のみ選択可能</li>
			</ol>
			
			<h4>3. 部署の編集・削除</h4>
			<ul>
				<li>部署名・担当者名・メールアドレスは編集可能</li>
				<li>「削除」ボタンで部署を削除</li>
				<li>削除時は確認ダイアログが表示されます</li>
			</ul>
			
			<h3>請求書への反映</h3>
			<ul>
				<li>選択された部署の情報が請求書の宛先に反映</li>
				<li>部署名・担当者名が適切に表示</li>
				<li>メール送信時も選択された部署のメールアドレスが使用</li>
			</ul>
			
			<h3>注意事項</h4>
			<ul>
				<li>部署名は空欄でも登録可能</li>
				<li>担当者名・メールアドレスは必須</li>
				<li>部署の削除は取り消しできません</li>
				<li>選択された部署が削除された場合、選択状態はリセットされます</li>
			</ul>';
		}

		/**
		 * Get terms content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_terms_content() {
			return '<h2>利用規約管理機能</h2>
			
			<h3>概要</h3>
			<p>利用規約の同意ダイアログ表示と管理画面での編集機能を提供します。</p>
			
			<h3>機能</h3>
			<ul>
				<li>利用規約の同意ダイアログ表示（ログイン済みユーザーのみ）</li>
				<li>管理画面での利用規約編集</li>
				<li>利用規約バージョン管理</li>
				<li>同意状態の追跡</li>
				<li>ショートコード設置ページでのみ表示</li>
			</ul>
			
			<h3>管理画面での設定</h3>
			<ol>
				<li>WordPress管理画面 → KantanPro → 利用規約管理</li>
				<li>利用規約の内容を編集</li>
				<li>バージョン情報を設定</li>
				<li>保存ボタンをクリック</li>
			</ol>
			
			<h3>表示条件</h3>
			<ul>
				<li>ログイン済みユーザーのみ表示</li>
				<li>ショートコード設置ページでのみ表示</li>
				<li>管理画面では表示されません</li>
			</ul>';
		}

		/**
		 * Get changelog content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_changelog_content() {
			return '<h2>動的更新履歴システム</h2>
			
			<h3>概要</h3>
			<p>データベースベースの更新履歴管理システムです。管理画面から更新履歴を編集できます。</p>
			
			<h3>機能</h3>
			<ul>
				<li>データベースベースの更新履歴管理</li>
				<li>管理画面からの更新履歴編集機能</li>
				<li>リアルタイムでの更新履歴表示</li>
				<li>最大20エントリまで保持</li>
			</ul>
			
			<h3>管理画面での編集</h3>
			<ol>
				<li>WordPress管理画面 → KantanPro → 更新履歴管理</li>
				<li>「新しいエントリを追加」ボタンで新しい更新履歴を追加</li>
				<li>既存エントリの「編集」「削除」ボタンで管理</li>
				<li>「デフォルトにリセット」で初期状態に戻す</li>
			</ol>
			
			<h3>API機能</h3>
			<ul>
				<li><code>ktpwp_add_changelog_entry()</code> - エントリ追加</li>
				<li><code>ktpwp_remove_changelog_entry()</code> - エントリ削除</li>
				<li><code>ktpwp_update_changelog_entry()</code> - エントリ更新</li>
				<li><code>ktpwp_reset_changelog()</code> - デフォルトにリセット</li>
			</ul>
			
			<h3>セキュリティ機能</h3>
			<ul>
				<li>権限チェック（管理者のみ）</li>
				<li>nonce認証</li>
				<li>データサニタイゼーション</li>
			</ul>';
		}

		/**
		 * Render modal HTML
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function render_modal() {
			// Modal HTML is now rendered in main ktpwp.php file
			// This method is kept for backward compatibility
		}

		/**
		 * Add modal HTML to footer
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function add_modal_html() {
			// Modal HTML is now rendered in main ktpwp.php file
			// This method is kept for backward compatibility
		}

		/**
		 * Plugin activation hook
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function on_plugin_activation() {
			// Set flag to refresh reference cache on next load
			update_option( 'ktpwp_reference_needs_refresh', true );
		}

		/**
		 * Clear all reference cache
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function clear_all_cache() {
			$sections = array( 'overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting', 'departments', 'terms', 'changelog' );

			foreach ( $sections as $section ) {
				delete_transient( "ktpwp_reference_content_{$section}" );
			}

			delete_transient( 'ktpwp_reference_cache' );
			update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
		}
	}
}
