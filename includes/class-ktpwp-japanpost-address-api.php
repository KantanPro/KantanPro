<?php
/**
 * 日本郵便 郵便番号・デジタルアドレスAPI（住所検索用）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * トークン取得・郵便番号検索（サーバー側のみでシークレットを扱う）
 */
class KTPWP_JapanPost_Address_API {

	const OPTION_KEY = 'ktp_japanpost_api_settings';

	const URL_PRODUCTION = 'https://api.da.pf.japanpost.jp';

	const URL_STUB = 'https://stub-qz73x.da.pf.japanpost.jp';

	/**
	 * テスト用APIリファレンス 1.0.2.x は searchcode V2 / token V2。スタブは v2 のみ。
	 * 本番ホストは現行ドキュメントでは v1 が一般的なため本番は v1。
	 */
	private static function api_prefix() {
		$s = self::get_settings();
		return ( isset( $s['environment'] ) && $s['environment'] === 'stub' ) ? '/api/v2' : '/api/v1';
	}

	/**
	 * 設定配列を取得
	 *
	 * @return array{enabled:bool,environment:string,client_id:string,secret_key:string}
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'     => false,
			'environment' => 'production',
			'client_id'   => '',
			'secret_key'  => '',
		);
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		return array_merge( $defaults, $raw );
	}

	/**
	 * 管理画面で「有効」かつクレデンシャルが揃っているか
	 */
	public static function is_enabled() {
		$s = self::get_settings();
		return ! empty( $s['enabled'] )
			&& ! empty( trim( (string) $s['client_id'] ) )
			&& ! empty( trim( (string) $s['secret_key'] ) );
	}

	/**
	 * APIベースURL
	 */
	public static function get_base_url() {
		$s = self::get_settings();
		return ( isset( $s['environment'] ) && $s['environment'] === 'stub' ) ? self::URL_STUB : self::URL_PRODUCTION;
	}

