<?php
/**
 * Plugin Reference class for KTPWP plugin
 *
 * KantanPro（KTPWP）の公式リファレンス・ヘルプを提供。
 * - モバイル対応UI、PDF出力、サービス選択ポップアップ、アバター、ヘルプボタン、セキュリティ強化など最新機能を網羅。
 * - 管理タブ・伝票処理・顧客・サービス・協力会社・レポート・チャット等の使い方を解説。
 * - 部署管理機能・利用規約管理機能の詳細説明を追加。
 * - 利用規約の表示対象を修正（より適切な対象への変更）。
 * - セッション管理最適化などの技術的改善を反映。
 * - ページネーション機能・ファイル添付機能・完了日自動設定機能・納期警告機能・商品管理機能を追加。
 * - シンプル更新システムを実装。
 * - 寄付機能（Stripe決済・進捗管理・自動メール送信）を追加。
 * - バージョンアップ履歴・トラブルシューティングも掲載。
 * - 最新バージョン: 1.0.7(a) - 2025年8月1日更新
 * - 部署選択エラーの修正（部署選択時のエラーハンドリング強化・部署データの整合性チェック機能追加・部署選択UIの安定性向上・部署関連のデータベース処理最適化・部署管理機能の全体的な改善）
 * - パフォーマンスの向上（キャッシュシステム最適化・データベースクエリ効率化・メモリ使用量最適化・画像最適化・JavaScript・CSS最適化・セッション管理最適化・Ajax通信最適化・フック管理最適化）
 * - 受注書のコスト項目発注メールの改善（適格請求書対応・詳細な税額内訳・計算根拠明示）
 * - 受注書のコスト項目更新処理の改善（リアルタイム保存・値変更検知・エラーハンドリング強化）
 * - 請求書プレビューが閉じない問題を修正
 * - 進捗アイコンのSVG化（仕事リストタブの進捗フィルターアイコンをSVG化）
 * - 消費税対応の改善（税率計算・表示の最適化）
 * - アイコンのSVG化（Material SymbolsからSVGアイコンへの完全移行）
 * - 協力会社職能選択の改善
 * - パフォーマンスの改善
 * - キャッシュシステムの最適化
 * - URL生成問題１−１の修正（動的パーマリンク取得による環境非依存のURL生成）
 * - モバイル表示の改善（レスポンシブデザイン強化・タッチ操作最適化・モバイル専用UI改善）
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.2.2
 * @author Kantan Pro
 * @copyright 2025 Kantan Pro
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

			// SVGアイコンに置換
			if (class_exists('KTPWP_SVG_Icons')) {
				$reference_icon = KTPWP_SVG_Icons::get_icon('help', array('style' => 'font-size: 20px; vertical-align: middle;'));
			}

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
			return '
			<h2>KantanPro プラグイン概要</h2>
			<p>KantanProは、WordPress上で業務管理・受注進捗・請求・顧客・サービス・協力会社・レポート・スタッフチャットまで一元管理できる多機能プラグインです。</p>
			
			<h3>主要機能</h3>
			<ul>
				<li><strong>6つの管理タブ</strong>：仕事リスト・伝票処理・顧客・サービス・協力会社・レポート</li>
				<li><strong>受注案件の進捗管理</strong>：7段階（受注→進行中→完了→請求→支払い→ボツ→見積中）</li>
				<li><strong>受注書・請求書の作成・編集・PDF保存</strong>：個別・一括出力対応</li>
				<li><strong>顧客・サービス・協力会社のマスター管理</strong>：検索・ソート・ページネーション</li>
				<li><strong>スタッフチャット</strong>：自動スクロール・削除連動・AJAX送信・キーボードショートカット</li>
				<li><strong>モバイル対応UI</strong>：gap→margin対応、iOS/Android実機対応、タッチ操作最適化</li>
				<li><strong>部署管理機能</strong>：顧客ごとの部署・担当者管理</li>
				<li><strong>利用規約管理機能</strong>：同意ダイアログ・管理画面・バージョン管理・表示対象の最適化</li>
				<li><strong>シンプル更新システム</strong>：WordPress標準の更新システムに最適化</li>
				<li><strong>セキュリティ機能</strong>：XSS/CSRF/SQLi/権限管理/ファイル検証/ノンス/prepare文</li>
				<li><strong>セッション管理最適化</strong>：REST API・AJAX・内部リクエスト対応</li>
				<li><strong>ページネーション機能</strong>：全タブ・ポップアップ対応・一般設定連携</li>
				<li><strong>ファイル添付機能</strong>：ドラッグ&ドロップ・複数ファイル・自動クリーンアップ</li>
				<li><strong>完了日自動設定機能</strong>：進捗変更時の自動処理</li>
				<li><strong>納期警告機能</strong>：期限管理・アラート表示</li>
				<li><strong>商品管理機能</strong>：価格・数量・単位管理・データマイグレーション対応</li>
				<li><strong>スタッフアバター表示機能</strong>：ログイン中スタッフの可視化</li>
				<li><strong>プラグインリファレンス機能</strong>：包括的なヘルプシステム</li>
				<li><strong>寄付機能</strong>：Stripe決済・セキュアな決済処理・進捗管理・自動メール送信</li>
				<li><strong>自動マイグレーション機能</strong>：データベース更新の安定化</li>
				<li><strong>データベース整合性チェック機能</strong>：データ品質向上</li>
				<li><strong>消費税対応機能</strong>：軽減税率・税区分対応の強化</li>
				<li><strong>パフォーマンス最適化機能</strong>：キャッシュ・クエリ最適化・メモリ効率化</li>
				<li><strong>協力会社職能選択の改善</strong>：ページネーション・検索・ソート機能の強化</li>
				<li><strong>部署選択エラーの修正</strong>：部署選択時のエラーハンドリング強化・部署データの整合性チェック機能追加・部署選択UIの安定性向上・部署関連のデータベース処理最適化・部署管理機能の全体的な改善</li>
				<li><strong>マイグレーション中にエラー解消</strong>：データベース更新処理の安定化・エラーハンドリング強化</li>
				<li><strong>モバイル表示の改善</strong>：レスポンシブデザイン強化・タッチ操作最適化・モバイル専用UI改善</li>
				<li><strong>KantanPro License Managerプラグインの連携１−１</strong>：ライセンス管理システムとの統合・ライセンス検証機能・ライセンス情報管理・レート制限機能・セキュアなAPI通信</li>
				<li><strong>管理メニュータブの廃止</strong>：管理画面のメニュー構造の簡素化・設定項目の統合・最適化・ユーザビリティの向上</li>
			</ul>
			
			<h3>モバイル表示の改善（最新機能）</h3>
			<ul>
				<li><strong>レスポンシブデザインの強化</strong>：ブレークポイント最適化・フレキシブルレイアウト</li>
				<li><strong>タッチ操作の最適化</strong>：タップターゲットサイズ拡大・スワイプジェスチャー対応</li>
				<li><strong>モバイル専用UI改善</strong>：ナビゲーション・ボタン配置・フォントサイズ調整</li>
				<li><strong>パフォーマンス最適化</strong>：モバイル向け画像最適化・CSS/JS軽量化</li>
				<li><strong>アクセシビリティ向上</strong>：スクリーンリーダー対応・キーボードナビゲーション改善</li>
			</ul>
			
			<h3>協力会社職能選択の改善</h3>
			<ul>
				<li><strong>ページネーション対応</strong>：大量の職能データの効率的な表示</li>
				<li><strong>検索・ソート機能</strong>：職能名・単価・頻度での絞り込み・並び替え</li>
				<li><strong>税率対応</strong>：軽減税率・税区分の管理</li>
				<li><strong>レスポンシブデザイン</strong>：モバイル・タブレット対応</li>
				<li><strong>リアルタイム更新</strong>：職能データの動的読み込み</li>
				<li><strong>ユーザビリティ向上</strong>：直感的な選択インターフェース</li>
			</ul>
			
			<h3>基本的な使い方</h3>
			<ol>
				<li><strong>固定ページに <code>[ktpwp_all_tab]</code> を挿入</strong>すると、6つのタブが表示されます</li>
				<li><strong>各タブで顧客・サービス・協力会社・受注書・レポート等を管理</strong></li>
				<li><strong>伝票編集時は「サービス選択」ポップアップから商品を追加</strong></li>
				<li><strong>受注書・請求書はPDFで出力可能</strong>（個別・一括対応）</li>
				<li><strong>モバイルでも快適に操作可能</strong>（レスポンシブデザイン・タッチ操作最適化）</li>
				<li><strong>スタッフチャットでリアルタイムコミュニケーション</strong>（Ctrl+Enter送信対応）</li>
				<li><strong>ヘルプボタンで使用方法を確認</strong></li>
				<li><strong>顧客管理で部署・担当者を管理</strong></li>
				<li><strong>利用規約に同意してから利用開始</strong>（表示対象の最適化）</li>
				<li><strong>ファイル添付でメール送信も可能</strong></li>
				<li><strong>寄付機能でプラグイン開発をサポート</strong></li>
				<li><strong>ライセンス管理システムでプラグインの利用を管理</strong></li>
				<li><strong>管理画面のメニュー構造が簡素化されました</strong></li>
			</ol>
			
			<h3>システム要件</h3>
			<ul>
				<li>WordPress 5.0 以上（推奨：最新版）</li>
				<li>PHP 7.4 以上（推奨：PHP 8.0以上）</li>
				<li>MySQL 5.6 以上 または MariaDB 10.0 以上</li>
				<li>推奨メモリ: 256MB 以上</li>
				<li>推奨PHP拡張: GD（画像処理用）</li>
			</ul>
			';
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
				<li>納期警告マーク表示</li>
				<li>完了日自動設定</li>
			</ul>
			
			<h3>2. 伝票処理タブ</h3>
			<p>受注書・請求書の作成・編集・PDF出力</p>
			<ul>
				<li>サービス選択ポップアップで商品追加（ページネーション対応）</li>
				<li>個別・一括PDF出力</li>
				<li>メール送信機能（ファイル添付対応）</li>
				<li>進捗状況の更新</li>
				<li>スタッフチャット機能（自動スクロール・AJAX送信）</li>
			</ul>
			
			<h3>3. 顧客タブ</h3>
			<p>顧客情報の管理・部署・担当者管理</p>
			<ul>
				<li>顧客情報の登録・編集・削除</li>
				<li>部署・担当者の追加・管理</li>
				<li>選択された部署の情報を請求書に反映</li>
				<li>顧客ごとのメールアドレス管理</li>
				<li>ページネーション対応</li>
			</ul>
			
			<h3>4. サービスタブ</h3>
			<p>商品・サービスのマスター管理</p>
			<ul>
				<li>商品・サービスの登録・編集・削除</li>
				<li>カテゴリー・価格・数量・単位管理</li>
				<li>検索・ソート・ページネーション</li>
				<li>小数点対応の価格設定</li>
			</ul>
			
			<h3>5. 協力会社タブ</h3>
			<p>協力会社・仕入先の管理</p>
			<ul>
				<li>協力会社情報の登録・編集・削除</li>
				<li>商品・サービス管理（価格・数量・単位）</li>
				<li>支払い条件の管理</li>
				<li>連絡先情報の管理</li>
				<li>ページネーション対応</li>
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
				<li><strong>一般設定</strong>：基本設定・システム情報・表示件数設定</li>
				<li><strong>メール・SMTP設定</strong>：メール送信設定・ファイル添付設定</li>
				<li><strong>デザイン</strong>：UI・UX設定・カスタムCSS</li>
				<li><strong>スタッフ管理</strong>：スタッフ情報・権限管理</li>
				<li><strong>ライセンス設定</strong>：ライセンス情報・ライセンス検証・ライセンス管理</li>
				<li><strong>管理メニュー最適化</strong>：メニュー構造の簡素化・設定項目の統合</li>
				<li><strong>利用規約管理</strong>：利用規約の編集・管理・表示対象の最適化</li>
			</ul>
			
			<h3>自動更新機能</h3>
			<p>GitHub連携による最新版の自動配信機能が利用できます。</p>
			<ul>
				<li>プラグイン詳細情報の表示</li>
				<li>セキュリティを重視した更新プロセス</li>
				<li>更新通知の管理画面表示</li>
			</ul>
			
			<h3>ページネーション設定</h3>
			<p>一般設定の「仕事リスト表示件数」で全タブの表示件数を制御できます。</p>
			<ul>
				<li>仕事リスト・顧客・サービス・協力会社タブに適用</li>
				<li>サービス選択ポップアップにも適用</li>
				<li>レスポンシブデザイン対応</li>
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
				<li>ファイルアップロード検証（MIME型・サイズ制限・自動クリーンアップ）</li>
				<li>権限管理・安全なDBアクセス（ロールベースアクセス制御）</li>
				<li>REST API制限（ログインユーザーのみアクセス可能）</li>
				<li>セッション管理最適化（内部リクエスト・API呼び出し時の自動クローズ）</li>
				<li>gap→margin対応によるUI崩れ防止（iOS/Android実機対応）</li>
			</ul>
			
			<h3>ファイル添付セキュリティ</h3>
			<ul>
				<li>許可されたファイル形式のみ受付（PDF、画像、Office文書、圧縮ファイル）</li>
				<li>ファイルサイズ制限（1ファイル10MB、合計50MB）</li>
				<li>自動クリーンアップによる一時ファイル管理</li>
				<li>MIME型検証による偽装ファイル防止</li>
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
			</ul>
			
			<h4>5. ファイル添付ができない</h4>
			<ul>
				<li>ファイルサイズが制限内か確認（1ファイル10MB、合計50MB）</li>
				<li>対応ファイル形式か確認（PDF、画像、Office文書、圧縮ファイル）</li>
				<li>サーバーの一時ディレクトリの書き込み権限を確認</li>
			</ul>
			
			<h4>6. ページネーションが動作しない</h4>
			<ul>
				<li>一般設定で表示件数が正しく設定されているか確認</li>
				<li>JavaScriptエラーがないかブラウザのコンソールを確認</li>
				<li>キャッシュプラグインを無効化してテスト</li>
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
			<p>顧客ごとに複数の部署・担当者を管理できる機能です。</p>
			
			<h3>機能</h3>
			<ul>
				<li>顧客ごとに複数の部署・担当者を管理</li>
				<li>部署ごとのメールアドレス管理</li>
				<li>選択された部署の情報を請求書に反映</li>
				<li>部署の追加・編集・削除機能</li>
				<li>AJAX対応のリアルタイム更新</li>
			</ul>
			
			<h3>使用方法</h3>
			<ol>
				<li>顧客タブで顧客を選択</li>
				<li>「部署管理」セクションで部署を追加</li>
				<li>部署名・担当者名・メールアドレスを入力</li>
				<li>「追加」ボタンで保存</li>
				<li>伝票作成時に部署を選択可能</li>
			</ol>
			
			<h3>請求書への反映</h3>
			<p>選択された部署の情報は自動的に請求書に反映されます：</p>
			<ul>
				<li>部署名が請求書に表示</li>
				<li>担当者名が宛先として使用</li>
				<li>部署のメールアドレスが送信先に設定</li>
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
			<p>利用規約の同意ダイアログ表示と管理画面での編集機能を提供します。表示対象を最適化して、より適切なユーザーに利用規約を表示します。</p>
			
			<h3>機能</h3>
			<ul>
				<li>利用規約の同意ダイアログ表示（表示対象の最適化）</li>
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
				<li>表示対象の最適化</li>
				<li>ショートコード設置ページでのみ表示</li>
				<li>管理画面では表示されません</li>
			</ul>
			
			<h3>表示対象の最適化</h3>
			<p>利用規約の表示対象をより適切なユーザーに限定し、プラグインの利用体験を向上させています。</p>';
		}

		/**
		 * Get changelog content
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function get_changelog_content() {
			return '
			<div class="ktpwp-reference-section">
				<h3>📋 バージョン履歴</h3>
				
				<div class="ktpwp-changelog-item">
					<h4>1.0.7(a) - 2025年8月1日</h4>
					<ul>
						<li><strong>ダミーデータツールの改善</strong>
							<ul>
								<li>管理画面でのテストデータ作成機能の改善</li>
								<li>顧客・サービス・協力会社・受注データの一括生成機能の強化</li>
								<li>開発・テスト環境での効率的なデータ作成の改善</li>
							</ul>
						</li>
						<li><strong>詳細タイトルの表示改善</strong>
							<ul>
								<li>各タブのタイトル表示の最適化</li>
								<li>詳細情報の可視性向上</li>
								<li>ユーザビリティの改善</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.0.6(a) - 2025年8月1日</h4>
					<ul>
						<li><strong>ダミーデータツールの改善</strong>
							<ul>
								<li>管理画面でのテストデータ作成機能の改善</li>
								<li>顧客・サービス・協力会社・受注データの一括生成機能の強化</li>
								<li>開発・テスト環境での効率的なデータ作成の改善</li>
							</ul>
						</li>
						<li><strong>ライセンスキー設定の表示改善</strong>
							<ul>
								<li>ライセンス管理画面のUI改善</li>
								<li>ライセンス情報の表示最適化</li>
								<li>ライセンス状態の可視性向上</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.0.4(a) - 2025年7月31日</h4>
					<ul>
						<li><strong>レポート機能の追加・改善</strong>
							<ul>
								<li>売上レポート機能の実装</li>
								<li>顧客レポート機能の実装</li>
								<li>サービスレポート機能の実装</li>
								<li>協力会社レポート機能の実装</li>
								<li>期間別分析機能の実装</li>
								<li>グラフ表示機能の実装（Chart.js対応）</li>
								<li>データエクスポート機能の実装</li>
								<li>ライセンス認証連携機能の実装</li>
								<li>レポートセレクター機能の実装</li>
								<li>リアルタイムデータ更新機能の実装</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.0.2(a) - 2025年7月30日</h4>
					<ul>
						<li><strong>ダミーデーターツール改善</strong>
							<ul>
								<li>管理画面でのテストデータ作成機能の改善</li>
								<li>顧客・サービス・協力会社・受注データの一括生成機能の強化</li>
								<li>開発・テスト環境での効率的なデータ作成の改善</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.0.1(a) - 2025年7月30日</h4>
					<ul>
						<li><strong>ダミーデーターツール実装</strong>
							<ul>
								<li>管理画面でのテストデータ作成機能</li>
								<li>顧客・サービス・協力会社・受注データの一括生成</li>
								<li>開発・テスト環境での効率的なデータ作成</li>
							</ul>
						</li>
						<li><strong>基本的レポート機能実装</strong>
							<ul>
								<li>月別売上レポート機能</li>
								<li>進捗状況の統計表示</li>
								<li>グラフ表示機能（Chart.js対応）</li>
								<li>データエクスポート機能</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.0.0(a) - 2025年7月29日</h4>
					<ul>
						<li>ktpa開発開始</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.22(preview) - 2025年7月28日</h4>
					<ul>
						<li><strong>部署選択エラーの修正</strong>
							<ul>
								<li>部署選択時のエラーハンドリング強化</li>
								<li>部署データの整合性チェック機能追加</li>
								<li>部署選択UIの安定性向上</li>
								<li>部署関連のデータベース処理最適化</li>
								<li>部署管理機能の全体的な改善</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.23(preview) - 2025年7月28日</h4>
					<ul>
						<li><strong>更新チェック問題テスト</strong>
							<ul>
								<li>更新システムの安定性向上</li>
								<li>バグ修正・パフォーマンス最適化</li>
								<li>更新チェック機能の改善</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.19(preview) - 2025年7月28日</h4>
					<ul>
						<li><strong>アップデートテスト</strong>
							<ul>
								<li>プラグイン機能の安定性向上</li>
								<li>パフォーマンス最適化の継続改善</li>
								<li>セキュリティ機能の強化</li>
								<li>ユーザビリティの改善</li>
								<li>バグ修正と安定性向上</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.17(preview) - 2025年7月28日</h4>
					<ul>
						<li><strong>モバイル表示の改善</strong>
							<ul>
								<li>レスポンシブデザインの強化（ブレークポイント最適化・フレキシブルレイアウト）</li>
								<li>タッチ操作の最適化（タップターゲットサイズ拡大・スワイプジェスチャー対応）</li>
								<li>モバイル専用UI改善（ナビゲーション・ボタン配置・フォントサイズ調整）</li>
								<li>パフォーマンス最適化（モバイル向け画像最適化・CSS/JS軽量化）</li>
								<li>アクセシビリティ向上（スクリーンリーダー対応・キーボードナビゲーション改善）</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.16(preview) - 2025年7月27日</h4>
					<ul>
						<li><strong>軽微な修正</strong>
							<ul>
								<li>バージョン通知番号の修正</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.15(preview) - 2025年7月26日</h4>
					<ul>
						<li><strong>利用規約の表示対象を修正</strong>
							<ul>
								<li>より適切な対象への変更</li>
								<li>プラグインの利用体験向上</li>
							</ul>
						</li>
						<li><strong>マイグレーション中にエラー解消</strong>
							<ul>
								<li>データベース更新処理の安定化</li>
								<li>エラーハンドリング強化</li>
								<li>マイグレーション実行時の安全性向上</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.13(preview) - 2025年7月25日</h4>
					<ul>
						<li><strong>パフォーマンスの向上</strong>
							<ul>
								<li>キャッシュシステムの最適化（トランジェント・オブジェクトキャッシュ）</li>
								<li>データベースクエリの効率化（インデックス・JOIN最適化）</li>
								<li>メモリ使用量の最適化（効率的なデータ構造）</li>
								<li>画像最適化（WebP変換・圧縮）</li>
								<li>JavaScript・CSSの最適化（非同期読み込み・バンドル化・圧縮）</li>
								<li>セッション管理の最適化（自動クローズ・メモリ効率化）</li>
								<li>Ajax通信の最適化（バッチ処理・レスポンス圧縮）</li>
								<li>フック管理の最適化（不要なフックの削除・条件付き登録）</li>
							</ul>
						</li>
					</ul>
				</div>

				<div class="ktpwp-changelog-item">
					<h4>1.1.12(preview) - 2025年7月24日</h4>
					<ul>
						<li>請求書プレビューが閉じない問題を修正</li>
					</ul>
				</div>
			
			<h3>1.1.11(preview) - 2025年7月23日</h3>
			<ul>
				<li><strong>受注書のコスト項目発注メールの改善</strong>
					<ul>
						<li>適格請求書対応</li>
						<li>詳細な税額内訳</li>
						<li>計算根拠明示</li>
					</ul>
				</li>
				<li><strong>受注書のコスト項目更新処理の改善</strong>
					<ul>
						<li>リアルタイム自動保存機能の強化</li>
						<li>値の変更検知による効率的なデータベース更新</li>
						<li>包括的なエラーハンドリングとログ出力</li>
						<li>セキュリティ強化（nonce検証・権限チェック）</li>
						<li>データ型の適切な処理（数値・文字列・NULL値）</li>
						<li>税率フィールドの改善（空文字・null・0の適切な処理）</li>
						<li>備考欄の改善（「0」の場合は空文字列として処理）</li>
						<li>成功通知の条件付き表示（実際に値が変更された場合のみ）</li>
						<li>Ajax通信の最適化（タイムアウト設定・レスポンス処理）</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.9(preview) - 2025年7月22日</h3>
			<ul>
				<li><strong>受注書の請求項目更新処理の改善</strong>
					<ul>
						<li>リアルタイム自動保存機能の強化</li>
						<li>値の変更検知による効率的なデータベース更新</li>
						<li>包括的なエラーハンドリングとログ出力</li>
						<li>セキュリティ強化（nonce検証・権限チェック）</li>
						<li>データ型の適切な処理（数値・文字列・NULL値）</li>
						<li>税率フィールドの改善（空文字・null・0の適切な処理）</li>
						<li>備考欄の改善（「0」の場合は空文字列として処理）</li>
						<li>成功通知の条件付き表示（実際に値が変更された場合のみ）</li>
						<li>Ajax通信の最適化（タイムアウト設定・レスポンス処理）</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.8(preview) - 2025年7月22日</h3>
			<ul>
				<li><strong>仕事リストタブの進捗アイコンのSVG化</strong>
					<ul>
						<li>進捗フィルターボタンのアイコンをSVG化</li>
						<li>軽量で高速なアイコン表示</li>
						<li>カスタマイズ可能なアイコンデザイン</li>
						<li>アクセシビリティの向上</li>
						<li>外部依存関係の削除</li>
					</ul>
				</li>
				<li><strong>消費税対応の改善</strong>
					<ul>
						<li>税率計算ロジックの最適化</li>
						<li>消費税表示の改善</li>
						<li>内税・外税の管理強化</li>
						<li>軽減税率対応の安定化</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.7(preview) - 2025年7月19日</h3>
			<ul>
				<li><strong>アイコンのSVG化</strong>
					<ul>
						<li>Material SymbolsからSVGアイコンへの完全移行</li>
						<li>軽量で高速なアイコン表示</li>
						<li>カスタマイズ可能なアイコンデザイン</li>
						<li>アクセシビリティの向上</li>
						<li>外部依存関係の削除</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.6(preview) - 2025年7月19日</h3>
			<ul>
				<li><strong>協力会社職能選択の改善</strong>
					<ul>
						<li>ページネーション機能の追加</li>
						<li>検索・ソート機能の強化</li>
						<li>税率対応の改善</li>
						<li>レスポンシブデザインの最適化</li>
						<li>ユーザビリティの向上</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.5(preview) - 2025年7月18日</h3>
			<ul>
				<li><strong>パフォーマンス最適化機能の追加</strong>
					<ul>
						<li>キャッシュシステムの改善</li>
						<li>クエリ最適化の強化</li>
						<li>メモリ効率化の実装</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.4(preview) - 2025年7月17日</h3>
			<ul>
				<li><strong>消費税対応機能の強化</strong>
					<ul>
						<li>軽減税率対応の改善</li>
						<li>税区分ラベルの統一</li>
						<li>消費税計算ロジックの最適化</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.3(preview) - 2025年7月16日</h3>
			<ul>
				<li><strong>データベース整合性チェック機能の追加</strong>
					<ul>
						<li>データ品質の自動チェック</li>
						<li>不整合データの検出と修復</li>
						<li>定期的な整合性確認</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.2(preview) - 2025年7月15日</h3>
			<ul>
				<li><strong>自動マイグレーション機能の実装</strong>
					<ul>
						<li>データベース更新の自動化</li>
						<li>安全なデータ移行</li>
						<li>バージョン管理による段階的更新</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.1(preview) - 2025年7月14日</h3>
			<ul>
				<li><strong>寄付機能の追加</strong>
					<ul>
						<li>Stripe決済による安全な寄付システム</li>
						<li>寄付進捗のリアルタイム表示</li>
						<li>寄付完了後の自動メール送信</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.1.0(preview) - 2025年7月13日</h3>
			<ul>
				<li><strong>プラグインリファレンス機能の実装</strong>
					<ul>
						<li>包括的なヘルプシステム</li>
						<li>機能別詳細ガイド</li>
						<li>トラブルシューティング情報</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.9(preview) - 2025年7月12日</h3>
			<ul>
				<li><strong>スタッフアバター表示機能の追加</strong>
					<ul>
						<li>ログイン中スタッフの可視化</li>
						<li>現在のユーザーを強調表示</li>
						<li>レスポンシブデザイン対応</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.8(preview) - 2025年7月11日</h3>
			<ul>
				<li><strong>商品管理機能の改善</strong>
					<ul>
						<li>DECIMAL型による精密な価格計算</li>
						<li>データマイグレーション機能</li>
						<li>インデックス最適化</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.7(preview) - 2025年7月10日</h3>
			<ul>
				<li><strong>納期警告機能の実装</strong>
					<ul>
						<li>期限間近の案件への警告表示</li>
						<li>リアルタイム更新対応</li>
						<li>ツールチップでの残り日数表示</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.6(preview) - 2025年7月9日</h3>
			<ul>
				<li><strong>完了日自動設定機能の追加</strong>
					<ul>
						<li>進捗ステータス変更時の自動処理</li>
						<li>完了時の自動日付設定</li>
						<li>受注以前への変更時の自動クリア</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.5(preview) - 2025年7月8日</h3>
			<ul>
				<li><strong>ファイル添付機能の実装</strong>
					<ul>
						<li>ドラッグ&ドロップによるファイル添付</li>
						<li>複数ファイル同時添付対応</li>
						<li>自動クリーンアップ機能</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.4(preview) - 2025年7月7日</h3>
			<ul>
				<li><strong>ページネーション機能の追加</strong>
					<ul>
						<li>全タブでの統一されたページネーション</li>
						<li>サービス選択ポップアップ対応</li>
						<li>一般設定による表示件数制御</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.3(preview) - 2025年7月6日</h3>
			<ul>
				<li><strong>シンプル更新システムの実装</strong>
					<ul>
						<li>WordPress標準の更新システムに完全対応</li>
						<li>軽量で安定性を重視した設計</li>
						<li>自動マイグレーション機能搭載</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.2(preview) - 2025年7月5日</h3>
			<ul>
				<li><strong>利用規約管理機能の追加</strong>
					<ul>
						<li>利用規約の同意ダイアログ表示</li>
						<li>管理画面での利用規約編集</li>
						<li>利用規約バージョン管理</li>
						<li>表示対象の最適化</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.1(preview) - 2025年7月4日</h3>
			<ul>
				<li><strong>部署管理機能の実装</strong>
					<ul>
						<li>顧客ごとの部署・担当者管理</li>
						<li>部署別のメールアドレス設定</li>
						<li>請求書への部署情報反映</li>
					</ul>
				</li>
			</ul>
			
			<h3>1.0.0(preview) - 2025年7月3日</h3>
			<ul>
				<li><strong>初回リリース</strong>
					<ul>
						<li>6つの管理タブ（仕事リスト・伝票処理・顧客・サービス・協力会社・レポート）</li>
						<li>受注案件の進捗管理</li>
						<li>受注書・請求書の作成・編集・PDF保存</li>
						<li>スタッフチャット機能</li>
						<li>モバイル対応UI</li>
						<li>セキュリティ機能</li>
						<li>セッション管理最適化</li>
					</ul>
				</li>
			</ul>
			
			<h3>最終更新日</h3>
			<p>2025年7月29日</p>
			';
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
			// Mark that reference cache needs refresh
			update_option( 'ktpwp_reference_needs_refresh', true );
			update_option( 'ktpwp_reference_last_updated', current_time( 'timestamp' ) );
			update_option( 'ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION );
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
