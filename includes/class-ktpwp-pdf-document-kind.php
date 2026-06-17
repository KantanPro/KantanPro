<?php
/**
 * 帳票種別（SaaS PdfDocumentKind 相当）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTPWP_Pdf_Document_Kind {

	public const ESTIMATE = 'estimate';

	public const ORDER = 'order';

	public const BULK_INVOICE = 'bulk_invoice';

	/**
	 * @return list<string>
	 */
	public static function all() {
		return array(
			self::ESTIMATE,
			self::ORDER,
			self::BULK_INVOICE,
		);
	}

	/**
	 * 受注進捗から帳票種別を判定（見積系 → estimate、それ以外 → order）
	 *
	 * @param int $progress 進捗 1–7
	 */
	public static function from_order_progress( $progress ) {
		$p = (int) $progress;
		if ( in_array( $p, array( 1, 2 ), true ) ) {
			return self::ESTIMATE;
		}

		return self::ORDER;
	}
}