	/**
	 * エンドユーザーIP（X-Forwarded-For 用）。日本郵便API仕様でトークン取得に必須。
	 *
	 * @return string
	 */
	public static function get_request_client_ip() {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$candidate = trim( $parts[0] );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) {
			return (string) $_SERVER['REMOTE_ADDR'];
		}
		return '127.0.0.1';
	}

	/**
	 * 7桁郵便番号で住所を取得（最初の1件）
	 *
	 * @param string $zip7 半角7桁
	 * @param string $xff_ip X-Forwarded-For に渡すIP
	 * @return array{prefecture:string,city:string,address:string}|WP_Error
	 */
	public static function lookup_zip( $zip7, $xff_ip ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'ktp_jp_disabled', __( '日本郵便APIの設定が有効ではありません。', 'ktpwp' ) );
		}
		$zip7 = preg_replace( '/\D/', '', (string) $zip7 );
		if ( strlen( $zip7 ) !== 7 ) {
			return new WP_Error( 'ktp_jp_zip', __( '郵便番号は7桁の数字で入力してください。', 'ktpwp' ) );
		}
		if ( ! filter_var( $xff_ip, FILTER_VALIDATE_IP ) ) {
			$xff_ip = '127.0.0.1';
		}

		$token = self::get_bearer_token( $xff_ip );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$base = rtrim( self::get_base_url(), '/' );
		$url  = $base . self::api_prefix() . '/searchcode/' . rawurlencode( $zip7 ) . '?limit=1&searchtype=2';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'   => 'Bearer ' . $token,
					'Accept'          => 'application/json',
					'X-Forwarded-For' => $xff_ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['message'] ) ? (string) $data['message'] : __( '郵便番号検索に失敗しました。', 'ktpwp' );
			return new WP_Error( 'ktp_jp_http', $msg, array( 'status' => $code ) );
		}
		if ( empty( $data['addresses'] ) || ! is_array( $data['addresses'] ) ) {
			return new WP_Error( 'ktp_jp_empty', __( '該当する住所が見つかりませんでした。', 'ktpwp' ) );
		}

		$rows = self::flatten_address_rows( $data['addresses'] );
		$a     = null;
		foreach ( $rows as $cand ) {
			if ( is_array( $cand ) && self::address_row_has_data( $cand ) ) {
				$a = $cand;
				break;
			}
		}
		if ( ! is_array( $a ) ) {
			return new WP_Error( 'ktp_jp_empty', __( '該当する住所が見つかりませんでした。', 'ktpwp' ) );
		}

		return self::format_address_from_api_row( $a );
	}

	/**
	 * addresses がネストした配列のときに平坦化（addresszip V2 形式など）
	 *
	 * @param array $addresses API の addresses
	 * @return array<int,array<string,mixed>>
	 */
	private static function flatten_address_rows( $addresses ) {
		$result = array();
		if ( ! is_array( $addresses ) ) {
			return $result;
		}
		foreach ( $addresses as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$keys   = array_keys( $item );
			$n      = count( $item );
			$is_seq = $n > 0 && $keys === range( 0, $n - 1 );
			if ( $is_seq && isset( $item[0] ) && is_array( $item[0] ) ) {
				foreach ( $item as $sub ) {
					if ( is_array( $sub ) ) {
						$result = array_merge( $result, self::flatten_address_rows( array( $sub ) ) );
					}
				}
			} else {
				$result[] = $item;
			}
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $a
	 */
	private static function address_row_has_data( $a ) {
		$keys = array( 'pref_name', 'city_name', 'town_name', 'prefecture', 'city', 'town', 'zip_code', 'zipcode' );
		foreach ( $keys as $k ) {
			if ( isset( $a[ $k ] ) && $a[ $k ] !== null && $a[ $k ] !== '' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $a
	 * @return array{prefecture:string,city:string,address:string}
	 */
	private static function format_address_from_api_row( $a ) {
		$prefecture = self::str_prop( $a, array( 'pref_name', 'prefecture', 'prefecture_name' ) );
		$city_name  = self::str_prop( $a, array( 'city_name', 'city', 'municipality_name', 'shikuchoson' ) );
		$town_name  = self::str_prop( $a, array( 'town_name', 'town', 'choiki', 'oaza_town_name', 'machiaza' ) );
		$block      = self::str_prop( $a, array( 'block_name', 'block', 'banchi', 'street_number' ) );
		$other      = self::str_prop( $a, array( 'other_name', 'other', 'koaza' ) );
		$biz        = self::str_prop( $a, array( 'biz_name', 'business_name', 'jigyosho_name' ) );

		$city = $city_name . $town_name;
		$addr_parts = array();
		if ( $biz !== '' ) {
			$addr_parts[] = $biz;
		}
		if ( $block !== '' ) {
			$addr_parts[] = $block;
		}
		if ( $other !== '' ) {
			$addr_parts[] = $other;
		}
		$address = implode( ' ', $addr_parts );

		return array(
			'prefecture' => $prefecture,
			'city'       => $city,
			'address'    => $address,
		);
	}

	/**
	 * @param array<string,mixed> $row
	 * @param string[]            $keys
	 */
	private static function str_prop( $row, $keys ) {
		foreach ( $keys as $k ) {
			if ( ! isset( $row[ $k ] ) || $row[ $k ] === null ) {
				continue;
			}
			$v = is_string( $row[ $k ] ) ? $row[ $k ] : (string) $row[ $k ];
			if ( $v !== '' ) {
				return $v;
			}
		}
		return '';
	}

	/**
	 * Bearer トークン取得（トランジェントでキャッシュ）
	 *
	 * @param string $xff_ip
	 * @return string|WP_Error
	 */
	private static function get_bearer_token( $xff_ip ) {
		$s        = self::get_settings();
		$cache_key = 'ktp_jp_da_tok_' . md5( (string) $s['client_id'] . '|' . self::get_base_url() . '|' . self::api_prefix() );
		$cached   = get_transient( $cache_key );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$base = rtrim( self::get_base_url(), '/' );
		$url  = $base . self::api_prefix() . '/j/token';
		$body = wp_json_encode(
			array(
				'grant_type' => 'client_credentials',
				'client_id'  => $s['client_id'],
				'secret_key' => $s['secret_key'],
			)
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'    => 'application/json',
					'X-Forwarded-For' => $xff_ip,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $json['token'] ) ) {
			$msg = isset( $json['message'] ) ? (string) $json['message'] : __( 'アクセストークンの取得に失敗しました。クライアントIDとシークレットを確認してください。', 'ktpwp' );
			return new WP_Error( 'ktp_jp_token', $msg, array( 'status' => $code ) );
		}

		$token      = (string) $json['token'];
		$expires_in = isset( $json['expires_in'] ) ? (int) $json['expires_in'] : 3600;
		$ttl        = max( 120, min( $expires_in - 60, DAY_IN_SECONDS ) );
		set_transient( $cache_key, $token, $ttl );

		return $token;
	}
}
