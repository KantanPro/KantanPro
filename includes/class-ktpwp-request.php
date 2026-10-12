<?php
/**
 * 画面移動用のクエリ引数（$_GET）の読み取り口
 *
 * 一覧画面の並べ替え・ページ送り・検索語・表示中のレコード ID など、
 * URL に載せて画面を切り替えるための引数だけをここで読む。
 * どれもデータを変更しない読み取り専用の引数で、ブックマークや
 * 共有リンクでもそのまま開ける必要があるため nonce は付けない。
 * その代わり、値はこのクラスで必ずサニタイズし、使う側でさらに
 * 許可リスト（並べ替えの列名・昇順/降順など）や整数化で検証する。
 *
 * データを変更する処理（保存・削除・メール送信など）は POST で受け、
 * 各ハンドラーで wp_verify_nonce() / check_ajax_referer() と
 * current_user_can() を通す。その入力にはこのクラスを使わない。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Request' ) ) {

	/**
	 * 読み取り専用のクエリ引数アクセサ。
	 */
	final class KTPWP_Request {

		/**
		 * クエリ引数があるか。
		 *
		 * @param string $key 引数名。
		 * @return bool
		 */
		public static function has_query( $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 読み取り専用の画面移動用引数（クラス冒頭の説明を参照）。
			return isset( $_GET[ $key ] );
		}

		/**
		 * クエリ引数を文字列で返す（sanitize_text_field 済み）。
		 *
		 * @param string $key     引数名。
		 * @param string $default 無いときの値。
		 * @return string
		 */
		public static function query( $key, $default = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 読み取り専用の画面移動用引数（クラス冒頭の説明を参照）。
			if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
				return $default;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 同上。
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}

		/**
		 * クエリ引数を 0 以上の整数で返す。
		 *
		 * @param string $key     引数名。
		 * @param int    $default 無いときの値。
		 * @return int
		 */
		public static function query_int( $key, $default = 0 ) {
			return self::has_query( $key ) ? absint( self::query( $key ) ) : (int) $default;
		}

		/**
		 * FILTER_SANITIZE_NUMBER_INT 相当（数字と符号だけの文字列）。無ければ null。
		 *
		 * filter_input( INPUT_GET, $key, FILTER_SANITIZE_NUMBER_INT ) の置き換え。
		 *
		 * @param string $key 引数名。
		 * @return string|null
		 */
		public static function query_number( $key ) {
			if ( ! self::has_query( $key ) ) {
				return null;
			}
			return (string) filter_var( self::query( $key ), FILTER_SANITIZE_NUMBER_INT );
		}

		/**
		 * 検索語など、利用側で別の整形（urldecode 等）をしてからサニタイズしたい引数。
		 *
		 * wp_unslash() だけを通した値を返す。戻り値は必ず利用側でサニタイズすること。
		 *
		 * @param string $key     引数名。
		 * @param string $default 無いときの値。
		 * @return string
		 */
		public static function query_unslashed( $key, $default = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 読み取り専用の画面移動用引数（クラス冒頭の説明を参照）。
			if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
				return $default;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 利用側でサニタイズする（メソッドの説明を参照）。
			return (string) wp_unslash( $_GET[ $key ] );
		}

		/**
		 * フォーム送信値 1 件をサニタイズする（array_walk_recursive のコールバック）。
		 *
		 * 呼び出し側で nonce を確認したあと、$_POST を丸ごと保存処理へ渡す前に使う。
		 * 改行を残す欄（メモ等）は sanitize_textarea_field、メールと URL は専用の関数、
		 * それ以外は sanitize_text_field を通す。
		 *
		 * @param mixed      $item 値（参照渡し）。
		 * @param int|string $key  フィールド名。
		 * @return void
		 */
		public static function sanitize_form_value( &$item, $key ) {
			$key = (string) $key;
			if ( ! is_scalar( $item ) ) {
				$item = '';
				return;
			}
			$item = (string) $item;
			if ( in_array( $key, array( 'memo', 'remarks', 'note', 'notes', 'description', 'lead' ), true ) ) {
				$item = sanitize_textarea_field( $item );
			} elseif ( $key === 'email' ) {
				$item = sanitize_email( $item );
			} elseif ( $key === 'url' ) {
				$item = esc_url_raw( $item );
			} else {
				$item = sanitize_text_field( $item );
			}
		}

		/**
		 * すべてのクエリ引数（キーは sanitize_key、値は sanitize_text_field 済み。配列の値は除く）。
		 *
		 * ページ送りのリンクに現在の引数を引き継ぐときなどに使う。
		 *
		 * @return array<string, string>
		 */
		public static function query_all() {
			$out = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 読み取り専用の画面移動用引数（クラス冒頭の説明を参照）。
			foreach ( array_keys( $_GET ) as $key ) {
				$clean_key = sanitize_key( (string) $key );
				if ( $clean_key === '' ) {
					continue;
				}
				$value = self::query( (string) $key, null );
				if ( $value === null ) {
					continue;
				}
				$out[ $clean_key ] = $value;
			}
			return $out;
		}
	}
}
