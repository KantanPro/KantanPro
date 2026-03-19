<?php
/**
 * List class for KTPWP plugin
 *
 * Handles order list display, filtering, and management.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_List_Class' ) ) {

	/**
	 * List class for managing order lists
	 *
	 * @since 1.0.0
	 */
	class KTPWP_List_Class {

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// Constructor initialization
		}

		/**
		 * Display list tab view
		 *
		 * @since 1.0.0
		 * @param string $tab_name Tab name
		 * @return void
		 */
		public function List_Tab_View( $tab_name ) {
			// Check user capabilities
			// if ( ! current_user_can( 'manage_options' ) ) {
			// wp_die( __( 'You do not have sufficient permissions to access this page.', 'ktpwp' ) );
			// }

			if ( empty( $tab_name ) ) {
				error_log( 'KTPWP: Empty tab_name provided to List_Tab_View method' );
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$content = '';

			// フリーワード検索用GETパラメータ
			$list_search = isset( $_GET['list_search'] ) ? sanitize_text_field( wp_unslash( $_GET['list_search'] ) ) : '';

			// Controller container display at top（左: 検索、右: 印刷）
			$content .= '<div class="controller" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">';

			// フリーワード検索（左寄せ）
			$content .= '<div class="ktp-list-search-wrap" style="display:flex;align-items:center;gap:6px;">';
			$content .= '<form method="get" action="" class="ktp-list-search-form" style="display:flex;align-items:center;gap:6px;">';
			// 仕事リストタブを維持
			$content .= '<input type="hidden" name="tab_name" value="' . esc_attr( $tab_name ) . '">';
			// 既存クエリを保持（tab_nameは上で固定したので除外）
			$keep_params = array( 'progress', 'page_start', 'page_stage', 'flg' );
			foreach ( $keep_params as $key ) {
				if ( isset( $_GET[ $key ] ) && (string) $_GET[ $key ] !== '' ) {
					$content .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( wp_unslash( $_GET[ $key ] ) ) . '">';
				}
			}
			$search_placeholder = ( $list_search !== '' ) ? $list_search : __( 'フリーワード', 'ktpwp' );
			$content .= '<input type="search" id="ktp-list-search-input" name="list_search" value="" placeholder="' . esc_attr( $search_placeholder ) . '" aria-label="' . esc_attr__( 'フリーワード', 'ktpwp' ) . '" class="ktp-list-search-input" style="min-width:160px;padding:6px 8px;border:1px solid #ddd;border-radius:4px;">';
			$content .= '<button type="submit" class="ktp-list-search-btn" title="' . esc_attr__( '検索', 'ktpwp' ) . '" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;cursor:pointer;">🔍</button>';
			$content .= '</form>';
			$content .= '</div>';

			// Print button（現在表示されている内容を印刷ダイアログで表示し、PDF保存・印刷可能）
			$content .= '<button type="button" title="' . esc_attr__( '印刷する', 'ktpwp' ) . '" onclick="typeof ktpListPrintOpen === \'function\' && ktpListPrintOpen();" style="padding: 6px 10px; font-size: 12px;">';
			$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( '印刷', 'ktpwp' ) . '">print</span>';
			$content .= '</button>';

			// Progress status buttons
			$progress_labels = array(
				1 => __( '受付中', 'ktpwp' ),
				2 => __( '見積中', 'ktpwp' ),
				3 => __( '受注', 'ktpwp' ),
				4 => __( '完了', 'ktpwp' ),
				5 => __( '請求済', 'ktpwp' ),
				6 => __( '入金済', 'ktpwp' ),
				7 => __( 'ボツ', 'ktpwp' ),
			);

			$selected_progress = isset( $_GET['progress'] ) ? absint( $_GET['progress'] ) : 1;
			// 印刷時だけページネーションを無視して全件取得する
			$print_all = isset( $_GET['print_all'] ) && (string) $_GET['print_all'] !== '' && (string) $_GET['print_all'] !== '0';

			// Get count for each progress status with prepared statements
			$progress_counts = array();
			$progress_warnings = array(); // 納期警告カウント用

			foreach ( $progress_labels as $num => $label ) {
				$count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$table_name}` WHERE progress = %d",
                        $num
                    )
                );
				$progress_counts[ $num ] = (int) $count;

				// 受注（progress = 3）の場合、納期警告の件数を取得
				if ( $num == 3 ) {
					// 一般設定から警告日数を取得
					$warning_days = 3; // デフォルト値
					if ( class_exists( 'KTPWP_Settings' ) ) {
						$warning_days = KTPWP_Settings::get_delivery_warning_days();
					}

					$warning_count = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$table_name}` WHERE progress = %d AND expected_delivery_date IS NOT NULL AND expected_delivery_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)",
                            $num,
                            $warning_days
                        )
                    );
					$progress_warnings[ $num ] = (int) $warning_count;
				} else {
					$progress_warnings[ $num ] = 0;
				}
			}

			// ▼▼▼ 完了タブの請求書締日警告件数をカウント ▼▼▼
			$invoice_warning_count = 0;
			if ( isset( $progress_labels[4] ) ) {
				// プレースホルダーが不要なクエリなので、直接実行
				$query_invoice_warning = "SELECT o.id, o.client_id, o.completion_date, c.closing_day FROM {$table_name} o LEFT JOIN {$wpdb->prefix}ktp_client c ON o.client_id = c.id WHERE o.progress = 4 AND o.completion_date IS NOT NULL AND c.closing_day IS NOT NULL AND c.closing_day != 'なし'";
				$orders_for_invoice_warning = $wpdb->get_results( $query_invoice_warning );
				$today = new DateTime();
				$today->setTime( 0, 0, 0 );
				foreach ( $orders_for_invoice_warning as $order ) {
					$completion_date = $order->completion_date;
					if ( empty( $completion_date ) ) {
						continue;
					}
					// 日付フォーマットチェック
					$dt = DateTime::createFromFormat( 'Y-m-d', $completion_date );
					$errors = DateTime::getLastErrors();
					if ( $dt === false || ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: 不正なcompletion_date検出: ' . print_r( $completion_date, true ) );
						}
						continue;
					}
					$completion_dt = $dt;
					$year = (int) $completion_dt->format( 'Y' );
					$month = (int) $completion_dt->format( 'm' );
					if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 ) {
						continue;
					}
					$closing_day = $order->closing_day;
					if ( $closing_day === '末日' ) {
						$closing_dt = new DateTime( "$year-$month-01" );
						$closing_dt->modify( 'last day of this month' );
					} else {
						$closing_day_num = intval( $closing_day );
						$closing_dt = new DateTime( "$year-$month-" . str_pad( $closing_day_num, 2, '0', STR_PAD_LEFT ) );
						$last_day = (int) $closing_dt->format( 't' );
						if ( $closing_day_num > $last_day ) {
							$closing_dt->modify( 'last day of this month' );
						}
					}
					$closing_dt->setTime( 0, 0, 0 );
					$diff = $today->diff( $closing_dt );
					$days_left = $diff->invert ? -$diff->days : $diff->days;
					// 請求日当日以降の場合に警告マークを表示
					if ( $days_left <= 0 ) {
						$invoice_warning_count++;
					}
				}
			}

			// ▼▼▼ 請求済タブの入金予定日（支払期日）超過件数をカウント（前入金済は対象外） ▼▼▼
			$payment_warning_count = 0;
			if ( isset( $progress_labels[5] ) ) {
				$client_table = $wpdb->prefix . 'ktp_client';
				$query_payment_warning = "SELECT o.id, o.client_id, o.completion_date, o.payment_timing AS order_payment_timing, c.closing_day, c.payment_month, c.payment_day, c.payment_timing AS client_payment_timing FROM {$table_name} o LEFT JOIN {$client_table} c ON o.client_id = c.id WHERE o.progress = 5 AND o.completion_date IS NOT NULL AND c.payment_month IS NOT NULL AND c.payment_day IS NOT NULL";
				$orders_for_payment_warning = $wpdb->get_results( $query_payment_warning );

				$today = new DateTime();
				$today->setTime( 0, 0, 0 );

				foreach ( $orders_for_payment_warning as $order ) {
					// 前入金済み（前払い・WC受注・EC受注）は未入金警告対象外
					if ( class_exists( 'KTPWP_Payment_Timing' ) ) {
						$order_obj  = (object) array(
							'payment_timing' => isset( $order->order_payment_timing ) ? $order->order_payment_timing : null,
							'client_id'      => isset( $order->client_id ) ? $order->client_id : 0,
						);
						$client_obj = (object) array(
							'payment_timing' => isset( $order->client_payment_timing ) ? $order->client_payment_timing : null,
						);
						if ( KTPWP_Payment_Timing::is_prepay( $order_obj, $client_obj ) ) {
							continue;
						}
					}

					$completion_date = isset( $order->completion_date ) ? (string) $order->completion_date : '';
					if ( $completion_date === '' ) {
						continue;
					}

					$completion_dt = DateTime::createFromFormat( 'Y-m-d', $completion_date );
					$errors = DateTime::getLastErrors();
					if ( $completion_dt === false || ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
						continue;
					}
					$completion_dt->setTime( 0, 0, 0 );

					$closing_day   = isset( $order->closing_day ) ? (string) $order->closing_day : '末日';
					$payment_month = isset( $order->payment_month ) ? (string) $order->payment_month : '翌月';
					$payment_day   = isset( $order->payment_day ) ? (string) $order->payment_day : '末日';

					$year  = (int) $completion_dt->format( 'Y' );
					$month = (int) $completion_dt->format( 'm' );
					if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 ) {
						continue;
					}

					// 完了日が属する請求月（締め日基準）を決定
					$billing_year  = $year;
					$billing_month = $month;
					if ( $closing_day !== '' && $closing_day !== 'なし' ) {
						if ( $closing_day === '末日' ) {
							$closing_dt = new DateTime( "$year-$month-01" );
							$closing_dt->modify( 'last day of this month' );
						} else {
							$closing_day_num = (int) $closing_day;
							$closing_dt      = new DateTime( "$year-$month-" . str_pad( (string) $closing_day_num, 2, '0', STR_PAD_LEFT ) );
							$last_day        = (int) $closing_dt->format( 't' );
							if ( $closing_day_num > $last_day ) {
								$closing_dt->modify( 'last day of this month' );
							}
						}
						$closing_dt->setTime( 0, 0, 0 );

						// 完了日が締め日を過ぎている場合は翌月締め扱い
						if ( $completion_dt > $closing_dt ) {
							$billing_month++;
							if ( $billing_month > 12 ) {
								$billing_month = 1;
								$billing_year++;
							}
						}
					}

					// 支払月を計算（今月/翌月/翌々月）
					$payment_year  = $billing_year;
					$payment_m_num = $billing_month;
					switch ( $payment_month ) {
						case '今月':
							$payment_m_num = $billing_month;
							break;
						case '翌々月':
							$payment_m_num = $billing_month + 2;
							if ( $payment_m_num > 12 ) {
								$payment_m_num -= 12;
								$payment_year++;
							}
							break;
						case '翌月':
						default:
							$payment_m_num = $billing_month + 1;
							if ( $payment_m_num > 12 ) {
								$payment_m_num = 1;
								$payment_year++;
							}
							break;
					}

					// 支払日を計算（末日/即日/指定日）
					if ( $payment_day === '即日' ) {
						$due_dt = clone $completion_dt;
					} else {
						$due_dt = new DateTime();
						$due_dt->setDate( $payment_year, $payment_m_num, 1 );
						if ( $payment_day === '末日' ) {
							$due_dt->modify( 'last day of this month' );
						} else {
							$payment_day_num = (int) str_replace( '日', '', $payment_day );
							$due_dt->setDate( $payment_year, $payment_m_num, max( 1, $payment_day_num ) );
							$last_day = (int) $due_dt->format( 't' );
							if ( $payment_day_num > $last_day ) {
								$due_dt->modify( 'last day of this month' );
							}
						}
						$due_dt->setTime( 0, 0, 0 );
					}

					// 入金予定日（支払期日）を過ぎている場合にカウント
					if ( $today > $due_dt ) {
						$payment_warning_count++;
					}
				}
			}

			$content .= '</div>'; // .controller end

			// 印刷対象エリア開始（現在表示されている内容を印刷するためのラッパー）
			$content .= '<div id="ktp_list_print_area">';

			// 検索結果（進捗ワークフローブロックの上に表示）
			if ( $list_search !== '' ) {
				$content .= $this->render_list_search_results( $list_search, $wpdb, remove_query_arg( 'list_search' ) );
			}

			// Workflow area to display progress buttons in full width
			$content .= '<div class="workflow" style="width:100%;margin:0px 0 0px 0;">';
			$content .= '<div class="progress-filter" style="display:flex;gap:8px;width:100%;justify-content:center;">';

			// 進捗アイコンの定義
			$progress_icons = array(
				1 => 'receipt',      // 受付中
				2 => 'calculate',    // 見積中
				3 => 'build',        // 受注
				4 => 'check_circle', // 完了
				5 => 'payment',      // 請求済
				6 => 'account_balance_wallet', // 入金済
				7 => 'cancel',        // ボツ
			);

			foreach ( $progress_labels as $num => $label ) {
				// ボツ（progress = 7）はワークフローに表示しない
				if ( $num == 7 ) {
					continue;
				}

				$active = ( $selected_progress === $num ) ? 'style="font-weight:bold;background:#1976d2;color:#fff;"' : '';
				$btn_label = esc_html( $label ) . ' (' . $progress_counts[ $num ] . ')';
				$icon = isset( $progress_icons[ $num ] ) ? $progress_icons[ $num ] : 'circle';

				// 進捗タブごとの赤い(!)マーク件数（CSSで右上オーバーレイ・表示制御）
				$warning_count = 0;
				$warning_title = '';
				if ( $num == 3 ) {
					$warning_count = isset( $progress_warnings[3] ) ? $progress_warnings[3] : 0;
					$warning_title = $warning_count > 0 ? sprintf( __( '納期が迫っている、または過ぎている案件が%d件あります', 'ktpwp' ), $warning_count ) : '';
				} elseif ( $num == 4 ) {
					$warning_count = $invoice_warning_count;
					$warning_title = $warning_count > 0 ? sprintf( __( '請求日を過ぎている案件が%d件あります', 'ktpwp' ), $warning_count ) : '';
				} elseif ( $num == 5 ) {
					$warning_count = $payment_warning_count;
					$warning_title = $warning_count > 0 ? sprintf( __( '入金予定日を過ぎている案件が%d件あります', 'ktpwp' ), $warning_count ) : '';
				}
				// 受注(3)・完了(4)・請求済(5)は常にバッジ要素を出力。赤い丸の中に通常の数字（1, 100, 1000等）
				$warning_badge = '';
				if ( $num == 3 || $num == 4 || $num == 5 ) {
					$badge_text = $warning_count > 0 ? (string) (int) $warning_count : '';
					$warning_badge = '<span class="ktp-progress-warning-badge" data-progress="' . esc_attr( $num ) . '" data-count="' . (int) $warning_count . '" title="' . esc_attr( $warning_title ) . '">' . esc_html( $badge_text ) . '</span>';
				}

				// 進捗ボタンはprogressを必ず付与
				$progress_btn_url = add_query_arg(
                    array(
						'tab_name' => $tab_name,
						'progress' => $num,
                    )
                );
				$content .= '<a href="' . $progress_btn_url . '" class="progress-btn" data-progress="' . $num . '" data-icon="' . $icon . '" ' . $active . '>';
				
				// SVGアイコンを使用
				if (class_exists('KTPWP_SVG_Icons')) {
					$content .= KTPWP_SVG_Icons::get_icon($icon, array('class' => 'progress-btn-icon ktp-svg-icon'));
				} else {
					// フォールバック: Material Symbols
					$content .= '<span class="progress-btn-icon material-symbols-outlined">' . $icon . '</span>';
				}
				
				$content .= '<span class="progress-btn-text">' . $btn_label . '</span>';
				$content .= $warning_badge;
				$content .= '</a>';
			}
			$content .= '</div>';
			$content .= '</div>';

			// 受注書リスト表示
			// $content .= '<h3>■ 受注書リスト</h3>';

			// ページネーション設定
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$query_limit = KTPWP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}
			$page_stage = isset( $_GET['page_stage'] ) ? $_GET['page_stage'] : '';
			$page_start = isset( $_GET['page_start'] ) ? intval( $_GET['page_start'] ) : 0;
			$flg = isset( $_GET['flg'] ) ? $_GET['flg'] : '';
			$selected_progress = isset( $_GET['progress'] ) ? intval( $_GET['progress'] ) : 1;
			if ( $page_stage == '' ) {
				$page_start = 0;
			}
			// 総件数取得
			$total_query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE progress = %d", $selected_progress );
			$total_rows = $wpdb->get_var( $total_query );
			$total_pages = ceil( $total_rows / $query_limit );
			$current_page = floor( $page_start / $query_limit ) + 1;

			// データ取得（進捗が「受注」の場合は納期順でソート）
			if ( $print_all ) {
				// ページネーション無視：LIMIT を付けず全件取得
				if ( $selected_progress == 3 ) {
					// 受注の場合は納期が迫っている順でソート
					$query = $wpdb->prepare(
						"SELECT *,
                    CASE 
                        WHEN expected_delivery_date IS NULL THEN 999999
                        WHEN expected_delivery_date <= CURDATE() THEN 0
                        ELSE DATEDIFF(expected_delivery_date, CURDATE())
                    END as days_until_delivery
                FROM {$table_name}
                WHERE progress = %d
                ORDER BY days_until_delivery ASC, time DESC",
						$selected_progress
					);
				} else {
					// その他の進捗は従来通り時間順でソート
					$query = $wpdb->prepare(
						"SELECT * FROM {$table_name}
                WHERE progress = %d
                ORDER BY time DESC",
						$selected_progress
					);
				}
			} else {
				// ページネーションあり（従来通り LIMIT/OFFSET）
				if ( $selected_progress == 3 ) {
					// 受注の場合は納期が迫っている順でソート
					$query = $wpdb->prepare(
                        "SELECT *, 
                    CASE 
                        WHEN expected_delivery_date IS NULL THEN 999999
                        WHEN expected_delivery_date <= CURDATE() THEN 0
                        ELSE DATEDIFF(expected_delivery_date, CURDATE())
                    END as days_until_delivery
                FROM {$table_name} 
                WHERE progress = %d 
                ORDER BY days_until_delivery ASC, time DESC 
                LIMIT %d, %d",
						$selected_progress,
						$page_start,
						$query_limit
					);
				} else {
					// その他の進捗は従来通り時間順でソート
					$query = $wpdb->prepare(
                        "SELECT * FROM {$table_name} 
                WHERE progress = %d 
                ORDER BY time DESC 
                LIMIT %d, %d",
						$selected_progress,
						$page_start,
						$query_limit
					);
				}
			}

			$order_list = $wpdb->get_results( $query );

			// --- ここからラッパー追加 ---
			$my_company = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company = KTPWP_Settings::get_company_info();
			}
			if ( empty( $my_company ) ) {
				$my_company = get_bloginfo( 'name' );
			}
			$my_company = wp_strip_all_tags( (string) $my_company );
			// メールアドレス表記はフッターでは不要なため削除
			$my_company = preg_replace( '/\S+@\S+\.\S+/', '', $my_company );
			$my_company = preg_replace( '/\s+/', ' ', trim( $my_company ) );

			$content .= '<div class="ktp_work_list_box">';
			$content .= '<div id="ktp_list_my_company_name" style="display:none;">' . esc_html( $my_company ) . '</div>';

			// 受注の場合はソート順を説明
			if ( $selected_progress == 3 ) {
				$content .= '<div style="background: #e3f2fd; border-left: 4px solid #1976d2; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; color: #1565c0;">';
				$content .= '<strong>📅 ソート順:</strong> 納期が迫っている順 → 受注日時順（新しい順）で表示されています。';
				$content .= '</div>';
			}

			if ( $order_list ) {
				// 進捗ラベル
				$progress_labels = array(
					1 => '受付中',
					2 => '見積中',
					3 => '受注',
					4 => '完了',
					5 => '請求済',
					6 => '入金済',
					7 => 'ボツ',
				);
				$content .= '<ul>';
				foreach ( $order_list as $order ) {
					$order_id = esc_html( $order->id );
					$customer_name = esc_html( $order->customer_name );
					$user_name = esc_html( $order->user_name );
					$project_name = isset( $order->project_name ) ? esc_html( $order->project_name ) : '';

					// 会社名と担当者名のフォールバック処理
					if ( empty( $customer_name ) || empty( $user_name ) ) {
						global $wpdb;
						$client_table = $wpdb->prefix . 'ktp_client';
						
						// 顧客IDがある場合は顧客テーブルから情報を取得
						if ( ! empty( $order->client_id ) ) {
							$client_info = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT company_name, name FROM `{$client_table}` WHERE id = %d",
									$order->client_id
								)
							);
							if ( $client_info ) {
								if ( empty( $customer_name ) ) {
									$customer_name = esc_html( $client_info->company_name );
								}
								if ( empty( $user_name ) ) {
									$user_name = esc_html( $client_info->name );
								}
							}
						}
					}

					// 顧客リンク用の顧客ID（仕事リスト行で会社名を顧客タブへリンクするために使用）
					$client_id = isset( $order->client_id ) ? (int) $order->client_id : 0;

					// 納期フィールドの値を取得（希望納期は削除、納品予定日のみ）
					$expected_delivery_date = isset( $order->expected_delivery_date ) ? $order->expected_delivery_date : '';

					// 完了日フィールドの値を取得
					$completion_date = isset( $order->completion_date ) ? $order->completion_date : '';

					// 納期警告の判定（納期が迫っている + 納期過ぎも対象）
					$show_warning = false;
					$is_urgent = false; // 緊急案件フラグ
					$delivery_warning_title = ''; // 行のツールチップ用
					if ( ! empty( $expected_delivery_date ) && $selected_progress == 3 ) {
						// 一般設定から警告日数を取得
						$warning_days = 3; // デフォルト値
						if ( class_exists( 'KTPWP_Settings' ) ) {
							$warning_days = KTPWP_Settings::get_delivery_warning_days();
						}

						// 納期が迫っているか／過ぎているかチェック（不正な日付の場合はスキップ）
						$delivery_date = DateTime::createFromFormat( 'Y-m-d', $expected_delivery_date );
						if ( $delivery_date !== false ) {
							$delivery_date->setTime( 0, 0, 0 ); // 時間を00:00:00に設定
							$today = new DateTime();
							$today->setTime( 0, 0, 0 ); // 時間を00:00:00に設定

							$diff = $today->diff( $delivery_date );
							$days_left = $diff->invert ? -$diff->days : $diff->days;

							// 納期が迫っている（警告日数以内）または納期過ぎのときに警告表示
							$show_warning = $days_left <= $warning_days;
							$is_urgent = $days_left <= $warning_days;
							$delivery_warning_title = $days_left < 0
								? __( '納期が過ぎています', 'ktpwp' )
								: __( '納期が迫っています', 'ktpwp' );

							// デバッグ情報（開発時のみ）
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								$debug_msg = '納期警告判定: 今日=' . $today->format( 'Y-m-d' ) . ', 納期=' . $delivery_date->format( 'Y-m-d' ) . ', 残り日数=' . $days_left . ', 警告日数=' . $warning_days . ', 表示=' . ( $show_warning ? 'YES' : 'NO' );
								error_log( $debug_msg );
							}
						}
					}

					// ▼▼▼ 請求書締日警告の判定 ▼▼▼
					$show_invoice_warning = false;
					$invoice_warning_message = '';
					if ( $selected_progress == 4 ) { // 完了
						// 顧客IDから締め日を取得
						$client_id = isset( $order->client_id ) ? intval( $order->client_id ) : 0;
						if ( $client_id > 0 ) {
							$client_table = $wpdb->prefix . 'ktp_client';
							$client_info = $wpdb->get_row( $wpdb->prepare( "SELECT closing_day FROM {$client_table} WHERE id = %d", $client_id ) );
							if ( $client_info && $client_info->closing_day && $client_info->closing_day !== 'なし' ) {
								// 案件の完了日を取得
								$completion_date = isset( $order->completion_date ) ? trim( (string) $order->completion_date ) : '';
								if ( $completion_date !== '' ) {
									$completion_dt = DateTime::createFromFormat( 'Y-m-d', $completion_date );
									if ( $completion_dt === false ) {
										$completion_dt = null;
									}
									if ( $completion_dt ) {
										$year = (int) $completion_dt->format( 'Y' );
										$month = (int) $completion_dt->format( 'm' );
										// 不正な年・月の場合は締め日計算をスキップ（-1-11-05 等の例外を防ぐ）
										if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 ) {
											$completion_dt = null;
										}
									}
									if ( $completion_dt ) {
										$closing_day = $client_info->closing_day;
										if ( $closing_day === '末日' ) {
											$closing_dt = new DateTime( "$year-$month-01" );
											$closing_dt->modify( 'last day of this month' );
										} else {
											$closing_day_num = intval( $closing_day );
											$closing_dt = new DateTime( "$year-$month-" . str_pad( $closing_day_num, 2, '0', STR_PAD_LEFT ) );
											// 月末を超える場合は末日に補正
											$last_day = (int) $closing_dt->format( 't' );
											if ( $closing_day_num > $last_day ) {
												$closing_dt->modify( 'last day of this month' );
											}
										}
										// 今日から締め日までの日数
										$today = new DateTime();
										$today->setTime( 0, 0, 0 );
										$closing_dt->setTime( 0, 0, 0 );
										$diff = $today->diff( $closing_dt );
										$days_left = $diff->invert ? -$diff->days : $diff->days;
										// 請求日当日以降の場合に警告マークを表示
										if ( $days_left <= 0 ) {
											$show_invoice_warning = true;
										}
									}
								}
							}
						}
					}

					// ▼▼▼ 入金予定日（支払期日）超過の判定（前入金済は対象外） ▼▼▼
					$show_payment_warning = false;
					$payment_warning_title = '';
					if ( $selected_progress == 5 ) { // 請求済
						// 前入金済みは未入金警告対象外
						$is_prepay = class_exists( 'KTPWP_Payment_Timing' ) && KTPWP_Payment_Timing::is_prepay( $order, null );
						if ( ! $is_prepay ) {
						$client_id = isset( $order->client_id ) ? (int) $order->client_id : 0;
						$completion_date_raw = isset( $order->completion_date ) ? trim( (string) $order->completion_date ) : '';
						if ( $client_id > 0 && $completion_date_raw !== '' ) {
							$client_table = $wpdb->prefix . 'ktp_client';
							$client_info = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT closing_day, payment_month, payment_day FROM {$client_table} WHERE id = %d",
									$client_id
								)
							);
							if ( $client_info && ! empty( $client_info->payment_month ) && ! empty( $client_info->payment_day ) ) {
								$completion_dt = DateTime::createFromFormat( 'Y-m-d', $completion_date_raw );
								$errors = DateTime::getLastErrors();
								if ( $completion_dt !== false && ! ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
									$completion_dt->setTime( 0, 0, 0 );
									$year  = (int) $completion_dt->format( 'Y' );
									$month = (int) $completion_dt->format( 'm' );
									if ( $year >= 1 && $year <= 9999 && $month >= 1 && $month <= 12 ) {
										$closing_day   = ! empty( $client_info->closing_day ) ? (string) $client_info->closing_day : '末日';
										$payment_month = (string) $client_info->payment_month;
										$payment_day   = (string) $client_info->payment_day;

										// 完了日が属する請求月（締め日基準）を決定
										$billing_year  = $year;
										$billing_month = $month;
										if ( $closing_day !== '' && $closing_day !== 'なし' ) {
											if ( $closing_day === '末日' ) {
												$closing_dt = new DateTime( "$year-$month-01" );
												$closing_dt->modify( 'last day of this month' );
											} else {
												$closing_day_num = (int) $closing_day;
												$closing_dt      = new DateTime( "$year-$month-" . str_pad( (string) $closing_day_num, 2, '0', STR_PAD_LEFT ) );
												$last_day        = (int) $closing_dt->format( 't' );
												if ( $closing_day_num > $last_day ) {
													$closing_dt->modify( 'last day of this month' );
												}
											}
											$closing_dt->setTime( 0, 0, 0 );
											if ( $completion_dt > $closing_dt ) {
												$billing_month++;
												if ( $billing_month > 12 ) {
													$billing_month = 1;
													$billing_year++;
												}
											}
										}

										// 支払月を計算（今月/翌月/翌々月）
										$payment_year  = $billing_year;
										$payment_m_num = $billing_month;
										switch ( $payment_month ) {
											case '今月':
												$payment_m_num = $billing_month;
												break;
											case '翌々月':
												$payment_m_num = $billing_month + 2;
												if ( $payment_m_num > 12 ) {
													$payment_m_num -= 12;
													$payment_year++;
												}
												break;
											case '翌月':
											default:
												$payment_m_num = $billing_month + 1;
												if ( $payment_m_num > 12 ) {
													$payment_m_num = 1;
													$payment_year++;
												}
												break;
										}

										// 支払日を計算（末日/即日/指定日）
										if ( $payment_day === '即日' ) {
											$due_dt = clone $completion_dt;
										} else {
											$due_dt = new DateTime();
											$due_dt->setDate( $payment_year, $payment_m_num, 1 );
											if ( $payment_day === '末日' ) {
												$due_dt->modify( 'last day of this month' );
											} else {
												$payment_day_num = (int) str_replace( '日', '', $payment_day );
												$due_dt->setDate( $payment_year, $payment_m_num, max( 1, $payment_day_num ) );
												$last_day = (int) $due_dt->format( 't' );
												if ( $payment_day_num > $last_day ) {
													$due_dt->modify( 'last day of this month' );
												}
											}
											$due_dt->setTime( 0, 0, 0 );
										}

										$today = new DateTime();
										$today->setTime( 0, 0, 0 );
										if ( $today > $due_dt ) {
											$show_payment_warning = true;
											$payment_warning_title = __( '入金予定日が過ぎています', 'ktpwp' );
										}
									}
								}
							}
						}
						}
					}

					// 日時フォーマット変換
					$raw_time = $order->time;
					$formatted_time = '';
					if ( ! empty( $raw_time ) ) {
						// UNIXタイムスタンプかMySQL日付か判定
						if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
							// UNIXタイムスタンプ（秒単位）
							$timestamp = (int) $raw_time;
							$dt = new DateTime( '@' . $timestamp );
							$dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
						} else {
							// MySQL DATETIME形式
							$dt = date_create( $raw_time, new DateTimeZone( 'Asia/Tokyo' ) );
						}
						if ( $dt ) {
							$week = array( '日', '月', '火', '水', '木', '金', '土' );
							$w = $dt->format( 'w' );
							$formatted_time = $dt->format( 'n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
						}
					}
					$time = esc_html( $formatted_time );
					$progress = intval( $order->progress );

					// シンプルなURL生成（パーマリンク設定に依存しない）
					// $detail_url = '?tab_name=order&order_id=' . $order_id;
					// progressはリスト詳細リンクには付与しない
					$detail_url = add_query_arg(
                        array(
							'tab_name' => 'order',
							'order_id' => $order_id,
                        )
                    );

					// プルダウンフォーム（警告バッジ対象の行は同じ赤強調）
					$urgent_class = ( $is_urgent || $show_invoice_warning || $show_payment_warning ) ? 'urgent-delivery' : '';
					$content .= "<li class='ktp_work_list_item {$urgent_class}'>";
					// 左寄せブロック（ID・顧客名・担当者・プロジェクト・日時を一まとまりで左寄せ）
					$content .= "<span class='ktp_work_list_item_text'>";
					// 受注詳細リンク（ID）＋ 顧客会社名（該当顧客がいれば顧客タブへのリンク）
					$content .= "<a href='" . esc_url( $detail_url ) . "'>ID: {$order_id}</a> - ";
					if ( $client_id > 0 ) {
						$client_url = add_query_arg(
							array(
								'tab_name' => 'client',
								'data_id'  => $client_id,
							)
						);
						$content .= "<a href='" . esc_url( $client_url ) . "' class='ktp-work-list-client-link' title='" . esc_attr__( '顧客詳細を表示', 'ktpwp' ) . "'>{$customer_name}</a>";
					} else {
						$content .= $customer_name;
					}
					$content .= " <a href='" . esc_url( $detail_url ) . "'>({$user_name})";
					if ( $project_name !== '' ) {
						$content .= " - <span class='project_name'>{$project_name}</span>";
					}
					$content .= " - {$time}</a>";
					// 前払いラベル（前入金済 / EC受注）
					if ( class_exists( 'KTPWP_Payment_Timing' ) ) {
						$prepay_label = KTPWP_Payment_Timing::get_prepay_label( $order, null );
						if ( $prepay_label !== '' ) {
							$content .= ' <span class="ktp-prepay-badge" style="display:inline-block;margin-left:6px;padding:2px 8px;font-size:11px;background:#e3f2fd;color:#1565c0;border-radius:4px;">' . esc_html( $prepay_label ) . '</span>';
						}
					}
					$content .= '</span>';

					// 納期フィールドと進捗プルダウンを1つのコンテナにまとめる
					$content .= "<div class='delivery-dates-container'>";
					$content .= "<div class='delivery-input-wrapper'>";
					$content .= "<span class='delivery-label'>納期</span>";
					$content .= "<input type='date' name='expected_delivery_date_{$order_id}' value='{$expected_delivery_date}' class='delivery-date-input' data-order-id='{$order_id}' data-field='expected_delivery_date' placeholder='納品予定日' title='納品予定日'>";

					// 納期警告マークを追加
					if ( $show_warning && $delivery_warning_title !== '' ) {
						$content .= '<span class="delivery-warning-mark-row" title="' . esc_attr( $delivery_warning_title ) . '">!</span>';
					}

					// ▼▼▼ 請求書締日警告マークを追加 ▼▼▼
					if ( $show_invoice_warning ) {
						$content .= '<span class="invoice-warning-mark-row" title="' . esc_attr__( '請求日を過ぎています', 'ktpwp' ) . '">!</span>';
					}

					// ▼▼▼ 入金予定日超過警告マークを追加 ▼▼▼
					if ( $show_payment_warning && $payment_warning_title !== '' ) {
						$content .= '<span class="payment-warning-mark-row" title="' . esc_attr( $payment_warning_title ) . '">!</span>';
					}

					$content .= '</div>';

					// 完了日カレンダーを納期カレンダーの右側に追加
					$content .= "<div class='completion-input-wrapper'>";
					$content .= "<span class='completion-label'><span class='completion-label-desktop'>完了日</span><span class='completion-label-mobile'>完了</span></span>";
					$content .= "<input type='date' name='completion_date_{$order_id}' value='{$completion_date}' class='completion-date-input' data-order-id='{$order_id}' data-field='completion_date' placeholder='完了日' title='完了日'>";
					$content .= '</div>';

					// 進捗プルダウンを納期コンテナ内に配置
					$content .= "<form method='post' action='' style='margin: 0px 0 0px 0;display:inline;'>";
					$content .= "<input type='hidden' name='update_progress_id' value='{$order_id}' />";
					$content .= "<select name='update_progress' class='progress-select status-{$progress}' onchange='this.form.submit()'>";
					foreach ( $progress_labels as $num => $label ) {
						$selected = ( $progress === $num ) ? 'selected' : '';
						$content .= "<option value='{$num}' {$selected}>{$label}</option>";
					}
					$content .= '</select>';
					$content .= '</form>';
					$content .= '</div>';
					$content .= '</li>';
				}
				$content .= '</ul>';
			} else {
				$content .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
                . '<span class="material-symbols-outlined" aria-label="データなし">search_off</span>'
                . '<span style="font-size: 1em; font-weight: 600;">' . esc_html__( '受注書データがありません。', 'ktpwp' ) . '</span>'
                . '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__( '顧客タブで顧客情報を入力し受注書を作成してください', 'ktpwp' ) . '</span>'
                . '</div>';
			}
			// 進捗更新処理
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['update_progress_id'], $_POST['update_progress'] ) ) {
				$update_id = intval( $_POST['update_progress_id'] );
				$update_progress = intval( $_POST['update_progress'] );
				if ( $update_id > 0 && $update_progress >= 1 && $update_progress <= 7 ) {
					// 現在の進捗を取得
					$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT progress FROM {$table_name} WHERE id = %d", $update_id ) );

					$update_data = array( 'progress' => $update_progress );

					// 進捗が「完了」（progress = 4）に変更された場合、完了日を記録
					if ( $update_progress == 4 && $current_order && $current_order->progress != 4 ) {
						$update_data['completion_date'] = current_time( 'Y-m-d' );
					}

					// 進捗が受注以前（受付中、見積中、受注）に変更された場合、完了日をクリア
					if ( in_array( $update_progress, array( 1, 2, 3 ) ) && $current_order && $current_order->progress > 3 ) {
						$update_data['completion_date'] = null;
					}

					$wpdb->update( $table_name, $update_data, array( 'id' => $update_id ) );
					// リダイレクトで再読み込み（POSTリダブミット防止）
					wp_redirect( esc_url_raw( $_SERVER['REQUEST_URI'] ) );
					exit;
				}
			}
			// --- ページネーション ---
			// データ0でも常にページネーションを表示するため、条件チェックを削除
			// 統一されたページネーションデザインを使用
			if ( ! $print_all ) {
				$content .= $this->render_pagination( $current_page, $total_pages, $query_limit, $tab_name, $flg, $selected_progress, $total_rows );
			}
			$content .= '</div>'; // .ktp_work_list_box 終了
			// --- ここまでラッパー追加 ---

			$content .= '</div>'; // #ktp_list_print_area 終了

			// 納期フィールドのJavaScriptファイルを読み込み
			wp_enqueue_script( 'ktp-delivery-dates' );
			wp_enqueue_script( 'ktp-list-print', plugins_url( 'js/ktp-list-print.js', dirname( __FILE__ ) ) . '?v=' . ( defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0' ), array( 'jquery' ), ( defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0' ), true );

			return $content;
		}

		/**
		 * 仕事リスト用フリーワード検索結果をレンダリング（受注書・顧客・サービス・協力会社を横断検索）
		 *
		 * @param string $keyword  検索キーワード
		 * @param \wpdb   $wpdb     WordPress DB インスタンス
		 * @param string $close_url 検索結果を閉じるリンク先URL（list_search を除いたURL）
		 * @return string 検索結果HTML
		 */
		private function render_list_search_results( $keyword, $wpdb, $close_url = '' ) {
			$like = '%' . $wpdb->esc_like( $keyword ) . '%';
			$results = array();

			// 受注書（memo/search_field はテーブルに存在する前提でLIKEに含める）
			$order_table = $wpdb->prefix . 'ktp_order';
			$order_args = array( $like, $like, $like );
			$order_where = " ( customer_name LIKE %s OR user_name LIKE %s OR project_name LIKE %s ";
			$order_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );
			if ( is_array( $order_cols ) && in_array( 'memo', $order_cols, true ) ) {
				$order_where .= " OR memo LIKE %s ";
				$order_args[] = $like;
			}
			if ( is_array( $order_cols ) && in_array( 'search_field', $order_cols, true ) ) {
				$order_where .= " OR search_field LIKE %s ";
				$order_args[] = $like;
			}
			$order_where .= ') ';
			$order_args[] = 50;
			$order_sql = "SELECT id, customer_name, user_name, project_name FROM `{$order_table}` WHERE " . $order_where . " ORDER BY time DESC LIMIT %d";
			$orders = $wpdb->get_results( $wpdb->prepare( $order_sql, $order_args ) );
			if ( $orders ) {
				foreach ( $orders as $row ) {
					$label = 'ID:' . (int) $row->id . ' - ' . ( $row->customer_name ?: '' ) . ' (' . ( $row->user_name ?: '' ) . ')' . ( $row->project_name ? ' - ' . $row->project_name : '' );
					$url = add_query_arg( array( 'tab_name' => 'order', 'order_id' => (int) $row->id ) );
					$results[] = array(
						'page_label' => __( '受注書', 'ktpwp' ),
						'label'     => $label,
						'url'       => $url,
					);
				}
			}

			// 顧客
			$client_table = $wpdb->prefix . 'ktp_client';
			$client_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$client_table}`" );
			$client_where = " ( company_name LIKE %s OR name LIKE %s ";
			$client_args = array( $like, $like );
			if ( is_array( $client_cols ) && in_array( 'memo', $client_cols, true ) ) {
				$client_where .= " OR memo LIKE %s ";
				$client_args[] = $like;
			}
			if ( is_array( $client_cols ) && in_array( 'search_field', $client_cols, true ) ) {
				$client_where .= " OR search_field LIKE %s ";
				$client_args[] = $like;
			}
			$client_where .= ') ';
			$client_args[] = 50;
			$client_sql = "SELECT id, company_name, name FROM `{$client_table}` WHERE " . $client_where . " ORDER BY id DESC LIMIT %d";
			$clients = $wpdb->get_results( $wpdb->prepare( $client_sql, $client_args ) );
			if ( $clients ) {
				foreach ( $clients as $row ) {
					$label = ( $row->company_name ?: '' ) . ' (' . ( $row->name ?: '' ) . ')';
					$url = add_query_arg( array( 'tab_name' => 'client', 'data_id' => (int) $row->id ) );
					$results[] = array(
						'page_label' => __( '顧客', 'ktpwp' ),
						'label'     => $label,
						'url'       => $url,
					);
				}
			}

			// サービス
			$service_table = $wpdb->prefix . 'ktp_service';
			$service_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$service_table}`" );
			$service_where = " ( service_name LIKE %s ";
			$service_args = array( $like );
			if ( is_array( $service_cols ) ) {
				if ( in_array( 'memo', $service_cols, true ) ) {
					$service_where .= " OR memo LIKE %s ";
					$service_args[] = $like;
				}
				if ( in_array( 'category', $service_cols, true ) ) {
					$service_where .= " OR category LIKE %s ";
					$service_args[] = $like;
				}
				if ( in_array( 'search_field', $service_cols, true ) ) {
					$service_where .= " OR search_field LIKE %s ";
					$service_args[] = $like;
				}
			}
			$service_where .= ') ';
			$service_args[] = 50;
			$service_sql = "SELECT id, service_name FROM `{$service_table}` WHERE " . $service_where . " ORDER BY id DESC LIMIT %d";
			$services = $wpdb->get_results( $wpdb->prepare( $service_sql, $service_args ) );
			if ( $services ) {
				foreach ( $services as $row ) {
					$label = ( $row->service_name ?: '' );
					$url = add_query_arg( array( 'tab_name' => 'service', 'data_id' => (int) $row->id ) );
					$results[] = array(
						'page_label' => __( 'サービス', 'ktpwp' ),
						'label'     => $label,
						'url'       => $url,
					);
				}
			}

			// 協力会社（職能・スキル名 product_name も検索対象）
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier_skills_table = $wpdb->prefix . 'ktp_supplier_skills';
			$supplier_skills_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $supplier_skills_table ) ) === $supplier_skills_table );
			$supplier_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$supplier_table}`" );
			$supplier_where = $supplier_skills_exists ? " ( s.company_name LIKE %s OR s.name LIKE %s " : " ( company_name LIKE %s OR name LIKE %s ";
			$supplier_args = array( $like, $like );
			if ( is_array( $supplier_cols ) ) {
				$pre = $supplier_skills_exists ? 's.' : '';
				if ( in_array( 'memo', $supplier_cols, true ) ) {
					$supplier_where .= " OR {$pre}memo LIKE %s ";
					$supplier_args[] = $like;
				}
				if ( in_array( 'search_field', $supplier_cols, true ) ) {
					$supplier_where .= " OR {$pre}search_field LIKE %s ";
					$supplier_args[] = $like;
				}
			}
			if ( $supplier_skills_exists ) {
				$supplier_where .= " OR ss.product_name LIKE %s ";
				$supplier_args[] = $like;
			}
			$supplier_where .= ') ';
			$supplier_args[] = 50;
			if ( $supplier_skills_exists ) {
				$supplier_sql = "SELECT DISTINCT s.id, s.company_name, s.name FROM `{$supplier_table}` s LEFT JOIN `{$supplier_skills_table}` ss ON ss.supplier_id = s.id WHERE " . $supplier_where . " ORDER BY s.id DESC LIMIT %d";
			} else {
				$supplier_sql = "SELECT id, company_name, name FROM `{$supplier_table}` WHERE " . $supplier_where . " ORDER BY id DESC LIMIT %d";
			}
			$suppliers = $wpdb->get_results( $wpdb->prepare( $supplier_sql, $supplier_args ) );
			if ( $suppliers ) {
				foreach ( $suppliers as $row ) {
					$label = ( $row->company_name ?: '' ) . ' (' . ( $row->name ?: '' ) . ')';
					$url = add_query_arg( array( 'tab_name' => 'supplier', 'data_id' => (int) $row->id ) );
					$results[] = array(
						'page_label' => __( '協力会社', 'ktpwp' ),
						'label'     => $label,
						'url'       => $url,
					);
				}
			}

			$close_btn = '';
			if ( $close_url !== '' ) {
				$close_btn = '<a href="' . esc_url( $close_url ) . '" class="ktp-list-search-results-close" title="' . esc_attr__( '閉じる', 'ktpwp' ) . '" style="position:absolute;top:8px;right:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;color:#666;text-decoration:none;font-size:18px;line-height:1;border-radius:4px;transition:color .2s,background .2s;" onmouseover="this.style.color=\'#333\';this.style.background=\'#eee\';" onmouseout="this.style.color=\'#666\';this.style.background=\'transparent\';">×</a>';
			}

			if ( empty( $results ) ) {
				$html = '<div class="ktp-list-search-results" style="position:relative;margin-bottom:16px;padding:14px 18px;padding-right:44px;background:#f9f9f9;border:1px solid #eee;border-radius:6px;">';
				$html .= $close_btn;
				$html .= '<p style="margin:0;color:#666;font-size:14px;">' . esc_html__( '検索に一致するデータはありません。', 'ktpwp' ) . '</p>';
				$html .= '</div>';
				return $html;
			}

			$html = '<div class="ktp-list-search-results" style="position:relative;margin-bottom:16px;padding:14px 18px;padding-right:44px;background:#f0f7ff;border:1px solid #bbdefb;border-radius:6px;">';
			$html .= $close_btn;
			$html .= '<p style="margin:0 0 10px 0;font-weight:bold;font-size:14px;color:#1565c0;">' . esc_html__( '検索結果', 'ktpwp' ) . '</p>';
			$html .= '<ul style="margin:0;padding-left:20px;list-style:disc;">';
			foreach ( $results as $r ) {
				$html .= '<li style="margin-bottom:6px;">';
				$html .= '<span>' . esc_html( $r['label'] ) . '</span> ';
				$html .= '<a href="' . esc_url( $r['url'] ) . '" style="color:#1976d2;font-weight:600;">' . esc_html( $r['page_label'] ) . '</a>';
				$html .= '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
			return $html;
		}

		/**
		 * 統一されたページネーションデザインをレンダリング
		 *
		 * @param int    $current_page 現在のページ
		 * @param int    $total_pages 総ページ数
		 * @param int    $total_pages 総ページ数
		 * @param int    $query_limit 1ページあたりの表示件数
		 * @param string $tab_name タブ名
		 * @param string $flg フラグ
		 * @param int    $selected_progress 選択された進捗
		 * @param int    $total_rows 総データ数
		 * @return string ページネーションHTML
		 */
		private function render_pagination( $current_page, $total_pages, $query_limit, $tab_name, $flg, $selected_progress, $total_rows ) {
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

			// ページネーションのリンクにはprogressを必ず付与
			$add_progress = isset( $_GET['progress'] );

			// 前のページボタン
			if ( $current_page > 1 && $total_pages > 1 ) {
				$prev_args = array(
					'tab_name' => $tab_name,
					'page_start' => ( $current_page - 2 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				if ( $add_progress ) {
					$prev_args['progress'] = $selected_progress;
				}
				$prev_url = esc_url( add_query_arg( $prev_args ) );
				$pagination_html .= "<a href=\"{$prev_url}\" style=\"{$button_style}\" {$hover_effect}>‹</a>";
			}

			// ページ番号ボタン（省略表示対応）
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			// 最初のページを表示（データが0件でも1ページ目は表示）
			if ( $start_page > 1 && $total_pages > 1 ) {
				$first_args = array(
					'tab_name' => $tab_name,
					'page_start' => 0,
					'page_stage' => 2,
					'flg' => $flg,
				);
				if ( $add_progress ) {
					$first_args['progress'] = $selected_progress;
				}
				$first_url = esc_url( add_query_arg( $first_args ) );
				$pagination_html .= "<a href=\"{$first_url}\" style=\"{$button_style}\" {$hover_effect}>1</a>";

				if ( $start_page > 2 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}
			}

			// 中央のページ番号
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				$page_args = array(
					'tab_name' => $tab_name,
					'page_start' => ( $i - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				if ( $add_progress ) {
					$page_args['progress'] = $selected_progress;
				}
				$page_url = esc_url( add_query_arg( $page_args ) );

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
					'tab_name' => $tab_name,
					'page_start' => ( $total_pages - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				if ( $add_progress ) {
					$last_args['progress'] = $selected_progress;
				}
				$last_url = esc_url( add_query_arg( $last_args ) );
				$pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_args = array(
					'tab_name' => $tab_name,
					'page_start' => $current_page * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				if ( $add_progress ) {
					$next_args['progress'] = $selected_progress;
				}
				$next_url = esc_url( add_query_arg( $next_args ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}
	}
} // class_exists
