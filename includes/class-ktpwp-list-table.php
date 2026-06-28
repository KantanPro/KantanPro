<?php
/**
 * データリスト用の表（ヘッダー付き）HTML を生成する。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_List_Table' ) ) {

	/**
	 * 顧客・協力会社・サービス等の一覧表マークアップ。
	 */
	class KTPWP_List_Table {

		/**
		 * 表の開始（thead まで）を返す。
		 *
		 * @param array<int, array<string, string>> $columns      列定義（class, label, sort_key 任意）。
		 * @param array<string, mixed>              $sort_context  ソート用コンテキスト（空ならヘッダーは非リンク）。
		 * @param string                            $table_class   表への追加クラス（例: ktp-list-table--party）。
		 * @return string
		 */
		public static function open( array $columns, array $sort_context = array(), $table_class = '' ) {
			$html = '<div class="ktp-service-list-table-wrap">';
			$table_classes = 'ktp-service-list-table widefat striped';
			if ( $table_class !== '' ) {
				$table_classes .= ' ' . sanitize_html_class( (string) $table_class );
			}
			$html .= '<table class="' . esc_attr( $table_classes ) . '">';
			$html .= '<thead><tr>';

			foreach ( $columns as $column ) {
				$html .= self::render_header_cell( $column, $sort_context );
			}

			$html .= '</tr></thead><tbody>';

			return $html;
		}

		/**
		 * 表の終了タグを返す。
		 *
		 * @return string
		 */
		public static function close() {
			return '</tbody></table></div>';
		}

		/**
		 * ソートリンク用に GET パラメータを引き継ぐ。
		 *
		 * @param array<int, string> $exclude_keys 除外するキー。
		 * @return array<string, string>
		 */
		public static function preserved_query_args( array $exclude_keys = array() ) {
			$exclude = array_merge(
				array(
					'sort_by',
					'sort_order',
					'page_start',
					'page_stage',
					'message',
				),
				$exclude_keys
			);

			$args = array();

			foreach ( $_GET as $key => $value ) {
				if ( in_array( $key, $exclude, true ) || is_array( $value ) ) {
					continue;
				}

				$args[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}

			return $args;
		}

		/**
		 * デフォルトのソート順。
		 *
		 * @return string
		 */
		public static function default_sort_order() {
			return 'DESC';
		}

		/**
		 * ソート順を正規化する（明示的な ASC 以外は降順）。
		 *
		 * @param string $sort_order 入力値。
		 * @return string ASC または DESC。
		 */
		public static function sanitize_sort_order( $sort_order ) {
			return strtoupper( trim( (string) $sort_order ) ) === 'ASC' ? 'ASC' : self::default_sort_order();
		}

		/**
		 * 列ヘッダー用のソート URL を組み立てる。
		 *
		 * @param string               $base_url      ベース URL。
		 * @param string               $sort_key      ソート対象カラム。
		 * @param string               $current_by    現在のソート列。
		 * @param string               $current_order 現在のソート順。
		 * @param array<string, string> $preserve_args 維持するクエリ。
		 * @return string
		 */
		public static function sort_url( $base_url, $sort_key, $current_by, $current_order, array $preserve_args = array() ) {
			$order = ( $sort_key === $current_by && 'DESC' === $current_order ) ? 'ASC' : 'DESC';

			$args = array_merge(
				$preserve_args,
				array(
					'sort_by'    => $sort_key,
					'sort_order' => $order,
					'page_start' => 0,
					'page_stage' => '',
				)
			);

			$base = remove_query_arg( array( 'message' ), $base_url );

			return add_query_arg( $args, $base );
		}

		/**
		 * クリックで遷移する行の属性文字列を返す。
		 *
		 * @param string $url         遷移先 URL。
		 * @param string $cookie_name クリック時に保存する Cookie 名（空なら保存しない）。
		 * @param int    $cookie_id   Cookie に保存する ID。
		 * @param string $row_class   追加の CSS クラス。
		 * @return string
		 */
		public static function row_nav_attrs( $url, $cookie_name = '', $cookie_id = 0, $row_class = '' ) {
			$classes = trim( 'ktp-service-list-data-row ' . $row_class );
			$attrs   = ' class="' . esc_attr( $classes ) . '"';
			$attrs  .= ' data-href="' . esc_url( $url ) . '"';

			if ( $cookie_name !== '' ) {
				$attrs .= ' data-cookie-name="' . esc_attr( $cookie_name ) . '"';
				$attrs .= ' data-cookie-id="' . esc_attr( (string) absint( $cookie_id ) ) . '"';
			}

			$attrs .= ' onclick="';
			if ( $cookie_name !== '' ) {
				$attrs .= "if(this.dataset.cookieName){document.cookie=this.dataset.cookieName+'='+this.dataset.cookieId;}";
			}
			$attrs .= "var _ktpNavUrl=this.dataset.href;if(window.matchMedia&&window.matchMedia('(max-width:767px)').matches){try{sessionStorage.setItem('ktp_scroll_to_detail','1');}catch(e){}_ktpNavUrl=_ktpNavUrl+(_ktpNavUrl.indexOf('#')>-1?'':'#ktp-detail');}window.location.href=_ktpNavUrl;\"";

			return $attrs;
		}

		/**
		 * ヘッダーセルを生成する。
		 *
		 * @param array<string, string> $column       列定義。
		 * @param array<string, mixed>  $sort_context ソートコンテキスト。
		 * @return string
		 */
		private static function render_header_cell( array $column, array $sort_context ) {
			$class   = isset( $column['class'] ) ? $column['class'] : '';
			$label   = isset( $column['label'] ) ? $column['label'] : '';
			$sort_key = isset( $column['sort_key'] ) ? $column['sort_key'] : '';

			$th_classes = array( $class );
			$can_sort   = $sort_key !== '' && ! empty( $sort_context['base_url'] );

			if ( $can_sort && isset( $sort_context['sort_by'] ) && $sort_context['sort_by'] === $sort_key ) {
				$th_classes[] = 'is-sorted';
				$th_classes[] = 'is-sorted-' . strtolower( (string) $sort_context['sort_order'] );
			}

			if ( $can_sort ) {
				$th_classes[] = 'ktp-sortable-col';
			}

			$th_class_attr = trim( implode( ' ', array_filter( $th_classes ) ) );

			if ( ! $can_sort ) {
				return '<th scope="col" class="' . esc_attr( $th_class_attr ) . '">' . esc_html( $label ) . '</th>';
			}

			$url = self::sort_url(
				(string) $sort_context['base_url'],
				$sort_key,
				isset( $sort_context['sort_by'] ) ? (string) $sort_context['sort_by'] : '',
				isset( $sort_context['sort_order'] ) ? (string) $sort_context['sort_order'] : self::default_sort_order(),
				isset( $sort_context['preserve_args'] ) && is_array( $sort_context['preserve_args'] ) ? $sort_context['preserve_args'] : array()
			);

			$indicator = '';
			if ( isset( $sort_context['sort_by'] ) && $sort_context['sort_by'] === $sort_key ) {
				$indicator = 'ASC' === $sort_context['sort_order']
					? ' <span class="ktp-sort-indicator" aria-hidden="true">▲</span>'
					: ' <span class="ktp-sort-indicator" aria-hidden="true">▼</span>';
			}

			$aria_attr = '';
			if ( isset( $sort_context['sort_by'] ) && $sort_context['sort_by'] === $sort_key ) {
				$aria_sort = 'ASC' === $sort_context['sort_order'] ? 'ascending' : 'descending';
				$aria_attr   = ' aria-sort="' . esc_attr( $aria_sort ) . '"';
			}

			return '<th scope="col" class="' . esc_attr( $th_class_attr ) . '"' . $aria_attr . '>'
				. '<a href="' . esc_url( $url ) . '" class="ktp-sortable-header">'
				. esc_html( $label )
				. $indicator
				. '</a></th>';
		}
	}
}
