<?php
/**
 * 「情報」タブ。
 *
 * レポートタブの代わりに置く、プラグイン自身の情報を表示する画面。
 * タブは6つある前提でレイアウトが組まれているため、枠を空けずにここを使う。
 *
 * **有料版（KantanProEX）の案内は GitHub 配布版だけに出す。**
 * wp.org 版ではガイドライン11（宣伝で画面を占有しない）に触れる余地を残さないよう、
 * tools/strip-wporg-features.py が upgrade_notice() の中身ごと空に差し替える。
 * フラグで隠すだけではコードが ZIP に残り、指摘の対象になる（Phase 1 の教訓）。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'KTPWP_Tab_Info' ) ) {

	class KTPWP_Tab_Info {

		/**
		 * 情報タブの内容を返す。
		 *
		 * @return string HTML
		 */
		public function render() {
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				return '<div class="error-message">'
					. esc_html__( 'このページにアクセスする権限がありません。', 'kantanpro' )
					. '</div>';
			}

			$name    = defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanPro(WP)';
			$version = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '';

			// 他タブと同じコントローラーバー。
			// 検索対象が無いタブなので、左に見出し・右によく使うリンクを置いて
			// 帯の高さと配置だけ他タブに合わせる（レイアウトが揃わないと浮いて見える）。
			$html  = '<div class="controller ktp-info-controller">';
			$html .= '<div class="ktp-info-controller__title">'
				. esc_html__( '情報', 'kantanpro' )
				. self::distribution_badge()
				. '</div>';
			$html .= '<div class="ktp-info-controller__bar">';
			$html .= '<a class="ktp-info-controller__link" href="' . esc_url( 'https://www.kantanpro.com/docs' ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'ヘルプ・使い方', 'kantanpro' ) . '</a>';
			$html .= '<a class="ktp-info-controller__link" href="' . esc_url( admin_url( 'admin.php?page=ktp-settings' ) ) . '">'
				. esc_html__( '設定画面を開く', 'kantanpro' ) . '</a>';
			$html .= '</div></div>';

			$html .= '<div class="ktp_data_contents ktp-info-tab">';
			$html .= '<div class="ktp_data_list_box">';

			// 概要
			$html .= '<div class="ktp-info-section">';
			$html .= '<h3>' . esc_html( $name );
			if ( $version !== '' ) {
				$html .= ' <span class="ktp-info-version">v' . esc_html( $version ) . '</span>';
			}
			$html .= '</h3>';
			$html .= '<p>' . esc_html__( '受注案件・顧客・サービス・協力会社を WordPress 上で一元管理するためのプラグインです。', 'kantanpro' ) . '</p>';
			$html .= '</div>';

			// 公開範囲。ショートコードの設置手順は書かない
			// （この画面を見ている時点で設置は済んでいる）。
			$html .= '<div class="ktp-info-section">';
			$html .= '<h4>' . esc_html__( '公開範囲', 'kantanpro' ) . '</h4>';
			$html .= '<p>'
				. esc_html__( 'この画面が見えるのは、ログイン済みで編集権限のある利用者だけです。未ログインの訪問者にはログイン画面が表示され、業務データは一切出ません。', 'kantanpro' )
				. '</p>';
			$html .= '</div>';

			// 現在の状態（サポート問い合わせ時に役立つ情報）
			$html .= '<div class="ktp-info-section">';
			$html .= '<h4>' . esc_html__( '動作環境', 'kantanpro' ) . '</h4>';
			$html .= '<table class="ktp-info-table"><tbody>';
			$html .= self::row( __( 'プラグイン', 'kantanpro' ), $name . ( $version !== '' ? ' v' . $version : '' ) );
			$html .= self::row( __( 'WordPress', 'kantanpro' ), get_bloginfo( 'version' ) );
			$html .= self::row( __( 'PHP', 'kantanpro' ), PHP_VERSION );
			$html .= self::row( __( 'サイトの言語', 'kantanpro' ), get_locale() );
			$html .= self::row( __( 'タイムゾーン', 'kantanpro' ), wp_timezone_string() );
			$html .= '</tbody></table>';
			$html .= '</div>';

			// 登録件数
			$counts = self::counts();
			if ( ! empty( $counts ) ) {
				$html .= '<div class="ktp-info-section">';
				$html .= '<h4>' . esc_html__( '登録件数', 'kantanpro' ) . '</h4>';
				$html .= '<table class="ktp-info-table"><tbody>';
				foreach ( $counts as $label => $count ) {
					$html .= self::row( $label, number_format_i18n( $count ) );
				}
				$html .= '</tbody></table>';
				$html .= '</div>';
			}

			// 有料版の案内（GitHub 配布版のみ。wp.org 版ではビルド時に空になる）
			$html .= self::upgrade_notice();

			// リンクはコントローラーバーに移したのでここには置かない。

			$html .= '</div></div>';

			return $html;
		}

		/**
		 * KantanProEX（WP）の案内。
		 *
		 * 自社配布（GitHub）版でのみ表示する。WordPress.org 版では
		 * ビルド時にこのメソッドの中身が空に差し替えられる。
		 *
		 * @return string
		 */
		private static function upgrade_notice() {
			if ( function_exists( 'ktpwp_uses_self_hosted_updates' ) && ! ktpwp_uses_self_hosted_updates() ) {
				return '';
			}

			// 効能で書く（機能名の羅列にしない）。数字や挙動は実装に基づくものだけ。
			$points = array(
				array(
					'title' => __( '数字で経営判断ができる', 'kantanpro' ),
					'body'  => __( '売上・利益の推移、顧客別・サービス別の集計をグラフで表示します。どの取引先とどの商品が実際に利益を生んでいるのか、勘ではなく数字で分かります。', 'kantanpro' ),
				),
				array(
					'title' => __( 'チームで使える', 'kantanpro' ),
					'body'  => __( '無料版はスタッフを追加できず管理者ひとりでの利用です。KantanProEX なら担当者を追加して、案件ごとに分担しながら同じデータを共有できます。', 'kantanpro' ),
				),
				array(
					'title' => __( 'サイトから申し込みを受け取れる', 'kantanpro' ),
					'body'  => __( '自社の商品をサイトに公開し、申し込みをそのまま案件として受け取れます。問い合わせメールを見て手で入力し直す作業がなくなります。', 'kantanpro' ),
				),
				array(
					'title' => __( 'カード決済まで一気通貫', 'kantanpro' ),
					'body'  => __( 'Stripe と連携し、請求書からそのままカード決済を受けられます。入金の確認と消し込みに追われる時間が減ります。', 'kantanpro' ),
				),
				array(
					'title' => __( '毎月の請求書送付を自動化', 'kantanpro' ),
					'body'  => __( '定期契約の請求書を、決めたサイクルで自動作成してメール送信します。月初の定型作業をまるごと任せられます。', 'kantanpro' ),
				),
			);

			$html  = '<div class="ktp-info-section ktp-info-upgrade">';
			$html .= '<h4>' . esc_html__( 'KantanProEX（WP）なら、ここまでできます', 'kantanpro' ) . '</h4>';
			$html .= '<p class="ktp-info-upgrade__lead">'
				. esc_html__( 'いま使っている画面と操作はそのままに、集計・チーム運用・決済までを一つにまとめた有料版です。買い切りなので月額はかかりません。', 'kantanpro' )
				. '</p>';

			$html .= '<ul class="ktp-info-upgrade__list">';
			foreach ( $points as $point ) {
				$html .= '<li>'
					. '<span class="ktp-info-upgrade__point">' . esc_html( $point['title'] ) . '</span>'
					. '<span class="ktp-info-upgrade__desc">' . esc_html( $point['body'] ) . '</span>'
					. '</li>';
			}
			$html .= '</ul>';

			$html .= '<p class="ktp-info-upgrade__cta"><a class="ktp-info-upgrade-link" href="'
				. esc_url( 'https://www.kantanpro.com/product/kantanpro-ex' )
				. '" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'KantanProEX（WP）の詳細と価格を見る', 'kantanpro' )
				. '</a></p>';
			$html .= '<p class="ktp-info-upgrade__note">'
				. esc_html__( '※ データはそのまま引き継げます。入れ替えても入力し直しは不要です。', 'kantanpro' )
				. '</p>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * どの配布版が動いているかのバッジ。
		 *
		 * wp.org 版と自社配布（GitHub）版は見た目がほぼ同じで、
		 * どちらを見ているのか画面から判断できないため常に出す。
		 * 更新の受け取り方が変わるので、利用者にとっても意味のある情報。
		 *
		 * @return string
		 */
		private static function distribution_badge() {
			$is_wporg = defined( 'KTPWP_DISTRIBUTION' ) && KTPWP_DISTRIBUTION === 'wporg';

			$label = $is_wporg
				? __( 'WordPress.org 版', 'kantanpro' )
				: __( 'GitHub 版', 'kantanpro' );

			$title = $is_wporg
				? __( 'WordPress.org から配布されている版です。更新は WordPress 本体が行います。', 'kantanpro' )
				: __( '開発元（GitHub）から配布されている版です。更新はプラグインが自身で確認します。', 'kantanpro' );

			$modifier = $is_wporg ? 'is-wporg' : 'is-github';

			return ' <span class="ktp-info-dist-badge ' . esc_attr( $modifier ) . '" title="' . esc_attr( $title ) . '">'
				. esc_html( $label )
				. '</span>';
		}

		/**
		 * 表の1行。
		 *
		 * @param string $label 見出し。
		 * @param string $value 値。
		 * @return string
		 */
		private static function row( $label, $value ) {
			return '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}

		/**
		 * 主要テーブルの件数。テーブルが無い場合はその行を出さない。
		 *
		 * @return array<string,int>
		 */
		private static function counts() {
			global $wpdb;

			$targets = array(
				__( '顧客', 'kantanpro' )     => 'ktp_client',
				__( '受注書', 'kantanpro' )   => 'ktp_order',
				__( 'サービス', 'kantanpro' ) => 'ktp_service',
				__( '協力会社', 'kantanpro' ) => 'ktp_supplier',
			);

			$out = array();
			foreach ( $targets as $label => $suffix ) {
				$table = $wpdb->prefix . $suffix;
				// テーブル名は $wpdb->prefix と固定文字列のみで組み立てているため
				// プレースホルダは使えない（識別子は prepare の対象外）。
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $exists !== $table ) {
					continue;
				}
				$out[ $label ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			}

			return $out;
		}
	}
}
