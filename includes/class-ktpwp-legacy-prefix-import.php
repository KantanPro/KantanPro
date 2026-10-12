<?php
/**
 * 1.3.48 以前のデータ（接頭辞 ktp_）を 1.3.49 以降の名前（ktpwp_）へ引き継ぐ
 *
 * 1.3.49 で、wp.org の審査（接頭辞は 4 文字以上）に合わせてテーブルとオプションの接頭辞を
 * ktp_ から ktpwp_ に変えた。そのとき引き継ぎ処理が無かったため、1.3.48 から更新したサイトでは
 * 顧客・受注・設定が空に見えていた（データは wp_ktp_* と ktp_* オプションに残っている）。
 *   1.3.48 まで: wp_ktp_order   / ktp_general_settings
 *   1.3.49 から: wp_ktpwp_order / ktpwp_general_settings
 *
 * - テーブルは RENAME TABLE で名前を変えるだけ（コピーしない）。件数が多くても一瞬で終わる。
 * - 新しい名前のテーブルが空（1.3.49 が作っただけ）なら、それを消してから名前を変える。
 *   利用規約は 1.3.49 が既定の文面を 1 行入れるので、既定の文面だけなら空とみなす。
 * - 両方にデータがある場合は何もしない（1.3.49 で入力した分を上書きで消さない）。管理画面に通知する。
 * - オプションは ktp_* を ktpwp_* へコピーする（元の ktp_* は残す）。
 * - 1.3.49 以降で新規に入れたサイトには wp_ktp_* が無いので何も起きない。
 *
 * KantanProEX の KTPWP_EX_Free_Data_Import（ktpwp_ → ktp_）の逆向き。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Legacy_Prefix_Import' ) ) {

	final class KTPWP_Legacy_Prefix_Import {

		/** 両方にデータがあって引き継がなかったテーブル */
		const CONFLICT_OPTION = 'ktpwp_legacy_prefix_import_conflicts';

		/** 引き継いだ結果（管理画面の通知用） */
		const RESULT_TRANSIENT = 'ktpwp_legacy_prefix_import_result';

		/**
		 * 「更新後にもう使われている」と判断する根拠にするテーブル。
		 *
		 * スタッフチャット等は、引き継ぎの最中に別のリクエストが受注を表示しただけで初期メッセージが入る。
		 * それを根拠にすると設定を引き継がなくなり、デザイン等が 1.3.49 の初期値のまま残る（1.3.50 で実際に起きた）。
		 */
		const CORE_TABLES = array( 'client', 'order', 'service', 'supplier' );

		/**
		 * 1.3.49 で ktp_ から ktpwp_ に名前が変わったオプション（tools/ktp-prefix-renamed.json から抜き出したもの）。
		 *
		 * @var string[]
		 */
		private static $renamed_options = array(
			'ktp_activation_key', 'ktp_active_edition', 'ktp_atena_label_position', 'ktp_banner_options',
			'ktp_central_banner_settings', 'ktp_cleanup_unused_columns_timestamp',
			'ktp_client_category_migration_done', 'ktp_company_info_migrated',
			'ktp_contract_payment_due_mode_migration_completed', 'ktp_contract_recurring_items_migration_completed',
			'ktp_contract_stripe_subscription_migration_completed', 'ktp_contract_tables_migration_completed',
			'ktp_department_table_version', 'ktp_design_settings', 'ktp_dev_license_enabled',
			'ktp_developer_edition_override', 'ktp_donation_settings', 'ktp_fm_ai_zip_import', 'ktp_fm_import_ai',
			'ktp_fm_import_openai', 'ktp_fm_import_openai_key_enc', 'ktp_fm_import_run', 'ktp_fm_import_upload',
			'ktp_general_settings', 'ktp_invoice_billing_scope_migration_completed', 'ktp_japanpost_api_settings',
			'ktp_license_info', 'ktp_license_key', 'ktp_license_requests', 'ktp_license_site_url',
			'ktp_license_status', 'ktp_license_verified_at', 'ktp_logo_image',
			'ktp_order_contract_fields_migration_completed', 'ktp_order_migration_20250108_invoice_items_completed',
			'ktp_order_migration_20250108_invoice_items_timestamp', 'ktp_order_migration_20250703_completed',
			'ktp_order_migration_20250703_timestamp', 'ktp_order_stripe_subscription_migration_completed',
			'ktp_order_table_version', 'ktp_pdf_document_settings', 'ktp_pdf_issuer_address', 'ktp_pdf_issuer_name',
			'ktp_pdf_layout', 'ktp_pdf_logo_path', 'ktp_pdf_seal_path',
			'ktp_service_contract_billing_cycle_migration_completed', 'ktp_service_initial_fees_migration_completed',
			'ktp_service_is_public_migration_completed', 'ktp_service_public_html_migration_completed',
			'ktp_service_public_instant_purchase_migration_completed',
			'ktp_service_public_quantity_fixed_migration_completed', 'ktp_service_stock_migration_completed',
			'ktp_service_tax_rate_migration_completed', 'ktp_settings', 'ktp_settings_migrated', 'ktp_smtp_settings',
			'ktp_strict_cleanup_timestamp', 'ktp_supplier_migration_20250703_completed',
			'ktp_supplier_skills_table_version', 'ktp_sync_production_to_local_structure_timestamp', 'ktp_tax_policy',
			'ktp_ultra_strict_cleanup_unused_columns_timestamp', 'ktp_uninstall_settings',
			'ktp_update_notification_settings',
		);

		/**
		 * 引き継ぎを実行する。自動マイグレーションと有効化処理の先頭（テーブル作成の前）から呼ぶ。
		 *
		 * @return int 名前を変えたテーブルの数。
		 */
		public static function run() {
			global $wpdb;

			$source_prefix = $wpdb->prefix . 'ktp_';
			$target_prefix = $wpdb->prefix . 'ktpwp_';
			$sources       = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $source_prefix ) . '%' ) );
			if ( empty( $sources ) ) {
				return 0;
			}

			// 更新直後は複数のリクエストが同時にここへ来る。並行して走ると、片方が空と判定した直後に
			// もう片方がデータ入りのテーブルに差し替え、それを消してしまう（検証で実際に起きた）。
			// サイトごとのロックを取れなかったリクエストは何もしない。
			$lock = $wpdb->prefix . 'ktpwp_legacy_prefix_import';
			if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) !== '1' ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 排他ロック。
				return 0;
			}

			$renamed       = array();
			$conflicts     = array();
			$new_had_data  = false;

			try {
				foreach ( $sources as $source ) {
					$suffix = substr( (string) $source, strlen( $source_prefix ) );
					// 識別子は英小文字・数字・_ のみ受け付ける
					if ( $suffix === '' || ! preg_match( '/^[a-z0-9_]+$/', $suffix ) || strpos( $suffix, 'migration_test' ) === 0 ) {
						continue;
					}
					$target = $target_prefix . $suffix;

					if ( ! self::table_exists( $target ) ) {
						$ok = $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $source, $target ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- 旧接頭辞のテーブルを新しい名前に変えるだけ。
						if ( $ok !== false ) {
							$renamed[] = $suffix;
						}
						continue;
					}

					if ( ! self::is_effectively_empty( $target, $suffix ) ) {
						if ( in_array( $suffix, self::CORE_TABLES, true ) ) {
							$new_had_data = true;
						}
						if ( self::count_rows( $source ) > 0 ) {
							$conflicts[] = $suffix;
						}
						// 新しい名前の側にデータがあるテーブルは触らない
						continue;
					}

					// 1.3.49 が作っただけの空テーブル。退避と差し替えを 1 文で行う（RENAME TABLE は全部成功か全部失敗）。
					// 消すのは退避した側だけで、消す直前にもう一度空であることを確かめる。
					$aside = $target . '_empty';
					$ok    = $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i, %i TO %i', $target, $aside, $source, $target ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- 同上。
					if ( $ok === false ) {
						continue;
					}
					$renamed[] = $suffix;
					if ( self::is_effectively_empty( $aside, $suffix ) ) {
						$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $aside ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- 1.3.49 が作った空テーブル（直前に確認）。
					}
				}
			} finally {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 排他ロックの解除。
			}

			if ( $renamed !== array() ) {
				// 新しい名前の側にまだデータが無かった（＝更新しただけ）なら、旧版の設定を優先する
				self::copy_options( ! $new_had_data );
				// 1.3.49 が空のテーブルを見て「新規インストール」と判定した結果が 1 時間残っている。
				// 残っていると引き継いだデータに対する段階的マイグレーションが飛ばされるので消す。
				delete_transient( 'ktpwp_new_installation_check' );
				set_transient( self::RESULT_TRANSIENT, count( $renamed ), DAY_IN_SECONDS );
			}

			if ( $conflicts !== array() ) {
				update_option( self::CONFLICT_OPTION, $conflicts, false );
			}

			return count( $renamed );
		}

		/**
		 * 新しい名前の設定に欠けている項目を、旧版の値で埋める（入っている値は変えない）。
		 *
		 * 1.3.50 で設定を引き継がなかったサイト向け。旧版の ktp_* が残っているときだけ働く。
		 * 自動マイグレーション（バージョンが上がったとき）から呼ぶ。
		 *
		 * @return void
		 */
		public static function fill_missing_options() {
			self::copy_options( false );
		}

		/**
		 * ktp_* のオプションを ktpwp_* へコピーする（元の ktp_* は残す）。
		 *
		 * 上書きしない場合も、設定が配列なら新しい側に無い項目だけは旧版の値で埋める。
		 *
		 * @param bool $overwrite 新しい名前の値があっても旧版の値で上書きするか。
		 * @return void
		 */
		private static function copy_options( $overwrite ) {
			global $wpdb;

			$names   = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ktp_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 名前の一覧を取るだけ。
			$missing = new stdClass();

			foreach ( (array) $names as $legacy ) {
				if ( ! self::is_renamed_option( (string) $legacy ) ) {
					continue;
				}
				$name    = 'ktpwp_' . substr( (string) $legacy, strlen( 'ktp_' ) );
				$current = get_option( $name, $missing );
				$value   = get_option( $legacy );
				if ( $current !== $missing && ! $overwrite ) {
					if ( ! is_array( $current ) || ! is_array( $value ) ) {
						continue;
					}
					// 配列の和: 新しい側の値を優先し、無い項目だけ旧版から足す（数値キーも振り直さない）
					$value = $current + $value;
					if ( $value === $current ) {
						continue;
					}
				}
				update_option( $name, $value );
			}
		}

		/**
		 * 1.3.49 で名前が変わったオプションか。
		 *
		 * 名前を組み立てて作るオプション（テーブルごとの版、マイグレーション済みフラグ）は形で判定する。
		 *
		 * @param string $legacy ktp_ で始まる名前。
		 * @return bool
		 */
		private static function is_renamed_option( $legacy ) {
			if ( in_array( $legacy, self::$renamed_options, true ) ) {
				return true;
			}
			return (bool) preg_match( '/^ktp_(?:[a-z0-9_]+_table_version|migration_[0-9a-f]{32}(?:_timestamp)?|[a-z0-9_]+_(?:completed|timestamp|done|migrated)|stripe_setup_url_[a-z0-9_]+)$/', $legacy );
		}

		/**
		 * 1.3.49 が作っただけの状態か（空、または既定の利用規約 1 行だけ）。
		 *
		 * @param string $table  テーブル名。
		 * @param string $suffix 接頭辞を除いた名前。
		 * @return bool
		 */
		private static function is_effectively_empty( $table, $suffix ) {
			global $wpdb;

			$rows = self::count_rows( $table );
			if ( $rows === 0 ) {
				return true;
			}
			if ( $suffix !== 'terms_of_service' || $rows !== 1 || ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
				return false;
			}
			$content = $wpdb->get_var( $wpdb->prepare( 'SELECT terms_content FROM %i LIMIT 1', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 1 行だけ確認する。
			return trim( (string) $content ) === trim( (string) KTPWP_Terms_Of_Service::get_default_terms_content() );
		}

		/**
		 * @param string $table テーブル名。
		 * @return bool
		 */
		private static function table_exists( $table ) {
			global $wpdb;
			return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 存在確認のみ。
		}

		/**
		 * @param string $table テーブル名。
		 * @return int
		 */
		private static function count_rows( $table ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- 件数の確認のみ。
		}

		/**
		 * 管理画面の通知（引き継ぎ結果 / 引き継げなかったテーブル）。
		 *
		 * @return void
		 */
		public static function admin_notices() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$count = get_transient( self::RESULT_TRANSIENT );
			if ( $count ) {
				delete_transient( self::RESULT_TRANSIENT );
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html(
						sprintf(
							/* translators: %d: number of tables */
							__( 'KantanPro 1.3.48 以前のデータ（%d テーブル）と設定を引き継ぎました。', 'kantanpro' ),
							(int) $count
						)
					)
					. '</p></div>';
			}

			$conflicts = get_option( self::CONFLICT_OPTION );
			if ( is_array( $conflicts ) && $conflicts !== array() ) {
				echo '<div class="notice notice-warning"><p>'
					. esc_html__( 'KantanPro 1.3.48 以前のデータが残っていますが、更新後に入力したデータもあるため自動では引き継いでいません（上書きでデータが消えるのを防ぐため）。対象:', 'kantanpro' )
					. ' ' . esc_html( implode( ', ', $conflicts ) )
					. '</p></div>';
			}
		}
	}
}
