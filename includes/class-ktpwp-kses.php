<?php
/**
 * 業務画面（ショートコード出力）用の wp_kses ラッパー
 *
 * kantanAllTab ショートコードは、顧客・受注・サービス・協力会社などの業務画面を
 * 1つの HTML として返す。フォーム・表・SVG アイコン・data-* 属性を大量に含むため、
 * wp_kses_post() では画面が壊れる。そこで、この画面が実際に出力するタグと属性だけを
 * 許可する専用リストを持ち、最終出力をここで必ず通す。
 *
 * 通常の wp_kses() をそのまま使うと壊れるもの（実測済み）:
 *   - style="display:none" など。WordPress 標準の許可 CSS プロパティに無いものがある。
 *   - style="... rgba(...)" の宣言。括弧を含むので宣言ごと破棄される。
 *   - aria-*。data-* と違い、ワイルドカードでは許可できない。
 *   - <script> / <style>。タグごと消えて中身が本文として残る。
 *
 * <script> と <style> は、画面に必要なものだけ register_raw() で「通す」と宣言する。
 * 正規表現で <script>…</script> を保護する方式にしないのは、顧客名やメモに
 * 埋め込まれた <script> まで保護してしまうため。register_raw() を呼んだ
 * プラグイン自身のコードだけが対象になる。
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.3.41
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Kses' ) ) {

	/**
	 * 業務画面用のエスケープ（wp_kses の許可リスト）。
	 */
	class KTPWP_Kses {

		/**
		 * register_raw() で預かった HTML。
		 *
		 * @var string[]
		 */
		private static $raw = array();

		/**
		 * このリクエスト限りのランダムなトークン接頭辞。
		 *
		 * @var string
		 */
		private static $token = '';

		/**
		 * プラグインが自前で組み立てたスクリプト／スタイルを、kses の対象外として預ける。
		 *
		 * 戻り値のトークン（英数字のみ）を出力の代わりに返し、business_screen() が
		 * kses の後で元の HTML に戻す。トークンはリクエストごとにランダムなので、
		 * 外部の入力から偽造して任意の HTML を通すことはできない。
		 *
		 * 呼び出してよいのは、プラグイン自身が組み立てた文字列だけ。ユーザー入力を
		 * 含む場合は、埋め込む値を必ず wp_json_encode() / esc_js() 等で処理してから渡すこと。
		 *
		 * @param string $html 通したい HTML（<script> ブロックなど）。
		 * @return string トークン。
		 */
		public static function register_raw( $html ) {
			$html = (string) $html;
			if ( $html === '' ) {
				return '';
			}
			self::$raw[] = $html;
			return self::token_prefix() . ( count( self::$raw ) - 1 ) . 'x';
		}

		/**
		 * 業務画面の HTML を許可リストで絞る。
		 *
		 * @param string $html ショートコードが組み立てた HTML。
		 * @return string
		 */
		public static function business_screen( $html ) {
			$html = (string) $html;

			/**
			 * kses の直前の HTML を受け取る（開発用の差分確認ツールが使う）。
			 *
			 * @param string $html kses 前の HTML。
			 */
			do_action( 'ktpwp_business_screen_before_kses', $html );

			add_filter( 'safe_style_css', array( __CLASS__, 'filter_safe_style_css' ) );
			add_filter( 'safecss_filter_attr_allow_css', array( __CLASS__, 'filter_allow_css' ), 10, 2 );

			$out = wp_kses( $html, self::allowed_html() );

			remove_filter( 'safe_style_css', array( __CLASS__, 'filter_safe_style_css' ) );
			remove_filter( 'safecss_filter_attr_allow_css', array( __CLASS__, 'filter_allow_css' ), 10 );

			return self::restore_raw( $out );
		}

		/**
		 * 預かった HTML をトークンの位置へ戻す。
		 *
		 * @param string $out kses 後の HTML。
		 * @return string
		 */
		private static function restore_raw( $out ) {
			if ( empty( self::$raw ) || self::$token === '' ) {
				return $out;
			}
			foreach ( self::$raw as $i => $raw ) {
				$out = str_replace( self::token_prefix() . $i . 'x', $raw, $out );
			}
			self::$raw = array();
			return $out;
		}

		/**
		 * @return string
		 */
		private static function token_prefix() {
			if ( self::$token === '' ) {
				self::$token = 'ktpwprawblock' . wp_generate_password( 20, false, false );
			}
			return self::$token;
		}

		/**
		 * 標準の許可リストに無い CSS プロパティを足す（この画面のインライン style が使うもの）。
		 *
		 * @param string[] $props 許可されている CSS プロパティ。
		 * @return string[]
		 */
		public static function filter_safe_style_css( $props ) {
			return array_merge(
				(array) $props,
				array(
					'display',
					'align-items',
					'align-self',
					'justify-content',
					'gap',
					'flex',
					'flex-shrink',
					'flex-grow',
					'flex-wrap',
					'flex-direction',
					'box-shadow',
					'box-sizing',
					'cursor',
					'white-space',
					'word-break',
					'word-wrap',
					'overflow-wrap',
					'text-decoration',
					'text-overflow',
					'text-shadow',
					'transition',
					'transition-property',
					'transition-duration',
					'transform',
					'overflow',
					'overflow-x',
					'overflow-y',
					'vertical-align',
					'float',
					'clear',
					'visibility',
					'opacity',
					'list-style',
					'list-style-position',
					'table-layout',
					'resize',
					'pointer-events',
					'outline',
					'page-break-before',
					'page-break-after',
					'page-break-inside',
					'break-inside',
					'min-width',
					'min-height',
					'max-width',
					'max-height',
				)
			);
		}

		/**
		 * rgba() などの色・変形関数を含む宣言を許可する。
		 *
		 * WordPress は括弧を含む宣言を、var() や calc() などの一部を除いて丸ごと捨てる。
		 * ここでは、画面が実際に使う無害な関数だけを取り除いて同じ判定をやり直す。
		 * url() や expression() は関数名リストに無いので、これまでどおり拒否される。
		 *
		 * @param bool   $allow           WordPress の判定結果。
		 * @param string $css_test_string 検査中の宣言。
		 * @return bool
		 */
		public static function filter_allow_css( $allow, $css_test_string ) {
			if ( $allow ) {
				return true;
			}
			$probe = preg_replace(
				'/\b(?:rgba?|hsla?|linear-gradient|radial-gradient|translate[XYZ]?|scale[XY]?|rotate|blur|cubic-bezier)(\((?:[^()]|(?1))*\))/i',
				'',
				(string) $css_test_string
			);
			return ! preg_match( '%[\\\(&=}]|/\*%', (string) $probe );
		}

		/**
		 * 全要素に許可する属性。
		 *
		 * @return array<string, bool>
		 */
		private static function global_attributes() {
			$attrs = array(
				'class'            => true,
				'id'               => true,
				'style'            => true,
				'title'            => true,
				'role'             => true,
				'tabindex'         => true,
				'lang'             => true,
				'dir'              => true,
				'hidden'           => true,
				'translate'        => true,
				'data-*'           => true,
				'itemprop'         => true,
				'itemscope'        => true,
				'itemtype'         => true,
				// aria-* は data-* と違ってワイルドカードが効かないので、使っているものを列挙する。
				'aria-label'       => true,
				'aria-labelledby'  => true,
				'aria-describedby' => true,
				'aria-hidden'      => true,
				'aria-expanded'    => true,
				'aria-haspopup'    => true,
				'aria-live'        => true,
				'aria-current'     => true,
				'aria-controls'    => true,
				'aria-selected'    => true,
				'aria-disabled'    => true,
				'aria-modal'       => true,
				'aria-sort'        => true,
				'aria-pressed'     => true,
				'aria-required'    => true,
				'aria-invalid'     => true,
				// インラインのイベント属性。値はすべてプラグインが組み立てた定数で、
				// esc_attr() / esc_js() 済み。属性値そのものへユーザー入力は入らない。
				// 全画面を委譲リスナーに書き換えるのは別作業（未対応）。
				'onclick'          => true,
				'onchange'         => true,
				'onsubmit'         => true,
				'onmouseover'      => true,
				'onmouseout'       => true,
				'onerror'          => true,
				'onkeydown'        => true,
			);
			return $attrs;
		}

		/**
		 * 許可するタグと属性。
		 *
		 * @return array<string, array<string, bool>>
		 */
		public static function allowed_html() {
			static $cache = null;
			if ( $cache !== null ) {
				return $cache;
			}

			$g = self::global_attributes();

			$plain = array(
				'div',
				'span',
				'p',
				'strong',
				'b',
				'em',
				'i',
				'small',
				'br',
				'hr',
				'h1',
				'h2',
				'h3',
				'h4',
				'h5',
				'h6',
				'ul',
				'ol',
				'li',
				'dl',
				'dt',
				'dd',
				'nav',
				'section',
				'header',
				'footer',
				'article',
				'aside',
				'main',
				'pre',
				'code',
				'summary',
				'legend',
				'figure',
				'figcaption',
				'thead',
				'tbody',
				'tfoot',
				'tr',
				'caption',
				'colgroup',
				'defs',
				'g',
				'title',
				'desc',
			);

			$html = array();
			foreach ( $plain as $tag ) {
				$html[ $tag ] = $g;
			}

			$extra = array(
				'a'        => array( 'href', 'target', 'rel', 'download', 'name' ),
				'details'  => array( 'open' ),
				'fieldset' => array( 'disabled', 'name' ),
				'form'     => array( 'action', 'method', 'enctype', 'accept-charset', 'name', 'target', 'novalidate' ),
				'input'    => array( 'type', 'name', 'value', 'placeholder', 'checked', 'disabled', 'readonly', 'required', 'min', 'max', 'step', 'maxlength', 'minlength', 'size', 'pattern', 'accept', 'multiple', 'autocomplete', 'autocapitalize', 'autocorrect', 'spellcheck', 'list', 'form', 'inputmode' ),
				'select'   => array( 'name', 'multiple', 'size', 'disabled', 'required', 'form' ),
				'option'   => array( 'value', 'selected', 'disabled', 'label' ),
				'optgroup' => array( 'label', 'disabled' ),
				'textarea' => array( 'name', 'rows', 'cols', 'placeholder', 'maxlength', 'disabled', 'readonly', 'required', 'wrap', 'spellcheck', 'autocomplete', 'autocapitalize', 'autocorrect' ),
				'button'   => array( 'type', 'name', 'value', 'disabled', 'form', 'formaction', 'formmethod' ),
				'label'    => array( 'for', 'form' ),
				'table'    => array( 'border', 'cellpadding', 'cellspacing' ),
				'th'       => array( 'colspan', 'rowspan', 'scope', 'abbr', 'headers' ),
				'td'       => array( 'colspan', 'rowspan', 'headers' ),
				'col'      => array( 'span' ),
				'img'      => array( 'src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding', 'fetchpriority' ),
				'meta'     => array( 'content' ),
				// SVG。属性名は小文字で書く（wp_kses は属性名を小文字化して照合する）。
				'svg'      => array( 'viewbox', 'xmlns', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'focusable', 'preserveaspectratio' ),
				'path'     => array( 'd', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule', 'opacity' ),
				'circle'   => array( 'cx', 'cy', 'r', 'fill', 'stroke', 'stroke-width' ),
				'rect'     => array( 'x', 'y', 'width', 'height', 'rx', 'ry', 'fill', 'stroke', 'stroke-width' ),
				'line'     => array( 'x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width', 'stroke-linecap' ),
				'polyline' => array( 'points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ),
				'polygon'  => array( 'points', 'fill', 'stroke', 'stroke-width' ),
				'ellipse'  => array( 'cx', 'cy', 'rx', 'ry', 'fill', 'stroke', 'stroke-width' ),
				'use'      => array( 'href', 'xlink:href' ),
			);
			foreach ( $extra as $tag => $attrs ) {
				$html[ $tag ] = isset( $html[ $tag ] ) ? $html[ $tag ] : $g;
				foreach ( $attrs as $attr ) {
					$html[ $tag ][ $attr ] = true;
				}
			}

			// path / g など SVG の子要素にも、共通属性（class / style / data-*）は必要。
			foreach ( array( 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'use', 'svg' ) as $tag ) {
				$html[ $tag ] = array_merge( $g, $html[ $tag ] );
			}

			$cache = $html;
			return $cache;
		}
	}
}
