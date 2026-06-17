<?php
/**
 * KantanPro（FileMaker Pro 版）からエクスポートした Zip を OpenAI（BYOK）で解析し、顧客・協力会社・商品を自動取り込みする。結果は画面上にレポート表示。
 *
 * @package KantanProEX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FileMaker 版データ取り込み（顧客・協力会社・商品）
 */
final class KTPWP_FM_Import {

	public const OPTION_OPENAI_KEY_ENC = 'ktp_fm_import_openai_key_enc';

	private const ENTITY_CLIENT   = 'client';
	private const ENTITY_SUPPLIER = 'supplier';
	private const ENTITY_SERVICE  = 'service';
	private const ENTITY_ORDER    = 'order';

	/** transient キー先頭（ユーザー ID を連結） */
	private const TRANSIENT_PREFIX     = 'ktp_fm_import_sess_';
	private const TRANSIENT_ZIP_PREFIX = 'ktp_fm_import_zip_';
	private const TRANSIENT_RPT_PREFIX = 'ktp_fm_import_rpt_';
	private const TRANSIENT_TTL        = 3600;
	private const MAX_ZIP_BYTES        = 52428800; // 50 MiB（FileMaker エクスポート想定）
	private const MAX_INNER_IMPORT_BYTES = 8388608; // Zip 内1ファイルあたり最大 8 MiB を取り込み対象
	private const MAX_INNER_SNAPSHOT_BYTES = 786432; // AI プレビュー用に先頭から最大 768 KiB
	private const MAX_FILES_IN_ZIP_AI  = 50;
	private const MAX_AI_USER_JSON_CHARS = 90000;
	private const MAX_DATA_ROWS        = 2000;
	private const NONCE_UPLOAD         = 'ktp_fm_import_upload';
	private const NONCE_IMPORT         = 'ktp_fm_import_run';
	private const NONCE_OPENAI         = 'ktp_fm_import_openai';
	private const NONCE_AJAX_AI        = 'ktp_fm_import_ai';
	private const NONCE_AI_ZIP         = 'ktp_fm_ai_zip_import';

