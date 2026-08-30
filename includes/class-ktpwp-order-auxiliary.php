<?php
/**
 * 受注書のメール送信履歴・案件ファイル（KantanBiz 相当）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KTPWP_Order_Auxiliary
 */
class KTPWP_Order_Auxiliary {

	const MAIL_LOG_LIMIT = 30;

	const MAX_FILE_BYTES = 20971520; // 20MB（SaaS の OrderFileStoreRequest と同水準）

	/**
	 * admin-post でのダウンロード登録
	 */
	public static function register_hooks() {
		add_action( 'admin_post_ktpwp_download_order_file', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_ktpwp_upload_order_file', array( __CLASS__, 'handle_upload_post' ) );
		add_action( 'admin_post_ktpwp_delete_order_file', array( __CLASS__, 'handle_delete_post' ) );
	}

	/**
	 * メールログ・案件ファイルテーブルを確保
	 */
	public static function install_tables() {
		global $wpdb;

		$mail_table = $wpdb->prefix . 'ktp_order_customer_mail_log';
		$file_table = $wpdb->prefix . 'ktp_order_file';

		$mail_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mail_table ) ) === $mail_table );
		$file_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $file_table ) ) === $file_table );

		if ( ! $mail_exists || ! $file_exists ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();

			$sql_mail = "CREATE TABLE {$mail_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mail_kind varchar(32) NOT NULL DEFAULT 'customer',
			context_note varchar(500) DEFAULT NULL,
			delivery_type varchar(20) NOT NULL DEFAULT 'to',
			to_email varchar(255) NOT NULL,
			primary_to_email varchar(255) DEFAULT NULL,
			cc_emails longtext DEFAULT NULL,
			bcc_emails longtext DEFAULT NULL,
			subject varchar(500) NOT NULL,
			body longtext DEFAULT NULL,
			send_status varchar(20) NOT NULL DEFAULT 'sent',
			error_message text DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_sent (order_id,sent_at),
			KEY order_id (order_id)
		) {$charset_collate};";

		$sql_file = "CREATE TABLE {$file_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			original_filename varchar(500) NOT NULL,
			storage_path varchar(1024) NOT NULL,
			mime_type varchar(255) DEFAULT NULL,
			size bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id)
		) {$charset_collate};";

			dbDelta( $sql_mail );
			dbDelta( $sql_file );
		}

		self::upgrade_mail_log_schema();
	}

	/**
	 * 既存 DB に mail_kind / context_note を追加
	 */
	public static function upgrade_mail_log_schema() {
		global $wpdb;

		$table = $wpdb->prefix . 'ktp_order_customer_mail_log';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
		$names   = array();
		foreach ( (array) $columns as $col ) {
			if ( isset( $col->Field ) ) {
				$names[] = $col->Field;
			}
		}

		if ( ! in_array( 'mail_kind', $names, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN mail_kind varchar(32) NOT NULL DEFAULT 'customer' AFTER user_id" );
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
		$names   = array();
		foreach ( (array) $columns as $col ) {
			if ( isset( $col->Field ) ) {
				$names[] = $col->Field;
			}
		}
		if ( ! in_array( 'context_note', $names, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN context_note varchar(500) DEFAULT NULL AFTER mail_kind" );
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
		$names   = array();
		foreach ( (array) $columns as $col ) {
			if ( isset( $col->Field ) ) {
				$names[] = $col->Field;
			}
		}
		if ( ! in_array( 'bcc_emails', $names, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN bcc_emails longtext DEFAULT NULL AFTER cc_emails" );
		}
	}

	/**
	 * wp_mail() の戻り値だけでは誤判定しうる（送信できているのに false）。
	 * wp_mail_failed が発火した場合のみ失敗とみなす。
	 *
	 * @param callable():bool $send_call wp_mail() を呼ぶクロージャ（その戻り値を返す）。
	 * @return array{ success: bool, error_message: ?string }
	 */
	public static function run_wp_mail_with_result( callable $send_call ): array {
		$captured = null;
		$on_fail  = static function ( $wp_error ) use ( &$captured ) {
			if ( $wp_error instanceof WP_Error ) {
				$captured = $wp_error->get_error_message();
				if ( $captured === '' ) {
					$codes = $wp_error->get_error_codes();
					$captured = $codes !== array() ? implode( ', ', $codes ) : null;
				}
			}
		};

		add_action( 'wp_mail_failed', $on_fail, 10, 1 );
		$fails_before = did_action( 'wp_mail_failed' );
		$returned     = (bool) call_user_func( $send_call );
		remove_action( 'wp_mail_failed', $on_fail, 10 );
		$failed_hook_ran = did_action( 'wp_mail_failed' ) > $fails_before;

		if ( $returned ) {
			return array(
				'success'        => true,
				'error_message' => null,
			);
		}

		if ( $failed_hook_ran ) {
			return array(
				'success'        => false,
				'error_message' => ( $captured !== null && $captured !== '' ) ? $captured : 'wp_mail failed',
			);
		}

		return array(
			'success'        => true,
			'error_message' => null,
		);
	}

	/**
	 * 顧客向けメール送信の記録（成功・失敗）
	 *
	 * @param string|null $primary_to_email CC別送時の本来の To など。
	 * @param array|null  $cc_emails CC アドレスの配列。
	 * @param string      $mail_kind customer | purchase_order など。
	 * @param string|null $context_note 発注先協力会社名など補足。
	 * @param array|null  $bcc_emails BCC アドレスの配列。
	 */
	public static function record_customer_mail(
		int $order_id,
		string $to,
		string $subject,
		string $body,
		bool $success,
		?string $error_message = null,
		string $delivery_type = 'to',
		?string $primary_to_email = null,
		?array $cc_emails = null,
		string $mail_kind = 'customer',
		?string $context_note = null,
		?array $bcc_emails = null
	): void {
		global $wpdb;

		self::install_tables();

		$table   = $wpdb->prefix . 'ktp_order_customer_mail_log';
		$uid     = get_current_user_id();
		$uid_db  = $uid > 0 ? $uid : 0;
		$cc_json = null;
		if ( is_array( $cc_emails ) && $cc_emails !== array() ) {
			$clean   = array_values(
				array_filter(
					array_map( 'trim', $cc_emails ),
					static function ( $v ) {
						return is_string( $v ) && $v !== '';
					}
				)
			);
			$cc_json = $clean !== array() ? wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) : null;
		}

		$bcc_json = null;
		if ( is_array( $bcc_emails ) && $bcc_emails !== array() ) {
			$clean_bcc = array_values(
				array_filter(
					array_map( 'trim', $bcc_emails ),
					static function ( $v ) {
						return is_string( $v ) && $v !== '';
					}
				)
			);
			$bcc_json = $clean_bcc !== array() ? wp_json_encode( $clean_bcc, JSON_UNESCAPED_UNICODE ) : null;
		}

		$note = $context_note !== null && $context_note !== '' ? mb_substr( $context_note, 0, 500 ) : null;
		$kind = $mail_kind !== '' ? $mail_kind : 'customer';

		if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
			$body = KTPWP_Stripe_Billing::sanitize_body_for_mail_log( $body );
		}

		$wpdb->insert(
			$table,
			array(
				'order_id'           => $order_id,
				'user_id'            => $uid_db,
				'mail_kind'          => $kind,
				'context_note'       => $note,
				'delivery_type'      => $delivery_type !== '' ? $delivery_type : 'to',
				'to_email'           => $to,
				'primary_to_email'   => $primary_to_email !== null && $primary_to_email !== '' ? $primary_to_email : $to,
				'cc_emails'          => $cc_json,
				'bcc_emails'         => $bcc_json,
				'subject'            => mb_substr( $subject, 0, 500 ),
				'body'               => $body,
				'send_status'        => $success ? 'sent' : 'failed',
				'error_message'      => $error_message !== null ? mb_substr( $error_message, 0, 5000 ) : null,
				'sent_at'            => current_time( 'mysql' ),
				'created_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_mail_logs( int $order_id ): array {
		global $wpdb;

		self::install_tables();
		$table = $wpdb->prefix . 'ktp_order_customer_mail_log';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE order_id = %d ORDER BY sent_at DESC, id DESC LIMIT %d",
				$order_id,
				self::MAIL_LOG_LIMIT
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_order_files( int $order_id ): array {
		global $wpdb;

		self::install_tables();
		$table = $wpdb->prefix . 'ktp_order_file';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE order_id = %d ORDER BY created_at DESC, id DESC",
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * メール送信履歴ブロックのみ
	 */
	public static function render_mail_log_section_html( int $order_id ): string {
		$logs = self::get_mail_logs( $order_id );

		$html  = '<details class="ktp-order-block ktp-order-details-toggle ktp-order-mail-log-wrap ktp-order-mail-log-details" data-ktp-order-toggle="mail_log" data-ktp-order-id="' . esc_attr( (string) $order_id ) . '">';
		$html .= '<summary class="ktp-order-details-summary">';
		$html .= esc_html__( 'メール送信履歴', 'kantanpro' );
		$html .= ' <span class="ktp-order-details-hint">' . esc_html( sprintf( /* translators: max rows */ __( '最新%d件', 'kantanpro' ), self::MAIL_LOG_LIMIT ) ) . '</span>';
		$html .= '</summary>';

		if ( $logs === array() ) {
			$html .= '<p class="description ktp-order-mail-log-empty">' . esc_html__( '送信履歴はまだありません。', 'kantanpro' ) . '</p>';
		} else {
			foreach ( $logs as $log ) {
				$sent_ts = ! empty( $log->sent_at ) ? strtotime( $log->sent_at ) : false;
				$dt      = $sent_ts ? date_i18n( 'Y/n/j H:i', $sent_ts ) : '—';

				$sender = __( 'システム', 'kantanpro' );
				if ( ! empty( $log->user_id ) ) {
					$u = get_userdata( (int) $log->user_id );
					if ( $u && $u->display_name ) {
						$sender = $u->display_name;
					}
				}

				$mk = isset( $log->mail_kind ) ? (string) $log->mail_kind : 'customer';
				if ( $mk === 'purchase_order' ) {
					$kind_label = '<span style="font-size:11px;background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:10px;">' . esc_html__( '発注', 'kantanpro' ) . '</span>';
				} elseif ( $mk === 'quote_request' ) {
					$kind_label = '<span style="font-size:11px;background:#f3e5f5;color:#6a1b9a;padding:2px 8px;border-radius:10px;">' . esc_html__( '見積依頼', 'kantanpro' ) . '</span>';
				} else {
					$kind_label = '<span style="font-size:11px;background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;">' . esc_html__( '顧客', 'kantanpro' ) . '</span>';
				}

				$dtype = isset( $log->delivery_type ) ? (string) $log->delivery_type : 'to';
				$type_label = ( $dtype === 'cc' )
					? '<span style="font-size:11px;background:#e8eaf6;color:#3949ab;padding:2px 8px;border-radius:10px;">' . esc_html__( 'CC別送', 'kantanpro' ) . '</span>'
					: '<span style="font-size:11px;background:#eee;color:#333;padding:2px 8px;border-radius:10px;">' . esc_html__( '通常', 'kantanpro' ) . '</span>';

				$cc_show = '—';
				if ( ! empty( $log->cc_emails ) ) {
					$dec = json_decode( (string) $log->cc_emails, true );
					if ( is_array( $dec ) && $dec !== array() ) {
						$cc_show = esc_html( implode( ', ', array_map( 'strval', $dec ) ) );
					}
				}

				$bcc_show = '—';
				if ( ! empty( $log->bcc_emails ) ) {
					$dec_bcc = json_decode( (string) $log->bcc_emails, true );
					if ( is_array( $dec_bcc ) && $dec_bcc !== array() ) {
						$bcc_show = esc_html( implode( ', ', array_map( 'strval', $dec_bcc ) ) );
					}
				}

				$ok     = isset( $log->send_status ) && $log->send_status === 'sent';
				$status = $ok
					? '<span style="font-size:11px;background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;">' . esc_html__( '成功', 'kantanpro' ) . '</span>'
					: '<span style="font-size:11px;background:#ffebee;color:#c62828;padding:2px 8px;border-radius:10px;">' . esc_html__( '失敗', 'kantanpro' ) . '</span>';

				$html .= '<div class="ktp-mail-log-entry">';
				$html .= '<div class="ktp-mail-log-entry-head">';
				$html .= '<span class="ktp-mail-log-entry-date">' . esc_html( $dt ) . '</span> ';
				$html .= $kind_label . ' ';
				$html .= $type_label . ' ';
				$html .= '<span class="ktp-mail-log-entry-sender">' . esc_html( $sender ) . '</span> ';
				$html .= '<span class="ktp-mail-log-entry-to">→ ' . esc_html( (string) $log->to_email ) . '</span> ';
				$html .= '<span class="ktp-mail-log-entry-subj">「' . esc_html( mb_substr( (string) $log->subject, 0, 80 ) ) . ( mb_strlen( (string) $log->subject ) > 80 ? '…' : '' ) . '」</span> ';
				$html .= $status;
				$html .= '</div>';
				$html .= '<div class="ktp-mail-log-entry-body">';
				if ( ! empty( $log->context_note ) ) {
					$html .= '<p class="ktp-mail-log-context">' . esc_html( sprintf( /* translators: %s supplier or context */ __( '補足: %s', 'kantanpro' ), (string) $log->context_note ) ) . '</p>';
				}
				$html .= '<table class="widefat ktp-mail-log-meta-table"><tbody>';
				$html .= '<tr><th>' . esc_html__( 'CC', 'kantanpro' ) . '</th><td>' . $cc_show . '</td></tr>';
				$html .= '<tr><th>' . esc_html__( 'BCC', 'kantanpro' ) . '</th><td>' . $bcc_show . '</td></tr>';
				$html .= '<tr><th>' . esc_html__( '件名', 'kantanpro' ) . '</th><td class="ktp-mail-log-subject-cell">' . esc_html( (string) $log->subject ) . '</td></tr>';
				if ( ! $ok && ! empty( $log->error_message ) ) {
					$html .= '<tr><th>' . esc_html__( 'エラー', 'kantanpro' ) . '</th><td class="ktp-mail-log-error-cell">' . esc_html( (string) $log->error_message ) . '</td></tr>';
				}
				if ( $dtype === 'cc' && ! empty( $log->primary_to_email ) ) {
					$html .= '<tr><td colspan="2" class="ktp-mail-log-cc-note">' . esc_html( sprintf( /* translators: %s email */ __( 'CC別送（本来の宛先: %s）', 'kantanpro' ), (string) $log->primary_to_email ) ) . '</td></tr>';
				}
				$html .= '</tbody></table>';
				$body_text = isset( $log->body ) && $log->body !== '' ? (string) $log->body : '';
				if ( $body_text !== '' && class_exists( 'KTPWP_Stripe_Billing' ) ) {
					$body_text = KTPWP_Stripe_Billing::sanitize_body_for_mail_log( $body_text );
				}
				$html     .= '<pre class="ktp-mail-log-body-pre">' . ( $body_text !== '' ? esc_html( $body_text ) : esc_html__( '（本文なし）', 'kantanpro' ) ) . '</pre>';
				$html     .= '</div></div>';
			}
		}

		$html .= '</details>';

		return $html;
	}

	/**
	 * 案件ファイルブロックのみ
	 */
	public static function render_order_files_section_html( int $order_id ): string {
		$files = self::get_order_files( $order_id );

		$redirect_url = home_url( '/' );
		if ( class_exists( 'KTPWP_Main' ) ) {
			$redirect_url = add_query_arg(
				array(
					'tab_name' => 'order',
					'order_id' => $order_id,
				),
				KTPWP_Main::get_current_page_base_url()
			);
		}

		$html  = '<details class="ktp-order-block ktp-order-details-toggle ktp-order-files-wrap ktp-order-files-details" data-ktp-order-toggle="order_files" data-ktp-order-id="' . esc_attr( (string) $order_id ) . '">';
		$html .= '<summary class="ktp-order-details-summary">' . esc_html__( '案件ファイル', 'kantanpro' );
		$html .= ' <span class="ktp-order-details-hint">' . esc_html__( 'PDF・画像（最大20MB）', 'kantanpro' ) . '</span></summary>';

		if ( $files === array() ) {
			$html .= '<p class="description ktp-order-files-empty">' . esc_html__( 'まだファイルがありません。', 'kantanpro' ) . '</p>';
		} else {
			$html .= '<ul class="ktp-order-file-list">';
			foreach ( $files as $f ) {
				$dl_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=ktpwp_download_order_file&file_id=' . (int) $f->id ),
					'ktpwp_download_order_file_' . (int) $f->id
				);
				$preview_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=ktpwp_download_order_file&file_id=' . (int) $f->id . '&inline=1' ),
					'ktpwp_download_order_file_' . (int) $f->id
				);
				$bytes  = (int) $f->size;
				$sz     = $bytes >= 1048576
					? number_format_i18n( $bytes / 1048576, 1 ) . ' MB'
					: ( $bytes >= 1024 ? number_format_i18n( $bytes / 1024, 1 ) . ' KB' : (string) $bytes . ' B' );
				$who    = __( '（不明）', 'kantanpro' );
				if ( ! empty( $f->user_id ) ) {
					$uu = get_userdata( (int) $f->user_id );
					if ( $uu && $uu->display_name ) {
						$who = $uu->display_name;
					}
				}
				$created = ! empty( $f->created_at ) ? date_i18n( 'Y-m-d H:i', strtotime( $f->created_at ) ) : '—';

				$mime_raw = is_string( $f->mime_type ) ? (string) $f->mime_type : '';
				$ext      = strtolower( pathinfo( (string) $f->original_filename, PATHINFO_EXTENSION ) );
				$img_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
				$is_img   = ( $mime_raw !== '' && strpos( $mime_raw, 'image/' ) === 0 )
					|| in_array( $ext, $img_exts, true );
				$is_pdf   = ( $mime_raw === 'application/pdf' ) || ( $ext === 'pdf' );
				$can_preview = $is_img || $is_pdf;

				$html .= '<li class="ktp-order-file-row">';
				$html .= '<div class="ktp-order-file-row-inner">';
				$html .= '<div class="ktp-order-file-icon">';
				if ( $is_img ) {
					$html .= '<img src="' . esc_url( $preview_url ) . '" alt="" loading="lazy" />';
				} else {
					$html .= '<span class="ktp-order-file-icon-label">PDF</span>';
				}
				$html .= '</div>';
				$html .= '<div class="ktp-order-file-text">';
				$html .= '<div class="ktp-order-file-name">' . esc_html( (string) $f->original_filename ) . '</div>';
				$html .= '<div class="ktp-order-file-meta">' . esc_html( $created ) . ' — ' . esc_html( $sz ) . ' — ' . esc_html( $who ) . '</div>';
				$html .= '</div>';
				$html .= '<div class="ktp-order-file-actions">';
				if ( $can_preview ) {
					$html .= '<a href="' . esc_url( $preview_url ) . '" class="ktp-order-file-action-btn ktp-order-file-preview-btn" target="_blank" rel="noopener noreferrer">' . esc_html__( 'プレビュー', 'kantanpro' ) . '</a>';
				}
				$html .= '<a href="' . esc_url( $dl_url ) . '" class="ktp-order-file-action-btn ktp-order-file-download-btn">' . esc_html__( 'ダウンロード', 'kantanpro' ) . '</a>';
				$html .= '<form class="ktp-order-file-delete-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'このファイルを削除しますか？', 'kantanpro' ) ) . '\');">';
				$html .= '<input type="hidden" name="action" value="ktpwp_delete_order_file" />';
				$html .= wp_nonce_field( 'ktpwp_order_file_delete_' . $order_id, 'ktpwp_order_file_delete_nonce', true, false );
				$html .= '<input type="hidden" name="order_id_for_file" value="' . esc_attr( (string) $order_id ) . '" />';
				$html .= '<input type="hidden" name="ktpwp_redirect" value="' . esc_url( $redirect_url ) . '" />';
				$html .= '<input type="hidden" name="ktpwp_order_file_id" value="' . esc_attr( (string) $f->id ) . '" />';
				$html .= '<button type="submit" class="ktp-order-file-action-btn ktp-order-file-delete-btn">' . esc_html__( '削除', 'kantanpro' ) . '</button>';
				$html .= '</form>';
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '<form class="ktp-order-file-upload-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="ktpwp_upload_order_file" />';
		$html .= wp_nonce_field( 'ktpwp_order_file_upload_' . $order_id, 'ktpwp_order_file_upload_nonce', true, false );
		$html .= '<input type="hidden" name="order_id_for_file" value="' . esc_attr( (string) $order_id ) . '" />';
		$html .= '<input type="hidden" name="ktpwp_redirect" value="' . esc_url( $redirect_url ) . '" />';
		$html .= '<label class="ktp-order-file-upload-label">' . esc_html__( 'ファイルを追加', 'kantanpro' ) . '</label>';
		$html .= '<div class="ktp-order-file-upload-row">';
		$html .= '<input type="file" name="ktpwp_order_file" class="ktp-order-file-input" accept=".pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf" required />';
		// .button はプラグイン CSS で「ラッパー用」定義されているため button 要素に付けない
		$html .= '<button type="submit" class="ktp-order-file-upload-btn">' . esc_html__( 'アップロード', 'kantanpro' ) . '</button>';
		$html .= '</div>';
		$html .= '<p class="description ktp-order-file-upload-hint">' . esc_html__( 'PDF または画像（JPEG・PNG・GIF・WebP）。最大 20MB。', 'kantanpro' ) . '</p>';
		$html .= '</form>';

		$html .= '</details>';

		return $html;
	}

	/**
	 * メール履歴・案件ファイル（独立した2ブロック）
	 */
	public static function render_sections_html( int $order_id ): string {
		return self::render_mail_log_section_html( $order_id ) . self::render_order_files_section_html( $order_id );
	}

	/**
	 * @param array<string, mixed> $file $_FILES 要素
	 */
	public static function handle_file_upload( int $order_id, array $file ): string {
		global $wpdb;

		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			return 'upload_err_perm';
		}

		self::install_tables();

		$table_order = $wpdb->prefix . 'ktp_order';
		$exists      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_order}` WHERE id = %d", $order_id ) );
		if ( $exists < 1 ) {
			return 'upload_err_order';
		}

		if ( ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return 'upload_err';
		}

		if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return 'upload_err';
		}

		if ( (int) $file['size'] > self::MAX_FILE_BYTES ) {
			return 'upload_err_size';
		}

		$ftype = wp_check_filetype( $file['name'] );
		$ext   = isset( $ftype['ext'] ) ? strtolower( (string) $ftype['ext'] ) : '';
		$allow = array( 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		if ( ! in_array( $ext, $allow, true ) ) {
			return 'upload_err_type';
		}

		$filetype_mime = isset( $ftype['type'] ) ? (string) $ftype['type'] : '';
		$check         = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime          = ! empty( $check['type'] ) ? (string) $check['type'] : $filetype_mime;
		if ( $mime === '' ) {
			return 'upload_err_type';
		}

		$ok_mimes = array(
			'application/pdf',
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
		);
		if ( ! in_array( $mime, $ok_mimes, true ) ) {
			return 'upload_err_type';
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return 'upload_err';
		}

		$subdir = 'ktp-order-files/' . $order_id;
		$dir    = trailingslashit( $upload['basedir'] ) . $subdir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return 'upload_err';
		}

		$orig = sanitize_file_name( $file['name'] );
		if ( $orig === '' ) {
			return 'upload_err';
		}
		if ( strlen( $orig ) > 500 ) {
			$orig = mb_substr( $orig, 0, 500 );
		}

		$dest_name = wp_unique_filename( $dir, $orig );
		$dest_path = $dir . '/' . $dest_name;

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			return 'upload_err';
		}

		$rel_path = $subdir . '/' . $dest_name;
		$uid      = get_current_user_id();
		$uid_db   = $uid > 0 ? $uid : 0;

		$file_table = $wpdb->prefix . 'ktp_order_file';
		$wpdb->insert(
			$file_table,
			array(
				'order_id'          => $order_id,
				'user_id'           => $uid_db,
				'original_filename' => $orig,
				'storage_path'      => $rel_path,
				'mime_type'         => $mime,
				'size'              => (int) filesize( $dest_path ),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return 'upload_ok';
	}

	public static function handle_file_delete( int $order_id, int $file_id ): string {
		global $wpdb;

		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			return 'del_err_perm';
		}

		if ( $file_id <= 0 ) {
			return 'del_err';
		}

		self::install_tables();
		$table = $wpdb->prefix . 'ktp_order_file';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND order_id = %d", $file_id, $order_id ) );
		if ( ! $row ) {
			return 'del_err';
		}

		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . str_replace( array( '..', '\\' ), '', (string) $row->storage_path );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}

		$wpdb->delete( $table, array( 'id' => $file_id, 'order_id' => $order_id ), array( '%d', '%d' ) );

		return 'del_ok';
	}

	/**
	 * 受注書削除時に紐づくログ・ファイルを削除
	 */
	public static function delete_all_for_order( int $order_id ): void {
		global $wpdb;

		self::install_tables();

		$file_table = $wpdb->prefix . 'ktp_order_file';
		$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT storage_path FROM `{$file_table}` WHERE order_id = %d", $order_id ) );
		if ( is_array( $rows ) ) {
			$upload = wp_upload_dir();
			$base   = trailingslashit( $upload['basedir'] );
			foreach ( $rows as $r ) {
				$p = $base . str_replace( array( '..', '\\' ), '', (string) $r->storage_path );
				if ( is_file( $p ) ) {
					wp_delete_file( $p );
				}
			}
		}
		$wpdb->delete( $file_table, array( 'order_id' => $order_id ), array( '%d' ) );

		$mail_table = $wpdb->prefix . 'ktp_order_customer_mail_log';
		$wpdb->delete( $mail_table, array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * admin-post: 案件ファイルアップロード（フロントでショートコード表示中の POST ではヘッダー送信済みになり得るためここで処理）
	 */
	public static function handle_upload_post() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ), '', array( 'response' => 403 ) );
		}

		$order_id = isset( $_POST['order_id_for_file'] ) ? absint( $_POST['order_id_for_file'] ) : 0;
		if ( $order_id <= 0 ) {
			wp_die( esc_html__( '無効なリクエストです。', 'kantanpro' ), '', array( 'response' => 400 ) );
		}

		$nonce = isset( $_POST['ktpwp_order_file_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ktpwp_order_file_upload_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ktpwp_order_file_upload_' . $order_id ) ) {
			wp_die( esc_html__( '無効なリクエストです。', 'kantanpro' ), '', array( 'response' => 403 ) );
		}

		$redirect = self::resolve_order_file_redirect( $order_id );

		if ( empty( $_FILES['ktpwp_order_file']['name'] ) || ! isset( $_FILES['ktpwp_order_file'] ) ) {
			$notice = 'upload_err';
		} else {
			$notice = self::handle_file_upload( $order_id, $_FILES['ktpwp_order_file'] );
		}

		wp_safe_redirect( add_query_arg( 'ktp_of_notice', $notice, $redirect ) );
		exit;
	}

	/**
	 * admin-post: 案件ファイル削除
	 */
	public static function handle_delete_post() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ), '', array( 'response' => 403 ) );
		}

		$order_id = isset( $_POST['order_id_for_file'] ) ? absint( $_POST['order_id_for_file'] ) : 0;
		if ( $order_id <= 0 ) {
			wp_die( esc_html__( '無効なリクエストです。', 'kantanpro' ), '', array( 'response' => 400 ) );
		}

		$nonce = isset( $_POST['ktpwp_order_file_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ktpwp_order_file_delete_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ktpwp_order_file_delete_' . $order_id ) ) {
			wp_die( esc_html__( '無効なリクエストです。', 'kantanpro' ), '', array( 'response' => 403 ) );
		}

		$redirect = self::resolve_order_file_redirect( $order_id );
		$file_id  = isset( $_POST['ktpwp_order_file_id'] ) ? absint( $_POST['ktpwp_order_file_id'] ) : 0;
		$notice   = self::handle_file_delete( $order_id, $file_id );

		wp_safe_redirect( add_query_arg( 'ktp_of_notice', $notice, $redirect ) );
		exit;
	}

	/**
	 * @param int $order_id 受注書 ID（フォールバック URL 用）
	 */
	private static function resolve_order_file_redirect( int $order_id ): string {
		$fallback = home_url( '/' );
		if ( class_exists( 'KTPWP_Main' ) ) {
			$fallback = add_query_arg(
				array(
					'tab_name' => 'order',
					'order_id' => $order_id,
				),
				KTPWP_Main::get_current_page_base_url()
			);
		}

		if ( empty( $_POST['ktpwp_redirect'] ) ) {
			return $fallback;
		}

		$candidate = esc_url_raw( wp_unslash( (string) $_POST['ktpwp_redirect'] ) );

		return wp_validate_redirect( $candidate, $fallback );
	}

	public static function handle_download() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ), 403 );
		}

		$file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;
		if ( $file_id <= 0 ) {
			wp_die( esc_html__( '無効なリクエストです。', 'kantanpro' ), 400 );
		}

		check_admin_referer( 'ktpwp_download_order_file_' . $file_id );

		global $wpdb;
		self::install_tables();
		$table = $wpdb->prefix . 'ktp_order_file';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $file_id ) );
		if ( ! $row ) {
			wp_die( esc_html__( 'ファイルが見つかりません。', 'kantanpro' ), 404 );
		}

		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . str_replace( array( '..', '\\' ), '', (string) $row->storage_path );
		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'ファイルが見つかりません。', 'kantanpro' ), 404 );
		}

		$inline = isset( $_GET['inline'] ) && (string) $_GET['inline'] === '1';
		$mime   = is_string( $row->mime_type ) && $row->mime_type !== '' ? (string) $row->mime_type : 'application/octet-stream';
		if ( $mime === 'application/octet-stream' && strtolower( pathinfo( (string) $row->original_filename, PATHINFO_EXTENSION ) ) === 'pdf' ) {
			$mime = 'application/pdf';
		}

		$inline_ok = $inline && ( strpos( $mime, 'image/' ) === 0 || $mime === 'application/pdf' );

		if ( $inline_ok ) {
			header( 'Content-Type: ' . $mime );
			header( 'Content-Disposition: inline; filename="' . rawurlencode( (string) $row->original_filename ) . '"' );
		} else {
			header( 'Content-Type: ' . $mime );
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( (string) $row->original_filename ) . '"' );
		}

		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		readfile( $path );
		exit;
	}
}
