<?php
/**
 * Client Tab Class for KTPWP Plugin
 *
 * Handles client management functionality including table creation,
 * data display, and client information management.
 *
 * @package KTPWP
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Client_Class' ) ) {
	class KTPWP_Client_Class {

		public function __construct() {
		}

		/**
		 * ソートプルダウンを生成するメソッド
		 *
		 * @param string $name テーブル名サフィックス
		 * @param string $view_mode 表示モード
		 * @param string $base_page_url 基本URL
		 * @param string $sort_by ソートカラム
		 * @param string $sort_order ソート順
		 * @param string $order_sort_by 注文履歴ソートカラム
		 * @param string $order_sort_order 注文履歴ソート順
		 * @return string ソートプルダウンHTML
		 */
		private function generate_sort_dropdown( $name, $view_mode, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order ) {
			if ( $view_mode !== 'order_history' ) {
				return '';
			}

			// 注文履歴のソートプルダウン
			$cookie_name = 'ktp_' . $name . '_id';
			$client_id = null;

			if ( isset( $_GET['data_id'] ) ) {
				$client_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
			} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$client_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
			}

			// 現在のURLからソート用プルダウンのアクションURLを生成
			$sort_url = add_query_arg(
				array(
					'tab_name'  => $name,
					'view_mode' => 'order_history',
					'data_id'   => $client_id ?? '',
				),
				$base_page_url
			);

			// ソート用プルダウンのHTMLを構築
			$sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
				'<form method="get" action="' . esc_url( $sort_url ) . '" style="display:flex;align-items:center;">';

			// 現在のGETパラメータを維持するための隠しフィールド
			foreach ( $_GET as $key => $value ) {
				if ( $key !== 'order_sort_by' && $key !== 'order_sort_order' ) {
					$sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}

			$sort_dropdown .=
				'<select id="' . esc_attr( 'ktp-' . $name . '-order-sort-select' ) . '" name="order_sort_by" style="margin-right:5px;">' .
				'<option value="id" ' . selected( $order_sort_by, 'id', false ) . '>' . esc_html__( '注文ID', 'ktpwp' ) . '</option>' .
				'<option value="time" ' . selected( $order_sort_by, 'time', false ) . '>' . esc_html__( '日付', 'ktpwp' ) . '</option>' .
				'<option value="progress" ' . selected( $order_sort_by, 'progress', false ) . '>' . esc_html__( '進捗', 'ktpwp' ) . '</option>' .
				'<option value="project_name" ' . selected( $order_sort_by, 'project_name', false ) . '>' . esc_html__( '案件名', 'ktpwp' ) . '</option>' .
				'</select>' .
				'<select id="' . esc_attr( 'ktp-' . $name . '-order-sort-order' ) . '" name="order_sort_order">' .
				'<option value="DESC" ' . selected( $order_sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
				'<option value="ASC" ' . selected( $order_sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
				'</select>' .
				'<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
				( class_exists( 'KTPWP_SVG_Icons' ) ? KTPWP_SVG_Icons::get_icon( 'check', array( 'style' => 'font-size:18px;line-height:18px;vertical-align:middle;' ) ) : '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' ) .
				'</button>' .
				'</form></div>';

			return $sort_dropdown;
		}

		/**
		 * 顧客フォームの郵便番号→住所（zipcloud または 日本郵便API）。
		 * 同一フォームは input.form で解決し、各 postal_code に blur と input（7桁で短い遅延）を直接付与。
		 *
		 * @return string
		 */
		private function render_client_postal_lookup_script() {
			$use_jp     = class_exists( 'KTPWP_JapanPost_Address_API' ) && KTPWP_JapanPost_Address_API::is_enabled();
			$ajax_url   = admin_url( 'admin-ajax.php' );
			$nonce      = wp_create_nonce( 'ktpwp_ajax_nonce' );
			$use_jp_js  = $use_jp ? 'true' : 'false';
			$ajax_json  = wp_json_encode( $ajax_url );
			$nonce_json = wp_json_encode( $nonce );
			$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
			$msg_zipcloud_empty = wp_json_encode(
				__( 'この郵便番号では zipcloud から住所を取得できませんでした（大口事業所など、データに無いコードがあります）。一般設定で日本郵便の住所APIを有効にするか、手動で入力してください。', 'ktpwp' ),
				$json_flags
			);
			$msg_jp_fail = wp_json_encode(
				__( '日本郵便の住所APIから該当住所を取得できませんでした。手動で入力してください。', 'ktpwp' ),
				$json_flags
			);

			return '<script>
(function() {
	// 入力ごとに data-ktp-postal-bound で二重バインドのみ防止する（ページ内にスクリプトが複数あっても全欄に付与できる）
	var useJapanPost = ' . $use_jp_js . ';
	var ajaxUrl = ' . $ajax_json . ';
	var ajaxNonce = ' . $nonce_json . ';
	var msgZipcloudEmpty = ' . $msg_zipcloud_empty . ';
	var msgJapanPostFail = ' . $msg_jp_fail . ';
	var postalGuideToastMs = 16000;
	function showPostalGuideToast(message) {
		var n = document.createElement("div");
		n.setAttribute("role", "status");
		n.style.cssText = "position:fixed;top:20px;right:20px;background:#856404;color:#fff;padding:12px 18px;border-radius:5px;z-index:10001;max-width:min(440px,92vw);font-size:13px;line-height:1.45;box-shadow:0 2px 10px rgba(0,0,0,.2);";
		n.textContent = message;
		document.body.appendChild(n);
		setTimeout(function() { try { if (n.parentNode) { n.parentNode.removeChild(n); } } catch (e) {} }, postalGuideToastMs);
	}
	function warnPostal(postalEl, zip, attrName, message) {
		if (!postalEl || !zip) { return; }
		if (postalEl.getAttribute(attrName) === zip) { return; }
		postalEl.setAttribute(attrName, zip);
		showPostalGuideToast(message);
	}
	function addressTargets(postalEl) {
		var form = postalEl.form;
		if (form) {
			return {
				pref: form.querySelector(\'input[name="prefecture"]\'),
				cityIn: form.querySelector(\'input[name="city"]\'),
				street: form.querySelector(\'input[name="address"]\')
			};
		}
		var box = postalEl.closest ? postalEl.closest(".data_detail_box") : null;
		if (box) {
			return {
				pref: box.querySelector(\'input[name="prefecture"]\'),
				cityIn: box.querySelector(\'input[name="city"]\'),
				street: box.querySelector(\'input[name="address"]\')
			};
		}
		return { pref: null, cityIn: null, street: null };
	}
	function applyZipcloud(ctx, zip, postalEl) {
		var pref = ctx.pref;
		var cityIn = ctx.cityIn;
		var xhr = new XMLHttpRequest();
		xhr.open("GET", "https://zipcloud.ibsnet.co.jp/api/search?zipcode=" + encodeURIComponent(zip));
		xhr.onload = function() {
			if (xhr.status < 200 || xhr.status >= 300) { return; }
			try {
				var response = JSON.parse(xhr.responseText);
				if (Number(response.status) !== 200 || !response.results || !response.results.length) {
					warnPostal(postalEl, zip, "data-ktp-zipcloud-warned", msgZipcloudEmpty);
					return;
				}
				var d = response.results[0];
				var a1 = d.address1 != null ? String(d.address1) : "";
				var a2 = d.address2 != null ? String(d.address2) : "";
				var a3 = d.address3 != null ? String(d.address3) : "";
				if (pref) { pref.value = a1; }
				if (cityIn) { cityIn.value = a2 + a3; }
				if (postalEl) {
					postalEl.removeAttribute("data-ktp-zipcloud-warned");
					postalEl.removeAttribute("data-ktp-jppost-warned");
				}
			} catch (err) { console.error("KTP zipcloud:", err); }
		};
		xhr.onerror = function() { console.warn("KTP zipcloud: network error"); };
		xhr.send();
	}
	function applyJapanPost(ctx, zip, postalEl) {
		var pref = ctx.pref;
		var cityIn = ctx.cityIn;
		var street = ctx.street;
		var fd = new FormData();
		fd.append("action", "ktp_lookup_postal_address");
		fd.append("nonce", ajaxNonce);
		fd.append("zipcode", zip);
		fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
			.then(function(r) {
				return r.text().then(function(txt) {
					try { return { ok: r.ok, body: JSON.parse(txt) }; }
					catch (e) { return { ok: false, body: null, raw: txt }; }
				});
			})
			.then(function(payload) {
				var res = payload.body;
				if (!res) {
					warnPostal(postalEl, zip, "data-ktp-jppost-warned", msgJapanPostFail);
					console.warn("KTP japanpost: invalid JSON", payload.raw);
					return;
				}
				if (res.success && res.data) {
					if (pref) { pref.value = res.data.prefecture || ""; }
					if (cityIn) { cityIn.value = res.data.city || ""; }
					if (street) { street.value = (res.data.address != null ? String(res.data.address) : "") || ""; }
					if (postalEl) {
						postalEl.removeAttribute("data-ktp-zipcloud-warned");
						postalEl.removeAttribute("data-ktp-jppost-warned");
					}
				} else {
					var detail = (res.data && res.data.message) ? String(res.data.message) : msgJapanPostFail;
					warnPostal(postalEl, zip, "data-ktp-jppost-warned", detail);
				}
			})
			.catch(function(err) { console.error("KTP japanpost:", err); });
	}
	function runLookup(postalEl) {
		if (!postalEl || postalEl.name !== "postal_code") { return; }
		var zip = String(postalEl.value || "").replace(/[^0-9]/g, "");
		if (zip.length !== 7) { return; }
		var ctx = addressTargets(postalEl);
		if (!ctx.pref && !ctx.cityIn) { return; }
		if (useJapanPost) { applyJapanPost(ctx, zip, postalEl); } else { applyZipcloud(ctx, zip, postalEl); }
	}
	function bindPostalInput(inp) {
		if (!inp || inp.getAttribute("data-ktp-postal-bound") === "1") { return; }
		inp.setAttribute("data-ktp-postal-bound", "1");
		var debounceTimer = null;
		function onLeave() { runLookup(inp); }
		inp.addEventListener("blur", onLeave);
		inp.addEventListener("input", function() {
			clearTimeout(debounceTimer);
			inp.removeAttribute("data-ktp-zipcloud-warned");
			inp.removeAttribute("data-ktp-jppost-warned");
			var z = String(inp.value || "").replace(/[^0-9]/g, "");
			if (z.length === 7) {
				debounceTimer = setTimeout(function() { runLookup(inp); }, 350);
			}
		});
	}
	function bindAllPostalInputs() {
		var list = document.querySelectorAll(\'input[name="postal_code"]\');
		for (var i = 0; i < list.length; i++) { bindPostalInput(list[i]); }
	}
	bindAllPostalInputs();
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", bindAllPostalInputs);
	}
	setTimeout(bindAllPostalInputs, 0);
	window.addEventListener("load", bindAllPostalInputs);
})();
</script>';
		}

		// -----------------------------
		// テーブル作成
		// -----------------------------


		/**
		 * Get cookie value or default
		 *
		 * @deprecated 1.1.0 Use KTPWP_Client_UI::get_instance()->set_cookie() instead
		 * @param string $name Cookie name suffix
		 * @return int Sanitized ID value
		 */
		public function set_cookie( $name ) {
			if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-ui.php';
			}
			return KTPWP_Client_UI::get_instance()->set_cookie( $name );
		}

		/**
		 * Create client table
		 *
		 * @param string $tab_name Table name suffix (sanitized)
		 * @return bool Success status
		 */
		public function create_table( $tab_name ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->create_table( $tab_name );
		}

		// -----------------------------
		// テーブルの操作（更新・追加・削除・検索）
		// -----------------------------

		/**
		 * Update table and handle POST operations
		 *
		 * @deprecated 1.1.0 Use KTPWP_Client_DB::get_instance()->update_table() instead
		 * @param string $tab_name Table name suffix
		 * @return void
		 */
		function Update_Table( $tab_name ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->update_table( $tab_name );
		}

		// 次に表示するIDを取得するヘルパーメソッド
		/**
		 * @deprecated 1.1.0 Use KTPWP_Client_DB::get_instance()->get_next_display_id() instead
		 */
		private function get_next_display_id( $table_name, $deleted_id ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->get_next_display_id( $table_name, $deleted_id );
		}

		/**
		 * 顧客タブで表示中の顧客 ID を解決（GET → DB に存在する Cookie → 最新 ID）。
		 *
		 * @param string $name       タブ名（client）。
		 * @param string $table_name 顧客テーブル名。
		 * @return int
		 */
		private function resolve_active_client_id( $name, $table_name ) {
			global $wpdb;

			$cookie_name = 'ktp_' . $name . '_id';
			$candidate   = 0;

			if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
				$candidate = (int) filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
			} elseif ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
				$candidate = (int) filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
			}

			if ( $candidate > 0 ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE id = %d",
						$candidate
					)
				);
				if ( $exists ) {
					return $candidate;
				}
			}

			$max_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );

			return $max_id_row ? (int) $max_id_row->id : 0;
		}


		// -----------------------------
		// テーブルの表示
		// -----------------------------

		/**
		 * View client table
		 *
		 * @param string $name Table name suffix
		 * @return void
		 */
		function View_Table( $name ) {
			global $wpdb;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// View_Table method started
				// error_log('KTPWP Client: View_Table method started');
			}

			// $search_results_listの使用前に初期化
			$search_results_list = '';

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;

			// 表示モードの取得（デフォルトは顧客一覧）
			$view_mode = isset( $_GET['view_mode'] ) ? sanitize_text_field( $_GET['view_mode'] ) : 'customer_list';
			$print_all = isset( $_GET['print_all'] ) && (string) $_GET['print_all'] !== '' && (string) $_GET['print_all'] !== '0';
			if ( $print_all ) {
				$view_mode = 'customer_list';
			}

			// ソート順の取得（デフォルトはIDの降順）
			$sort_by = 'id';
			$sort_order = class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::default_sort_order() : 'DESC';

			// 注文履歴用のソート順（デフォルトは日付の降順）
			$order_sort_by = 'time';
			$order_sort_order = class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::default_sort_order() : 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'company_name', 'name', 'phone', 'frequency', 'time', 'client_status' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['sort_order'] ) ) {
				$sort_order = class_exists( 'KTPWP_List_Table' )
					? KTPWP_List_Table::sanitize_sort_order( sanitize_text_field( $_GET['sort_order'] ) )
					: 'DESC';
			}

			// 注文履歴のソート順を取得
			if ( isset( $_GET['order_sort_by'] ) ) {
				$order_sort_by = sanitize_text_field( $_GET['order_sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_order_columns = array( 'id', 'time', 'progress', 'project_name' );
				if ( ! in_array( $order_sort_by, $allowed_order_columns ) ) {
					$order_sort_by = 'time'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['order_sort_order'] ) ) {
				$order_sort_order = class_exists( 'KTPWP_List_Table' )
					? KTPWP_List_Table::sanitize_sort_order( sanitize_text_field( $_GET['order_sort_order'] ) )
					: 'DESC';
			}

			// リスト表示前に頻度を加算（一覧に反映させる）
			if ( $view_mode !== 'order_history' && isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && ( ! function_exists( 'ktpwp_should_skip_frequency_on_view' ) || ! ktpwp_should_skip_frequency_on_view() ) ) {
				$view_record_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				if ( $view_record_id > 0 && function_exists( 'ktpwp_increment_record_frequency_on_view' ) ) {
					ktpwp_increment_record_frequency_on_view( 'client', (int) $view_record_id );
				}
			}

			// 現在のページのURLを生成（動的パーマリンク取得）
			$base_page_url = KTPWP_Main::get_current_page_base_url();

			// 検索結果が複数ある場合：リダイレクト後のGETでダイアログにリストを表示（協力会社タブと同じ方式）
			// HTMLは hidden div に置きスクリプトで読み取る方式で、JSON埋め込みによる構文エラーを防ぐ
			if ( isset( $_GET['multiple_results'] ) && $_GET['multiple_results'] === '1' && ! empty( $_GET['search_query'] ) ) {
				$search_query_multiple = sanitize_text_field( wp_unslash( $_GET['search_query'] ) );
				$like_pattern = '%' . $wpdb->esc_like( $search_query_multiple ) . '%';
				$multi_results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$table_name} WHERE (COALESCE(search_field,'') LIKE %s OR company_name LIKE %s OR name LIKE %s) ORDER BY id DESC",
						$like_pattern,
						$like_pattern,
						$like_pattern
					)
				);
				if ( ! empty( $multi_results ) ) {
					$multi_results_id = 'ktp-client-multi-results-' . wp_rand( 10000, 99999 );
					$search_results_html = "<div class='data_contents'><div class='search_list_box'><div class='data_list_title'>■ " . esc_html__( '検索結果が複数あります！', 'ktpwp' ) . "</div><ul>";
					foreach ( $multi_results as $row ) {
						$id = esc_html( (string) $row->id );
						$company_name = esc_html( isset( $row->company_name ) ? $row->company_name : '' );
						$disp_name = esc_html( isset( $row->name ) ? $row->name : '' );
						$category = esc_html( isset( $row->category ) ? $row->category : '' );
						$link_url = esc_url(
							add_query_arg(
								array(
									'tab_name' => $name,
									'data_id' => (int) $row->id,
									'query_post' => 'update',
								),
								$base_page_url
							)
						);
						$search_results_html .= "<li style='text-align:left;'><a href='" . $link_url . "' style='text-align:left;'>ID: " . $id . " " . esc_html__( '会社名：', 'ktpwp' ) . $company_name . " " . esc_html__( '名前：', 'ktpwp' ) . $disp_name . ( $category !== '' ? " " . esc_html__( 'カテゴリー：', 'ktpwp' ) . $category : '' ) . "</a></li>";
					}
					$search_results_html .= '</ul></div></div>';
					// 閉じる＝検索モードへ。現在のリクエストURLからダイアログ用パラメータを除き検索モード用のみ付与
					$close_redirect_base = home_url( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' ) );
					$close_redirect_base = remove_query_arg( array( 'multiple_results', 'search_query', 'message' ), $close_redirect_base );
					$close_redirect_url = esc_url(
						add_query_arg(
							array(
								'tab_name' => $name,
								'query_post' => 'srcmode',
							),
							$close_redirect_base
						)
					);
					$search_results_list = '<div id="' . esc_attr( $multi_results_id ) . '" style="display:none;">' . $search_results_html . '</div>' . "\n" . '<script>
(function() {
	var run = function() {
		var el = document.getElementById("' . esc_js( $multi_results_id ) . '");
		if (!el) return;
		var searchResultsHtml = el.innerHTML;
		var popup = document.createElement("div");
		popup.innerHTML = searchResultsHtml;
		popup.style.cssText = "position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;z-index:10001;width:80%;max-width:600px;border:1px solid #ccc;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,0.1)";
		document.body.appendChild(popup);
		var closeBtn = document.createElement("button");
		closeBtn.textContent = "' . esc_js( __( '閉じる', 'ktpwp' ) ) . '";
		closeBtn.style.cssText = "font-size:0.8em;color:#000;display:block;margin:10px auto 0;padding:10px;background:#cdcccc;border-radius:5px;border-color:#999;cursor:pointer";
		closeBtn.onclick = function() { document.body.removeChild(popup); location.href = "' . esc_js( $close_redirect_url ) . '"; };
		popup.appendChild(closeBtn);
	};
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", run);
	} else {
		run();
	}
})();
</script>';
				}
			}

			// 表示タイトルの設定（国際化対応）
			$list_title = ( $view_mode === 'order_history' )
            ? esc_html__( '■ 注文履歴', 'ktpwp' )
            : esc_html__( '■ 顧客リスト', 'ktpwp' );

			// 注文履歴表示時のみソートプルダウンを生成
			$sort_dropdown = '';
			if ( $view_mode === 'order_history' ) {
				$sort_dropdown = $this->generate_sort_dropdown( $name, $view_mode, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );
			}

			// 段階的にUIクラスに処理を委譲
			if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-ui.php';
			}
			$client_ui = KTPWP_Client_UI::get_instance();
			$results_h = $client_ui->view_table( $name );

			if ( ! class_exists( 'KTPWP_List_Table' ) ) {
				require_once __DIR__ . '/class-ktpwp-list-table.php';
			}

			$list_header = '';
			$list_footer = '';

			// スタート位置を決める
			$page_stage = $_GET['page_stage'] ?? '';
			$page_start = $_GET['page_start'] ?? 0;
			$flg = $_GET['flg'] ?? '';
			if ( $page_stage == '' ) {
				$page_start = 0;
			}
			
			// 負の値を防ぐ安全対策
			$page_start = max( 0, intval( $page_start ) );

			// 表示件数を取得
			$query_limit = 20; // デフォルト値
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$query_limit = KTPWP_Settings::get_work_list_range();
			}

			$query_range = $page_start . ',' . $query_limit;

			$query_order_by = 'frequency';

			// 注文履歴モードの場合
			if ( $view_mode === 'order_history' ) {
				$cookie_name = 'ktp_' . $name . '_id';
				$client_id   = $this->resolve_active_client_id( $name, $table_name );
				if ( $client_id <= 0 ) {
					$client_id = 1;
				}

				// 受注書テーブル
				$order_table = $wpdb->prefix . 'ktp_order';

				// 全データ数を取得（この顧客IDに関連する受注書）
				$related_client_ids = array( $client_id );

				// IDのリストを文字列に変換（安全対策）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用 (元のコメントだが、現状は単一ID)
				// $total_query = "SELECT COUNT(*) FROM {$order_table} WHERE client_id IN ({$client_ids_str})";
				// $total_rows = $wpdb->get_var($total_query);
				// $client_id 変数を直接使用する方が明確
				$total_query_prepared = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$order_table} WHERE client_id = %d",
					intval( $client_id )
				);
				$total_rows = $wpdb->get_var( $total_query_prepared );
				$total_pages = ceil( $total_rows / $query_limit );

				// 現在のページ番号を計算
				$current_page = floor( $page_start / $query_limit ) + 1;

				// この顧客の受注書を取得
				$related_client_ids = array( $client_id );

				// IDのリストを文字列に変換（safety check）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用（ソートオプションを適用）
				$order_sort_column = esc_sql( $order_sort_by ); // SQLインジェクション対策
				$order_sort_direction = $order_sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策

				// 修正: $order_sort_column 内の % を %% にエスケープして prepare に渡す
				$order_sort_column_prepared = str_replace( '%', '%%', $order_sort_column );

				// 修正: $client_ids_str をプレースホルダーに置き換え
				$client_ids_array = explode( ',', $client_ids_str );
				$placeholders = implode( ',', array_fill( 0, count( $client_ids_array ), '%d' ) );
				$query = $wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE client_id IN ($placeholders) ORDER BY {$order_sort_column_prepared} {$order_sort_direction} LIMIT %d, %d", // $order_sort_column_prepared を使用
					array_merge( $client_ids_array, array( intval( $page_start ), intval( $query_limit ) ) )
				);

				$order_rows = $wpdb->get_results( $query );

				// 顧客情報を取得して表示
				$client_query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $client_id );
				$client_data = $wpdb->get_row( $client_query );

				if ( $client_data ) {
					$client_name = esc_html( $client_data->company_name );
					$client_user_name = esc_html( $client_data->name );

					// 注文履歴のリストヘッダーを更新
					$results_h = '<div class="ktp_data_contents">'
						. '<div class="ktp_data_list_box">'
						. '<div class="data_list_title">■ ' . $client_name . esc_html__( 'の注文履歴', 'ktpwp' ) . '</div>';

					if ( isset( $sort_dropdown ) ) {
						$results_h = str_replace( '</div>', "$sort_dropdown</div>", $results_h );
					}

					$results = array(); // 結果を格納する配列を初期化

					if ( $order_rows ) {
						// 進捗ラベル
						$progress_labels = array(
							1 => esc_html__( '受付中', 'ktpwp' ),
							2 => esc_html__( '見積中', 'ktpwp' ),
							3 => esc_html__( '受注', 'ktpwp' ),
							4 => esc_html__( '完了', 'ktpwp' ),
							5 => esc_html__( '請求済', 'ktpwp' ),
							6 => esc_html__( '入金済', 'ktpwp' ),
							7 => esc_html__( 'ボツ', 'ktpwp' ),
						);

						foreach ( $order_rows as $order ) {
							$order_id = esc_html( $order->id );
							$project_name = isset( $order->project_name ) ? esc_html( $order->project_name ) : '';
							$progress = intval( $order->progress );
							$progress_label = isset( $progress_labels[ $progress ] ) ? $progress_labels[ $progress ] : esc_html__( '不明', 'ktpwp' );

							// 日時フォーマット変換
							$raw_time = $order->time;
							$formatted_time = '';
							if ( ! empty( $raw_time ) ) {
								if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
									$timestamp = (int) $raw_time;
									$dt = new DateTime( '@' . $timestamp );
									$dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
								} else {
									$dt = date_create( $raw_time, new DateTimeZone( 'Asia/Tokyo' ) );
								}
								if ( $dt ) {
									$week = array( __( '日', 'ktpwp' ), __( '月', 'ktpwp' ), __( '火', 'ktpwp' ), __( '水', 'ktpwp' ), __( '木', 'ktpwp' ), __( '金', 'ktpwp' ), __( '土', 'ktpwp' ) );
									$w = $dt->format( 'w' );
									$formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
								}
							}

							// 完了日のフォーマット
							$completion_date = '';
							if ( ! empty( $order->completion_date ) ) {
								$completion_dt = date_create( $order->completion_date, new DateTimeZone( 'Asia/Tokyo' ) );
								if ( $completion_dt ) {
									$week = array( __( '日', 'ktpwp' ), __( '月', 'ktpwp' ), __( '火', 'ktpwp' ), __( '水', 'ktpwp' ), __( '木', 'ktpwp' ), __( '金', 'ktpwp' ), __( '土', 'ktpwp' ) );
									$w = $completion_dt->format( 'w' );
									$completion_date = $completion_dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）';
								}
							}

							// 受注書の詳細へのリンク（シンプルなURL生成）
							$detail_url = add_query_arg(
                                array(
									'tab_name' => 'order',
									'order_id' => $order_id,
                                ),
                                $base_page_url
                            );

							// 1行化：ID・案件名・完了日・進捗を同じ行に表示（完了日は「完了日：」ラベル付き）
							$order_info = 'ID: ' . $order_id . ' - ' . $project_name;
							if ( ! empty( $completion_date ) ) {
								$order_info .= ' <span style="color: #666; margin-left: 12px;">' . esc_html__( '完了日：', 'ktpwp' ) . $completion_date . '</span>';
							}
							$order_info .= ' <span style="float:right;" class="status-' . $progress . '">' . $progress_label . '</span>';

							$results[] = <<<END
                       <a href="{$detail_url}">
                           <div class="ktp_data_list_item">{$order_info}</div>
                       </a>
                       END;
						}
					} else {
						$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
							. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
							. esc_html__( 'まだ注文がありません。', 'ktpwp' )
							. '</div>';
					}
				} else {
					$results[] = '<div class="ktp_data_list_item">' . esc_html__( '顧客データが見つかりません。', 'ktpwp' ) . '</div>';
				}
			} else {
				// 通常の顧客一覧表示（既存のコード）
				$list_search_where = '';
				$list_search_args  = array();
				if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
					$list_search_keyword = KTPWP_Tab_Search_UI::get_instance()->get_keyword();
					if ( $list_search_keyword !== '' ) {
						list( $list_search_where, $list_search_args ) = KTPWP_Tab_Search_UI::get_instance()->master_list_search_clause( $table_name, $list_search_keyword, 'client' );
					}
				}

				// 全データ数を取得
				$total_query_prepared = "SELECT COUNT(*) FROM {$table_name} WHERE 1=1{$list_search_where}";
				if ( $list_search_args !== array() ) {
					$total_rows = $wpdb->get_var( $wpdb->prepare( $total_query_prepared, $list_search_args ) );
				} else {
					$total_rows = $wpdb->get_var( $total_query_prepared );
				}
				$total_pages = ceil( $total_rows / $query_limit );

				// 現在のページ番号を計算
				$current_page = floor( $page_start / $query_limit ) + 1;

				// データを取得（選択されたソート順で）
				$sort_column = esc_sql( $sort_by ); // SQLインジェクション対策
				$sort_column_prepared = str_replace( '%', '%%', $sort_column ); // % を %% にエスケープ
				$sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策
				if ( $print_all ) {
					$page_start   = 0;
					$current_page = 1;
					$total_pages  = 1;
					$query        = $wpdb->prepare(
						"SELECT * FROM {$table_name} WHERE 1=1{$list_search_where} ORDER BY {$sort_column_prepared} {$sort_direction}",
						$list_search_args
					);
				} else {
					$query = $wpdb->prepare(
						"SELECT * FROM {$table_name} WHERE 1=1{$list_search_where} ORDER BY {$sort_column_prepared} {$sort_direction} LIMIT %d, %d",
						array_merge( $list_search_args, array( intval( $page_start ), intval( $query_limit ) ) )
					);
				}
				$post_row = $wpdb->get_results( $query );

				$results = array(); // 結果を格納する配列を初期化

				if ( $post_row ) {
					$list_header = KTPWP_List_Table::open(
						array(
							array(
								'class'    => 'col-id',
								'label'    => __( 'ID', 'ktpwp' ),
								'sort_key' => 'id',
							),
							array(
								'class'    => 'col-company',
								'label'    => __( '会社名', 'ktpwp' ),
								'sort_key' => 'company_name',
							),
							array(
								'class'    => 'col-contact',
								'label'    => __( '担当者', 'ktpwp' ),
								'sort_key' => 'name',
							),
							array(
								'class'    => 'col-phone',
								'label'    => __( '電話番号', 'ktpwp' ),
								'sort_key' => 'phone',
							),
							array(
								'class'    => 'col-frequency no-print',
								'label'    => __( '頻度', 'ktpwp' ),
								'sort_key' => 'frequency',
							),
						),
						array(
							'base_url'      => $base_page_url,
							'sort_by'       => $sort_by,
							'sort_order'    => $sort_order,
							'preserve_args' => KTPWP_List_Table::preserved_query_args(
								array( 'query_post', 'send_post' )
							),
						),
						'ktp-list-table--party'
					);

					foreach ( $post_row as $row ) {
						$id = esc_html( $row->id );
						$time = esc_html( $row->time );
						$company_name = esc_html( $row->company_name );
						$user_name = esc_html( $row->name );
						$email = esc_html( $row->email );
						$url = esc_html( $row->url );
						$representative_name = esc_html( $row->representative_name );
						$phone = esc_html( $row->phone );
						$postal_code = esc_html( $row->postal_code );
						$prefecture = esc_html( $row->prefecture );
						$city = esc_html( $row->city );
						$address = esc_html( $row->address );
						$building = esc_html( $row->building );
						$closing_day = esc_html( $row->closing_day );
						$payment_month = esc_html( $row->payment_month );
						$payment_day = esc_html( $row->payment_day );
						$payment_method = esc_html( $row->payment_method );
						$tax_category = esc_html( $row->tax_category );
						$memo = esc_html( $row->memo );
						$client_status = esc_html( $row->client_status );
						$frequency = esc_html( $row->frequency );
						$category = esc_html( $row->category ?? '' ); // カテゴリーフィールドを追加

						// リスト項目
						$cookie_name = 'ktp_' . $name . '_id';
						$link_url = esc_url(
                            add_query_arg(
                                array(
									'tab_name' => $name,
									'data_id' => $id,
									'page_start' => $page_start,
									'page_stage' => $page_stage,
                                ),
                                $base_page_url
                            )
                        );

						$is_excluded = ( $client_status === '対象外' || $client_status === 'Inactive' );
						$row_class   = $is_excluded ? 'ktp-data-list-row--excluded' : '';
						$display_phone = $phone !== '' ? $phone : '—';

						$row_attrs = KTPWP_List_Table::row_nav_attrs( $link_url, $cookie_name, (int) $row->id, $row_class );
						$results[] = '<tr' . $row_attrs . '>'
							. '<td class="col-id">' . $id . '</td>'
							. '<td class="col-company">' . $company_name . '</td>'
							. '<td class="col-contact">' . $user_name . '</td>'
							. '<td class="col-phone">' . esc_html( $display_phone ) . '</td>'
							. '<td class="col-frequency no-print">' . $frequency . '</td>'
							. '</tr>';
					}

					$list_footer = KTPWP_List_Table::close();
				} else {
					// 新しい0データ案内メッセージ（統一デザイン・ガイダンス）
					$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
					. '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'データ作成', 'ktpwp' ) . '">add_circle</span>'
					. '<span style="font-size: 1em; font-weight: 600;">' . esc_html__( '[＋]ボタンを押してデーターを作成してください', 'ktpwp' ) . '</span>'
					. '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__( 'データがまだ登録されていません', 'ktpwp' ) . '</span>'
					. '</div>';
				}
			}

			// 統一されたページネーションデザインを使用
			$results_f = '';
			if ( $view_mode !== 'order_history' ) {
				$results_f = '</div></div>';
			}
			if ( ! $print_all ) {
				$results_f .= $this->render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $view_mode, $client_id ?? null );
			}

			// 顧客データが0件の場合の処理
			if ( ! $print_all && $total_rows == 0 ) {
				// 協力会社タブと同じパターンで「info顧客データがありません」を表示
				$results_f .= '<div style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
					. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
					. esc_html__( '顧客データがありません。', 'ktpwp' )
					. '</div>';
			} elseif ( ! $print_all ) {
				// 受注履歴セクションを追加（リストBOX内、ページネーションの後）
				$cookie_name           = 'ktp_' . $name . '_id';
				$current_customer_name = '';
				$current_customer_id   = $this->resolve_active_client_id( $name, $table_name );

				// 顧客名を取得
				if ( $current_customer_id ) {
					$customer_query = $wpdb->prepare( "SELECT company_name FROM {$table_name} WHERE id = %d", $current_customer_id );
					$customer_data = $wpdb->get_row( $customer_query );
					if ( $customer_data ) {
						$current_customer_name = esc_html( $customer_data->company_name );
					}
				}

				// 注文履歴タイトル（ソートシステム）
				$order_sort_dropdown = $this->generate_sort_dropdown( $name, 'order_history', $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );
				$results_f .= '<div class="data_list_title">■ ' . esc_html( $current_customer_name ) . esc_html__( 'の注文履歴', 'ktpwp' );
				if ( ! empty( $order_sort_dropdown ) ) {
					$results_f .= $order_sort_dropdown;
				}
				$results_f .= '</div>';

				// 注文履歴データを取得（注文履歴ボタンと同じロジック）
				$order_table = $wpdb->prefix . 'ktp_order';

				// この顧客の受注書を取得
				$related_client_ids = array( $current_customer_id );

				// IDのリストを文字列に変換（safety check）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用（ソートオプションを適用）
				$order_sort_column = esc_sql( $order_sort_by ); // SQLインジェクション対策
				$order_sort_direction = $order_sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策

				// 修正: $order_sort_column 内の % を %% にエスケープして prepare に渡す
				$order_sort_column_prepared = str_replace( '%', '%%', $order_sort_column );

				// 修正: $client_ids_str をプレースホルダーに置き換え
				$client_ids_array = explode( ',', $client_ids_str );
				$placeholders = implode( ',', array_fill( 0, count( $client_ids_array ), '%d' ) );

				// 総件数取得（顧客のステータスに関係なく注文履歴を取得）
				$order_total_query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$order_table} WHERE client_id IN ($placeholders)",
					$client_ids_array
				);
				$order_total_rows = $wpdb->get_var( $order_total_query );
				$order_total_pages = ceil( $order_total_rows / $query_limit );

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Debug - Order History: client_id={$current_customer_id}, order_total_rows={$order_total_rows}, order_total_query={$order_total_query}" );
				}

				// 注文履歴のページを取得（order_pageパラメータを使用）
				$order_page = isset( $_GET['order_page'] ) ? intval( $_GET['order_page'] ) : 1;
				$order_page = max( 1, $order_page ); // 最小値は1
				$order_page = min( $order_page, $order_total_pages ); // 最大値は総ページ数
				$order_start = ( $order_page - 1 ) * $query_limit;
				$order_start = max( 0, $order_start ); // 負の値を防ぐ安全対策
				$order_current_page = $order_page;

				// 受注書データを取得（顧客のステータスに関係なく注文履歴を取得）
				$order_query = $wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE client_id IN ($placeholders) ORDER BY {$order_sort_column_prepared} {$order_sort_direction} LIMIT %d, %d",
					array_merge( $client_ids_array, array( intval( $order_start ), intval( $query_limit ) ) )
				);
				$order_rows = $wpdb->get_results( $order_query );

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Debug - Order History: order_rows_count=' . count( $order_rows ) . ", order_query={$order_query}" );
				}

				// 注文履歴リスト表示（注文履歴ボタンと同じ表示形式）
				if ( $order_rows ) {
					// 進捗ラベル
					$progress_labels = array(
						1 => esc_html__( '受付中', 'ktpwp' ),
						2 => esc_html__( '見積中', 'ktpwp' ),
						3 => esc_html__( '受注', 'ktpwp' ),
						4 => esc_html__( '完了', 'ktpwp' ),
						5 => esc_html__( '請求済', 'ktpwp' ),
						6 => esc_html__( '入金済', 'ktpwp' ),
						7 => esc_html__( 'ボツ', 'ktpwp' ),
					);

					foreach ( $order_rows as $order ) {
						$order_id = esc_html( $order->id );
						$project_name = isset( $order->project_name ) ? esc_html( $order->project_name ) : '';
						$progress = intval( $order->progress );
						$progress_label = isset( $progress_labels[ $progress ] ) ? $progress_labels[ $progress ] : esc_html__( '不明', 'ktpwp' );

						// 日時フォーマット変換
						$raw_time = $order->time;
						$formatted_time = '';
						if ( ! empty( $raw_time ) ) {
							if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
								$timestamp = (int) $raw_time;
								$dt = new DateTime( '@' . $timestamp );
								$dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
							} else {
								$dt = date_create( $raw_time, new DateTimeZone( 'Asia/Tokyo' ) );
							}
							if ( $dt ) {
								$week = array( __( '日', 'ktpwp' ), __( '月', 'ktpwp' ), __( '火', 'ktpwp' ), __( '水', 'ktpwp' ), __( '木', 'ktpwp' ), __( '金', 'ktpwp' ), __( '土', 'ktpwp' ) );
								$w = $dt->format( 'w' );
								$formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
							}
						}

						// 完了日のフォーマット
						$completion_date = '';
						if ( ! empty( $order->completion_date ) ) {
							$completion_dt = date_create( $order->completion_date, new DateTimeZone( 'Asia/Tokyo' ) );
							if ( $completion_dt ) {
								$week = array( __( '日', 'ktpwp' ), __( '月', 'ktpwp' ), __( '火', 'ktpwp' ), __( '水', 'ktpwp' ), __( '木', 'ktpwp' ), __( '金', 'ktpwp' ), __( '土', 'ktpwp' ) );
								$w = $completion_dt->format( 'w' );
								$completion_date = $completion_dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）';
							}
						}

						// 受注書の詳細へのリンク（シンプルなURL生成）
						$detail_url = add_query_arg(
                            array(
								'tab_name' => 'order',
								'order_id' => $order_id,
                            ),
                            $base_page_url
                        );

						// 1行化：ID・案件名・完了日・前入金済/EC受注・進捗を同じ行に表示
						$order_info = 'ID: ' . $order_id . ' - ' . $project_name;
						if ( ! empty( $completion_date ) ) {
							$order_info .= ' <span style="color: #666; margin-left: 12px;">' . esc_html__( '完了日：', 'ktpwp' ) . $completion_date . '</span>';
						}
						// 受注経路ラベル（WEB受注 / 前入金済 / EC受注 等）
						if ( class_exists( 'KTPWP_Payment_Timing' ) ) {
							$source_label = KTPWP_Payment_Timing::get_inbound_source_label( $order, null );
							if ( $source_label !== '' ) {
								$order_info .= ' <span class="ktp-prepay-badge" style="display:inline-block;margin-left:6px;padding:2px 8px;font-size:11px;background:#e3f2fd;color:#1565c0;border-radius:4px;">' . esc_html( $source_label ) . '</span>';
							}
						}
						$order_info .= ' <span style="float:right;" class="status-' . $progress . '">' . $progress_label . '</span>';

						$results_f .= '<a href="' . $detail_url . '">'
							. '<div class="ktp_data_list_item">' . $order_info . '</div>'
							. '</a>';
					}
				} else {
					$results_f .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
						. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
						. esc_html__( 'まだ注文がありません。', 'ktpwp' )
						. '</div>';
				}

				// 注文履歴ページネーション
				// どんな場合でもページネーションを表示するため、条件チェックを削除
				// if ($order_total_pages > 1) {
					// 注文履歴のページネーションではview_modeを設定せず、通常の顧客リスト表示を維持
					$results_f .= $this->render_order_history_pagination( $order_current_page, $order_total_pages, $query_limit, $name, $flg, $base_page_url, $order_total_rows, $current_customer_id );
				// }
			}

			// リストBOXを閉じる
			$results_f .= '</div>';

			$data_list = $results_h . $list_header . implode( $results ) . $list_footer . $results_f;

			// -----------------------------
			// 詳細表示(GET)
			// -----------------------------

			// アクションを取得（POSTパラメータを優先、次にGETパラメータ、デフォルトは'update'）
			$action = isset( $_POST['query_post'] ) ? $_POST['query_post'] : ( isset( $_GET['query_post'] ) ? $_GET['query_post'] : 'update' );

			// 安全性確保: GETリクエストの場合は危険なアクションを実行しない（srcmode/istmode は表示用のため許可）
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' && in_array( $action, array( 'delete', 'insert', 'search', 'duplicate' ) ) ) {
				$action = 'update';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			// デバッグ: アクション値を確認
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// アクション値を保護するため、元の値を保存
			$original_action = $action;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// 初期化：追加モードかどうかを最初に判定
			$cookie_name = 'ktp_' . $name . '_id';
			$query_id = null;

			// 変数の初期化
			$data_id = '';
			$time = '';
			$company_name = '';
			$user_name = '';
			$email = '';
			$url = '';
			$representative_name = '';
			$phone = '';
			$postal_code = '';
			$prefecture = '';
			$city = '';
			$address = '';
			$building = '';
			$closing_day = '';
			$payment_month = '';
			$payment_day = '';
			$payment_method = '';
			$tax_category = '';
			$memo = '';
			$client_status = '';
			$order_customer_name = '';
			$order_user_name = '';

			// 追加モード以外の場合のみデータを取得
			if ( $action !== 'istmode' ) {
				$query_id = $this->resolve_active_client_id( $name, $table_name );

				// データを取得し変数に格納
				$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
				$post_row = $wpdb->get_results( $query );
				if ( ! $post_row || count( $post_row ) === 0 ) {
					$query_id = $this->resolve_active_client_id( $name, $table_name );
					if ( $query_id > 0 ) {
						$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
						$post_row = $wpdb->get_results( $query );
					}
					// それでもデータがなければ「データがありません」
					if ( ! $post_row || count( $post_row ) === 0 ) {
						// データが0件でもフォーム・レイアウトを必ず出す
						$data_id = '';
						$time = '';
						$company_name = '';
						$user_name = '';
						$email = '';
						$url = '';
						$representative_name = '';
						$phone = '';
						$postal_code = '';
						$prefecture = '';
						$city = '';
						$address = '';
						$building = '';
						$closing_day = '';
						$payment_month = '';
						$payment_day = '';
						$payment_method = '';
						$tax_category = '';
						$memo = '';
						$client_status = '対象';
						$order_customer_name = '';
						$order_user_name = '';
						// $post_row を空配列にして以降のフォーム生成処理を通す
						$post_row = array();
						// リスト部分にだけ「0データ」ガイダンスを出す（統一デザイン）
						$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
							. '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'データ作成', 'ktpwp' ) . '">add_circle</span>'
							. '<span style="font-size: 1em; font-weight: 600;">' . esc_html__( '[＋]ボタンを押してデーターを作成してください', 'ktpwp' ) . '</span>'
							. '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__( 'データがまだ登録されていません', 'ktpwp' ) . '</span>'
							. '</div>';
					}
				}
				// 表示したIDをクッキーに保存（ヘッダー送信前のみ実行）
				if ( ! headers_sent() ) {
					setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' ); // 30日間有効
				}
				foreach ( $post_row as $row ) {
					$data_id = esc_html( $row->id );
					$time = esc_html( $row->time );
					$company_name = esc_html( $row->company_name );
					$user_name = esc_html( $row->name );
					$email = esc_html( $row->email );
					$url = esc_html( $row->url );
					$representative_name = esc_html( $row->representative_name );
					$phone = esc_html( $row->phone );
					$postal_code = esc_html( $row->postal_code );
					$prefecture = esc_html( $row->prefecture );
					$city = esc_html( $row->city );
					$address = esc_html( $row->address );
					$building = esc_html( $row->building );
					$closing_day = esc_html( $row->closing_day );
					$payment_month = esc_html( $row->payment_month );
					$payment_day = esc_html( $row->payment_day );
					$payment_method = esc_html( $row->payment_method );
					$tax_category = esc_html( $row->tax_category );
					$payment_timing = isset( $row->payment_timing ) && in_array( $row->payment_timing, array( 'postpay', 'prepay', 'prepay_wc' ), true ) ? $row->payment_timing : 'postpay';
					$memo = esc_html( $row->memo );
					$client_status = esc_html( $row->client_status );
					$frequency = esc_html( $row->frequency );
					$category = esc_html( $row->category ?? '' ); // カテゴリーフィールドを追加
					// 受注書作成用のデータを保持
					$order_customer_name = $company_name;
					$order_user_name = $user_name;
				}
			} else {
				// 追加モードの場合は全ての変数を空で初期化
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
				$data_id = '';
				$time = '';
				$company_name = '';
				$user_name = '';
				$email = '';
				$url = '';
				$representative_name = '';
				$phone = '';
				$postal_code = '';
				$prefecture = '';
				$city = '';
				$address = '';
				$building = '';
				$closing_day = '末日';
				$payment_month = '翌月';
				$payment_day = '末日';
				$payment_method = '';
				$tax_category = '';
				$payment_timing = 'postpay';
				$memo = '';
				$client_status = '対象'; // デフォルト値を設定
				$category = ''; // カテゴリーフィールドを追加
				$order_customer_name = '';
				$order_user_name = '';
			}

			// カテゴリーフィールド用の値を設定
			$category_value = isset( $category ) ? $category : '';

			// 表示するフォーム要素を定義
			$fields = array(
				// 'ID' => ['type' => 'text', 'name' => 'data_id', 'readonly' => true],
				'会社名' => array(
					'type' => 'text',
					'name' => 'company_name',
					'required' => true,
					'placeholder' => '必須 法人名または屋号',
				),
				'名前' => array(
					'type' => 'text',
					'name' => 'user_name',
					'placeholder' => '担当者名',
				),
				'メール' => array(
					'type' => 'email',
					'name' => 'email',
				),
				'電話番号' => array(
					'type' => 'text',
					'name' => 'phone',
					'pattern' => '\\d*',
					'placeholder' => '半角数字 ハイフン不要',
				),
				'URL' => array(
					'type' => 'text',
					'name' => 'url',
					'placeholder' => 'https://....',
				),
				'代表者名' => array(
					'type' => 'text',
					'name' => 'representative_name',
					'placeholder' => '代表者名',
				),
				'郵便番号' => array(
					'type' => 'text',
					'name' => 'postal_code',
					'pattern' => '[0-9]*',
					'placeholder' => '半角数字 ハイフン不要',
				),
				'都道府県' => array(
					'type' => 'text',
					'name' => 'prefecture',
				),
				'市区町村' => array(
					'type' => 'text',
					'name' => 'city',
				),
				'番地' => array(
					'type' => 'text',
					'name' => 'address',
				),
				'建物名' => array(
					'type' => 'text',
					'name' => 'building',
				),
				'締め日' => array(
					'type' => 'select',
					'name' => 'closing_day',
					'options' => array( '5日', '10日', '15日', '20日', '25日', '末日', 'なし' ),
					'default' => '末日',
				),
				'支払月' => array(
					'type' => 'select',
					'name' => 'payment_month',
					'options' => array( '今月', '翌月', '翌々月', 'その他' ),
					'default' => '翌月',
				),
				'支払日' => array(
					'type' => 'select',
					'name' => 'payment_day',
					'options' => array( '即日', '5日', '10日', '15日', '20日', '25日', '末日' ),
					'default' => '末日',
				),
				'支払方法' => array(
					'type' => 'select',
					'name' => 'payment_method',
					'options' => array( '銀行振込', 'クレジット', '現金集金' ),
					'default' => '銀行振込',
				),
				'税区分' => array(
					'type' => 'select',
					'name' => 'tax_category',
					'options' => array( '内税', '外税' ),
					'default' => '内税',
				),
				'支払タイミング' => array(
					'type' => 'select',
					'name' => 'payment_timing',
					'options' => array(
						'postpay'   => __( '後払い', 'ktpwp' ),
						'prepay'    => __( '前入金済', 'ktpwp' ),
						'prepay_wc' => __( 'WC受注', 'ktpwp' ),
					),
					'default' => 'postpay',
					'options_assoc' => true,
				),
				'カテゴリー' => array(
					'type' => 'text',
					'name' => 'category',
				), // カテゴリーフィールド（valueは動的に設定）
				'対象｜対象外' => array(
					'type' => 'select',
					'name' => 'client_status',
					'options' => array( '対象', '対象外' ),
					'default' => '対象',
				),
				'メモ' => array(
					'type' => 'textarea',
					'name' => 'memo',
				),
			);

			// 税制モード: 税廃止 または 税率/税額の列を非表示 の場合、税区分フィールドをUIから隠す
			if ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) {
				$fields = array_filter(
					$fields,
					function ( $field ) {
						return ! ( isset( $field['name'] ) && $field['name'] === 'tax_category' );
					}
				);
			}

			$data_forms = ''; // フォームのHTMLコードを格納する変数を初期化
			$data_title = ''; // タイトルのHTMLコードを格納する変数を初期化
			$div_end = ''; // 終了タグを格納する変数を初期化

			// URL パラメータからのメッセージ表示処理を追加
			$session_message = '';
			if ( isset( $_GET['message'] ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const messageType = "' . esc_js( $_GET['message'] ) . '";
                switch (messageType) {
                    case "updated":
                        showSuccessNotification("' . esc_js( __( '更新しました。', 'ktpwp' ) ) . '");
                        break;
                    case "added":
                        showSuccessNotification("' . esc_js( __( '新しい顧客を追加しました。', 'ktpwp' ) ) . '");
                        break;
                    case "deleted":
                        showSuccessNotification("' . esc_js( __( '削除しました。', 'ktpwp' ) ) . '");
                        break;
                    case "found":
                        showInfoNotification("' . esc_js( __( '検索結果を表示しています。', 'ktpwp' ) ) . '");
                        break;
                    case "not_found":
                        showWarningNotification("' . esc_js( __( '該当する顧客が見つかりませんでした。', 'ktpwp' ) ) . '");
                        break;
                }
            });
            </script>';
			}

			// セッションメッセージの確認と表示
			if ( ! session_id() ) {
				ktpwp_safe_session_start();
			}
			if ( isset( $_SESSION['ktp_search_message'] ) ) {
				$message_id = 'ktp-message-' . uniqid();
				$session_message = '<div id="' . $message_id . '" class="ktp-message" style="
                padding: 15px 20px;
                background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
                border-radius: 6px;
                margin: 10px 0;
                color: #333333;
                font-weight: 500;
                box-shadow: 0 3px 10px rgba(0,0,0,0.08);
                display: flex;
                align-items: center;
                font-size: 14px;
                max-width: 90%;
            ">
            <span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">info</span>'
                . esc_html( $_SESSION['ktp_search_message'] ) . '</div>';
				unset( $_SESSION['ktp_search_message'] ); // メッセージを表示後に削除
			}

			// controllerブロックを必ず先頭に追加
			$controller_html = '<div class="controller" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">';

			// 左側：検索＋ボタン群
			$controller_html .= '<div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">';
			$controller_html .= '<div class="ktp-client-controller-actions" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';

			// 現在の顧客IDを取得（後で使用するため）
			$cookie_name       = 'ktp_' . $name . '_id';
			$current_client_id = $this->resolve_active_client_id( $name, $table_name );

			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$client_search_keep = array();
				if ( $current_client_id > 0 ) {
					$client_search_keep['data_id'] = (string) $current_client_id;
				}
				$controller_html .= KTPWP_Tab_Search_UI::get_instance()->render_toolbar_form( 'client', $client_search_keep );
			}

			// 受注書作成用に現在の顧客IDから最新のデータを取得する
			$current_customer_name = '';
			$current_user_name = '';
			$current_client_status = '';
			if ( $current_client_id > 0 ) {
				$current_client_data_query = $wpdb->prepare( "SELECT company_name, name, client_status FROM {$table_name} WHERE id = %d", $current_client_id );
				$current_client_data = $wpdb->get_row( $current_client_data_query );
				if ( $current_client_data ) {
					$current_customer_name = esc_html( $current_client_data->company_name );
					$current_user_name = esc_html( $current_client_data->name );
					$current_client_status = esc_html( $current_client_data->client_status );
				}

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Debug - Order Button Control: client_id={$current_client_id}, client_status={$current_client_status}, query={$current_client_data_query}" );
				}
			}

			// 受注書作成ボタン（左端に配置）
			$controller_html .= '<form method="post" action="" id="create-order-form" style="display:inline-block;">';
			$controller_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
			$controller_html .= '<input type="hidden" name="tab_name" value="order">';
			$controller_html .= '<input type="hidden" name="from_client" value="1">';
			$customer_name_to_use = ! empty( $current_customer_name ) ? $current_customer_name : $order_customer_name;
			$user_name_to_use = ! empty( $current_user_name ) ? $current_user_name : $order_user_name;
			$controller_html .= '<input type="hidden" name="customer_name" value="' . esc_attr( $customer_name_to_use ) . '">';
			$controller_html .= '<input type="hidden" name="user_name" value="' . esc_attr( $user_name_to_use ) . '">';
			$controller_html .= '<input type="hidden" id="client-id-input" name="client_id" value="' . esc_attr( $current_client_id ) . '">';
			$controller_html .= '<div data-client-status="' . esc_attr( $current_client_status ) . '" style="display:none;"></div>';
			$is_data_empty = empty( $post_row ) && empty( $data_id );
			$is_client_excluded = ( $current_client_status === '対象外' || $current_client_status === 'Inactive' );
			$should_disable = $is_data_empty || $is_client_excluded;
			$disabled_attr = $should_disable ? 'disabled' : '';
			$button_title = $is_client_excluded ? __( '受注書作成（対象外顧客のため無効）', 'ktpwp' ) : __( '受注書作成', 'ktpwp' );
			$controller_html .= '<button type="submit" id="createOrderButton" class="create-order-btn" ' . $disabled_attr . ' title="' . esc_attr( $button_title ) . '"><span class="material-symbols-outlined" aria-label="作成">create</span><span class="btn-label">' . esc_html__( '受注書作成', 'ktpwp' ) . '</span></button>';
			$controller_html .= '</form>';

			// 請求書発行ボタンを追加
			$controller_html .= '<button id="invoiceButton" title="' . esc_attr__( '請求書発行', 'ktpwp' ) . '"><span class="material-symbols-outlined" aria-label="' . esc_attr__( '請求書', 'ktpwp' ) . '">receipt_long</span><span class="btn-label">' . esc_html__( '請求書発行', 'ktpwp' ) . '</span></button>';

			// 宛名印刷（フォーム入力をそのまま印刷。長形3号想定レイアウト）
			$address_label_icon = class_exists( 'KTPWP_SVG_Icons' )
				? KTPWP_SVG_Icons::get_icon( 'contact_mail', array( 'aria-label' => __( '宛名', 'ktpwp' ) ) )
				: '<span class="material-symbols-outlined" aria-label="' . esc_attr__( '宛名', 'ktpwp' ) . '">contact_mail</span>';
			$controller_html .= '<button type="button" id="addressLabelPrintButton" class="ktp-client-address-label-btn" onclick="printClientAddressLabel(); return false;" title="' . esc_attr__( '宛名印刷', 'ktpwp' ) . '">' . $address_label_icon . '<span class="btn-label">' . esc_html__( '宛名印刷', 'ktpwp' ) . '</span></button>';

			// 請求書発行ポップアップ
			$controller_html .= '<div id="ktp-invoice-preview-popup" style="display:none;background:rgba(0,0,0,0.5);">';
			$controller_html .= '<div class="ktp-invoice-preview-dialog" style="background:white;padding:20px;border-radius:8px;width:90%;max-width:800px;">';

			// ヘッダー部分（固定）
			$controller_html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #ddd;padding-bottom:10px;">';
			$controller_html .= '<h3 style="margin:0;color:#333;">' . esc_html__( '請求書プレビュー', 'ktpwp' ) . '</h3>';
			$controller_html .= '<button type="button" id="ktp-invoice-preview-close" style="background: none; color: #333; border: none; cursor: pointer; font-size: 28px; padding: 0; line-height: 1;">×</button>';
			$controller_html .= '</div>';

			$controller_html .= '<p class="ktp-invoice-envelope-hint" style="margin:0 0 12px 0;padding:10px 12px;font-size:12px;line-height:1.55;color:#444;background:#f6f7f7;border-radius:4px;border-left:3px solid #2271b1;">' . esc_html__( '長形３号窓明封筒を利用するには印刷の余白を上下左右10mmにしてください（参考値）', 'ktpwp' ) . '</p>';

			// コンテンツ部分（スクロール可能）
			$controller_html .= '<div id="invoiceList" style="flex:1;overflow-y:scroll;padding-right:10px;padding:50px;">';
			$controller_html .= '<div style="text-align:center;color:#888;">' . esc_html__( '読み込み中...', 'ktpwp' ) . '</div>';
			$controller_html .= '</div>';

			$controller_html .= '</div>';
			$controller_html .= '</div>';

			// デザイン設定から奇数偶数カラーを取得
			$design_options = get_option( 'ktp_design_settings', array() );
			$odd_row_color = isset( $design_options['odd_row_color'] ) ? $design_options['odd_row_color'] : '#E7EEFD';
			$even_row_color = isset( $design_options['even_row_color'] ) ? $design_options['even_row_color'] : '#FFFFFF';

			$controller_html .= '</div>'; // ktp-client-controller-actions 終了

			// 右側：印刷ボタン（プレビューは廃止）
			$controller_html .= '</div>'; // 左側（検索＋ボタン）終了
			$controller_html .= '<div style="display: flex; gap: 5px;">';
			$my_company_for_print = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company_for_print = KTPWP_Settings::get_company_info();
			}
			if ( empty( $my_company_for_print ) ) {
				$my_company_for_print = get_bloginfo( 'name' );
			}
			$my_company_for_print = wp_strip_all_tags( (string) $my_company_for_print );
			$my_company_for_print = preg_replace( '/\S+@\S+\.\S+/', '', $my_company_for_print );
			$my_company_for_print = preg_replace( '/\s+/', ' ', trim( $my_company_for_print ) );

			$clients_print_args = array(
				'tab_name'   => $name,
				'print_all'  => 1,
				'page_start' => 0,
				'page_stage' => 2,
			);
			if ( isset( $_GET['sort_by'] ) && (string) $_GET['sort_by'] !== '' ) {
				$clients_print_args['sort_by'] = sanitize_text_field( wp_unslash( $_GET['sort_by'] ) );
			}
			if ( isset( $_GET['sort_order'] ) && (string) $_GET['sort_order'] !== '' ) {
				$clients_print_args['sort_order'] = sanitize_text_field( wp_unslash( $_GET['sort_order'] ) );
			}
			$clients_list_print_url = esc_url( add_query_arg( $clients_print_args, $base_page_url ) );

			if ( class_exists( 'KTPWP_Ui_Generator' ) ) {
				$controller_html .= KTPWP_Ui_Generator::render_tab_print_button(
					array(
						'id'    => 'js-clients-list-print-btn',
						'label' => __( '顧客リスト印刷', 'ktpwp' ),
						'title' => __( '印刷（ブラウザの印刷／PDFに保存）', 'ktpwp' ),
						'attrs' => array(
							'data-tab-list-print'        => '1',
							'data-print-target'          => '#ktp-clients-print-list-only',
							'data-print-fetch-url'       => $clients_list_print_url,
							'data-print-extract-selector' => '#ktp-clients-print-list-only',
							'data-print-filename-base'   => __( '顧客リスト', 'ktpwp' ),
							'data-print-title'           => __( '顧客リスト', 'ktpwp' ),
							'data-print-footer-name'     => $my_company_for_print,
						),
					)
				);
			}

			$controller_html .= '</div>'; // 右側のボタン群終了
			$controller_html .= '</div>'; // 左側（検索＋ボタン）終了
			$controller_html .= '</div>'; // controller終了

			$cross_search_panel = '';
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$cross_search_panel = KTPWP_Tab_Search_UI::get_instance()->maybe_render_cross_search_panel( 'client' );
			}

			// 空のフォームを表示(追加モードの場合)
			if ( $action === 'istmode' ) {

					// デバッグ: 追加モード実行時のアクション値確認
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// 追加モードではデータベース挿入は行わず、フォーム表示のみ
				// $data_id = $wpdb->insert_id; // この行を削除

				// 詳細表示部分の開始
				$data_title = '<div class="data_detail_box"><div class="data_detail_title">' . esc_html__( '■ 顧客追加中', 'ktpwp' ) . '</div>';

				// 追加モード用のフォーム開始
				$data_forms .= '<form method="post" action="">';
				// nonceフィールド追加
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );

				// デバッグ: フォーム生成開始をログ出力
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// 空のフォームフィールドを生成
				foreach ( $fields as $label => $field ) {
					// カテゴリーフィールドの特別処理
					if ( $field['name'] === 'category' ) {
						$value = ( $action === 'istmode' ) ? '' : ( isset( $category ) ? $category : '' );
					} else {
						// 追加モード（istmode）では常に空の値を設定
						$value = ( $action === 'istmode' ) ? '' : ( isset( ${$field['name']} ) ? ${$field['name']} : '' );
					}

					// デバッグ: istmode時のフィールド値をログ出力
					if ( $action === 'istmode' ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						}
					}

					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = $field['name'];
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );
					if ( $field['type'] === 'textarea' ) {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$data_forms .= "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <textarea id=\"{$fieldId}\" name=\"{$fieldName}\"{$pattern}{$required}>" . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						$options_assoc = ! empty( $field['options_assoc'] );
						foreach ( $field['options'] as $opt_value => $opt_label ) {
							if ( ! $options_assoc ) {
								$opt_value = $opt_label;
							}
							if ( $action === 'istmode' ) {
								$selected = ( isset( $field['default'] ) && $field['default'] === $opt_value ) ? ' selected' : '';
							} else {
								$selected = ( $value === $opt_value ) ? ' selected' : '';
							}
							$options .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . ( $options_assoc ? esc_html( $opt_label ) : esc_html__( $opt_label, 'ktpwp' ) ) . '</option>';
						}
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$data_forms .= "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <select id=\"{$fieldId}\" name=\"{$fieldName}\"{$required}>{$options}</select></div>";
					} else {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$contact_html = class_exists( 'KTPWP_External_Url' )
							? KTPWP_External_Url::maybe_render_form_group(
								$fieldName,
								__( $label, 'ktpwp' ),
								$fieldId,
								$field,
								$value,
								$pattern,
								$required,
								$placeholder
							)
							: null;
						if ( $contact_html !== null ) {
							$data_forms .= $contact_html;
						} else {
							$generated_html = "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <input id=\"{$fieldId}\" type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"" . esc_attr( $value ) . "\"{$pattern}{$required}{$placeholder}></div>";
							$data_forms .= $generated_html;
						}

						// デバッグ: 生成されたHTMLをログ出力（istmodeの場合のみ）
						if ( $action === 'istmode' ) {
						}
					}
				}

				$data_forms .= $this->render_client_postal_lookup_script();

				// ボタン群
				$data_forms .= "<div class='button'>";

				if ( $action === 'istmode' ) {
					// 追加実行ボタン（同じフォーム内）
					$data_forms .= '<input type="hidden" name="query_post" value="insert">'
						. '<input type="hidden" name="data_id" value="">'
						. '<button type="submit" name="send_post" title="' . esc_attr__( '追加実行', 'ktpwp' ) . '" class="insert-submit-btn">'
						. '<span class="material-symbols-outlined">select_check_box</span>'
						. esc_html__( '追加実行', 'ktpwp' ) . '</button>';

					// キャンセルボタン（独立したフォーム）
					$data_forms .= '</form>'; // 追加フォーム終了
					$data_forms .= '<form method="post" action="" style="display:inline-block;margin-left:10px;">';
					$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
					$data_forms .= '<input type="hidden" name="query_post" value="update">';
					$data_forms .= '<button type="submit" title="' . esc_attr__( 'キャンセル', 'ktpwp' ) . '" style="background-color: #666 !important; margin-left: 10px;">'
						. '<span class="material-symbols-outlined">disabled_by_default</span>'
						. esc_html__( 'キャンセル', 'ktpwp' ) . '</button>';
					$data_forms .= '</form>'; // キャンセルフォーム終了
				}
				$data_forms .= '</div>'; // button div終了

				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			// 空のフォームを表示(検索モードの場合)
			elseif ( $action === 'srcmode' ) {

				// デバッグ: 検索モード実行時のアクション値確認

				$data_title = '<div class="data_detail_box search-mode"><div class="data_detail_title">' . esc_html__( '■ 顧客の詳細（検索モード）', 'ktpwp' ) . '</div>';

				// 検索モード用のフォーム
				$data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
				$data_forms .= '<div class="notice notice-info ktp-search-mode-notice" style="margin: 10px 0; padding: 10px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 4px; display: flex; align-items: center;">';
				$data_forms .= '<span style="margin-right: 10px; color: #17a2b8; font-size: 18px;" class="material-symbols-outlined" aria-hidden="true">search</span>';
				$data_forms .= esc_html__( '検索モードです。条件を入力して検索してください。', 'ktpwp' );
				$data_forms .= '</div>';
				$data_forms .= '<form method="post" action="">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$data_forms .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';

				// 検索クエリの値を取得（POSTが優先、次にGET）
				$search_query_value = '';
				if ( isset( $_POST['search_query'] ) ) {
					$search_query_value = esc_attr( $_POST['search_query'] );
				} elseif ( isset( $_GET['search_query'] ) ) {
					$search_query_value = esc_attr( urldecode( $_GET['search_query'] ) );
				}

				$data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
				$data_forms .= '<input type="text" name="search_query" placeholder="' . esc_attr__( 'フリーワード検索', 'ktpwp' ) . '" value="' . $search_query_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
				$data_forms .= '</div>';

				// ボタンを横並びにするためのラップクラスを追加
				$data_forms .= '<div class="button-group" style="display: flex; gap: 10px; margin-top: 15px !important; justify-content: flex-end !important;">';

				// 検索実行ボタン
				$data_forms .= '<input type="hidden" name="query_post" value="search">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索実行', 'ktpwp' ) . '" class="search-submit-btn" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
				$data_forms .= esc_html__( '検索実行', 'ktpwp' );
				$data_forms .= '</button>';

				$data_forms .= '</form>'; // 検索フォームの閉じタグ

				// 検索モードのキャンセルボタン（独立したフォーム）
				$data_forms .= '<form method="post" action="" style="margin: 0 !important;">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( 'キャンセル', 'ktpwp' ) . '" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';

				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
				$data_forms .= esc_html__( 'キャンセル', 'ktpwp' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // ボタンラップクラスの閉じタグ
				// 該当なしメッセージは検索実行・キャンセルボタンの直下に表示（サービスと同様）
				if ( ( isset( $_POST['query_post'] ) && $_POST['query_post'] === 'search' && empty( $search_results_list ) ) ||
					( isset( $_GET['no_results'] ) && $_GET['no_results'] === '1' ) ) {
					$no_results_id = 'no-results-' . uniqid();
					$data_forms .= '<div id="' . esc_attr( $no_results_id ) . '" class="no-results ktp-client-no-results" style="
                    margin-top: 16px !important;
                    padding: 15px 20px !important;
                    background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%) !important;
                    border-radius: 6px !important;
                    margin-right: 0 !important;
                    margin-bottom: 15px !important;
                    margin-left: 0 !important;
                    color: #333333 !important;
                    font-weight: 500 !important;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08) !important;
                    display: flex !important;
                    align-items: center !important;
                    font-size: 14px !important;
                ">
                <span style="margin-right: 10px !important; color: #ff6b8b !important; font-size: 18px !important;" class="material-symbols-outlined">search_off</span>
                ' . esc_html__( '検索結果が見つかりませんでした。別のキーワードをお試しください。', 'ktpwp' ) . '
                </div>';
				}
				$data_forms .= '</div>'; // search-mode-formの閉じタグ
				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			// 追加・検索 以外なら更新フォームを表示
			elseif ( $action !== 'srcmode' && $action !== 'istmode' && $action !== 'search' ) { // searchも除外

				// cookieに保存されたIDを取得（未決定の場合のみ上書き）
				if ( ! isset( $data_id ) || $data_id === '' || $data_id === null ) {
					$cookie_name = 'ktp_' . $name . '_id';
					if ( isset( $_GET['data_id'] ) ) {
						$data_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
					} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
						// クッキーIDがDBに存在する場合のみ適用
						$cookie_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
						$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id ) );
						$data_id = $exists ? $cookie_id : '';
					} else {
						$data_id = $last_id_row ? $last_id_row->id : null;
					}
				}

				// ボタン群HTMLの準備
				$button_group_html = '<div class="button-group" style="display: flex; gap: 8px; margin-left: auto;">';

				// 削除ボタン
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$button_group_html .= '<input type="hidden" name="query_post" value="delete">';
				$button_group_html .= '<input type="hidden" name="delete_type" value="soft">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '削除（無効化）する', 'ktpwp' ) . '" onclick="return confirm(\"' . esc_js( __( 'この顧客を削除（無効化）しますか？\nデータは残りますが、表示ラベルが「対象外」に変更されます。', 'ktpwp' ) ) . '\")" class="button-style delete-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">delete</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 追加モードボタン
				$add_action = 'istmode';
				$next_data_id = is_numeric( $data_id ) ? ( (int) $data_id + 1 ) : '';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="">';
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $add_action ) . '">';
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $next_data_id ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加する', 'ktpwp' ) . '" class="button-style add-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">add</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 検索モードボタン
				$search_action = 'srcmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $search_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索する', 'ktpwp' ) . '" class="button-style search-mode-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">search</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				$button_group_html .= '</div>'; // ボタングループ終了

				// 表題にボタングループを含める
				// デバッグ用：data_idの値を確認
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('KTPWP Client Tab: data_id = ' . var_export($data_id, true));
					error_log('KTPWP Client Tab: data_id type = ' . gettype($data_id));
					error_log('KTPWP Client Tab: id_display condition = ' . (!empty($data_id) && $data_id !== '0' && $data_id !== 0 ? 'true' : 'false'));
				}
				$id_display = ( ! empty( $post_row ) && ! empty( $data_id ) && $data_id !== '0' && $data_id !== 0 ) ? '（ ID: ' . esc_html( $data_id ) . ' ）' : '';
				$data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
            <div>' . esc_html__( '■ 顧客の詳細', 'ktpwp' ) . $id_display . '</div>' . $button_group_html . '</div>';

				// メイン更新フォーム
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_url ) . '">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );

				// 基本情報フィールド（URLまで）
				$basic_field_labels = array(
					'company_name' => '会社名',
					'user_name'    => '名前',
					'email'        => 'メール',
					'phone'        => '電話番号',
					'url'          => 'URL',
				);
				$basic_fields = array_keys( $basic_field_labels );
				foreach ( $basic_fields as $field_name ) {
					$field = $fields[ $basic_field_labels[ $field_name ] ];
					$value = $action === 'update' ? ( isset( ${$field_name} ) ? ${$field_name} : '' ) : '';

					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';

					$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
					$contact_html = class_exists( 'KTPWP_External_Url' )
						? KTPWP_External_Url::maybe_render_form_group(
							$field['name'],
							__( $basic_field_labels[ $field_name ], 'ktpwp' ),
							$fieldId,
							$field,
							$value,
							$pattern,
							$required,
							$placeholder
						)
						: null;
					if ( $contact_html !== null ) {
						$data_forms .= $contact_html;
					} else {
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html__( $basic_field_labels[ $field_name ], 'ktpwp' ) . '：</label> <input id="' . $fieldId . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $field['name'] ) . '" value="' . esc_attr( $value ) . '"' . $pattern . $required . $placeholder . '></div>';
					}
				}

				// 部署設定セクション（シンプルデザイン）
				$data_forms .= '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px;">';
				$data_forms .= '<h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px; font-weight: normal;">' . esc_html__( '部署設定（複数の部署や担当者がある場合は設定してください）', 'ktpwp' ) . '</h4>';

				// 部署追加フォーム（ラベルなし、シンプルデザイン）
				$data_forms .= '<div style="display: flex; gap: 10px; align-items: end; margin-bottom: 15px;">';
				$data_forms .= '<div style="flex: 1;"><input type="text" name="department_name" placeholder="' . esc_attr__( '部署名', 'ktpwp' ) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 1;"><input type="text" name="contact_person" placeholder="' . esc_attr__( '担当者名', 'ktpwp' ) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 1;"><input type="email" name="department_email" placeholder="' . esc_attr__( 'メールアドレス', 'ktpwp' ) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 0 0 auto;"><button type="button" onclick="addDepartment()" style="padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">' . esc_html__( '追加', 'ktpwp' ) . '</button></div>';
				$data_forms .= '</div>';

				// 部署リスト表示
				$data_forms .= '<div id="department-list" style="margin-top: 15px;">';
				if ( $action === 'update' && ! empty( $data_id ) ) {
					// 既存の部署を取得して表示
					if ( class_exists( 'KTPWP_Department_Manager' ) ) {
						$departments = KTPWP_Department_Manager::get_departments_by_client( $data_id );
						if ( ! empty( $departments ) ) {
							$data_forms .= '<table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #ddd;">';
							$data_forms .= '<thead><tr style="background: #f5f5f5;"><th style="padding: 8px; border: 1px solid #ddd; text-align: center; width: 30px;">✔</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__( '部署名', 'ktpwp' ) . '</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__( '担当者名', 'ktpwp' ) . '</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__( 'メールアドレス', 'ktpwp' ) . '</th><th style="padding: 8px; border: 1px solid #ddd; text-align: center; width: 80px;">' . esc_html__( '操作', 'ktpwp' ) . '</th></tr></thead>';
							$data_forms .= '<tbody>';
							// 現在選択されている部署IDを取得
							$selected_department_id = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT selected_department_id FROM `{$table_name}` WHERE id = %d",
                                    $data_id
                                )
                            );

							// selected_department_idがNULLまたは空の場合は選択なし状態
							if ( empty( $selected_department_id ) ) {
								$selected_department_id = null;
							}

							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( "KTPWP Client Tab: 顧客ID {$data_id} の選択された部署ID: " . ( $selected_department_id ?: 'NULL' ) );
								error_log( 'KTPWP Client Tab: 部署数: ' . count( $departments ) );
								error_log( 'KTPWP Client Tab: ページリロード時の状態確認完了' );
							}

							foreach ( $departments as $dept ) {
								// 部署IDと選択された部署IDを比較してチェック状態を決定
								// selected_department_idがNULLの場合は選択されていない
								$is_checked = ( ! empty( $selected_department_id ) && $dept->id == $selected_department_id );
								$checked = $is_checked ? ' checked' : '';

								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( "KTPWP Client Tab: 部署ID {$dept->id} のチェック状態: " . ( $is_checked ? 'true' : 'false' ) );
								}

								$data_forms .= '<tr>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><input type="checkbox" name="department_selection[]" value="' . $dept->id . '" id="dept_' . $dept->id . '"' . $checked . ' class="department-checkbox" data-department-id="' . $dept->id . '" onchange="updateDepartmentSelection(' . $dept->id . ', this.checked)"></td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->department_name ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->contact_person ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->email ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><button type="button" onclick="deleteDepartment(' . $dept->id . ')" style="padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">' . esc_html__( '削除', 'ktpwp' ) . '</button></td>';
								$data_forms .= '</tr>';
							}
							$data_forms .= '</tbody></table>';

							// 選択された部署の情報を表示（シンプルデザイン）
							$data_forms .= '<div id="selected-department-info" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
							$data_forms .= '<h5 style="margin: 0 0 10px 0; color: #333; font-size: 13px; font-weight: normal;">' . esc_html__( '選択された部署', 'ktpwp' ) . '</h5>';

							// 選択された部署の情報を表示
							$selected_departments = array();
							if ( ! empty( $selected_department_id ) ) {
								$selected_departments = array_filter(
                                    $departments,
                                    function ( $dept ) use ( $selected_department_id ) {
                                        return $dept->id == $selected_department_id;
                                    }
                                );
							}

							if ( ! empty( $selected_departments ) ) {
								foreach ( $selected_departments as $selected_dept ) {
									$data_forms .= '<div class="selected-dept-detail" style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 3px; font-size: 12px; color: #666;">';
									$data_forms .= '<strong>' . esc_html__( '部署名:', 'ktpwp' ) . '</strong> ' . esc_html( $selected_dept->department_name ) . '<br>';
									$data_forms .= '<strong>' . esc_html__( '担当者名:', 'ktpwp' ) . '</strong> ' . esc_html( $selected_dept->contact_person ) . '<br>';
									$data_forms .= '<strong>' . esc_html__( 'メールアドレス:', 'ktpwp' ) . '</strong> ' . esc_html( $selected_dept->email );
									$data_forms .= '</div>';
								}
							} else {
								$data_forms .= '<div class="selected-dept-detail" style="font-size: 12px; color: #666;">' . esc_html__( '部署を選択してください', 'ktpwp' ) . '</div>';
							}
							$data_forms .= '</div>';
						} else {
							$data_forms .= '<p style="color: #666; font-size: 12px; margin: 0;">' . esc_html__( '部署が登録されていません。', 'ktpwp' ) . '</p>';
						}
					}
				} else {
					$data_forms .= '<p style="color: #666; font-size: 12px; margin: 0;">' . esc_html__( '顧客を保存後に部署を追加できます。', 'ktpwp' ) . '</p>';
				}
				$data_forms .= '</div>';
				$data_forms .= '</div>';

				// 残りのフィールド
				$remaining_fields = array( 'representative_name', 'postal_code', 'prefecture', 'city', 'address', 'building', 'closing_day', 'payment_month', 'payment_day', 'payment_method', 'tax_category', 'payment_timing', 'category', 'client_status', 'memo' );
				foreach ( $remaining_fields as $field_name ) {
					$field_key = '';
					switch ( $field_name ) {
						case 'representative_name':
									$field_key = '代表者名';
			                break;
						case 'postal_code':
									$field_key = '郵便番号';
			                break;
						case 'prefecture':
									$field_key = '都道府県';
			                break;
						case 'city':
									$field_key = '市区町村';
			                break;
						case 'address':
									$field_key = '番地';
			                break;
						case 'building':
									$field_key = '建物名';
			                break;
						case 'closing_day':
									$field_key = '締め日';
			                break;
						case 'payment_month':
									$field_key = '支払月';
			                break;
						case 'payment_day':
									$field_key = '支払日';
			                break;
						case 'payment_method':
									$field_key = '支払方法';
			                break;
						case 'tax_category':
									$field_key = '税区分';
			                break;
						case 'payment_timing':
									$field_key = '支払タイミング';
			                break;
						case 'category':
									$field_key = 'カテゴリー';
			                break;
						case 'client_status':
									$field_key = '対象｜対象外';
			                break;
						case 'memo':
									$field_key = 'メモ';
			                break;
					}

                    // 税制モードで一部フィールド（例: 税区分）が除外されている場合があるため存在チェック
                    if ( ! isset( $fields[ $field_key ] ) ) {
                        continue;
                    }
                    $field = $fields[ $field_key ];

					// カテゴリーフィールドの特別処理
					if ( $field['name'] === 'category' ) {
						$value = isset( $category ) ? $category : '';
					} else {
						$value = $action === 'update' ? ( isset( ${$field['name']} ) ? ${$field['name']} : '' ) : '';
					}

					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';

					if ( $field['type'] === 'textarea' ) {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html__( $field_key, 'ktpwp' ) . '：</label> <textarea id="' . $fieldId . '" name="' . esc_attr( $field['name'] ) . '"' . $pattern . $required . '>' . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						$options_assoc = ! empty( $field['options_assoc'] );
						foreach ( $field['options'] as $opt_value => $opt_label ) {
							if ( ! $options_assoc ) {
								$opt_value = $opt_label;
							}
							$selected = $value === $opt_value ? ' selected' : '';
							$options .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . ( $options_assoc ? esc_html( $opt_label ) : esc_html__( $opt_label, 'ktpwp' ) ) . '</option>';
						}
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html__( $field_key, 'ktpwp' ) . '：</label> <select id="' . $fieldId . '" name="' . esc_attr( $field['name'] ) . '"' . $required . '>' . $options . '</select></div>';
					} else {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$contact_html = class_exists( 'KTPWP_External_Url' )
							? KTPWP_External_Url::maybe_render_form_group(
								$field['name'],
								__( $field_key, 'ktpwp' ),
								$fieldId,
								$field,
								$value,
								$pattern,
								$required,
								$placeholder
							)
							: null;
						if ( $contact_html !== null ) {
							$data_forms .= $contact_html;
						} else {
							$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html__( $field_key, 'ktpwp' ) . '：</label> <input id="' . $fieldId . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $field['name'] ) . '" value="' . esc_attr( $value ) . '"' . $pattern . $required . $placeholder . '></div>';
						}
					}
				}
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$data_forms .= '<div class="button">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '更新する', 'ktpwp' ) . '" class="update-submit-btn"><span class="material-symbols-outlined">cached</span></button>';
				$data_forms .= '</div>';
				$data_forms .= '</form>';

				$data_forms .= $this->render_client_postal_lookup_script();

				// 部署管理用JavaScript
				$data_forms .= '<script>
        function addDepartment() {
            var departmentName = document.querySelector("input[name=\'department_name\']").value;
            var contactPerson = document.querySelector("input[name=\'contact_person\']").value;
            var email = document.querySelector("input[name=\'department_email\']").value;
            var clientId = ' . esc_js( $data_id ) . ';
            
            // 部署設定は空欄でも可
            if (!contactPerson || !email) {
                alert(ktpwpTranslate("担当者名とメールアドレスを入力してください。"));
                return;
            }
            
            var formData = new FormData();
            formData.append("action", "ktp_add_department");
            formData.append("client_id", clientId);
            formData.append("department_name", departmentName);
            formData.append("contact_person", contactPerson);
            formData.append("email", email);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(ktpwpTranslate("部署の追加に失敗しました: ") + data.data);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert(ktpwpTranslate("部署の追加に失敗しました。"));
            });
        }
        
        function deleteDepartment(departmentId) {
            if (!confirm(ktpwpTranslate("この部署を削除しますか？"))) {
                return;
            }
            
            var formData = new FormData();
            formData.append("action", "ktp_delete_department");
            formData.append("department_id", departmentId);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(ktpwpTranslate("部署の削除に失敗しました: ") + data.data);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert(ktpwpTranslate("部署の削除に失敗しました。"));
            });
        }
        
        function updateDepartmentSelection(departmentId, isSelected) {
            console.log("updateDepartmentSelection called - departmentId:", departmentId, "isSelected:", isSelected);
            console.log("ajaxurl available:", typeof ajaxurl !== "undefined" ? ajaxurl : "undefined");
            
            // 重複実行を防ぐため、処理中の場合はスキップ
            if (window.departmentUpdateInProgress) {
                console.log("Department update already in progress, skipping...");
                return;
            }
            
            window.departmentUpdateInProgress = true;
            
            // 他の部署のチェックを外す（単一選択のため）
            if (isSelected) {
                var allCheckboxes = document.querySelectorAll(\'input[name="department_selection[]"]\');
                allCheckboxes.forEach(function(checkbox) {
                    if (checkbox.dataset.departmentId != departmentId) {
                        checkbox.checked = false;
                    }
                });
            }
            // 選択解除時は他の部署のチェックはそのまま（自動選択しない）
            
            // AJAXで部署選択状態を更新
            var formData = new FormData();
            formData.append("action", "ktp_update_department_selection");
            formData.append("department_id", departmentId);
            formData.append("is_selected", isSelected);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            console.log("Sending AJAX request for department selection update");
            console.log("FormData contents:");
            console.log("action:", formData.get("action"));
            console.log("department_id:", formData.get("department_id"));
            console.log("is_selected:", formData.get("is_selected"));
            console.log("nonce:", formData.get("nonce"));
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log("Response status:", response.status);
                console.log("Response ok:", response.ok);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log("Department selection update successful");
                    // 選択された部署情報を更新
                    updateSelectedDepartmentInfo();
                } else {
                    console.log("Department selection update failed:", data.data);
                    alert(ktpwpTranslate("部署選択状態の更新に失敗しました: ") + data.data);
                    // チェックボックスの状態を元に戻す
                    var checkbox = document.querySelector(\'input[data-department-id="\' + departmentId + \'"]\');
                    if (checkbox) {
                        checkbox.checked = !isSelected;
                    }
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                console.error("Error name:", error.name);
                console.error("Error message:", error.message);
                alert(ktpwpTranslate("部署選択状態の更新に失敗しました。"));
                // チェックボックスの状態を元に戻す
                var checkbox = document.querySelector(\'input[data-department-id="\' + departmentId + \'"]\');
                if (checkbox) {
                    checkbox.checked = !isSelected;
                }
            })
            .finally(function() {
                // 処理完了後、フラグをリセット
                window.departmentUpdateInProgress = false;
            });
        }
        
        function updateSelectedDepartmentInfo() {
            // 選択された部署の情報を表示エリアに反映
            var selectedCheckboxes = document.querySelectorAll(\'input[name="department_selection[]"]:checked\');
            var infoDiv = document.getElementById("selected-department-info");
            
            console.log("updateSelectedDepartmentInfo called - selected checkboxes:", selectedCheckboxes.length);
            
            if (infoDiv) {
                // 既存の詳細表示要素を全て削除
                var existingDetails = infoDiv.querySelectorAll(".selected-dept-detail");
                existingDetails.forEach(function(detail) {
                    detail.remove();
                });
                
                if (selectedCheckboxes.length > 0) {
                    selectedCheckboxes.forEach(function(checkbox) {
                        var row = checkbox.closest("tr");
                        var departmentName = row.cells[1].textContent;
                        var contactPerson = row.cells[2].textContent;
                        var email = row.cells[3].textContent;
                        
                        var detailDiv = document.createElement("div");
                        detailDiv.className = "selected-dept-detail";
                        detailDiv.style.marginBottom = "10px";
                        detailDiv.style.padding = "8px";
                        detailDiv.style.background = "white";
                        detailDiv.style.borderRadius = "3px";
                        detailDiv.style.fontSize = "12px";
                        detailDiv.style.color = "#666";
                        
                        detailDiv.innerHTML = \'<strong>部署名:</strong> \' + departmentName + \'<br>\';
                        detailDiv.innerHTML += \'<strong>担当者名:</strong> \' + contactPerson + \'<br>\';
                        detailDiv.innerHTML += \'<strong>メールアドレス:</strong> \' + email;
                        
                        infoDiv.appendChild(detailDiv);
                    });
                    console.log("Updated with selected department info");
                } else {
                    // 選択された部署がない場合は「選択なし」を表示
                    var noSelectionDiv = document.createElement("div");
                    noSelectionDiv.className = "selected-dept-detail";
                    noSelectionDiv.style.fontSize = "12px";
                    noSelectionDiv.style.color = "#666";
                    noSelectionDiv.textContent = "部署を選択してください";
                    infoDiv.appendChild(noSelectionDiv);
                    console.log("Updated with no selection message");
                }
            } else {
                console.log("selected-department-info div not found");
            }
        }
        </script>';

				if ( function_exists( 'ktpwp_contracts_feature_enabled' ) && ktpwp_contracts_feature_enabled()
					&& class_exists( 'KTPWP_Contract_UI' ) && ! empty( $data_id ) && is_numeric( $data_id ) ) {
					$data_forms .= KTPWP_Contract_UI::get_instance()->render_client_section( (int) $data_id );
				}

				// ボタン群は既にタイトル内に配置済み

				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			$data_forms .= '</div>'; // フォームを囲む<div>タグの終了

			// 詳細表示部分の終了タグを設定（全モード共通）
			// if (empty($div_end)) {
			// </div> <!-- data_contentsの終了 -->
			// END;
			// }

			// -----------------------------
			// テンプレート印刷
			// -----------------------------

			        			// KTPWP_Print_Classのパスを指定
			require_once __DIR__ . '/class-ktpwp-print.php';

			// 変数の初期化（未定義の場合に備えて）
			if ( ! isset( $company_name ) ) {
				$company_name = '';
			}
			if ( ! isset( $user_name ) ) {
				$user_name = '';
			}
			if ( ! isset( $email ) ) {
				$email = '';
			}
			if ( ! isset( $url ) ) {
				$url = '';
			}
			if ( ! isset( $representative_name ) ) {
				$representative_name = '';
			}
			if ( ! isset( $phone ) ) {
				$phone = '';
			}
			if ( ! isset( $postal_code ) ) {
				$postal_code = '';
			}
			if ( ! isset( $prefecture ) ) {
				$prefecture = '';
			}
			if ( ! isset( $city ) ) {
				$city = '';
			}
			if ( ! isset( $address ) ) {
				$address = '';
			}
			if ( ! isset( $building ) ) {
				$building = '';
			}
			if ( ! isset( $closing_day ) ) {
				$closing_day = '';
			}
			if ( ! isset( $payment_month ) ) {
				$payment_month = '';
			}
			if ( ! isset( $payment_day ) ) {
				$payment_day = '';
			}
			if ( ! isset( $payment_method ) ) {
				$payment_method = '';
			}
			if ( ! isset( $tax_category ) ) {
				$tax_category = '';
			}
			if ( ! isset( $payment_timing ) ) {
				$payment_timing = 'postpay';
			}
			if ( ! isset( $category ) ) {
				$category = '';
			}
			if ( ! isset( $client_status ) ) {
				$client_status = '';
			}
			if ( ! isset( $memo ) ) {
				$memo = '';
			}

			// 顧客情報のプレビュー用HTMLを生成
			$customer_preview_html = $this->generateCustomerPreviewHTML(
                array(
					'company_name' => $company_name,
					'name' => $user_name,
					'email' => $email,
					'url' => $url,
					'representative_name' => $representative_name,
					'phone' => $phone,
					'postal_code' => $postal_code,
					'prefecture' => $prefecture,
					'city' => $city,
					'address' => $address,
					'building' => $building,
					'closing_day' => $closing_day,
					'payment_month' => $payment_month,
					'payment_day' => $payment_day,
					'payment_method' => $payment_method,
					'tax_category' => $tax_category,
					'category' => $category,
					'client_status' => $client_status,
					'memo' => $memo,
                )
            );

			// インライン <script> 埋め込み用（</script> によるタグ切断を防ぐ）
			$customer_preview_html = wp_json_encode(
				$customer_preview_html,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
			);
			if ( false === $customer_preview_html ) {
				$customer_preview_html = '""';
			}

			$customer_print_title = wp_json_encode( __( '顧客情報印刷', 'ktpwp' ) );
			if ( false === $customer_print_title ) {
				$customer_print_title = '""';
			}

			// Simplified JavaScript - matching Update_Table approach
			$print = <<<END
        <script>
            // var isPreviewOpen = false; // プレビュー機能は廃止

            function printContent() {
                var printContent = $customer_preview_html;
                var printHTML = '<!DOCTYPE html>';
                printHTML += '<html lang="' + (document.documentElement.lang || 'ja') + '">';
                printHTML += '<head>';
                printHTML += '<meta charset="UTF-8">';
                printHTML += '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                printHTML += '<title>' + $customer_print_title + '</title>';
                printHTML += '<style>';
                printHTML += '* { margin: 0; padding: 0; box-sizing: border-box; }';
                printHTML += 'body { font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; padding: 20px; }';
                printHTML += '@page { size: A4; margin: 15mm; }';
                printHTML += '@media print { body { margin: 0; padding: 0; background: white; } }';
                printHTML += '@media print { button, .no-print { display: none !important; } }';
                printHTML += '</style>';
                printHTML += '</head>';
                printHTML += '<body>';
                printHTML += printContent;
                printHTML += '</body>';
                printHTML += '</html>';

                // 他タブと同様に隠しiframeで印刷する（ポップアップの位置ズレを回避）
                var iframe = document.createElement('iframe');
                iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
                document.body.appendChild(iframe);

                var cleanupDone = false;
                function cleanup() {
                    if (cleanupDone) return;
                    cleanupDone = true;
                    setTimeout(function() {
                        try { document.body.removeChild(iframe); } catch (_) {}
                    }, 300);
                }

                var printed = false;
                function triggerPrint() {
                    if (printed) return;
                    printed = true;
                    try {
                        var frameWin = iframe.contentWindow || iframe;
                        frameWin.focus();
                        frameWin.onafterprint = cleanup;
                        setTimeout(function() {
                            try { frameWin.print(); } catch (e) { cleanup(); }
                        }, 50);
                    } catch (e) {
                        cleanup();
                    }
                }

                try {
                    var frameDoc = iframe.contentDocument || iframe.contentWindow.document;
                    frameDoc.open();
                    frameDoc.write(printHTML);
                    frameDoc.close();
                    setTimeout(triggerPrint, 50);
                } catch (e) {
                    console.error('[顧客印刷] iframe印刷処理に失敗:', e);
                    cleanup();
                }

                console.log('[顧客印刷] iframeを作成し印刷ダイアログを開きます。');

                // プレビュー機能は廃止
                // if (isPreviewOpen) {
                //     togglePreview();
                // }
            }

            function printClientAddressLabel() {
                function t(msg) { return (typeof ktpwpTranslate === 'function') ? ktpwpTranslate(msg) : msg; }
                function esc(s) {
                    if (s == null || s === '') { return ''; }
                    return String(s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }
                function field(name) {
                    var box = document.querySelector('.data_detail_box');
                    var el = box ? box.querySelector('[name="' + name + '"]') : null;
                    if (!el) { el = document.querySelector('[name="' + name + '"]'); }
                    if (!el || el.value === undefined || el.value === null) { return ''; }
                    return String(el.value).trim();
                }
                function formatPostal(pc) {
                    var d = String(pc).replace(/\D/g, '');
                    if (d.length === 7) { return '\u3012' + d.slice(0, 3) + '-' + d.slice(3); }
                    if (d.length > 0) { return '\u3012' + d; }
                    return '';
                }
                var postal = formatPostal(field('postal_code'));
                var pref = field('prefecture');
                var city = field('city');
                var street = field('address');
                var building = field('building');
                var company = field('company_name');
                var person = field('user_name');
                var line2 = (pref + city).trim();
                var line3 = (street + building).trim();
                var honor = (/^ja/i.test(document.documentElement.lang || '') || (window.ktpwpI18n && /^ja/i.test(String(window.ktpwpI18n.locale || '')))) ? ' \u69d8' : '';
                if (!postal && !line2 && !line3 && !company && !person) {
                    alert(t('宛先情報がありません。顧客詳細を表示して住所などを入力してください。'));
                    return;
                }
                var inner = '';
                if (postal) { inner += '<div>' + esc(postal) + '</div>'; }
                if (line2) { inner += '<div>' + esc(line2) + '</div>'; }
                if (line3) { inner += '<div>' + esc(line3) + '</div>'; }
                if (company) { inner += '<div style="font-weight:bold;margin-top:0.35em;">' + esc(company) + '</div>'; }
                if (person) { inner += '<div style="margin-top:0.25em;">' + esc(person) + esc(honor) + '</div>'; }
                if (!window.KtpAtenaPrint || typeof window.KtpAtenaPrint.openPreview !== 'function') {
                    alert(t('宛名印刷の準備ができていません。ページを再読み込みしてください。'));
                    return;
                }
                var recordId = field('client_id') || field('data_id');
                if (!recordId) {
                    var params = new URLSearchParams(window.location.search);
                    recordId = params.get('data_id') || '0';
                }
                window.KtpAtenaPrint.openPreview({
                    entityType: 'client',
                    recordId: recordId,
                    labelInnerHtml: inner,
                    title: t('宛名印刷'),
                    maxMemoLines: 18
                });
            }

            // プレビュー機能（廃止）
            // function togglePreview() {
            //     var previewWindow = document.getElementById('previewWindow');
            //     var previewButton = document.getElementById('previewButton');

            //     if (isPreviewOpen) {
            //         previewWindow.style.display = 'none';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="プレビュー" style="font-size: 18px; color: #333;">preview</span>';
            //         previewButton.style.background = '#fff';
            //         previewButton.style.borderColor = '#ddd';
            //         isPreviewOpen = false;
            //     } else {
            //         var printContent = $customer_preview_html;

            //         if (!previewWindow) {
            //             previewWindow = document.createElement('div');
            //             previewWindow.id = 'previewWindow';
            //             previewWindow.style.cssText = 'display:none;position:relative;z-index:100;background:#fff;padding:25px;border:2px solid #ddd;border-radius:8px;margin:15px 0;box-shadow:0 4px 12px rgba(0,0,0,0.1);';

            //             var controllerDiv = document.querySelector('.controller');
            //             if (controllerDiv) {
            //                 controllerDiv.parentNode.insertBefore(previewWindow, controllerDiv.nextSibling);
            //         } else {
            //                 document.querySelector('.box').appendChild(previewWindow);
            //             }
            //         }

            //         // プレビューウィンドウに閉じるボタンを追加
            //         var closeButton = '<div style="text-align: right; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;"><button type="button" onclick="togglePreview()" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #333; padding: 5px; line-height: 1; font-weight: bold;">×</button></div>';
            //         previewWindow.innerHTML = closeButton + printContent;
            //         previewWindow.style.display = 'block';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="閉じる" style="font-size: 18px; color: #d32f2f;">close</span>';
            //         previewButton.style.background = '#ffebee';
            //         previewButton.style.borderColor = '#d32f2f';
            //         isPreviewOpen = true;
            //     }
            // }
        </script>
        END;

			// コンテンツを返す
			// controller, workflow（受注書作成ボタン）を$print直後に追加
			// controller_html, workflow_htmlが重複しないようにcontroller_htmlは1回のみ出力
			// プレビューウィンドウはJavaScriptで動的に作成されるため、HTMLに直接書く必要はなくなった

			// 必要な変数の初期化確認
			if ( ! isset( $search_results_list ) ) {
				$search_results_list = '';
			}
			if ( ! isset( $data_title ) ) {
				$data_title = '';
			}
			if ( ! isset( $data_forms ) ) {
				$data_forms = '';
			}
			if ( ! isset( $div_end ) ) {
				$div_end = '';
			}
			// 検索モードでも顧客リストを表示する
			$content = $print . $session_message . $controller_html . $cross_search_panel . $data_list . $data_title . $data_forms . $search_results_list . $div_end;
			return $content;
		}

		/**
		 * ページネーションを生成するメソッド
		 *
		 * @param int      $current_page 現在のページ
		 * @param int      $total_pages 総ページ数
		 * @param int      $query_limit 1ページあたりの件数
		 * @param string   $name テーブル名
		 * @param string   $flg フラグ
		 * @param string   $base_page_url ベースURL
		 * @param int      $total_rows 総データ数
		 * @param string   $view_mode 表示モード
		 * @param int|null $client_id 顧客ID（注文履歴用）
		 * @return string ページネーションHTML
		 */
		private function render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $view_mode = '', $client_id = null ) {
			// 0データの場合でもページネーションを表示（要件対応）
			// データが0件の場合はtotal_pagesが0になるため、最低1ページとして扱う
			if ( $total_pages == 0 ) {
				$total_pages = 1;
				$current_page = 1;
			}

			$pagination_html = '<div class="pagination" style="text-align: center; margin: 20px 0; padding: 20px 0;">';

			// 1行目：ページ情報表示
			$pagination_html .= '<div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">';
			$pagination_html .= esc_html( $current_page ) . ' / ' . esc_html( $total_pages ) . ' ページ（全 ' . esc_html( $total_rows ) . ' 件）';
			$pagination_html .= '</div>';

			// 2行目：ページネーションボタン
			$pagination_html .= '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: center; width: 100%;">';

			// ページネーションボタンのスタイル（正円ボタン）
			$button_style = 'display: inline-block; width: 36px; height: 36px; padding: 0; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 50%; color: #333; background: #fff; transition: all 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 34px; text-align: center; vertical-align: middle; font-size: 14px;';
			$current_style = 'background: #1976d2; color: white; border-color: #1976d2; font-weight: bold; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
			$hover_effect = 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"';

			// 前のページボタン
			if ( $current_page > 1 && $total_pages > 1 ) {
				$prev_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page - 1,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$prev_args['view_mode'] = 'order_history';
					$prev_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$prev_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$prev_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$prev_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$prev_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$prev_url = esc_url( add_query_arg( $prev_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$prev_url}\" style=\"{$button_style}\" {$hover_effect}>‹</a>";
			}

			// ページ番号ボタン（省略表示対応）
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			// 最初のページを表示（データが0件でも1ページ目は表示）
			if ( $start_page > 1 && $total_pages > 1 ) {
				$first_args = array(
					'tab_name' => $name,
					'page_start' => 0,
					'page_stage' => 2,
					'flg' => $flg,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$first_args['view_mode'] = 'order_history';
					$first_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$first_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$first_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$first_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$first_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$first_url = esc_url( add_query_arg( $first_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$first_url}\" style=\"{$button_style}\" {$hover_effect}>1</a>";

				if ( $start_page > 2 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}
			}

			// 中央のページ番号
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				$page_args = array(
					'tab_name' => $name,
					'page_start' => ( $i - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$page_args['view_mode'] = 'order_history';
					$page_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$page_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$page_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$page_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$page_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$page_url = esc_url( add_query_arg( $page_args, $base_page_url ) );

				if ( $i == $current_page ) {
					$pagination_html .= "<span style=\"{$button_style} {$current_style}\">{$i}</span>";
				} else {
					$pagination_html .= "<a href=\"{$page_url}\" style=\"{$button_style}\" {$hover_effect}>{$i}</a>";
				}
			}

			// 最後のページを表示
			if ( $end_page < $total_pages && $total_pages > 1 ) {
				if ( $end_page < $total_pages - 1 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}

				$last_args = array(
					'tab_name' => $name,
					'page_start' => ( $total_pages - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$last_args['view_mode'] = 'order_history';
					$last_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$last_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$last_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$last_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$last_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$last_url = esc_url( add_query_arg( $last_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_args = array(
					'tab_name' => $name,
					'page_start' => $current_page * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$next_args['view_mode'] = 'order_history';
					$next_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$next_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$next_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$next_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$next_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

		/**
		 * 注文履歴専用のページネーションデザインをレンダリング
		 *
		 * @param int    $current_page 現在のページ
		 * @param int    $total_pages 総ページ数
		 * @param int    $query_limit 1ページあたりの表示件数
		 * @param string $name テーブル名
		 * @param string $flg フラグ
		 * @param string $base_page_url ベースURL
		 * @param int    $total_rows 総データ数
		 * @param int    $client_id 顧客ID
		 * @return string ページネーションHTML
		 */
		private function render_order_history_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $client_id ) {
			// 0データの場合でもページネーションを表示（要件対応）
			// データが0件の場合はtotal_pagesが0になるため、最低1ページとして扱う
			if ( $total_pages == 0 ) {
				$total_pages = 1;
				$current_page = 1;
			}

			$pagination_html = '<div class="pagination" style="text-align: center; margin: 20px 0; padding: 20px 0;">';

			// 1行目：ページ情報表示
			$pagination_html .= '<div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">';
			$pagination_html .= esc_html( $current_page ) . ' / ' . esc_html( $total_pages ) . ' ページ（全 ' . esc_html( $total_rows ) . ' 件）';
			$pagination_html .= '</div>';

			// 2行目：ページネーションボタン
			$pagination_html .= '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: center; width: 100%;">';

			// ページネーションボタンのスタイル（正円ボタン）
			$button_style = 'display: inline-block; width: 36px; height: 36px; padding: 0; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 50%; color: #333; background: #fff; transition: all 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 34px; text-align: center; vertical-align: middle; font-size: 14px;';
			$current_style = 'background: #1976d2; color: white; border-color: #1976d2; font-weight: bold; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
			$hover_effect = 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"';

			// 前のページボタン
			if ( $current_page > 1 && $total_pages > 1 ) {
				$prev_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page - 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$prev_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$prev_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$prev_url = esc_url( add_query_arg( $prev_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$prev_url}\" style=\"{$button_style}\" {$hover_effect}>‹</a>";
			}

			// ページ番号ボタン（省略表示対応）
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			// 最初のページを表示（データが0件でも1ページ目は表示）
			if ( $start_page > 1 && $total_pages > 1 ) {
				$first_args = array(
					'tab_name' => $name,
					'page_start' => 0,
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$first_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$first_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$first_url = esc_url( add_query_arg( $first_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$first_url}\" style=\"{$button_style}\" {$hover_effect}>1</a>";

				if ( $start_page > 2 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}
			}

			// 中央のページ番号
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				$page_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $i,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$page_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$page_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$page_url = esc_url( add_query_arg( $page_args, $base_page_url ) );

				if ( $i == $current_page ) {
					$pagination_html .= "<span style=\"{$button_style} {$current_style}\">{$i}</span>";
				} else {
					$pagination_html .= "<a href=\"{$page_url}\" style=\"{$button_style}\" {$hover_effect}>{$i}</a>";
				}
			}

			// 最後のページを表示
			if ( $end_page < $total_pages && $total_pages > 1 ) {
				if ( $end_page < $total_pages - 1 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}

				$last_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $total_pages,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$last_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$last_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$last_url = esc_url( add_query_arg( $last_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page + 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$next_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$next_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

		/**
		 * 顧客情報のプレビュー用HTMLを生成するメソッド
		 *
		 * @param array $customer_data 顧客データ
		 * @return string 顧客情報のプレビューHTML
		 */
		private function generateCustomerPreviewHTML( $customer_data ) {
			$company_name = $customer_data['company_name'] ?? '';
			$name = $customer_data['name'] ?? '';
			$email = $customer_data['email'] ?? '';
			$url = $customer_data['url'] ?? '';
			$representative_name = $customer_data['representative_name'] ?? '';
			$phone = $customer_data['phone'] ?? '';
			$postal_code = $customer_data['postal_code'] ?? '';
			$prefecture = $customer_data['prefecture'] ?? '';
			$city = $customer_data['city'] ?? '';
			$address = $customer_data['address'] ?? '';
			$building = $customer_data['building'] ?? '';
			$closing_day = $customer_data['closing_day'] ?? '';
			$payment_month = $customer_data['payment_month'] ?? '';
			$payment_day = $customer_data['payment_day'] ?? '';
			$payment_method = $customer_data['payment_method'] ?? '';
			$tax_category = $customer_data['tax_category'] ?? '';
			$category = $customer_data['category'] ?? '';
			$client_status = $customer_data['client_status'] ?? '';
			$memo = $customer_data['memo'] ?? '';

			// 住所の組み立て
			$full_address = '';
			if ( ! empty( $prefecture ) ) {
				$full_address .= $prefecture;
			}
			if ( ! empty( $city ) ) {
				$full_address .= $city;
			}
			if ( ! empty( $address ) ) {
				$full_address .= $address;
			}
			if ( ! empty( $building ) ) {
				$full_address .= $building;
			}

			return '
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                <h1 style="color: #333; margin: 0; font-size: 24px;">' . esc_html__( '顧客情報', 'ktpwp' ) . '</h1>
            </div>
            
            <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa; width: 25%;">' . esc_html__( '会社名', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $company_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '担当者名', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( 'メールアドレス', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $email ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">URL</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $url ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '代表者名', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $representative_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '電話番号', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $phone ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '郵便番号', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $postal_code ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '住所', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $full_address ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '締め日', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $closing_day ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '支払月', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_month ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '支払日', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_day ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '支払方法', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_method ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '税区分', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $tax_category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '対象｜対象外', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $client_status ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( 'メモ', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px; white-space: pre-wrap;">' . esc_html( $memo ) . '</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
                <p>' . esc_html__( '印刷日時:', 'ktpwp' ) . ' ' . esc_html( wp_date( __( 'Y年m月d日 H:i', 'ktpwp' ) ) ) . '</p>
            </div>
        </div>';
		}
	}
	// class_exists
}
