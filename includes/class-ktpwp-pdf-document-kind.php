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
		if ( self::print_uses_estimate_layout( $p ) ) {
			return self::ESTIMATE;
		}

		return self::ORDER;
	}

	/**
	 * 見積レイアウト（原価なし・有効期限文言）を使う進捗か（SaaS Order::printUsesEstimateLayout 相当）
	 *
	 * @param int $progress 進捗 1–7
	 */
	public static function print_uses_estimate_layout( $progress ) {
		return in_array( (int) $progress, array( 1, 2 ), true );
	}

	/**
	 * 原価明細を印刷に含める進捗か（SaaS Order::printShowsCostSection 相当）
	 *
	 * @param int $progress 進捗 1–7
	 */
	public static function print_shows_cost_section( $progress ) {
		return in_array( (int) $progress, array( 3, 7 ), true );
	}

	/**
	 * 進捗番号 → ステータスラベル
	 *
	 * @param int $progress 進捗 1–7
	 */
	public static function progress_label( $progress ) {
		$labels = array(
			1 => '受付中',
			2 => '見積中',
			3 => '受注',
			4 => '完了',
			5 => '請求済',
			6 => '入金済',
			7 => 'ボツ',
		);
		$p = (int) $progress;

		return $labels[ $p ] ?? '';
	}
}
