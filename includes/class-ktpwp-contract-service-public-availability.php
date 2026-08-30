<?php
/**
 * 定期契約の状態に応じた公開ページの受付判定。
 *
 * 在庫数（stock）に応じた受付制御（公開一覧の表示可否は is_public チェックに委ねる）:
 * - 在庫 0: 完売御礼！（全顧客の問い合わせ不可）
 * - 在庫 1: 有効（active）または保留中（paused）の契約、または Web 問い合わせ案件が 1 件でもあれば全顧客の問い合わせを停止（保留中）
 * - 在庫 2 以上: 有効契約件数が在庫数以上なら全顧客の問い合わせを停止（保留中）。Web 問い合わせ案件は枠に含めない
 *
 * 販売所は不特定多数がアクセスするため、枠の判定は顧客単位ではなく受付総数で行う。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {

	/**
	 * サービス公開フラグの自動同期。
	 */
	class KTPWP_Contract_Service_Public_Availability {

		/**
		 * 契約保存・サービス保存後に呼ばれる互換フック。
		 *
		 * is_public（サイト公開チェック）はユーザー操作のみとし、ここでは変更しない。
		 * 受付可否は get_public_availability() で実行時判定する。
		 *
		 * @param int $service_id サービス ID。
		 * @return void
		 */
		public static function sync_for_service( $service_id ) {
			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return;
			}
		}

		/**
		 * 在庫数の生値（0=完売、未設定は 1）。
		 *
		 * @param object $service サービス行。
		 * @return int
		 */
		public static function get_stock_value( $service ) {
			if ( ! isset( $service->stock ) ) {
				return 1;
			}

			return max( 0, (int) $service->stock );
		}

		/**
		 * 在庫数を解決する（受付枠計算用。0 の場合は 0 のまま）。
		 *
		 * @param object $service サービス行。
		 * @return int
		 * @deprecated 1.0 以降は get_stock_value() を使用。
		 */
		public static function resolve_stock( $service ) {
			return self::get_stock_value( $service );
		}

		/**
		 * 完売（在庫 0）か。
		 *
		 * @param int $stock 在庫数。
		 * @return bool
		 */
		public static function is_sold_out( $stock ) {
			return (int) $stock <= 0;
		}

		/**
		 * 管理画面向けの枠使用状況サマリー。
		 *
		 * @param int         $service_id   サービス ID。
		 * @param object|null $service      サービス行（省略時は DB から取得）。
		 * @param bool|null   $is_recurring 定期契約用か。
		 * @return array{
		 *   stock:int,
		 *   used_slots:int,
		 *   contract_active_count:int,
		 *   contract_paused_count:int,
		 *   inquiry_count:int,
		 *   acceptance_open:bool,
		 *   availability_state:string,
		 *   status_label:string
		 * }
		 */
		public static function get_slot_usage_summary( $service_id, $service = null, $is_recurring = null ) {
			$service_id = absint( $service_id );
			$service    = $service ?? self::get_service_row( $service_id );

			if ( $service_id <= 0 || ! $service ) {
				return array(
					'stock'                   => 1,
					'used_slots'              => 0,
					'contract_active_count'   => 0,
					'contract_paused_count'   => 0,
					'inquiry_count'           => 0,
					'acceptance_open'         => true,
					'availability_state'    => 'open',
					'status_label'            => '',
				);
			}

			$stock = self::get_stock_value( $service );

			if ( $is_recurring === null ) {
				$is_recurring = self::service_is_recurring( $service_id );
			}

			if ( ! $is_recurring ) {
				return array(
					'stock'                   => $stock,
					'used_slots'              => 0,
					'contract_active_count'   => 0,
					'contract_paused_count'   => 0,
					'inquiry_count'           => 0,
					'acceptance_open'         => true,
					'availability_state'    => 'open',
					'status_label'            => '',
				);
			}

			$include_paused          = $stock <= 1;
			$contract_active_count     = self::count_contracts_with_status( $service_id, 'active' );
			$contract_paused_count     = $include_paused ? self::count_contracts_with_status( $service_id, 'paused' ) : 0;
			$inquiry_count             = self::count_open_inquiry_orders( $service_id );
			$used_slots                = self::count_used_public_slots( $service_id, $stock );
			$availability              = self::get_public_availability( $service_id, $service, $is_recurring );

			return array(
				'stock'                 => $stock,
				'used_slots'            => $used_slots,
				'contract_active_count' => $contract_active_count,
				'contract_paused_count' => $contract_paused_count,
				'inquiry_count'         => $inquiry_count,
				'acceptance_open'       => (bool) $availability['acceptance_open'],
				'availability_state'    => (string) $availability['availability_state'],
				'status_label'          => (string) $availability['status_label'],
			);
		}

		/**
		 * 公開ページの受付状態を返す。
		 *
		 * @param int      $service_id   サービス ID。
		 * @param object   $service      サービス行。
		 * @param bool|null $is_recurring 定期契約用か。
		 * @return array{acceptance_open:bool,availability_state:string,status_label:string}
		 */
		public static function get_public_availability( $service_id, $service, $is_recurring = null ) {
			$service_id = absint( $service_id );
			$result     = array(
				'acceptance_open'    => true,
				'availability_state' => 'open',
				'status_label'       => '',
			);

			if ( $service_id <= 0 ) {
				return $result;
			}

			$stock = self::get_stock_value( $service );

			if ( self::is_sold_out( $stock ) ) {
				return array(
					'acceptance_open'    => false,
					'availability_state' => 'sold_out',
					'status_label'       => __( '完売御礼！', 'kantanpro' ),
				);
			}

			if ( $is_recurring === null ) {
				$is_recurring = self::service_is_recurring( $service_id );
			}

			if ( ! $is_recurring ) {
				return $result;
			}

			if ( ! self::is_acceptance_open( $service_id, $stock, true ) ) {
				return array(
					'acceptance_open'    => false,
					'availability_state' => 'pending',
					'status_label'       => __( '保留中', 'kantanpro' ),
				);
			}

			return $result;
		}

		/**
		 * 公開受付を続けるべきか。
		 *
		 * @param int $service_id サービス ID。
		 * @param int $stock        在庫数。
		 * @return bool
		 */
		public static function should_be_public( $service_id, $stock ) {
			$stock = max( 1, (int) $stock );

			return self::count_used_public_slots( $service_id, $stock ) < $stock;
		}

		/**
		 * 公開ページから問い合わせを受け付けられるか。
		 *
		 * @param int      $service_id サービス ID。
		 * @param int|null $stock        在庫数（省略時は 1）。
		 * @return bool
		 */
		public static function is_acceptance_open( $service_id, $stock = null, $is_recurring = null ) {
			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return true;
			}

			if ( $is_recurring === null ) {
				$is_recurring = self::service_is_recurring( $service_id );
			}

			$stock = $stock === null ? 1 : (int) $stock;

			if ( self::is_sold_out( $stock ) ) {
				return false;
			}

			if ( ! $is_recurring ) {
				return true;
			}

			$stock = max( 1, $stock );

			return self::count_used_public_slots( $service_id, $stock ) < $stock;
		}

		/**
		 * 公開受付枠の占有数（契約件数＋問い合わせ案件件数。顧客の同一性は問わない）。
		 *
		 * @param int $service_id サービス ID。
		 * @param int $stock        在庫数。
		 * @return int
		 */
		public static function count_used_public_slots( $service_id, $stock ) {
			$service_id = absint( $service_id );
			$stock      = max( 1, (int) $stock );

			if ( $service_id <= 0 ) {
				return 0;
			}

			$include_paused = $stock <= 1;
			$statuses       = $include_paused ? array( 'active', 'paused' ) : array( 'active' );
			$contract_count = self::count_contracts_with_statuses( $service_id, $statuses );

			if ( $stock > 1 ) {
				return max( 0, $contract_count );
			}

			if ( $contract_count > 0 ) {
				return $contract_count;
			}

			return max( 0, self::count_open_inquiry_orders( $service_id ) );
		}

		/**
		 * 契約成立後に残った Web 問い合わせ案件をボツにする（枠カウントから外す）。
		 *
		 * @param int $service_id サービス ID。
		 * @return int 更新した案件数。
		 */
		public static function close_stale_public_inquiries_for_service( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return 0;
			}

			$order_ids = array_keys( self::get_open_inquiry_order_ids( $service_id ) );
			if ( $order_ids === array() ) {
				return 0;
			}

			$table       = $wpdb->prefix . 'ktp_order';
			$placeholders = implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) );
			$params       = array_merge( array( 7 ), $order_ids );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET progress = %d WHERE id IN ({$placeholders}) AND progress IN (1, 2, 3)",
					$params
				)
			);

			return false === $result ? 0 : (int) $result;
		}

		/**
		 * 指定ステータスの契約件数。
		 *
		 * @param int          $service_id サービス ID。
		 * @param list<string> $statuses   契約ステータス。
		 * @return int
		 */
		private static function count_contracts_with_statuses( $service_id, array $statuses ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 || $statuses === array() || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return 0;
			}

			$db = KTPWP_Contract_DB::get_instance();
			if ( ! $db->tables_exist() ) {
				return 0;
			}

			$table    = $db->get_contract_table_name();
			$statuses = array_values(
				array_filter(
					array_map(
						static function ( $status ) {
							return sanitize_key( (string) $status );
						},
						$statuses
					)
				)
			);

			if ( $statuses === array() ) {
				return 0;
			}

			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$params       = array_merge( array( $service_id ), $statuses );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE service_id = %d AND status IN ({$placeholders})",
					$params
				)
			);

			return max( 0, (int) $count );
		}

		/**
		 * 枠を占有する Web 問い合わせ案件数（受付中・見積中・受注）。
		 *
		 * @param int $service_id サービス ID。
		 * @return int
		 */
		private static function count_open_inquiry_orders( $service_id ) {
			return count( self::get_open_inquiry_order_ids( $service_id ) );
		}

		/**
		 * 枠を占有する Web 問い合わせ案件 ID 一覧。
		 *
		 * @param int $service_id サービス ID。
		 * @return array<int, int> 案件 ID をキーとした連想配列。
		 */
		private static function get_open_inquiry_order_ids( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			$orders     = array();

			if ( $service_id <= 0 ) {
				return $orders;
			}

			$table = $wpdb->prefix . 'ktp_order';
			$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			$cols  = is_array( $cols ) ? $cols : array();

			if ( in_array( 'external_source', $cols, true ) && in_array( 'external_order_id', $cols, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE external_source = %s AND external_order_id = %s AND progress IN (1, 2, 3)",
						'public_product',
						(string) $service_id
					)
				);

				if ( is_array( $found ) ) {
					foreach ( $found as $order_id ) {
						$order_id = absint( $order_id );
						if ( $order_id > 0 ) {
							$orders[ $order_id ] = $order_id;
						}
					}
				}
			}

			$memo_prefix = sprintf(
				/* translators: 1: service ID (must keep trailing space for exact match) */
				__( '商品ID: %1$d ', 'kantanpro' ),
				$service_id
			);
			$memo_like   = '%' . $wpdb->esc_like( $memo_prefix ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE memo LIKE %s AND progress IN (1, 2, 3)",
					$memo_like
				)
			);

			if ( is_array( $found ) ) {
				foreach ( $found as $order_id ) {
					$order_id = absint( $order_id );
					if ( $order_id > 0 ) {
						$orders[ $order_id ] = $order_id;
					}
				}
			}

			return $orders;
		}

		/**
		 * サービス行を取得する。
		 *
		 * @param int $service_id サービス ID。
		 * @return object|null
		 */
		private static function get_service_row( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return null;
			}

			$table = $wpdb->prefix . 'ktp_service';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$service_id
				)
			);
		}

		/**
		 * 定期契約用サービスか。
		 *
		 * @param int $service_id サービス ID。
		 * @return bool
		 */
		public static function service_is_recurring( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return false;
			}

			$table = $wpdb->prefix . 'ktp_service';
			$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			if ( ! is_array( $cols ) || ! in_array( 'contract_billing_cycle', $cols, true ) ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cycle = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT contract_billing_cycle FROM {$table} WHERE id = %d",
					$service_id
				)
			);

			return class_exists( 'KTPWP_Contract_Billing_Cycle' )
				&& KTPWP_Contract_Billing_Cycle::is_recurring( $cycle ?? 'none' );
		}

		/**
		 * 公開ページからの問い合わせ案件が枠を占有しているか（受付中・見積中・受注）。
		 *
		 * @param int $service_id サービス ID。
		 * @return bool
		 */
		public static function service_has_open_inquiry_order( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return false;
			}

			$table = $wpdb->prefix . 'ktp_order';
			$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			$cols  = is_array( $cols ) ? $cols : array();

			if ( in_array( 'external_source', $cols, true ) && in_array( 'external_order_id', $cols, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE external_source = %s AND external_order_id = %s AND progress IN (1, 2, 3) LIMIT 1",
						'public_product',
						(string) $service_id
					)
				);

				if ( ! empty( $found ) ) {
					return true;
				}
			}

			$memo_prefix = sprintf(
				/* translators: 1: service ID (must keep trailing space for exact match) */
				__( '商品ID: %1$d ', 'kantanpro' ),
				$service_id
			);
			$memo_like   = '%' . $wpdb->esc_like( $memo_prefix ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE memo LIKE %s AND progress IN (1, 2, 3) LIMIT 1",
					$memo_like
				)
			);

			return ! empty( $found );
		}

		/**
		 * 指定ステータスの契約が存在するか。
		 *
		 * @param int          $service_id サービス ID。
		 * @param list<string> $statuses   契約ステータス。
		 * @return bool
		 */
		private static function service_has_contract_with_statuses( $service_id, array $statuses ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 || $statuses === array() || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return false;
			}

			$db = KTPWP_Contract_DB::get_instance();
			if ( ! $db->tables_exist() ) {
				return false;
			}

			$table    = $db->get_contract_table_name();
			$statuses = array_values(
				array_filter(
					array_map(
						static function ( $status ) {
							return sanitize_key( (string) $status );
						},
						$statuses
					)
				)
			);

			if ( $statuses === array() ) {
				return false;
			}

			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$params       = array_merge( array( $service_id ), $statuses );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE service_id = %d AND status IN ({$placeholders}) LIMIT 1",
					$params
				)
			);

			return ! empty( $found );
		}

		/**
		 * 指定ステータスの契約件数。
		 *
		 * @param int    $service_id サービス ID。
		 * @param string $status     契約ステータス。
		 * @return int
		 */
		private static function count_contracts_with_status( $service_id, $status ) {
			global $wpdb;

			$service_id = absint( $service_id );
			$status     = sanitize_key( (string) $status );

			if ( $service_id <= 0 || $status === '' || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return 0;
			}

			$db = KTPWP_Contract_DB::get_instance();
			if ( ! $db->tables_exist() ) {
				return 0;
			}

			$table = $db->get_contract_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE service_id = %d AND status = %s",
					$service_id,
					$status
				)
			);

			return max( 0, (int) $count );
		}

		/**
		 * @param string $table_name テーブル名。
		 * @return bool
		 */
		private static function service_table_has_is_public_column( $table_name ) {
			return self::service_table_has_column( $table_name, 'is_public' );
		}

		/**
		 * @param string $table_name テーブル名。
		 * @return bool
		 */
		private static function service_table_has_stock_column( $table_name ) {
			return self::service_table_has_column( $table_name, 'stock' );
		}

		/**
		 * @param string $table_name  テーブル名。
		 * @param string $column_name カラム名。
		 * @return bool
		 */
		private static function service_table_has_column( $table_name, $column_name ) {
			global $wpdb;

			static $cache = array();
			$cache_key    = $table_name . ':' . $column_name;

			if ( isset( $cache[ $cache_key ] ) ) {
				return $cache[ $cache_key ];
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`" );
			$cache[ $cache_key ] = is_array( $columns ) && in_array( $column_name, $columns, true );

			return $cache[ $cache_key ];
		}
	}
}
