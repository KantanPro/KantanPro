<?php
/**
 * サービス初回費用（既定）の正規化・保存
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Service_Initial_Fees' ) ) {

	/**
	 * サービスマスタの初回費用行を扱う。
	 */
	class KTPWP_Service_Initial_Fees {

		/**
		 * 明細行を正規化する。
		 *
		 * @param array<int, array<string, mixed>> $rows 入力行。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: ?float}>
		 */
		public static function normalize_rows( $rows ) {
			$fees = array();

			if ( ! is_array( $rows ) ) {
				return $fees;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$name = trim( (string) ( $row['fee_name'] ?? '' ) );
				if ( $name === '' ) {
					continue;
				}

				$tax_rate = isset( $row['tax_rate'] ) && $row['tax_rate'] !== '' && $row['tax_rate'] !== null
					? floatval( $row['tax_rate'] )
					: null;

				$fees[] = array(
					'fee_name' => $name,
					'amount'   => max( 0, floatval( $row['amount'] ?? 0 ) ),
					'tax_rate' => $tax_rate,
				);
			}

			return $fees;
		}

		/**
		 * テーブル名
		 *
		 * @return string
		 */
		public static function table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_service_initial_fee';
		}

		/**
		 * テーブルが存在するか
		 *
		 * @return bool
		 */
		public static function tables_exist() {
			global $wpdb;

			$table = self::table_name();

			return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}

		/**
		 * 初回費用テーブルが無ければ作成する。
		 *
		 * @return bool
		 */
		public static function ensure_tables() {
			if ( self::tables_exist() ) {
				return true;
			}

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();
			$table           = self::table_name();

			$sql = "CREATE TABLE {$table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				service_id MEDIUMINT(9) NOT NULL DEFAULT 0,
				fee_name VARCHAR(255) NOT NULL DEFAULT '',
				amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
				sort_order SMALLINT(5) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY service_id (service_id)
			) {$charset_collate};";

			dbDelta( $sql );

			return self::tables_exist();
		}

		/**
		 * サービスに紐づく初回費用行
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

			$table = self::table_name();

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
		 * サービス初回費用を全置換
		 *
		 * @param int                              $service_id サービス ID。
		 * @param array<int, array<string, mixed>> $rows       明細行。
		 * @return bool
		 */
		public static function replace_for_service( $service_id, $rows ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return false;
			}

			if ( ! self::ensure_tables() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: ktp_service_initial_fee テーブルを作成できませんでした。' );
				}

				return false;
			}

			$table = self::table_name();
			$wpdb->delete( $table, array( 'service_id' => $service_id ), array( '%d' ) );

			$sort = 0;
			foreach ( self::normalize_rows( $rows ) as $fee ) {
				$insert_data = array(
					'service_id' => $service_id,
					'fee_name'   => $fee['fee_name'],
					'amount'     => $fee['amount'],
					'sort_order' => $sort,
				);
				$insert_format = array( '%d', '%s', '%f', '%d' );

				if ( $fee['tax_rate'] !== null ) {
					$insert_data['tax_rate'] = $fee['tax_rate'];
					array_splice( $insert_format, 3, 0, array( '%f' ) );
				}

				$result = $wpdb->insert( $table, $insert_data, $insert_format );
				if ( false === $result ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP: 初回費用の保存に失敗しました: ' . $wpdb->last_error );
					}

					return false;
				}

				++$sort;
			}

			return true;
		}

		/**
		 * 別サービスから初回費用をコピーする。
		 *
		 * @param int $from_service_id コピー元サービス ID。
		 * @param int $to_service_id   コピー先サービス ID。
		 * @return bool
		 */
		public static function copy_from_service( $from_service_id, $to_service_id ) {
			$from_service_id = absint( $from_service_id );
			$to_service_id   = absint( $to_service_id );

			if ( $from_service_id <= 0 || $to_service_id <= 0 ) {
				return false;
			}

			$rows = array();
			foreach ( self::get_by_service_id( $from_service_id ) as $fee ) {
				$rows[] = array(
					'fee_name' => (string) ( $fee->fee_name ?? '' ),
					'amount'   => (float) ( $fee->amount ?? 0 ),
					'tax_rate' => isset( $fee->tax_rate ) && $fee->tax_rate !== null ? $fee->tax_rate : null,
				);
			}

			return self::replace_for_service( $to_service_id, $rows );
		}

		/**
		 * Ajax 応答用に配列化
		 *
		 * @param array<int, object> $rows DB 行。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: string|float}>
		 */
		public static function rows_to_payload( $rows ) {
			$payload = array();

			foreach ( (array) $rows as $row ) {
				$payload[] = array(
					'fee_name' => (string) ( $row->fee_name ?? '' ),
					'amount'   => (float) ( $row->amount ?? 0 ),
					'tax_rate' => isset( $row->tax_rate ) && $row->tax_rate !== null && $row->tax_rate !== ''
						? (float) $row->tax_rate
						: '',
				);
			}

			return $payload;
		}
	}
}