	/**
	 * @return void
	 */
	public static function bootstrap(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 10, 1 );
		add_action( 'wp_ajax_ktp_fm_import_ai_mapping', array( __CLASS__, 'ajax_ai_mapping' ) );
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public static function enqueue_admin( $hook_suffix ): void {
		if ( strpos( (string) $hook_suffix, 'ktp-fm-import' ) === false ) {
			return;
		}
		wp_enqueue_script(
			'ktp-fm-import-admin',
			plugin_dir_url( __FILE__ ) . '../js/ktp-fm-import-admin.js',
			array( 'jquery' ),
			defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0.0',
			true
		);
		wp_localize_script(
			'ktp-fm-import-admin',
			'ktpFmImport',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_AJAX_AI ),
				'i18n'    => array(
					'working' => __( 'AI に問い合わせ中…', 'ktpwp' ),
					'done'    => __( 'マッピングを反映しました。内容を確認してから取り込みを実行してください。', 'ktpwp' ),
					'error'   => __( 'AI マッピングに失敗しました。', 'ktpwp' ),
				),
			)
		);
	}

	/**
	 * 管理画面（ KantanPro 設定配下）
	 *
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'ktpwp' ) );
		}

		if ( ! empty( $_GET['ktp_fm_reset'] ) && isset( $_GET['ktp_fm_reset_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ktp_fm_reset_nonce'] ) ), 'ktp_fm_reset' ) ) {
			self::cleanup_zip_stored_file();
			delete_transient( self::transient_key_for_user() );
			delete_transient( self::transient_zip_session_key() );
			delete_transient( self::transient_report_key() );
			wp_safe_redirect( admin_url( 'admin.php?page=ktp-fm-import' ) );
			exit;
		}

		if ( ! empty( $_GET['ktp_fm_dismiss_report'] ) && isset( $_GET['ktp_fm_dismiss_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ktp_fm_dismiss_nonce'] ) ), 'ktp_fm_dismiss_report' ) ) {
			delete_transient( self::transient_report_key() );
			wp_safe_redirect( admin_url( 'admin.php?page=ktp-fm-import' ) );
			exit;
		}

		self::maybe_handle_save_openai_key();
		$ai_zip_notice = self::maybe_handle_ai_zip_import();
		$upload_notice = self::maybe_handle_upload();
		$import_notice = self::maybe_handle_import();

		$transient_key = self::transient_key_for_user();
		$session       = get_transient( $transient_key );

		echo '<div class="wrap ktp-admin-wrap">';
		echo '<h1>' . esc_html__( 'FileMaker版データ取り込み', 'ktpwp' ) . '</h1>';

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'FileMaker Pro 版から書き出した Zip ファイルを1つアップロードしてください。Zip 内の構成は問いません。OpenAI API キー（BYOK）を設定したうえで「AI で解析して取り込む」を実行すると、Zip 内の表形式ファイルを判別し、顧客・協力会社・商品マスタへ可能な範囲で取り込みます。', 'ktpwp' );
		echo ' ';
		echo esc_html__( 'OpenAI の従量課金はご利用のアカウントに発生します。', 'ktpwp' );
		echo '</p></div>';

		if ( is_string( $ai_zip_notice ) && $ai_zip_notice !== '' ) {
			echo wp_kses_post( $ai_zip_notice );
		}
		if ( is_string( $upload_notice ) && $upload_notice !== '' ) {
			echo wp_kses_post( $upload_notice );
		}
		if ( is_string( $import_notice ) && $import_notice !== '' ) {
			echo wp_kses_post( $import_notice );
		}

		self::render_openai_key_form();
		self::render_last_import_report();

		$zip_sess = self::sanitize_zip_session( get_transient( self::transient_zip_session_key() ) );
		if ( $zip_sess !== null ) {
			self::render_zip_pending_ui( $zip_sess );
		} else {
			$session_raw   = $session;
			$session_clean = self::sanitize_session_for_render( $session_raw );
			if ( $session_raw !== false && $session_raw !== null && $session_clean === null ) {
				delete_transient( $transient_key );
				echo '<div class="notice notice-warning"><p>' . esc_html__( '取り込み途中のデータが壊れているため破棄しました。ファイルを再度アップロードしてください。', 'ktpwp' ) . '</p></div>';
				self::render_upload_form();
			} elseif ( $session_clean !== null ) {
				try {
					self::render_mapping_and_import( $session_clean );
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( 'KTPWP_FM_Import: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
					}
					echo '<div class="notice notice-error"><p>' . esc_html__( '取り込み画面の表示中にエラーが発生しました。「最初からやり直す」でセッションを消すか、ファイルを確認してください。', 'ktpwp' ) . '</p></div>';
					self::render_upload_form();
				}
			} else {
				self::render_upload_form();
			}
		}

		echo '</div>';
	}

	/**
	 * @return void
	 */
	private static function maybe_handle_save_openai_key(): void {
		if ( ! isset( $_POST['ktp_fm_openai_save'], $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_OPENAI ) ) {
			wp_die( esc_html__( 'セキュリティ検証に失敗しました。', 'ktpwp' ) );
		}
		$key = isset( $_POST['ktp_fm_openai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ktp_fm_openai_key'] ) ) : '';
		if ( $key === '' ) {
			delete_option( self::OPTION_OPENAI_KEY_ENC );
		} else {
			$enc = self::encrypt_string( $key );
			if ( $enc !== '' ) {
				update_option( self::OPTION_OPENAI_KEY_ENC, $enc, false );
			}
		}
	}

	/**
	 * @return string
	 */
	private static function maybe_handle_upload(): string {
		if ( ! isset( $_POST['ktp_fm_upload'], $_POST['_wpnonce'] ) ) {
			return '';
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_UPLOAD ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'セキュリティ検証に失敗しました。', 'ktpwp' ) . '</p></div>';
		}
		if ( empty( $_FILES['ktp_fm_file']['tmp_name'] ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'ファイルを選択してください。', 'ktpwp' ) . '</p></div>';
		}

		$file = $_FILES['ktp_fm_file'];
		if ( ! empty( $file['error'] ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'ファイルのアップロードに失敗しました。', 'ktpwp' ) . '</p></div>';
		}
		if ( (int) $file['size'] > self::MAX_ZIP_BYTES ) {
			return '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: %s: max size like 50 MB */
					__( 'Zip が大きすぎます（%s 以下にしてください）。', 'ktpwp' ),
					size_format( self::MAX_ZIP_BYTES )
				)
			) . '</p></div>';
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( $ext !== 'zip' ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'アップロードできるのは FileMaker 版から書き出した Zip ファイルのみです。', 'ktpwp' ) . '</p></div>';
		}

		if ( ! class_exists( 'ZipArchive', false ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'このサーバーでは Zip を扱えません（ZipArchive がありません）。', 'ktpwp' ) . '</p></div>';
		}

		$probe = new ZipArchive();
		if ( $probe->open( $file['tmp_name'] ) !== true ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Zip ファイルを開けませんでした。', 'ktpwp' ) . '</p></div>';
		}
		$probe->close();

		self::cleanup_zip_stored_file();
		delete_transient( self::transient_zip_session_key() );

		$dest = self::allocate_zip_storage_path();
		if ( is_wp_error( $dest ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $dest->get_error_message() ) . '</p></div>';
		}

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'サーバーへ Zip を保存できませんでした。', 'ktpwp' ) . '</p></div>';
		}

		set_transient(
			self::transient_zip_session_key(),
			array(
				'path'        => $dest,
				'orig_name'   => $name,
				'uploaded_at' => time(),
			),
			self::TRANSIENT_TTL
		);

		return '<div class="notice notice-success"><p>' . esc_html__( 'Zip を受け付けました。下の「AI で解析して取り込む」から続行してください（OpenAI API キーが必要です）。', 'ktpwp' ) . '</p></div>';
	}

	/**
	 * @return string
	 */
	private static function maybe_handle_import(): string {
		if ( ! isset( $_POST['ktp_fm_import_run'], $_POST['_wpnonce'] ) ) {
			return '';
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_IMPORT ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'セキュリティ検証に失敗しました。', 'ktpwp' ) . '</p></div>';
		}

		$key           = self::transient_key_for_user();
		$session_raw   = get_transient( $key );
		$session_clean = self::sanitize_session_for_render( $session_raw );
		if ( $session_clean === null ) {
			delete_transient( $key );
			return '<div class="notice notice-error"><p>' . esc_html__( 'セッションが切れたか、保存データが不正です。ファイルを再度アップロードしてください。', 'ktpwp' ) . '</p></div>';
		}

		$headers = $session_clean['headers'];
		$rows    = $session_clean['rows'];
		$entity  = $session_clean['entity'];
		$map_in  = isset( $_POST['ktp_fm_map'] ) && is_array( $_POST['ktp_fm_map'] ) ? wp_unslash( $_POST['ktp_fm_map'] ) : array();

		$map = array();
		foreach ( self::target_fields_for_entity( $entity ) as $field => $_label ) {
			if ( ! isset( $map_in[ $field ] ) ) {
				continue;
			}
			$idx = sanitize_text_field( (string) $map_in[ $field ] );
			if ( $idx === '' || $idx === '__skip__' ) {
				continue;
			}
			$i = (int) $idx;
			if ( $i >= 0 && $i < count( $headers ) ) {
				$map[ $field ] = $i;
			}
		}

		$skip_dup_email   = ! empty( $_POST['ktp_fm_skip_dup_email'] );
		$skip_dup_service = ! empty( $_POST['ktp_fm_skip_dup_service_name'] );

		if ( $entity === self::ENTITY_CLIENT ) {
			$result = self::import_client_rows( $rows, $headers, $map, $skip_dup_email );
		} elseif ( $entity === self::ENTITY_SUPPLIER ) {
			$result = self::import_supplier_rows( $rows, $headers, $map, $skip_dup_email );
		} else {
			$result = self::import_service_rows( $rows, $headers, $map, $skip_dup_service );
		}
		delete_transient( $key );

		$cls = $result['errors'] > 0 ? 'notice-warning' : 'notice-success';
		$msg = sprintf(
			/* translators: 1: inserted count, 2: skipped, 3: errors */
			__( '取り込み完了：追加 %1$d 件、スキップ %2$d 件、エラー %3$d 件。', 'ktpwp' ),
			(int) $result['inserted'],
			(int) $result['skipped'],
			(int) $result['errors']
		);

		return '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Zip を OpenAI で解析し、取り込み実行とレポート保存。
	 *
	 * @return string 画面上に出す HTML（空なら何もしない）
	 */
	private static function maybe_handle_ai_zip_import(): string {
		if ( ! isset( $_POST['ktp_fm_ai_zip_run'], $_POST['_wpnonce'] ) ) {
			return '';
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_AI_ZIP ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'セキュリティ検証に失敗しました。', 'ktpwp' ) . '</p></div>';
		}

		$api_key = self::decrypt_api_key( (string) get_option( self::OPTION_OPENAI_KEY_ENC, '' ) );
		if ( $api_key === '' ) {
			return '<div class="notice notice-error"><p>' . esc_html__( '先に OpenAI API キーを保存してください。', 'ktpwp' ) . '</p></div>';
		}

		$zip_sess = self::sanitize_zip_session( get_transient( self::transient_zip_session_key() ) );
		if ( $zip_sess === null ) {
			return '<div class="notice notice-error"><p>' . esc_html__( '取り込み待ちの Zip がありません。もう一度 Zip をアップロードしてください。', 'ktpwp' ) . '</p></div>';
		}

		$zip_path = $zip_sess['path'];
		if ( ! self::is_valid_stored_zip_path( $zip_path ) ) {
			delete_transient( self::transient_zip_session_key() );
			return '<div class="notice notice-error"><p>' . esc_html__( '保存された Zip のパスが無効です。最初からやり直してください。', 'ktpwp' ) . '</p></div>';
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		$manifest = self::build_zip_manifest_for_ai( $zip_path );
		if ( is_wp_error( $manifest ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $manifest->get_error_message() ) . '</p></div>';
		}
		if ( $manifest === array() ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Zip 内に取り込み候補となる表形式ファイル（csv / tsv / tab / txt）が見つかりませんでした。', 'ktpwp' ) . '</p></div>';
		}

		$user_json = self::truncate_manifest_json_for_openai( $manifest );
		$decoded   = self::openai_zip_plan_request( $api_key, $user_json );
		if ( is_wp_error( $decoded ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $decoded->get_error_message() ) . '</p></div>';
		}

		$skip_dup_email   = ! empty( $_POST['ktp_fm_zip_skip_dup_email'] );
		$skip_dup_service = ! empty( $_POST['ktp_fm_zip_skip_dup_service'] );

		$skip_dup_order = ! empty( $_POST['ktp_fm_zip_skip_dup_order'] );
		$report         = self::execute_ai_zip_plans( $zip_path, $manifest, $decoded, $skip_dup_email, $skip_dup_service, $skip_dup_order );
		$report['zip_name']    = isset( $zip_sess['orig_name'] ) ? (string) $zip_sess['orig_name'] : basename( $zip_path );
		$report['time']        = time();
		$report['other_files'] = self::list_zip_non_tabular_entries( $zip_path );

		@unlink( $zip_path );
		delete_transient( self::transient_zip_session_key() );
		set_transient( self::transient_report_key(), $report, self::TRANSIENT_TTL );

		return '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI 解析と取り込み処理が完了しました。下のレポートを確認してください。', 'ktpwp' ) . '</p></div>';
	}

	/**
	 * @return void
	 */
	public static function ajax_ai_mapping(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'ktpwp' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_AJAX_AI, 'nonce' );

		$key = self::decrypt_api_key( (string) get_option( self::OPTION_OPENAI_KEY_ENC, '' ) );
		if ( $key === '' ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI API キーが未設定です。', 'ktpwp' ) ) );
		}

		$headers = isset( $_POST['headers'] ) ? json_decode( wp_unslash( (string) $_POST['headers'] ), true ) : null;
		$samples = isset( $_POST['samples'] ) ? json_decode( wp_unslash( (string) $_POST['samples'] ), true ) : null;
		if ( ! is_array( $headers ) || $headers === array() ) {
			wp_send_json_error( array( 'message' => __( 'ヘッダーが不正です。', 'ktpwp' ) ) );
		}
		$headers = array_map( 'sanitize_text_field', array_map( 'strval', $headers ) );
		if ( ! is_array( $samples ) ) {
			$samples = array();
		}

		$entity_post = isset( $_POST['entity'] ) ? wp_unslash( $_POST['entity'] ) : '';
		$entity        = is_string( $entity_post ) ? self::normalize_entity( $entity_post ) : self::ENTITY_CLIENT;

		$system = self::build_openai_system_prompt_for_entity( $entity );
		$user   = wp_json_encode(
			array(
				'headers'       => $headers,
				'sample_rows'   => array_slice( $samples, 0, 3 ),
				'target_fields' => array_keys( self::target_fields_for_entity( $entity ) ),
			),
			JSON_UNESCAPED_UNICODE
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'           => 'gpt-4o-mini',
						'temperature'     => 0.1,
						'response_format' => array( 'type' => 'json_object' ),
						'messages'        => array(
							array( 'role' => 'system', 'content' => $system ),
							array( 'role' => 'user', 'content' => $user ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'OpenAI API エラー', 'ktpwp' );
			wp_send_json_error( array( 'message' => $msg ) );
		}
		$content = is_array( $body ) && isset( $body['choices'][0]['message']['content'] ) ? (string) $body['choices'][0]['message']['content'] : '';
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) || empty( $decoded['column_map'] ) || ! is_array( $decoded['column_map'] ) ) {
			wp_send_json_error( array( 'message' => __( 'AI の応答を解釈できませんでした。', 'ktpwp' ) ) );
		}

		$out_map = array();
		foreach ( $decoded['column_map'] as $kantan_field => $header_name ) {
			$kantan_field = sanitize_key( (string) $kantan_field );
			if ( ! isset( self::target_fields_for_entity( $entity )[ $kantan_field ] ) ) {
				continue;
			}
			if ( $header_name === null || $header_name === '' ) {
				continue;
			}
			$header_name = sanitize_text_field( (string) $header_name );
			$idx         = array_search( $header_name, $headers, true );
			if ( $idx !== false ) {
				$out_map[ $kantan_field ] = (int) $idx;
			}
		}

		wp_send_json_success( array( 'map' => $out_map ) );
	}

	/**
	 * @param string $entity Entity.
	 * @return string
	 */
	private static function build_openai_system_prompt_for_entity( $entity ): string {
		$labels = self::target_fields_for_entity( $entity );
		$lines  = array();
		foreach ( $labels as $k => $lab ) {
			$lines[] = "- {$k} … {$lab}";
		}
		$list = implode( "\n", $lines );

		if ( $entity === self::ENTITY_SUPPLIER ) {
			$role = '協力会社（supplier）マスタ';
		} elseif ( $entity === self::ENTITY_SERVICE ) {
			$role = '商品・サービス（service）マスタ';
		} else {
			$role = '顧客（client）マスタ';
		}

		return <<<PROMPT
あなたは KantanPro（FileMaker Pro 版）から CSV/TSV で書き出された列を、WordPress プラグイン KantanProEX の{$role}の列に対応づける専門家です。
入力 JSON の headers（列名の配列）と sample_rows（最大3行の配列の配列）を見て、次の Kantan 側フィールド名ごとに「一致する元の列名」を1つ選ぶ。該当がなければ null。

【対象フィールド】
{$list}

JSON のみ。形式:
{ "column_map": { "フィールド名": "元CSVの列名またはnull", ... } }
列名は headers に存在する文字列と完全一致にすること。
PROMPT;
	}

	/**
	 * @param string $entity client|supplier|service.
	 * @return array<string, string>
	 */
	private static function target_fields_for_entity( $entity ): array {
		$entity = self::normalize_entity( $entity );
		if ( $entity === self::ENTITY_SUPPLIER ) {
			return array(
				'company_name'             => __( '会社名', 'ktpwp' ),
				'name'                     => __( '担当者名', 'ktpwp' ),
				'email'                    => __( 'メール', 'ktpwp' ),
				'url'                      => __( 'URL', 'ktpwp' ),
				'representative_name'      => __( '代表者名', 'ktpwp' ),
				'phone'                    => __( '電話', 'ktpwp' ),
				'postal_code'              => __( '郵便番号', 'ktpwp' ),
				'prefecture'               => __( '都道府県', 'ktpwp' ),
				'city'                     => __( '市区町村', 'ktpwp' ),
				'address'                  => __( '住所', 'ktpwp' ),
				'building'                 => __( '建物名', 'ktpwp' ),
				'closing_day'              => __( '締め日', 'ktpwp' ),
				'payment_month'            => __( '支払月', 'ktpwp' ),
				'payment_day'              => __( '支払日', 'ktpwp' ),
				'payment_method'           => __( '支払方法', 'ktpwp' ),
				'tax_category'             => __( '税区分', 'ktpwp' ),
				'memo'                     => __( 'メモ', 'ktpwp' ),
				'qualified_invoice_number' => __( '適格請求書番号', 'ktpwp' ),
				'category'                 => __( 'カテゴリ', 'ktpwp' ),
			);
		}
		if ( $entity === self::ENTITY_SERVICE ) {
			return array(
				'service_name' => __( '商品・サービス名', 'ktpwp' ),
				'price'        => __( '単価（数値）', 'ktpwp' ),
				'tax_rate'     => __( '税率（%・空可）', 'ktpwp' ),
				'unit'         => __( '単位', 'ktpwp' ),
				'memo'         => __( 'メモ', 'ktpwp' ),
				'category'     => __( 'カテゴリ', 'ktpwp' ),
			);
		}
		if ( $entity === self::ENTITY_ORDER ) {
			return array(
				'link_company_name' => __( '顧客マスタと紐づける会社名（必須・ktp_client.company_name と一致）', 'ktpwp' ),
				'link_email'        => __( '顧客マスタと紐づけるメール（任意・会社名で一意に決められないとき）', 'ktpwp' ),
				'project_name'      => __( '案件名（project_name）', 'ktpwp' ),
				'customer_name'     => __( '受注書上の顧客表示名（空なら会社名にフォールバック可）', 'ktpwp' ),
				'user_name'         => __( '担当者名', 'ktpwp' ),
				'order_date'        => __( '受注日・起算日（日付文字列）', 'ktpwp' ),
				'progress_label'    => __( '進捗ラベル（例: 受付中・見積中・受注・完了・請求済・入金済・ボツ）', 'ktpwp' ),
				'memo'              => __( 'メモ', 'ktpwp' ),
				'external_order_key' => __( '行の一意キー（FileMaker 内部ID 等・重複スキップ用・任意）', 'ktpwp' ),
			);
		}

		return array(
			'company_name'        => __( '会社名', 'ktpwp' ),
			'name'                => __( '担当者名（顧客側）', 'ktpwp' ),
			'email'               => __( 'メール', 'ktpwp' ),
			'url'                 => __( 'URL', 'ktpwp' ),
			'representative_name' => __( '代表者名', 'ktpwp' ),
			'phone'               => __( '電話', 'ktpwp' ),
			'postal_code'         => __( '郵便番号', 'ktpwp' ),
			'prefecture'          => __( '都道府県', 'ktpwp' ),
			'city'                => __( '市区町村', 'ktpwp' ),
			'address'             => __( '住所', 'ktpwp' ),
			'building'            => __( '建物名', 'ktpwp' ),
			'closing_day'         => __( '締め日', 'ktpwp' ),
			'payment_month'       => __( '支払月', 'ktpwp' ),
			'payment_day'         => __( '支払日', 'ktpwp' ),
			'payment_method'      => __( '支払方法', 'ktpwp' ),
			'tax_category'        => __( '税区分', 'ktpwp' ),
			'payment_timing'      => __( '支払タイミング（postpay/prepay/prepay_wc）', 'ktpwp' ),
			'memo'                => __( 'メモ', 'ktpwp' ),
			'client_status'       => __( '顧客ステータス', 'ktpwp' ),
			'category'            => __( 'カテゴリ', 'ktpwp' ),
		);
	}

	/**
	 * @return void
	 */
	private static function render_openai_key_form(): void {
		$has_key = get_option( self::OPTION_OPENAI_KEY_ENC, '' ) !== '';
		echo '<div class="card" style="max-width:720px;margin:16px 0;padding:16px;">';
		echo '<h2>' . esc_html__( 'OpenAI API キー（BYOK・必須）', 'ktpwp' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Zip の自動解析・取り込みに使用します。キーはサイト内で暗号化して保存し、OpenAI の従量課金はご利用のアカウントに発生します。', 'ktpwp' ) . '</p>';
		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_OPENAI );
		echo '<p><input type="password" name="ktp_fm_openai_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $has_key ? __( '（保存済み・上書きする場合のみ入力）', 'ktpwp' ) : '' ) . '" /></p>';
		echo '<p><button type="submit" name="ktp_fm_openai_save" class="button">' . esc_html__( 'API キーを保存', 'ktpwp' ) . '</button> ';
		if ( $has_key ) {
			echo '<span class="description">' . esc_html__( '保存済みのキーを削除するには空のまま保存してください。', 'ktpwp' ) . '</span>';
		}
		echo '</p></form></div>';
	}

	/**
	 * @return void
	 */
	private static function render_upload_form(): void {
		echo '<div class="card" style="max-width:720px;margin:16px 0;padding:16px;">';
		echo '<h2>' . esc_html__( 'Zip をアップロード', 'ktpwp' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="">';
		wp_nonce_field( self::NONCE_UPLOAD );
		echo '<p><input type="file" name="ktp_fm_file" accept=".zip,application/zip" required /></p>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: max upload size */
				__( 'FileMaker Pro 版から書き出した Zip のみ（最大 %s）。', 'ktpwp' ),
				size_format( self::MAX_ZIP_BYTES )
			)
		) . '</p>';
		echo '<p><button type="submit" name="ktp_fm_upload" class="button button-primary">' . esc_html__( 'Zip をアップロード', 'ktpwp' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * transient に保存された取り込み途中データを検証・正規化する。
	 *
	 * @param mixed $session Raw transient value.
	 * @return array<string, mixed>|null 不正なら null。
	 */
	private static function sanitize_session_for_render( $session ) {
		if ( ! is_array( $session ) ) {
			return null;
		}
		if ( ! isset( $session['headers'], $session['rows'] ) ) {
			return null;
		}
		if ( ! is_array( $session['headers'] ) || ! is_array( $session['rows'] ) ) {
			return null;
		}

		$headers = array();
		foreach ( $session['headers'] as $h ) {
			if ( is_array( $h ) || is_object( $h ) ) {
				return null;
			}
			$headers[] = sanitize_text_field( (string) $h );
		}
		if ( $headers === array() ) {
			return null;
		}

		$ncol = count( $headers );
		$rows = array();
		foreach ( $session['rows'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$norm = array();
			for ( $c = 0; $c < $ncol; $c++ ) {
				$cell = $row[ $c ] ?? '';
				if ( is_array( $cell ) || is_object( $cell ) ) {
					$norm[ $c ] = '';
				} else {
					$norm[ $c ] = sanitize_text_field( (string) $cell );
				}
			}
			$rows[] = $norm;
		}

		return array(
			'entity'      => isset( $session['entity'] ) ? self::normalize_entity( $session['entity'] ) : self::ENTITY_CLIENT,
			'headers'     => $headers,
			'rows'        => $rows,
			'filename'    => isset( $session['filename'] ) ? sanitize_text_field( (string) $session['filename'] ) : '',
			'uploaded_at' => isset( $session['uploaded_at'] ) ? (int) $session['uploaded_at'] : time(),
		);
	}

	/**
	 * @param array<string, mixed> $session Session payload.
	 * @return void
	 */
	private static function render_mapping_and_import( array $session ): void {
		$headers = $session['headers'];
		$rows    = $session['rows'];
		$entity  = isset( $session['entity'] ) ? self::normalize_entity( $session['entity'] ) : self::ENTITY_CLIENT;
		$guess   = self::guess_for_entity( $entity, $headers );

		$entity_label = self::entity_label( $entity );

		echo '<div class="card" style="margin:16px 0;padding:16px;">';
		echo '<p><strong>' . esc_html__( '対象ファイル:', 'ktpwp' ) . '</strong> ' . esc_html( isset( $session['filename'] ) ? (string) $session['filename'] : '' ) . '</p>';
		echo '<h2>' . esc_html__( '2. 列の対応', 'ktpwp' ) . '（' . esc_html( $entity_label ) . '）</h2>';
		echo '<p><button type="button" class="button" id="ktp-fm-ai-suggest">' . esc_html__( 'AI で列を提案（OpenAI）', 'ktpwp' ) . '</button> ';
		echo '<span class="description" id="ktp-fm-ai-status"></span></p>';

		echo '<form method="post" action="" id="ktp-fm-import-form">';
		wp_nonce_field( self::NONCE_IMPORT );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( '取り込み先', 'ktpwp' ) . '</th><th>' . esc_html__( 'ファイルの列', 'ktpwp' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( self::target_fields_for_entity( $entity ) as $field => $label ) {
			$selected = isset( $guess[ $field ] ) ? (int) $guess[ $field ] : '';
			echo '<tr><th scope="row">' . esc_html( $label ) . '<br><code style="font-size:11px;">' . esc_html( $field ) . '</code></th><td>';
			echo '<select name="ktp_fm_map[' . esc_attr( $field ) . ']">';
			echo '<option value="__skip__">' . esc_html__( '（マッピングしない）', 'ktpwp' ) . '</option>';
			foreach ( $headers as $i => $h ) {
				$opt = esc_html( $h !== '' ? $h : (string) $i );
				echo '<option value="' . (int) $i . '"' . selected( $selected, $i, false ) . '>' . $opt . '</option>';
			}
			echo '</select></td></tr>';
		}

		echo '</tbody></table>';

		if ( $entity === self::ENTITY_CLIENT || $entity === self::ENTITY_SUPPLIER ) {
			echo '<p style="margin-top:12px;"><label><input type="checkbox" name="ktp_fm_skip_dup_email" value="1" checked /> ';
			echo esc_html__( 'メールアドレスが既に登録済みの行はスキップする', 'ktpwp' ) . '</label></p>';
		}
		if ( $entity === self::ENTITY_SERVICE ) {
			echo '<p style="margin-top:12px;"><label><input type="checkbox" name="ktp_fm_skip_dup_service_name" value="1" checked /> ';
			echo esc_html__( '同名の商品（サービス名）が既に登録済みの行はスキップする', 'ktpwp' ) . '</label></p>';
		}

		$confirm = __( 'データベースに追加します。よろしいですか？', 'ktpwp' );
		echo '<p><button type="submit" name="ktp_fm_import_run" class="button button-primary" onclick="return confirm(\'' . esc_js( $confirm ) . '\');">';
		echo esc_html__( '取り込みを実行', 'ktpwp' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=ktp-fm-import&ktp_fm_reset=1' ), 'ktp_fm_reset', 'ktp_fm_reset_nonce' ) ) . '">' . esc_html__( '最初からやり直す', 'ktpwp' ) . '</a></p>';
		echo '</form>';

		echo '<h3>' . esc_html__( 'プレビュー（先頭5行）', 'ktpwp' ) . '</h3>';
		echo '<div style="overflow:auto;max-height:280px;border:1px solid #ccd0d4;">';
		echo '<table class="widefat" style="font-size:12px;"><thead><tr>';
		foreach ( $headers as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( array_slice( $rows, 0, 5 ) as $r ) {
			echo '<tr>';
			foreach ( $headers as $ci => $_h ) {
				$cell = isset( $r[ $ci ] ) ? (string) $r[ $ci ] : '';
				$cell_short = function_exists( 'mb_substr' ) ? mb_substr( $cell, 0, 80 ) : substr( $cell, 0, 80 );
				echo '<td>' . esc_html( $cell_short ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		$bootstrap = array(
			'entity'  => $entity,
			'headers' => $headers,
			'samples' => array_slice( $rows, 0, 3 ),
		);
		$json_flags = JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$data_for_js = wp_json_encode( $bootstrap, $json_flags );
		if ( ! is_string( $data_for_js ) || $data_for_js === '' ) {
			$data_for_js = wp_json_encode(
				array(
					'entity'  => $entity,
					'headers' => array(),
					'samples' => array(),
				),
				JSON_UNESCAPED_UNICODE
			);
		}
		if ( ! is_string( $data_for_js ) ) {
			$data_for_js = '{"entity":"client","headers":[],"samples":[]}';
		}
		echo '<script type="application/json" id="ktp-fm-import-bootstrap">' . esc_html( $data_for_js ) . '</script>';

		echo '</div>';
	}

	/**
	 * @param string   $entity  Entity.
	 * @param string[] $headers Headers.
	 * @return array<string, int>
	 */
	private static function guess_for_entity( $entity, array $headers ): array {
		$entity = self::normalize_entity( $entity );
		if ( $entity === self::ENTITY_SUPPLIER ) {
			return self::guess_supplier_column_indexes( $headers );
		}
		if ( $entity === self::ENTITY_SERVICE ) {
			return self::guess_service_column_indexes( $headers );
		}
		return self::guess_client_column_indexes( $headers );
	}

	/**
	 * @param string[] $headers Header labels.
	 * @return array<string, int>
	 */
	private static function guess_client_column_indexes( array $headers ): array {
		return self::guess_by_rules(
			$headers,
			array(
				'company_name'        => array( '会社名', '取引先', '社名', '顧客名', 'company', 'client', '顧客' ),
				'name'                => array( '担当者', '氏名', '名前', 'name', 'contact', 'ご担当' ),
				'email'               => array( 'メール', 'e-mail', 'mail', 'email' ),
				'phone'               => array( '電話', 'tel', 'phone', '携帯' ),
				'postal_code'         => array( '郵便', 'zip', 'postal' ),
				'prefecture'          => array( '都道府県', 'prefecture' ),
				'city'                => array( '市区町村', 'city' ),
				'address'             => array( '住所', 'address' ),
				'building'            => array( '建物', 'ビル', 'building' ),
				'representative_name' => array( '代表', '代表者' ),
				'memo'                => array( 'メモ', '備考', 'memo', 'note' ),
				'category'            => array( 'カテゴリ', '分類', 'category' ),
				'tax_category'        => array( '税区分', '税', 'tax' ),
			)
		);
	}

	/**
	 * @param string[] $headers Header labels.
	 * @return array<string, int>
	 */
	private static function guess_supplier_column_indexes( array $headers ): array {
		return self::guess_by_rules(
			$headers,
			array(
				'company_name'             => array( '会社名', '社名', '協力会社', '仕入先', '外注', 'supplier', 'ベンダ' ),
				'name'                     => array( '担当者', '氏名', '名前', 'contact' ),
				'email'                    => array( 'メール', 'e-mail', 'mail', 'email' ),
				'phone'                    => array( '電話', 'tel', 'phone' ),
				'postal_code'              => array( '郵便', 'zip', 'postal' ),
				'prefecture'               => array( '都道府県', 'prefecture' ),
				'city'                     => array( '市区町村', 'city' ),
				'address'                  => array( '住所', 'address' ),
				'building'                 => array( '建物', 'ビル', 'building' ),
				'representative_name'      => array( '代表', '代表者' ),
				'qualified_invoice_number' => array( '適格', 'インボイス', '登録番号', 't+', 'invoice' ),
				'memo'                     => array( 'メモ', '備考', 'memo' ),
				'category'                 => array( 'カテゴリ', '分類', 'category' ),
				'tax_category'             => array( '税区分', '税', 'tax' ),
				'closing_day'              => array( '締め', '締日', 'closing' ),
				'payment_month'            => array( '支払月' ),
				'payment_day'              => array( '支払日' ),
				'payment_method'           => array( '支払方法', '振込', 'payment' ),
			)
		);
	}

	/**
	 * @param string[] $headers Header labels.
	 * @return array<string, int>
	 */
	private static function guess_service_column_indexes( array $headers ): array {
		return self::guess_by_rules(
			$headers,
			array(
				'service_name' => array( '商品', 'サービス', '品名', '名称', 'name', 'service', '項目' ),
				'price'        => array( '単価', '価格', '金額', 'price', '料金' ),
				'tax_rate'     => array( '税率', 'tax', '%' ),
				'unit'         => array( '単位', 'unit' ),
				'memo'         => array( 'メモ', '備考', '説明' ),
				'category'     => array( 'カテゴリ', '分類', 'category' ),
			)
		);
	}

	/**
	 * @param string[]                $headers Header labels.
	 * @param array<string, string[]> $rules   Field => keyword list.
	 * @return array<string, int>
	 */
	private static function guess_by_rules( array $headers, array $rules ): array {
		$norm = array();
		foreach ( $headers as $i => $h ) {
			$norm[ $i ] = self::str_to_lower_unicode( trim( (string) $h ) );
		}

		$out = array();
		foreach ( $rules as $field => $keywords ) {
			foreach ( $norm as $idx => $low ) {
				foreach ( $keywords as $kw ) {
					$kwl = self::str_to_lower_unicode( $kw );
					if ( $low !== '' && self::str_contains_unicode( $low, $kwl ) ) {
						$out[ $field ] = (int) $idx;
						break 2;
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<int, array<int, string>> $rows Data rows.
	 * @param string[]                       $headers Headers.
	 * @param array<string, int>             $map Field => column index.
	 * @param bool                           $skip_dup Skip duplicate emails.
	 * @return array{inserted: int, skipped: int, errors: int}
	 */
	private static function import_client_rows( array $rows, array $headers, array $map, bool $skip_dup ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_client';

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		$default_timing = 'postpay';

		foreach ( $rows as $row ) {
			$data = self::build_client_row_from_map( $row, $map );
			if ( trim( (string) ( $data['company_name'] ?? '' ) ) === '' ) {
				$skipped++;
				continue;
			}

			$email = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
			if ( $skip_dup && $email !== '' ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE email = %s", $email ) );
				if ( $exists > 0 ) {
					$skipped++;
					continue;
				}
			}

			$search = self::build_client_search_field( $data );

			$insert = array(
				'time'                   => current_time( 'mysql' ),
				'company_name'           => $data['company_name'],
				'name'                   => isset( $data['name'] ) ? $data['name'] : '',
				'email'                  => $email,
				'url'                    => isset( $data['url'] ) ? $data['url'] : '',
				'representative_name'    => isset( $data['representative_name'] ) ? $data['representative_name'] : '',
				'phone'                  => isset( $data['phone'] ) ? $data['phone'] : '',
				'postal_code'            => isset( $data['postal_code'] ) ? $data['postal_code'] : '',
				'prefecture'             => isset( $data['prefecture'] ) ? $data['prefecture'] : '',
				'city'                   => isset( $data['city'] ) ? $data['city'] : '',
				'address'                => isset( $data['address'] ) ? $data['address'] : '',
				'building'               => isset( $data['building'] ) ? $data['building'] : '',
				'closing_day'            => isset( $data['closing_day'] ) ? $data['closing_day'] : '',
				'payment_month'          => isset( $data['payment_month'] ) ? $data['payment_month'] : '',
				'payment_day'            => isset( $data['payment_day'] ) ? $data['payment_day'] : '',
				'payment_method'         => isset( $data['payment_method'] ) ? $data['payment_method'] : '',
				'tax_category'           => isset( $data['tax_category'] ) && $data['tax_category'] !== '' ? $data['tax_category'] : __( '内税', 'ktpwp' ),
				'payment_timing'         => isset( $data['payment_timing'] ) && in_array( $data['payment_timing'], array( 'postpay', 'prepay', 'prepay_wc' ), true ) ? $data['payment_timing'] : $default_timing,
				'memo'                   => isset( $data['memo'] ) ? $data['memo'] : '',
				'client_status'          => isset( $data['client_status'] ) && $data['client_status'] !== '' ? $data['client_status'] : __( '対象', 'ktpwp' ),
				'category'               => isset( $data['category'] ) ? $data['category'] : '',
				'selected_department_id' => null,
				'search_field'           => $search,
			);

			// time … category まで 21 列が文字列、selected_department_id、search_field。
			$formats = array_merge(
				array_fill( 0, 21, '%s' ),
				array( '%d', '%s' )
			);

			$res = $wpdb->insert( $table, $insert, $formats );
			if ( $res === false ) {
				$errors++;
			} else {
				$inserted++;
			}
		}

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * @param array<int, string>   $row CSV row.
	 * @param array<string, int>   $map Map.
	 * @return array<string, string>
	 */
	private static function build_client_row_from_map( array $row, array $map ): array {
		$out = array();
		foreach ( $map as $field => $col_idx ) {
			$val = isset( $row[ $col_idx ] ) ? trim( (string) $row[ $col_idx ] ) : '';
			switch ( $field ) {
				case 'email':
					$out[ $field ] = sanitize_email( $val );
					break;
				case 'url':
					$out[ $field ] = $val !== '' ? esc_url_raw( $val ) : '';
					break;
				case 'payment_timing':
					$v = strtolower( $val );
					if ( in_array( $v, array( 'prepay', 'postpay', 'prepay_wc' ), true ) ) {
						$out[ $field ] = $v;
					} elseif ( strpos( $v, '前' ) !== false || strpos( $v, '先払' ) !== false ) {
						$out[ $field ] = 'prepay';
					} else {
						$out[ $field ] = '';
					}
					break;
				default:
					$out[ $field ] = sanitize_text_field( $val );
			}
		}
		if ( ! isset( $out['company_name'] ) ) {
			$out['company_name'] = '';
		}
		return $out;
	}

	/**
	 * @param array<string, string> $data Row.
	 * @return string
	 */
	private static function build_client_search_field( array $data ): string {
		$parts = array(
			$data['company_name'] ?? '',
			$data['name'] ?? '',
			$data['email'] ?? '',
			$data['representative_name'] ?? '',
			$data['phone'] ?? '',
			$data['prefecture'] ?? '',
			$data['city'] ?? '',
			$data['address'] ?? '',
			$data['client_status'] ?? '',
			$data['category'] ?? '',
		);
		return implode( ' ', array_map( 'strval', $parts ) );
	}

	/**
	 * @param array<int, string>   $row CSV row.
	 * @param array<string, int>   $map Map.
	 * @return array<string, string>
	 */
	private static function build_supplier_row_from_map( array $row, array $map ): array {
		$out = array();
		foreach ( $map as $field => $col_idx ) {
			$val = isset( $row[ $col_idx ] ) ? trim( (string) $row[ $col_idx ] ) : '';
			switch ( $field ) {
				case 'email':
					$out[ $field ] = sanitize_email( $val );
					break;
				case 'url':
					$out[ $field ] = $val !== '' ? esc_url_raw( $val ) : '';
					break;
				case 'memo':
					$out[ $field ] = sanitize_textarea_field( $val );
					break;
				default:
					$out[ $field ] = sanitize_text_field( $val );
			}
		}
		if ( ! isset( $out['company_name'] ) ) {
			$out['company_name'] = '';
		}
		return $out;
	}

	/**
	 * @param array<string, string> $data Row.
	 * @return string
	 */
	private static function build_supplier_search_field( array $data ): string {
		return implode(
			', ',
			array(
				(string) current_time( 'timestamp' ),
				$data['company_name'] ?? '',
				$data['name'] ?? '',
				$data['email'] ?? '',
				$data['url'] ?? '',
				$data['representative_name'] ?? '',
				$data['phone'] ?? '',
				$data['postal_code'] ?? '',
				$data['prefecture'] ?? '',
				$data['city'] ?? '',
				$data['address'] ?? '',
				$data['building'] ?? '',
				$data['closing_day'] ?? '',
				$data['payment_month'] ?? '',
				$data['payment_day'] ?? '',
				$data['payment_method'] ?? '',
				$data['tax_category'] ?? '',
				$data['memo'] ?? '',
				$data['qualified_invoice_number'] ?? '',
				$data['category'] ?? '',
			)
		);
	}

	/**
	 * @param array<int, array<int, string>> $rows    Rows.
	 * @param string[]                       $headers Headers.
	 * @param array<string, int>             $map     Map.
	 * @param bool                             $skip_dup Skip duplicate email.
	 * @return array{inserted: int, skipped: int, errors: int}
	 */
	private static function import_supplier_rows( array $rows, array $headers, array $map, bool $skip_dup ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_supplier';

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			$data = self::build_supplier_row_from_map( $row, $map );
			if ( trim( (string) ( $data['company_name'] ?? '' ) ) === '' ) {
				$skipped++;
				continue;
			}

			$email = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
			if ( $skip_dup && $email !== '' ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE email = %s", $email ) );
				if ( $exists > 0 ) {
					$skipped++;
					continue;
				}
			}

			$defaults = array(
				'name'                     => '',
				'url'                      => '',
				'representative_name'      => '',
				'phone'                    => '',
				'postal_code'              => '',
				'prefecture'               => '',
				'city'                     => '',
				'address'                  => '',
				'building'                 => '',
				'closing_day'              => '',
				'payment_month'            => '',
				'payment_day'              => '',
				'payment_method'           => '',
				'tax_category'             => __( '内税', 'ktpwp' ),
				'memo'                     => '',
				'qualified_invoice_number' => '',
				'category'                 => __( 'General', 'ktpwp' ),
			);
			foreach ( $defaults as $k => $v ) {
				if ( ! isset( $data[ $k ] ) || $data[ $k ] === '' ) {
					$data[ $k ] = $v;
				}
			}
			if ( $data['tax_category'] === '' ) {
				$data['tax_category'] = __( '内税', 'ktpwp' );
			}

			$search = self::build_supplier_search_field( $data );

			$insert = array(
				'time'                     => current_time( 'timestamp' ),
				'company_name'             => $data['company_name'],
				'name'                     => $data['name'],
				'email'                    => $email,
				'url'                      => $data['url'],
				'representative_name'      => $data['representative_name'],
				'phone'                    => $data['phone'],
				'postal_code'              => $data['postal_code'],
				'prefecture'               => $data['prefecture'],
				'city'                     => $data['city'],
				'address'                  => $data['address'],
				'building'                 => $data['building'],
				'closing_day'              => $data['closing_day'],
				'payment_month'            => $data['payment_month'],
				'payment_day'              => $data['payment_day'],
				'payment_method'           => $data['payment_method'],
				'tax_category'             => $data['tax_category'],
				'memo'                     => $data['memo'],
				'qualified_invoice_number' => $data['qualified_invoice_number'],
				'category'                 => $data['category'],
				'search_field'             => $search,
			);

			$formats = array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			);

			$res = $wpdb->insert( $table, $insert, $formats );
			if ( $res === false ) {
				$errors++;
			} else {
				$inserted++;
			}
		}

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * @param array<int, string>   $row CSV row.
	 * @param array<string, int>   $map Map.
	 * @return array<string, mixed>
	 */
	private static function build_service_row_from_map( array $row, array $map ): array {
		$out = array();
		foreach ( $map as $field => $col_idx ) {
			$val = isset( $row[ $col_idx ] ) ? trim( (string) $row[ $col_idx ] ) : '';
			if ( $field === 'price' ) {
				$val = str_replace( array( ',', ' ', '　' ), '', $val );
				$out['price'] = is_numeric( $val ) ? (float) $val : 0.0;
				continue;
			}
			if ( $field === 'tax_rate' ) {
				$val = str_replace( array( ',', '%', ' ', '　' ), '', $val );
				if ( $val === '' || ! is_numeric( $val ) ) {
					$out['tax_rate'] = null;
				} else {
					$out['tax_rate'] = (float) $val;
				}
				continue;
			}
			if ( $field === 'memo' ) {
				$out['memo'] = sanitize_textarea_field( $val );
				continue;
			}
			$out[ $field ] = sanitize_text_field( $val );
		}
		if ( ! isset( $out['service_name'] ) ) {
			$out['service_name'] = '';
		}
		if ( ! isset( $out['price'] ) ) {
			$out['price'] = 0.0;
		}
		return $out;
	}

	/**
	 * @param array<int, array<int, string>> $rows    Rows.
	 * @param string[]                       $headers Headers.
	 * @param array<string, int>             $map     Map.
	 * @param bool                             $skip_dup Skip duplicate service_name.
	 * @return array{inserted: int, skipped: int, errors: int}
	 */
	private static function import_service_rows( array $rows, array $headers, array $map, bool $skip_dup ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_service';

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			$data = self::build_service_row_from_map( $row, $map );
			$name = trim( (string) ( $data['service_name'] ?? '' ) );
			if ( $name === '' ) {
				$skipped++;
				continue;
			}

			if ( $skip_dup ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE service_name = %s", $name ) );
				if ( $exists > 0 ) {
					$skipped++;
					continue;
				}
			}

			$unit     = isset( $data['unit'] ) ? (string) $data['unit'] : '';
			$memo     = isset( $data['memo'] ) ? (string) $data['memo'] : '';
			$category = isset( $data['category'] ) && $data['category'] !== '' ? (string) $data['category'] : __( 'General', 'ktpwp' );
			$price    = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
			$tax_rate = array_key_exists( 'tax_rate', $data ) ? $data['tax_rate'] : null;

			$search = implode(
				', ',
				array(
					current_time( 'mysql' ),
					$name,
					(string) $price,
					$tax_rate === null ? '' : (string) $tax_rate,
					$unit,
					$memo,
					$category,
				)
			);

			$insert = array(
				'time'         => current_time( 'mysql' ),
				'service_name' => $name,
				'price'        => $price,
			);
			$formats = array( '%s', '%s', '%f' );

			if ( $tax_rate !== null ) {
				$insert['tax_rate'] = $tax_rate;
				$formats[]        = '%f';
			}

			$insert['unit']         = $unit;
			$insert['memo']         = $memo;
			$insert['category']     = $category;
			$insert['image_url']    = '';
			$insert['frequency']    = 0;
			$insert['search_field'] = $search;

			$formats[] = '%s';
			$formats[] = '%s';
			$formats[] = '%s';
			$formats[] = '%s';
			$formats[] = '%d';
			$formats[] = '%s';

			$res = $wpdb->insert( $table, $insert, $formats );
			if ( $res === false ) {
				$errors++;
			} else {
				$inserted++;
			}
		}

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * @param array<int, array<int, string>> $rows    Rows.
	 * @param string[]                       $headers Headers.
	 * @param array<string, int>             $map     Map.
	 * @param bool                             $skip_dup_external Skip duplicate external_order_id.
	 * @return array{inserted: int, skipped: int, errors: int}
	 */
	private static function import_order_rows( array $rows, array $headers, array $map, bool $skip_dup_external ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_order';

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		$order_cols = self::ktp_order_table_columns();

		foreach ( $rows as $row ) {
			$d = self::build_order_import_row_from_map( $row, $map );
			$link_company = trim( (string) ( $d['link_company_name'] ?? '' ) );
			$link_email   = trim( (string) ( $d['link_email'] ?? '' ) );
			if ( $link_company === '' ) {
				$link_company = trim( (string) ( $d['customer_name'] ?? '' ) );
			}
			if ( $link_company === '' ) {
				$skipped++;
				continue;
			}

			$client_id = self::resolve_client_id_for_order_import( $link_company, $link_email );
			if ( $client_id <= 0 ) {
				$skipped++;
				continue;
			}

			$ext_key = trim( (string) ( $d['external_order_key'] ?? '' ) );
			if ( $ext_key === '' ) {
				$ext_key = substr( md5( $link_company . '|' . ( $d['project_name'] ?? '' ) . '|' . ( $d['order_date'] ?? '' ) . '|' . wp_json_encode( $row ) ), 0, 32 );
			}
			if ( $skip_dup_external && self::fm_order_external_exists( $ext_key ) ) {
				$skipped++;
				continue;
			}

			$project = trim( (string) ( $d['project_name'] ?? '' ) );
			if ( $project === '' ) {
				$project = __( 'FileMaker取込', 'ktpwp' );
			}
			$cust_display = trim( (string) ( $d['customer_name'] ?? '' ) );
			if ( $cust_display === '' ) {
				$cust_display = $link_company;
			}
			$user_name = trim( (string) ( $d['user_name'] ?? '' ) );
			$memo      = isset( $d['memo'] ) ? sanitize_textarea_field( (string) $d['memo'] ) : '';
			$ts        = self::fm_parse_date_to_timestamp( (string) ( $d['order_date'] ?? '' ) );
			$progress  = self::fm_map_progress_label_to_int( (string) ( $d['progress_label'] ?? '' ) );

			$order_number = self::fm_generate_import_order_number();
			$now          = current_time( 'mysql' );
			$search       = sanitize_text_field( implode( ', ', array_filter( array( $cust_display, $user_name, $project ) ) ) );

			$insert  = array();
			$formats = array();
			$add     = static function ( $col, $val, $fmt ) use ( &$insert, &$formats, $order_cols ) {
				if ( in_array( $col, $order_cols, true ) ) {
					$insert[ $col ] = $val;
					$formats[]    = $fmt;
				}
			};

			$add( 'order_number', $order_number, '%s' );
			$add( 'time', $ts, '%d' );
			$add( 'client_id', $client_id, '%d' );
			$add( 'customer_name', sanitize_text_field( $cust_display ), '%s' );
			$add( 'user_name', sanitize_text_field( $user_name ), '%s' );
			$add( 'project_name', sanitize_text_field( $project ), '%s' );
			$add( 'invoice_items', '', '%s' );
			$add( 'cost_items', '', '%s' );
			$add( 'memo', $memo, '%s' );
			$add( 'search_field', $search, '%s' );
			$add( 'company_name', sanitize_text_field( $link_company ), '%s' );
			$add( 'progress', $progress, '%d' );
			$add( 'created_at', $now, '%s' );
			$add( 'updated_at', $now, '%s' );
			if ( in_array( 'external_source', $order_cols, true ) && in_array( 'external_order_id', $order_cols, true ) ) {
				$add( 'external_source', 'fm_pro_export', '%s' );
				$add( 'external_order_id', substr( $ext_key, 0, 100 ), '%s' );
			}

			if ( $insert === array() ) {
				$errors++;
				continue;
			}

			$res = $wpdb->insert( $table, $insert, $formats );
			if ( $res === false ) {
				$errors++;
				continue;
			}
			$new_id = (int) $wpdb->insert_id;
			self::fm_finalize_new_order_children( $new_id );
			$inserted++;
		}

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * @return list<string>
	 */
	private static function ktp_order_table_columns(): array {
		static $cols = null;
		if ( $cols !== null ) {
			return $cols;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'ktp_order';
		$c = $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`", 0 );
		$cols = is_array( $c ) ? $c : array();

		return $cols;
	}

	/**
	 * @param array<int, string>   $row Row.
	 * @param array<string, int>   $map Map.
	 * @return array<string, string>
	 */
	private static function build_order_import_row_from_map( array $row, array $map ): array {
		$out = array();
		foreach ( $map as $field => $idx ) {
			$val = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
			if ( $field === 'memo' ) {
				$out[ $field ] = $val;
			} else {
				$out[ $field ] = sanitize_text_field( $val );
			}
		}

		return $out;
	}

	/**
	 * @param string $company Company name.
	 * @param string $email   Email.
	 * @return int client id or 0.
	 */
	private static function resolve_client_id_for_order_import( $company, $email ): int {
		global $wpdb;
		$company = trim( (string) $company );
		$email   = trim( (string) $email );
		$t       = $wpdb->prefix . 'ktp_client';

		if ( $company !== '' ) {
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE company_name = %s LIMIT 1", $company ) );
			if ( $id ) {
				return (int) $id;
			}
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE TRIM(company_name) = %s LIMIT 1", $company ) );
			if ( $id ) {
				return (int) $id;
			}
		}
		if ( $email !== '' && is_email( $email ) ) {
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE email = %s LIMIT 1", $email ) );
			if ( $id ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * @param string $ext_key External key.
	 * @return bool
	 */
	private static function fm_order_external_exists( $ext_key ): bool {
		$ext_key = trim( (string) $ext_key );
		if ( $ext_key === '' ) {
			return false;
		}
		$cols = self::ktp_order_table_columns();
		if ( ! in_array( 'external_source', $cols, true ) || ! in_array( 'external_order_id', $cols, true ) ) {
			return false;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'ktp_order';
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$t}` WHERE external_source = %s AND external_order_id = %s",
				'fm_pro_export',
				substr( $ext_key, 0, 100 )
			)
		);

		return $n > 0;
	}

	/**
	 * @return string
	 */
	private static function fm_generate_import_order_number(): string {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_order';
		$ts    = (int) current_time( 'timestamp' );
		$pref  = 'FM-' . gmdate( 'Ymd', $ts ) . '-';
		$cnt   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE order_number LIKE %s",
				$wpdb->esc_like( $pref ) . '%'
			)
		);

		return $pref . str_pad( (string) ( $cnt + 1 ), 4, '0', STR_PAD_LEFT );
	}

	/**
	 * @param string $s Date string.
	 * @return int Unix timestamp.
	 */
	private static function fm_parse_date_to_timestamp( $s ): int {
		$s = trim( (string) $s );
		if ( $s === '' || $s === '?' ) {
			return (int) current_time( 'timestamp' );
		}
		if ( preg_match( '/^(\d{4})[.\/](\d{1,2})[.\/](\d{1,2})/u', $s, $m ) ) {
			$try = strtotime( sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] ) );
			if ( $try !== false ) {
				return (int) $try;
			}
		}
		$try = strtotime( $s );
		if ( $try !== false ) {
			return (int) $try;
		}

		return (int) current_time( 'timestamp' );
	}

	/**
	 * @param string $label Progress label (JP).
	 * @return int 1..7
	 */
	private static function fm_map_progress_label_to_int( $label ): int {
		$label = trim( (string) $label );
		if ( $label === '' ) {
			return 1;
		}
		$low = self::str_to_lower_unicode( $label );
		if ( self::str_contains_unicode( $low, 'ボツ' ) || self::str_contains_unicode( $low, '却下' ) ) {
			return 7;
		}
		if ( self::str_contains_unicode( $low, '入金' ) ) {
			return 6;
		}
		if ( self::str_contains_unicode( $low, '請求' ) ) {
			return 5;
		}
		if ( self::str_contains_unicode( $low, '完了' ) ) {
			return 4;
		}
		if ( self::str_contains_unicode( $low, '受注' ) && ! self::str_contains_unicode( $low, '受付' ) ) {
			return 3;
		}
		if ( self::str_contains_unicode( $low, '見積' ) ) {
			return 2;
		}
		if ( self::str_contains_unicode( $low, '受付' ) ) {
			return 1;
		}
		if ( preg_match( '/^[1-7]$/', $label ) ) {
			return (int) $label;
		}

		return 1;
	}

	/**
	 * @param int $order_id Order id.
	 * @return void
	 */
	private static function fm_finalize_new_order_children( $order_id ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}
		if ( ! self::fm_load_order_main_class() ) {
			return;
		}
		try {
			$order_class = new KTPWP_Order_Class();
			$order_class->Create_Initial_Invoice_Item( $order_id );
			$order_class->Create_Initial_Cost_Item( $order_id );
			if ( ! defined( 'KTPWP_DUMMY_DATA_CREATION' ) || ! KTPWP_DUMMY_DATA_CREATION ) {
				$uid = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
				$order_class->Create_Initial_Staff_Chat( $order_id, $uid );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'KTPWP_FM_Import finalize order: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * @return bool
	 */
	private static function fm_load_order_main_class(): bool {
		if ( class_exists( 'KTPWP_Order_Class', false ) ) {
			return true;
		}
		$candidates = array();
		if ( defined( 'KTPWP_PLUGIN_DIR' ) && is_string( KTPWP_PLUGIN_DIR ) ) {
			$candidates[] = trailingslashit( KTPWP_PLUGIN_DIR ) . 'includes/class-ktpwp-order-main.php';
		}
		$candidates[] = dirname( __DIR__ ) . '/includes/class-ktpwp-order-main.php';
		foreach ( $candidates as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return class_exists( 'KTPWP_Order_Class', false );
			}
		}

		return false;
	}

	/**
	 * @param string $raw File contents.
	 * @return array{headers: string[], rows: array<int, array<int, string>>}|WP_Error
	 */
	private static function parse_delimited_text( $raw ) {
		$raw = (string) $raw;
		if ( strncmp( $raw, "\xEF\xBB\xBF", 3 ) === 0 ) {
			$raw = substr( $raw, 3 );
		}
		$lines = preg_split( '/\R/u', $raw, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $lines ) || $lines === array() ) {
			return new WP_Error( 'ktp_fm_empty', __( '有効な行がありません。', 'ktpwp' ) );
		}

		$first = $lines[0];
		$delim = self::detect_delimiter( $first );
		if ( $delim === null ) {
			return new WP_Error( 'ktp_fm_delim', __( '区切り文字を判定できませんでした（カンマまたはタブを含む1行目が必要です）。', 'ktpwp' ) );
		}

		$headers = str_getcsv( $first, $delim );
		if ( ! is_array( $headers ) || $headers === array() ) {
			return new WP_Error( 'ktp_fm_header', __( 'ヘッダー行を読み取れませんでした。', 'ktpwp' ) );
		}
		$headers = array_map(
			static function ( $h ) {
				return sanitize_text_field( trim( (string) $h ) );
			},
			$headers
		);

		$col_count = count( $headers );
		$rows      = array();
		for ( $i = 1, $len = count( $lines ); $i < $len; $i++ ) {
			$cells = str_getcsv( $lines[ $i ], $delim );
			if ( ! is_array( $cells ) ) {
				continue;
			}
			$norm = array();
			for ( $c = 0; $c < $col_count; $c++ ) {
				$norm[ $c ] = isset( $cells[ $c ] ) ? sanitize_text_field( (string) $cells[ $c ] ) : '';
			}
			if ( count( array_filter( $norm ) ) === 0 ) {
				continue;
			}
			$rows[] = $norm;
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * @param string $first_line First line.
	 * @return non-falsy-string|null
	 */
	private static function detect_delimiter( $first_line ) {
		$tab = substr_count( $first_line, "\t" );
		$com = substr_count( $first_line, ',' );
		if ( $tab === 0 && $com === 0 ) {
			return null;
		}
		return $tab >= $com ? "\t" : ',';
	}

	/**
	 * KantanPro（FileMaker）標準エクスポートの *.tab（1行目がヘッダーでない）か。
	 *
	 * @param string $leaf basename.
	 * @return bool
	 */
	private static function is_fm_export_tab_leaf( $leaf ): bool {
		$leaf = strtolower( (string) $leaf );

		return (bool) preg_match( '/^(user|order|goods|therdparty|cost|setting|work|item|image|main|user_mail)\.tab$/', $leaf );
	}

	/**
	 * Zip 内ファイルごとに区切りテキストを解析（FM .tab は COL_1 形式の疑似ヘッダー）。
	 *
	 * @param string $raw        File body.
	 * @param string $inner_path Zip 内パス.
	 * @return array{headers: string[], rows: array<int, array<int, string>>}|WP_Error
	 */
	private static function parse_tabular_for_zip_entry( $raw, $inner_path ) {
		$leaf = strtolower( basename( (string) $inner_path ) );
		if ( self::is_fm_export_tab_leaf( $leaf ) ) {
			return self::parse_fm_tab_as_col_headers( $raw );
		}
		// user.csv は拡張子が csv でも FileMaker 由来でタブ区切り・ヘッダー無しのことがある。
		if ( $leaf === 'user.csv' ) {
			$probe = (string) $raw;
			if ( strncmp( $probe, "\xEF\xBB\xBF", 3 ) === 0 ) {
				$probe = substr( $probe, 3 );
			}
			$probe  = str_replace( array( "\r\n", "\r" ), "\n", $probe );
			$lines  = preg_split( '/\n/', $probe, 2 );
			$first  = isset( $lines[0] ) ? (string) $lines[0] : '';
			$tab    = substr_count( $first, "\t" );
			$com    = substr_count( $first, ',' );
			if ( $tab > 0 && $tab >= $com ) {
				return self::parse_fm_tab_as_col_headers( $raw );
			}
		}

		return self::parse_delimited_text( $raw );
	}

	/**
	 * Zip 内取り込み順: user.tab → user.csv → その他（先に顧客マスタを確実にする）。
	 *
	 * @param string $inner_path Zip 内パス.
	 * @return int 小さいほど先.
	 */
	private static function user_export_file_rank( $inner_path ): int {
		$b = strtolower( basename( (string) $inner_path ) );
		if ( $b === 'user.tab' ) {
			return 0;
		}
		if ( $b === 'user.csv' ) {
			return 1;
		}

		return 100;
	}

	/**
	 * FileMaker エクスポート想定: 全行をデータとみなし、列名を COL_1 … COL_n とする。
	 *
	 * @param string $raw Raw.
	 * @return array{headers: string[], rows: array<int, array<int, string>>}|WP_Error
	 */
	private static function parse_fm_tab_as_col_headers( $raw ) {
		$raw = (string) $raw;
		if ( strncmp( $raw, "\xEF\xBB\xBF", 3 ) === 0 ) {
			$raw = substr( $raw, 3 );
		}
		$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$lines = preg_split( '/\R/u', $raw, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $lines ) || $lines === array() ) {
			return new WP_Error( 'ktp_fm_empty', __( '有効な行がありません。', 'ktpwp' ) );
		}

		$first = $lines[0];
		$delim = self::detect_delimiter( $first );
		if ( $delim === null ) {
			return new WP_Error( 'ktp_fm_delim', __( '区切り文字を判定できませんでした。', 'ktpwp' ) );
		}

		$matrix = array();
		$max    = 0;
		foreach ( $lines as $line ) {
			$cells = str_getcsv( (string) $line, $delim );
			if ( ! is_array( $cells ) ) {
				continue;
			}
			$n = count( $cells );
			if ( $n > $max ) {
				$max = $n;
			}
			$matrix[] = $cells;
		}
		if ( $max < 1 || $matrix === array() ) {
			return new WP_Error( 'ktp_fm_empty', __( '有効な行がありません。', 'ktpwp' ) );
		}

		$headers = array();
		for ( $c = 0; $c < $max; $c++ ) {
			$headers[] = 'COL_' . (string) ( $c + 1 );
		}

		$rows = array();
		foreach ( $matrix as $cells ) {
			$norm = array();
			for ( $c = 0; $c < $max; $c++ ) {
				$cell       = $cells[ $c ] ?? '';
				$norm[ $c ] = sanitize_text_field( is_string( $cell ) ? trim( $cell ) : (string) $cell );
			}
			if ( count( array_filter( $norm ) ) === 0 ) {
				continue;
			}
			$rows[] = $norm;
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * @return list<string>
	 */
	private static function allowed_entities(): array {
		return array( self::ENTITY_CLIENT, self::ENTITY_SUPPLIER, self::ENTITY_SERVICE, self::ENTITY_ORDER );
	}

	/**
	 * @param string $entity Entity slug.
	 * @return string
	 */
	private static function entity_label( $entity ): string {
		$entity = self::normalize_entity( $entity );
		if ( $entity === self::ENTITY_SUPPLIER ) {
			return __( '協力会社', 'ktpwp' );
		}
		if ( $entity === self::ENTITY_SERVICE ) {
			return __( '商品（サービス）', 'ktpwp' );
		}
		if ( $entity === self::ENTITY_ORDER ) {
			return __( '受注', 'ktpwp' );
		}
		return __( '顧客', 'ktpwp' );
	}

	/**
	 * @param string $entity Entity slug.
	 * @return string
	 */
	private static function normalize_entity( $entity ): string {
		if ( is_array( $entity ) || is_object( $entity ) ) {
			return self::ENTITY_CLIENT;
		}
		$e = sanitize_key( (string) $entity );
		return in_array( $e, self::allowed_entities(), true ) ? $e : self::ENTITY_CLIENT;
	}

	/**
	 * mbstring 未環境でも落ちないよう小文字化（日本語は mb 推奨）。
	 *
	 * @param string $str Input.
	 * @return string
	 */
	private static function str_to_lower_unicode( $str ): string {
		$str = (string) $str;
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $str, 'UTF-8' );
		}
		return strtolower( $str );
	}

	/**
	 * mbstring 未環境でも落ちないよう部分文字列判定。
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	private static function str_contains_unicode( $haystack, $needle ): bool {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( $needle === '' ) {
			return false;
		}
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( $haystack, $needle, 0, 'UTF-8' ) !== false;
		}
		return strpos( $haystack, $needle ) !== false;
	}

	/**
	 * @param string $tmp_path Temp path.
	 * @param string $ext      Extension (csv or zip).
	 * @param string $outer_name Sanitized outer filename (by ref: zip 時は「外側 / 内側」に書き換え).
	 * @return string|WP_Error
	 */
	private static function read_upload_delimited_raw( $tmp_path, $ext, &$outer_name ) {
		if ( $ext !== 'zip' ) {
			$raw = file_get_contents( $tmp_path );
			return is_string( $raw ) ? $raw : new WP_Error( 'ktp_fm_read', __( 'ファイルを読み取れませんでした。', 'ktpwp' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ktp_fm_zip', __( 'このサーバーでは Zip を展開できません（ZipArchive がありません）。', 'ktpwp' ) );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $tmp_path ) !== true ) {
			return new WP_Error( 'ktp_fm_zip_open', __( 'Zip ファイルを開けませんでした。', 'ktpwp' ) );
		}

		$candidates = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! is_array( $st ) || empty( $st['name'] ) ) {
				continue;
			}
			$fn = (string) $st['name'];
			if ( preg_match( '#(^|/)\.\.#', $fn ) ) {
				continue;
			}
			if ( substr( $fn, -1 ) === '/' || strpos( $fn, '__MACOSX/' ) === 0 ) {
				continue;
			}
			$leaf = basename( $fn );
			$ie   = strtolower( pathinfo( $leaf, PATHINFO_EXTENSION ) );
			if ( in_array( $ie, array( 'csv', 'tsv', 'tab', 'txt' ), true ) ) {
				$candidates[] = $fn;
			}
		}
		sort( $candidates, SORT_STRING );
		if ( $candidates === array() ) {
			$zip->close();
			return new WP_Error( 'ktp_fm_zip_empty', __( 'Zip 内に csv/tsv/tab/txt が見つかりませんでした。', 'ktpwp' ) );
		}

		$first = $candidates[0];
		$raw   = $zip->getFromName( $first );
		$zip->close();

		if ( ! is_string( $raw ) || $raw === '' ) {
			return new WP_Error( 'ktp_fm_zip_read', __( 'Zip 内のテーブルデータを読み取れませんでした。', 'ktpwp' ) );
		}

		$outer_name = $outer_name . ' / ' . $first;

		return $raw;
	}

	/**
	 * @return string
	 */
	private static function transient_key_for_user(): string {
		return self::TRANSIENT_PREFIX . (string) get_current_user_id();
	}

	/**
	 * @return string
	 */
	private static function transient_zip_session_key(): string {
		return self::TRANSIENT_ZIP_PREFIX . (string) get_current_user_id();
	}

	/**
	 * @return string
	 */
	private static function transient_report_key(): string {
		return self::TRANSIENT_RPT_PREFIX . (string) get_current_user_id();
	}

	/**
	 * アップロード済み Zip の一時ファイルを削除（transient 参照があれば）。
	 *
	 * @return void
	 */
	private static function cleanup_zip_stored_file(): void {
		$raw = get_transient( self::transient_zip_session_key() );
		$s   = self::sanitize_zip_session( $raw );
		if ( $s !== null && ! empty( $s['path'] ) && self::is_valid_stored_zip_path( $s['path'] ) ) {
			@unlink( $s['path'] );
		}
	}

	/**
	 * @return string|WP_Error 絶対パス
	 */
	private static function allocate_zip_storage_path() {
		$dir = self::zip_storage_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$name = 'import-' . (int) get_current_user_id() . '-' . wp_generate_password( 12, false, false ) . '.zip';
		$full = trailingslashit( $dir ) . $name;
		return $full;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function zip_storage_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'ktp_fm_upload_dir', __( 'アップロードディレクトリが利用できません。', 'ktpwp' ) );
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'ktp-fm-import-tmp';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'ktp_fm_mkdir', __( '一時ディレクトリを作成できませんでした。', 'ktpwp' ) );
		}
		$ht = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $ht ) ) {
			@file_put_contents( $ht, "deny from all\n" );
		}
		return $dir;
	}

	/**
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private static function is_valid_stored_zip_path( $path ): bool {
		$path = wp_normalize_path( (string) $path );
		if ( $path === '' || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		$upload = wp_upload_dir();
		if ( ! empty( $upload['basedir'] ) ) {
			$base = wp_normalize_path( trailingslashit( $upload['basedir'] ) . 'ktp-fm-import-tmp/' );
			if ( strpos( $path, $base ) === 0 ) {
				$bn = basename( $path );
				return (bool) preg_match( '/^import-' . (int) get_current_user_id() . '-[a-zA-Z0-9]+\.zip$/', $bn );
			}
		}
		return false;
	}

	/**
	 * @param mixed $session Raw transient.
	 * @return array{path: string, orig_name: string, uploaded_at: int}|null
	 */
	private static function sanitize_zip_session( $session ) {
		if ( ! is_array( $session ) || empty( $session['path'] ) ) {
			return null;
		}
		$path = wp_normalize_path( (string) $session['path'] );
		if ( ! self::is_valid_stored_zip_path( $path ) ) {
			return null;
		}
		return array(
			'path'        => $path,
			'orig_name'   => isset( $session['orig_name'] ) ? sanitize_file_name( (string) $session['orig_name'] ) : '',
			'uploaded_at' => isset( $session['uploaded_at'] ) ? (int) $session['uploaded_at'] : 0,
		);
	}

	/**
	 * @param array{path: string, orig_name: string, uploaded_at: int} $zip_sess Zip session.
	 * @return void
	 */
	private static function render_zip_pending_ui( array $zip_sess ): void {
		echo '<div class="card" style="max-width:920px;margin:16px 0;padding:16px;">';
		echo '<h2>' . esc_html__( 'Zip を受け付け済み', 'ktpwp' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'ファイル:', 'ktpwp' ) . '</strong> ' . esc_html( $zip_sess['orig_name'] ) . '</p>';
		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_AI_ZIP );
		echo '<p><label><input type="checkbox" name="ktp_fm_zip_skip_dup_email" value="1" checked /> ';
		echo esc_html__( '顧客・協力会社: メールが既に登録済みの行はスキップ', 'ktpwp' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="ktp_fm_zip_skip_dup_service" value="1" checked /> ';
		echo esc_html__( '商品: 同名のサービスが既に登録済みの行はスキップ', 'ktpwp' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="ktp_fm_zip_skip_dup_order" value="1" checked /> ';
		echo esc_html__( '受注: 同一の外部ID（取り込み用）が既に登録済みの行はスキップ', 'ktpwp' ) . '</label></p>';
		$confirm = __( 'OpenAI に Zip の概要を送信し、判別結果に基づきデータベースへ書き込みます。よろしいですか？', 'ktpwp' );
		echo '<p><button type="submit" name="ktp_fm_ai_zip_run" class="button button-primary" onclick="return confirm(\'' . esc_js( $confirm ) . '\');">';
		echo esc_html__( 'AI で解析して取り込む', 'ktpwp' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=ktp-fm-import&ktp_fm_reset=1' ), 'ktp_fm_reset', 'ktp_fm_reset_nonce' ) ) . '">' . esc_html__( '最初からやり直す', 'ktpwp' ) . '</a></p>';
		echo '</form></div>';
	}

	/**
	 * @return void
	 */
	private static function render_last_import_report(): void {
		$raw = get_transient( self::transient_report_key() );
		if ( ! is_array( $raw ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin.php?page=ktp-fm-import&ktp_fm_dismiss_report=1' ), 'ktp_fm_dismiss_report', 'ktp_fm_dismiss_nonce' );

		echo '<div class="card" style="max-width:960px;margin:16px 0;padding:16px;border-left:4px solid #2271b1;">';
		echo '<h2>' . esc_html__( '直近の取り込みレポート', 'ktpwp' ) . '</h2>';
		if ( ! empty( $raw['zip_name'] ) ) {
			echo '<p><strong>' . esc_html__( 'Zip:', 'ktpwp' ) . '</strong> ' . esc_html( (string) $raw['zip_name'] ) . '</p>';
		}

		if ( ! empty( $raw['imported'] ) && is_array( $raw['imported'] ) ) {
			echo '<h3>' . esc_html__( '取り込んだファイル', 'ktpwp' ) . '</h3>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Zip 内パス', 'ktpwp' ) . '</th>';
			echo '<th>' . esc_html__( '種別', 'ktpwp' ) . '</th>';
			echo '<th>' . esc_html__( '追加', 'ktpwp' ) . '</th><th>' . esc_html__( 'スキップ', 'ktpwp' ) . '</th><th>' . esc_html__( 'エラー', 'ktpwp' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $raw['imported'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				echo '<tr>';
				echo '<td><code>' . esc_html( isset( $row['path'] ) ? (string) $row['path'] : '' ) . '</code></td>';
				echo '<td>' . esc_html( isset( $row['entity_label'] ) ? (string) $row['entity_label'] : '' ) . '</td>';
				echo '<td>' . (int) ( $row['inserted'] ?? 0 ) . '</td>';
				echo '<td>' . (int) ( $row['skipped'] ?? 0 ) . '</td>';
				echo '<td>' . (int) ( $row['errors'] ?? 0 ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">' . esc_html__( '表ファイルとして DB に書き込んだものはありません（すべてスキップ・失敗・または対象外だった可能性があります）。', 'ktpwp' ) . '</p>';
		}

		if ( ! empty( $raw['skipped_by_ai'] ) && is_array( $raw['skipped_by_ai'] ) ) {
			echo '<h3>' . esc_html__( 'AI が取り込み対象外と判断したファイル', 'ktpwp' ) . '</h3>';
			echo '<ul style="list-style:disc;margin-left:1.5em;">';
			foreach ( $raw['skipped_by_ai'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				echo '<li><code>' . esc_html( isset( $row['path'] ) ? (string) $row['path'] : '' ) . '</code> — ' . esc_html( isset( $row['reason'] ) ? (string) $row['reason'] : '' ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $raw['failed'] ) && is_array( $raw['failed'] ) ) {
			echo '<h3>' . esc_html__( '取り込めなかったファイル', 'ktpwp' ) . '</h3>';
			echo '<ul style="list-style:disc;margin-left:1.5em;">';
			foreach ( $raw['failed'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				echo '<li><code>' . esc_html( isset( $row['path'] ) ? (string) $row['path'] : '' ) . '</code> — ' . esc_html( isset( $row['message'] ) ? (string) $row['message'] : '' ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $raw['not_assigned'] ) && is_array( $raw['not_assigned'] ) ) {
			echo '<h3>' . esc_html__( 'AI の応答に含まれなかった Zip 内ファイル', 'ktpwp' ) . '</h3>';
			echo '<ul style="list-style:disc;margin-left:1.5em;">';
			foreach ( $raw['not_assigned'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				echo '<li><code>' . esc_html( isset( $row['path'] ) ? (string) $row['path'] : '' ) . '</code> — ' . esc_html( isset( $row['message'] ) ? (string) $row['message'] : '' ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $raw['other_files'] ) && is_array( $raw['other_files'] ) ) {
			echo '<h3>' . esc_html__( '表形式（csv/tsv/tab/txt）以外の Zip 内ファイル（自動取り込みの対象外）', 'ktpwp' ) . '</h3>';
			echo '<p class="description">' . esc_html__( '画像・PDF・xlsx などはこの機能では取り込みません。', 'ktpwp' ) . '</p>';
			echo '<ul style="list-style:disc;margin-left:1.5em;max-height:220px;overflow:auto;">';
			foreach ( $raw['other_files'] as $op ) {
				if ( ! is_string( $op ) || $op === '' ) {
					continue;
				}
				echo '<li><code>' . esc_html( $op ) . '</code></li>';
			}
			echo '</ul>';
		}

		echo '<p><a class="button" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'レポートを閉じる', 'ktpwp' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * @param string $zip_path Absolute path to zip.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function build_zip_manifest_for_ai( $zip_path ) {
		if ( ! class_exists( 'ZipArchive', false ) ) {
			return new WP_Error( 'ktp_fm_zip', __( 'ZipArchive がありません。', 'ktpwp' ) );
		}
		$zip = new ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			return new WP_Error( 'ktp_fm_zip_open', __( 'Zip を開けませんでした。', 'ktpwp' ) );
		}

		$candidates = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! is_array( $st ) || empty( $st['name'] ) ) {
				continue;
			}
			$fn = (string) $st['name'];
			if ( preg_match( '#(^|/)\.\.#', $fn ) ) {
				continue;
			}
			if ( substr( $fn, -1 ) === '/' || strpos( $fn, '__MACOSX/' ) === 0 ) {
				continue;
			}
			$leaf = basename( $fn );
			$ie   = strtolower( pathinfo( $leaf, PATHINFO_EXTENSION ) );
			if ( in_array( $ie, array( 'csv', 'tsv', 'tab', 'txt' ), true ) ) {
				$candidates[] = $fn;
			}
		}
		sort( $candidates, SORT_STRING );
		$candidates = array_slice( $candidates, 0, self::MAX_FILES_IN_ZIP_AI );

		$out = array();
		foreach ( $candidates as $fn ) {
			$raw = $zip->getFromName( $fn );
			if ( ! is_string( $raw ) || $raw === '' ) {
				$out[] = array(
					'path'        => $fn,
					'headers'     => array(),
					'sample_rows' => array(),
					'parse_error' => __( '空または読み取れませんでした。', 'ktpwp' ),
				);
				continue;
			}
			if ( strlen( $raw ) > self::MAX_INNER_SNAPSHOT_BYTES ) {
				$raw = substr( $raw, 0, self::MAX_INNER_SNAPSHOT_BYTES );
			}
			$parsed = self::parse_tabular_for_zip_entry( $raw, $fn );
			if ( is_wp_error( $parsed ) ) {
				$out[] = array(
					'path'        => $fn,
					'headers'     => array(),
					'sample_rows' => array(),
					'parse_error' => $parsed->get_error_message(),
				);
				continue;
			}
			$rows = $parsed['rows'];
			if ( count( $rows ) > self::MAX_DATA_ROWS ) {
				$rows = array_slice( $rows, 0, self::MAX_DATA_ROWS );
			}
			$out[] = array(
				'path'        => $fn,
				'headers'     => $parsed['headers'],
				'sample_rows' => array_slice( $rows, 0, 3 ),
				'parse_error' => null,
				'row_count'   => count( $rows ),
			);
		}
		$zip->close();
		return $out;
	}

	/**
	 * Zip 内で csv/tsv/tab/txt 以外のファイルパスを列挙（レポート用・最大150件）。
	 *
	 * @param string $zip_path Absolute path.
	 * @return list<string>
	 */
	private static function list_zip_non_tabular_entries( $zip_path ): array {
		if ( ! class_exists( 'ZipArchive', false ) ) {
			return array();
		}
		$zip = new ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			return array();
		}
		$tab_ext = array( 'csv', 'tsv', 'tab', 'txt' );
		$out     = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! is_array( $st ) || empty( $st['name'] ) ) {
				continue;
			}
			$fn = (string) $st['name'];
			if ( preg_match( '#(^|/)\.\.#', $fn ) ) {
				continue;
			}
			if ( substr( $fn, -1 ) === '/' || strpos( $fn, '__MACOSX/' ) === 0 ) {
				continue;
			}
			$leaf = basename( $fn );
			$ie   = strtolower( pathinfo( $leaf, PATHINFO_EXTENSION ) );
			if ( in_array( $ie, $tab_ext, true ) ) {
				continue;
			}
			$out[] = $fn;
			if ( count( $out ) >= 150 ) {
				break;
			}
		}
		$zip->close();
		sort( $out, SORT_STRING );
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $manifest Manifest.
	 * @return string JSON string
	 */
	private static function truncate_manifest_json_for_openai( array $manifest ): string {
		$payload = array( 'files' => array() );
		foreach ( $manifest as $m ) {
			if ( ! is_array( $m ) || empty( $m['path'] ) ) {
				continue;
			}
			$headers = isset( $m['headers'] ) && is_array( $m['headers'] ) ? $m['headers'] : array();
			$samples = isset( $m['sample_rows'] ) && is_array( $m['sample_rows'] ) ? $m['sample_rows'] : array();
			$entry   = array(
				'path'        => (string) $m['path'],
				'headers'     => array_slice( array_map( 'strval', $headers ), 0, 200 ),
				'sample_rows' => array_slice( $samples, 0, 3 ),
				'parse_error' => isset( $m['parse_error'] ) && $m['parse_error'] !== null ? (string) $m['parse_error'] : null,
				'row_count'   => isset( $m['row_count'] ) ? (int) $m['row_count'] : null,
			);
			$payload['files'][] = $entry;
		}
		$flags = JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$json = wp_json_encode( $payload, $flags );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		while ( strlen( $json ) > self::MAX_AI_USER_JSON_CHARS && count( $payload['files'] ) > 1 ) {
			array_pop( $payload['files'] );
			$json = wp_json_encode( $payload, $flags );
			if ( ! is_string( $json ) ) {
				break;
			}
		}
		if ( is_string( $json ) && strlen( $json ) > self::MAX_AI_USER_JSON_CHARS ) {
			$json = substr( $json, 0, self::MAX_AI_USER_JSON_CHARS );
		}
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * @param string $api_key API key.
	 * @param string $user_json User JSON string.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function openai_zip_plan_request( $api_key, $user_json ) {
		$system = self::build_zip_ai_system_prompt();
		$body   = self::openai_chat_completion_request_body( $system, $user_json );
		$res    = self::openai_remote_post( $api_key, $body );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$content = $res['content'];
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['per_file'] ) || ! is_array( $decoded['per_file'] ) ) {
			return new WP_Error( 'ktp_fm_ai_bad', __( 'AI の応答形式が不正です（per_file がありません）。', 'ktpwp' ) );
		}
		return $decoded;
	}

	/**
	 * @return string
	 */
	private static function build_zip_ai_system_prompt(): string {
		$client_fields   = self::target_fields_for_entity( self::ENTITY_CLIENT );
		$supplier_fields = self::target_fields_for_entity( self::ENTITY_SUPPLIER );
		$service_fields  = self::target_fields_for_entity( self::ENTITY_SERVICE );
		$order_fields    = self::target_fields_for_entity( self::ENTITY_ORDER );

		$fmt_client = array();
		foreach ( $client_fields as $k => $lab ) {
			$fmt_client[] = "{$k} … {$lab}";
		}
		$fmt_supplier = array();
		foreach ( $supplier_fields as $k => $lab ) {
			$fmt_supplier[] = "{$k} … {$lab}";
		}
		$fmt_service = array();
		foreach ( $service_fields as $k => $lab ) {
			$fmt_service[] = "{$k} … {$lab}";
		}
		$fmt_order = array();
		foreach ( $order_fields as $k => $lab ) {
			$fmt_order[] = "{$k} … {$lab}";
		}

		$block_client   = implode( "\n", $fmt_client );
		$block_supplier = implode( "\n", $fmt_supplier );
		$block_service  = implode( "\n", $fmt_service );
		$block_order    = implode( "\n", $fmt_order );

		return <<<PROMPT
あなたは KantanPro（FileMaker Pro 版）のエクスポート Zip を解析し、WordPress プラグイン KantanProEX に取り込む担当です。
入力は JSON。files 配列の各要素は Zip 内の1ファイルで path・headers（列名）・sample_rows（データ例）・parse_error（あれば）が含まれます。
FileMaker の user.tab / order.tab などでは headers が COL_1, COL_2 のように列位置のみの場合があります。sample_rows と突き合わせて意味を推定し、column_map では必ずその文字列（COL_n）と完全一致させること。

【タスク】
各ファイルについて次を JSON で返すこと。
- entity は次のいずれか: client（顧客マスタ）, supplier（協力会社）, service（商品・サービス）, order（受注）, skip（取り込み不要）
- order のときは link_company_name を、Zip 内の顧客データと一致する会社名の列に必ず対応させること（取り込み後に顧客マスタの id と紐づく）。顧客マスタは通常 user.tab または user.csv（サーバ側で他ファイルより先に取り込まれる）。
- column_map は Kantan 側のフィールドキー → そのファイルの headers に実在する列名（文字列）の対応。不要なキーは省略可。parse_error があるファイルは entity を skip にし reason を日本語で書くこと。

【顧客 client のフィールド】
{$block_client}

【協力会社 supplier のフィールド】
{$block_supplier}

【商品 service のフィールド】
{$block_service}

【受注 order のフィールド】
{$block_order}

【出力 JSON の形式（この形のみ）】
{ "per_file": [ { "path": "Zip内のパスと一致", "entity": "client|supplier|service|order|skip", "column_map": { "フィールドキー": "元の列名", ... }, "reason": "skip のときなど日本語で簡潔に" } ] }

入力 files に列挙された path はすべて per_file にちょうど1回ずつ含めること。列名は headers と完全一致させること。
PROMPT;
	}

	/**
	 * @param string $system System prompt.
	 * @param string $user_content User message (plain or JSON string).
	 * @return array<string, mixed>
	 */
	private static function openai_chat_completion_request_body( $system, $user_content ): array {
		return array(
			'model'           => 'gpt-4o-mini',
			'temperature'     => 0.1,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $user_content ),
			),
		);
	}

	/**
	 * @param string $api_key API key.
	 * @param array<string, mixed> $body Request body.
	 * @return array{content: string}|WP_Error
	 */
	private static function openai_remote_post( $api_key, array $body ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $raw ) && isset( $raw['error']['message'] ) ? (string) $raw['error']['message'] : __( 'OpenAI API エラー', 'ktpwp' );
			return new WP_Error( 'ktp_fm_openai_http', $msg );
		}
		$content = is_array( $raw ) && isset( $raw['choices'][0]['message']['content'] ) ? (string) $raw['choices'][0]['message']['content'] : '';
		if ( $content === '' ) {
			return new WP_Error( 'ktp_fm_openai_empty', __( 'OpenAI から空の応答でした。', 'ktpwp' ) );
		}
		return array( 'content' => $content );
	}

	/**
	 * 取り込み順序（受注は顧客マスタの後に実行する）。
	 *
	 * @param string $entity Entity slug.
	 * @return int
	 */
	private static function entity_import_priority( $entity ): int {
		switch ( self::normalize_entity( $entity ) ) {
			case self::ENTITY_CLIENT:
				return 0;
			case self::ENTITY_SUPPLIER:
				return 1;
			case self::ENTITY_SERVICE:
				return 2;
			case self::ENTITY_ORDER:
				return 3;
			default:
				return 9;
		}
	}

	/**
	 * @param string $zip_path Zip path.
	 * @param array<int, array<string, mixed>> $manifest Manifest.
	 * @param array<string, mixed> $decoded AI decoded JSON.
	 * @param bool $skip_dup_email Skip dup email for client/supplier.
	 * @param bool $skip_dup_service Skip dup service name.
	 * @param bool $skip_dup_order Skip dup FM external id for orders.
	 * @return array<string, mixed> Report structure.
	 */
	private static function execute_ai_zip_plans( $zip_path, array $manifest, array $decoded, $skip_dup_email, $skip_dup_service, $skip_dup_order ): array {
		$report = array(
			'imported'      => array(),
			'failed'        => array(),
			'skipped_by_ai' => array(),
			'not_assigned'  => array(),
		);

		$manifest_by_path = array();
		foreach ( $manifest as $m ) {
			if ( is_array( $m ) && ! empty( $m['path'] ) ) {
				$manifest_by_path[ (string) $m['path'] ] = $m;
			}
		}

		$planned = array();
		if ( isset( $decoded['per_file'] ) && is_array( $decoded['per_file'] ) ) {
			foreach ( $decoded['per_file'] as $p ) {
				if ( ! is_array( $p ) || empty( $p['path'] ) ) {
					continue;
				}
				$planned[ (string) $p['path'] ] = $p;
			}
		}

		foreach ( array_keys( $manifest_by_path ) as $path ) {
			if ( ! isset( $planned[ $path ] ) ) {
				$report['not_assigned'][] = array(
					'path'    => $path,
					'message' => __( 'AI の per_file に含まれませんでした（入力が長すぎて省略された可能性があります）。', 'ktpwp' ),
				);
			}
		}

		if ( ! class_exists( 'ZipArchive', false ) ) {
			$report['failed'][] = array( 'path' => '-', 'message' => __( 'ZipArchive がありません。', 'ktpwp' ) );
			return $report;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			$report['failed'][] = array( 'path' => '-', 'message' => __( 'Zip を開けませんでした。', 'ktpwp' ) );
			return $report;
		}

		$jobs = array();
		foreach ( $planned as $path => $p ) {
			if ( ! isset( $manifest_by_path[ $path ] ) ) {
				continue;
			}
			$mentry = $manifest_by_path[ $path ];
			$entity = isset( $p['entity'] ) ? sanitize_key( (string) $p['entity'] ) : 'skip';
			if ( $entity === 'skip' ) {
				$report['skipped_by_ai'][] = array(
					'path'   => $path,
					'reason' => isset( $p['reason'] ) ? sanitize_text_field( (string) $p['reason'] ) : '',
				);
				continue;
			}
			if ( ! in_array( $entity, self::allowed_entities(), true ) ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => __( 'AI が返した entity が不正です。', 'ktpwp' ),
				);
				continue;
			}
			if ( ! empty( $mentry['parse_error'] ) ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => (string) $mentry['parse_error'],
				);
				continue;
			}
			$headers = isset( $mentry['headers'] ) && is_array( $mentry['headers'] ) ? $mentry['headers'] : array();
			if ( $headers === array() ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => __( '列ヘッダーがありません。', 'ktpwp' ),
				);
				continue;
			}

			$map_in = isset( $p['column_map'] ) && is_array( $p['column_map'] ) ? $p['column_map'] : array();
			$map    = self::column_map_names_to_indexes( $entity, $headers, $map_in );
			if ( $map === array() ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => __( '有効な列マッピングがありません（AI の column_map と列名が一致しませんでした）。', 'ktpwp' ),
				);
				continue;
			}

			$jobs[] = array(
				'path'    => $path,
				'entity'  => $entity,
				'map'     => $map,
				'headers' => $headers,
			);
		}

		usort(
			$jobs,
			function ( $a, $b ) {
				$ua = self::user_export_file_rank( $a['path'] );
				$ub = self::user_export_file_rank( $b['path'] );
				if ( $ua !== $ub ) {
					return $ua - $ub;
				}
				$pa = self::entity_import_priority( $a['entity'] );
				$pb = self::entity_import_priority( $b['entity'] );
				if ( $pa !== $pb ) {
					return $pa - $pb;
				}

				return strcmp( (string) $a['path'], (string) $b['path'] );
			}
		);

		foreach ( $jobs as $job ) {
			$path    = $job['path'];
			$entity  = $job['entity'];
			$map     = $job['map'];
			$headers = $job['headers'];

			$raw_full = $zip->getFromName( $path );
			if ( ! is_string( $raw_full ) || $raw_full === '' ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => __( 'Zip からファイルを読み取れませんでした。', 'ktpwp' ),
				);
				continue;
			}
			if ( strlen( $raw_full ) > self::MAX_INNER_IMPORT_BYTES ) {
				$raw_full = substr( $raw_full, 0, self::MAX_INNER_IMPORT_BYTES );
			}
			$parsed = self::parse_tabular_for_zip_entry( $raw_full, $path );
			if ( is_wp_error( $parsed ) ) {
				$report['failed'][] = array(
					'path'    => $path,
					'message' => $parsed->get_error_message(),
				);
				continue;
			}
			$rows = $parsed['rows'];
			if ( count( $rows ) > self::MAX_DATA_ROWS ) {
				$rows = array_slice( $rows, 0, self::MAX_DATA_ROWS );
			}

			if ( $entity === self::ENTITY_CLIENT ) {
				$res = self::import_client_rows( $rows, $parsed['headers'], $map, $skip_dup_email );
			} elseif ( $entity === self::ENTITY_SUPPLIER ) {
				$res = self::import_supplier_rows( $rows, $parsed['headers'], $map, $skip_dup_email );
			} elseif ( $entity === self::ENTITY_ORDER ) {
				$res = self::import_order_rows( $rows, $parsed['headers'], $map, $skip_dup_order );
			} else {
				$res = self::import_service_rows( $rows, $parsed['headers'], $map, $skip_dup_service );
			}

			$report['imported'][] = array(
				'path'         => $path,
				'entity'       => $entity,
				'entity_label' => self::entity_label( $entity ),
				'inserted'     => (int) $res['inserted'],
				'skipped'      => (int) $res['skipped'],
				'errors'       => (int) $res['errors'],
			);
		}

		$zip->close();
		return $report;
	}

	/**
	 * AI の column_map（列名）を列インデックスへ。
	 *
	 * @param string               $entity  Entity.
	 * @param array<int, string>   $headers Headers.
	 * @param array<string, mixed> $map_in  Field => header name.
	 * @return array<string, int>
	 */
	private static function column_map_names_to_indexes( $entity, array $headers, array $map_in ): array {
		$out = array();
		foreach ( $map_in as $kantan_field => $header_name ) {
			$kantan_field = sanitize_key( (string) $kantan_field );
			if ( ! isset( self::target_fields_for_entity( $entity )[ $kantan_field ] ) ) {
				continue;
			}
			if ( $header_name === null || $header_name === '' ) {
				continue;
			}
			$header_name = sanitize_text_field( (string) $header_name );
			$idx         = array_search( $header_name, $headers, true );
			if ( $idx !== false ) {
				$out[ $kantan_field ] = (int) $idx;
			}
		}
		return $out;
	}

	/**
	 * @param string $plain Plaintext.
	 * @return string
	 */
	private static function encrypt_string( $plain ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|ktp_fm_import', true );
		try {
			$iv = random_bytes( 16 );
		} catch ( \Exception $e ) {
			return '';
		}
		$enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( $enc === false ) {
			return '';
		}
		return base64_encode( $iv . $enc );
	}

	/**
	 * @param string $stored Stored ciphertext (base64).
	 * @return string
	 */
	private static function decrypt_api_key( $stored ): string {
		$stored = (string) $stored;
		if ( $stored === '' || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$bin = base64_decode( $stored, true );
		if ( ! is_string( $bin ) || strlen( $bin ) < 17 ) {
			return '';
		}
		$iv  = substr( $bin, 0, 16 );
		$enc = substr( $bin, 16 );
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|ktp_fm_import', true );
		$dec = openssl_decrypt( $enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return is_string( $dec ) ? $dec : '';
	}
}
