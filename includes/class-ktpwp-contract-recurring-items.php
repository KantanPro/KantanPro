<?php
/**
 * 定期請求明細行の正規化・保存
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {

	/**
	 * サービス・契約の定期請求明細行を扱う。
	 */
	class KTPWP_Contract_Recurring_Items {

		/**
		 * 明細行を正規化する。
		 *
		 * @param array<int, array<string, mixed>> $rows 入力行。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: ?float}>
		 */
		public static function normalize_rows( $rows ) {
			$items = array();

			if ( ! is_array( $rows ) ) {
				return $items;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$name = trim( (string) ( $row['item_name'] ?? '' ) );
				if ( $name === '' ) {
					continue;
				}

				$tax_rate = isset( $row['tax_rate'] ) && $row['tax_rate'] !== '' && $row['tax_rate'] !== null
					? floatval( $row['tax_rate'] )
					: null;

				$items[] = array(
					'item_name'             => $name,
					'amount'                => max( 0, floatval( $row['amount'] ?? 0 ) ),
					'tax_rate'              => $tax_rate,
					'bill_on_first_invoice' => self::parse_bill_on_first_invoice( $row ),
				);
			}

			return $items;
		}

		/**
		 * WEB初回請求フラグ（未指定時はオン）。
		 *
		 * @param array<string, mixed> $row 入力行。
		 * @return bool
		 */
		private static function parse_bill_on_first_invoice( $row ) {
			if ( ! isset( $row['bill_on_first_invoice'] ) ) {
				return true;
			}

			return rest_sanitize_boolean( $row['bill_on_first_invoice'] );
		}

		/**
		 * 合計金額を返す。
		 *
		 * @param array<int, array{item_name: string, amount: float, tax_rate: ?float}> $items 明細行。
		 * @return float
		 */
		public static function total_amount( $items ) {
			$total = 0.0;

			foreach ( (array) $items as $item ) {
				$total += (float) ( $item['amount'] ?? 0 );
			}

			return round( $total, 2 );
		}

		/**
		 * サービス明細テーブル名
		 *
		 * @return string
		 */
		public static function service_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_service_recurring_item';
		}

		/**
		 * 契約明細テーブル名
		 *
		 * @return string
		 */
		public static function contract_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_contract_recurring_item';
		}

		/**
		 * テーブルが存在するか
		 *
		 * @return bool
		 */
		public static function tables_exist() {
			global $wpdb;

			$table = self::service_table_name();

			return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}

		/**
		 * 定期請求明細テーブルが無ければ作成する。
		 *
		 * @return bool
		 */
		public static function ensure_tables() {
			if ( self::tables_exist() ) {
				self::ensure_service_billing_scope_column();
				return true;
			}

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();
			$service_table   = self::service_table_name();
			$contract_table  = self::contract_table_name();

			$service_sql = "CREATE TABLE {$service_table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				service_id MEDIUMINT(9) NOT NULL DEFAULT 0,
				item_name VARCHAR(255) NOT NULL DEFAULT '',
				amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
				sort_order SMALLINT(5) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY service_id (service_id)
			) {$charset_collate};";

			$contract_sql = "CREATE TABLE {$contract_table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				contract_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				item_name VARCHAR(255) NOT NULL DEFAULT '',
				amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
				sort_order SMALLINT(5) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY contract_id (contract_id)
			) {$charset_collate};";

			dbDelta( $service_sql );
			dbDelta( $contract_sql );

			return self::tables_exist();
		}

		/**
		 * bill_on_first_invoice カラムを追加（未適用環境向け）。
		 *
		 * @return void
		 */
		private static function ensure_service_billing_scope_column() {
			if ( self::service_table_has_column( 'bill_on_first_invoice' ) ) {
				return;
			}

			global $wpdb;
			$table = self::service_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				"ALTER TABLE `{$table}` ADD COLUMN `bill_on_first_invoice` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=WEB初回見積に含める' AFTER `tax_rate`"
			);
		}

		/**
		 * サービスに紐づく明細行
		 *
		 * @param int $service_id サービス ID。
		 * @return array<int, object>
		 */
		public static function get_by_service_id( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return array();
			}

			self::ensure_tables();

			if ( ! self::tables_exist() ) {
				return array();
			}

			$table = self::service_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE service_id = %d ORDER BY sort_order ASC, id ASC",
					$service_id
				)
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * 契約に紐づく明細行
		 *
		 * @param int $contract_id 契約 ID。
		 * @return array<int, object>
		 */
		public static function get_by_contract_id( $contract_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! self::tables_exist() ) {
				return array();
			}

			$table = self::contract_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE contract_id = %d ORDER BY sort_order ASC, id ASC",
					$contract_id
				)
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * サービス明細を全置換
		 *
		 * @param int                              $service_id サービス ID。
		 * @param array<int, array<string, mixed>> $rows       明細行。
		 * @return void
		 */
		public static function replace_for_service( $service_id, $rows ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return false;
			}

			if ( ! self::ensure_tables() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: ktp_service_recurring_item テーブルを作成できませんでした。' );
				}

				return false;
			}

			$table = self::service_table_name();
			$wpdb->delete( $table, array( 'service_id' => $service_id ), array( '%d' ) );

			$sort = 0;
			foreach ( self::normalize_rows( $rows ) as $item ) {
				$insert_data = array(
					'service_id' => $service_id,
					'item_name'  => $item['item_name'],
					'amount'     => $item['amount'],
					'sort_order' => $sort,
				);
				$insert_format = array( '%d', '%s', '%f', '%d' );

				if ( $item['tax_rate'] !== null ) {
					$insert_data['tax_rate'] = $item['tax_rate'];
					array_splice( $insert_format, 3, 0, array( '%f' ) );
				}

				if ( self::service_table_has_column( 'bill_on_first_invoice' ) ) {
					$insert_data['bill_on_first_invoice'] = ! empty( $item['bill_on_first_invoice'] ) ? 1 : 0;
					$insert_format[]                      = '%d';
				}

				$result = $wpdb->insert( $table, $insert_data, $insert_format );
				if ( false === $result ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP: 定期請求項目の保存に失敗しました: ' . $wpdb->last_error );
					}

					return false;
				}

				++$sort;
			}

			return true;
		}

		/**
		 * 契約明細を全置換
		 *
		 * @param int                              $contract_id 契約 ID。
		 * @param array<int, array<string, mixed>> $rows        明細行。
		 * @return void
		 */
		public static function replace_for_contract( $contract_id, $rows ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 ) {
				return false;
			}

			if ( ! self::ensure_tables() ) {
				return false;
			}

			$table = self::contract_table_name();
			$wpdb->delete( $table, array( 'contract_id' => $contract_id ), array( '%d' ) );

			$sort = 0;
			foreach ( self::normalize_rows( $rows ) as $item ) {
				$insert_data = array(
					'contract_id' => $contract_id,
					'item_name'   => $item['item_name'],
					'amount'      => $item['amount'],
					'sort_order'  => $sort,
				);
				$insert_format = array( '%d', '%s', '%f', '%d' );

				if ( $item['tax_rate'] !== null ) {
					$insert_data['tax_rate'] = $item['tax_rate'];
					array_splice( $insert_format, 3, 0, array( '%f' ) );
				}

				$result = $wpdb->insert( $table, $insert_data, $insert_format );
				if ( false === $result ) {
					return false;
				}

				++$sort;
			}

			return true;
		}

		/**
		 * Ajax 応答用に配列化
		 *
		 * @param array<int, object> $rows DB 行。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: string|float}>
		 */
		public static function rows_to_payload( $rows ) {
			$payload = array();

			foreach ( (array) $rows as $row ) {
				$payload[] = array(
					'item_name' => (string) ( $row->item_name ?? '' ),
					'amount'    => (float) ( $row->amount ?? 0 ),
					'tax_rate'  => isset( $row->tax_rate ) && $row->tax_rate !== null && $row->tax_rate !== ''
						? (float) $row->tax_rate
						: '',
				);
			}

			return $payload;
		}

		/**
		 * @param string $column Column name.
		 * @return bool
		 */
		private static function service_table_has_column( $column ) {
			global $wpdb;

			$table = self::service_table_name();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return false;
			}

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$table}` LIKE %s",
					$column
				)
			);

			return is_string( $found ) && $found === $column;
		}
	}
}
