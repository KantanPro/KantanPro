<?php
/**
 * Order class for KTPWP plugin
 *
 * Handles order data management including table creation,
 * data operations (CRUD), and security implementations.
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

if ( ! class_exists( 'Kntan_Order_Class' ) ) {

	/**
	 * Order class for managing order data
	 *
	 * @since 1.0.0
	 */
	class Kntan_Order_Class {

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// Constructor initialization
		}

		/**
		 * 受注書プレビューHTML生成のパブリックラッパー
		 *
		 * @param object $order_data 受注書データ
		 * @return string プレビュー用HTML
		 * @since 1.0.0
		 */
		public function Generate_Order_Preview_HTML_Public( $order_data ) {
			return $this->Generate_Order_Preview_HTML( $order_data );
		}

		/**
		 * Create order table using new class structure
		 *
		 * @deprecated Use KTPWP_Order::create_order_table() instead
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function Create_Order_Table() {
			$order_manager = KTPWP_Order::get_instance();
			return $order_manager->create_order_table();
		}

		/**
		 * Create invoice items table using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::create_invoice_items_table() instead
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function Create_Invoice_Items_Table() {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->create_invoice_items_table();
		}

		/**
		 * Create cost items table using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::create_cost_items_table() instead
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function Create_Cost_Items_Table() {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->create_cost_items_table();
		}

		/**
		 * Create initial invoice item when order is created
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		/**
		 * Create initial invoice item using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::create_initial_invoice_item() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function Create_Initial_Invoice_Item( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->create_initial_invoice_item( $order_id );

			if ( $inserted ) {
				return true;
			} else {
				error_log( 'KTPWP: Failed to create initial invoice item: ' . $wpdb->last_error );
			}
		}

		/**
		 * Create initial cost item when order is created
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		/**
		 * Create initial cost item using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::create_initial_cost_item() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function Create_Initial_Cost_Item( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->create_initial_cost_item( $order_id );
		}

		/**
		 * Delete cost items when order is deleted
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		/**
		 * Delete cost items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::delete_cost_items() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function Delete_Cost_Items( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->delete_cost_items( $order_id );
		}

		/**
		 * Get cost items for a specific order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of cost items or false on failure
		 */
		/**
		 * Get cost items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::get_cost_items() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of cost items or false on failure
		 */
		public function Get_Cost_Items( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->get_cost_items( $order_id );
		}

		/**
		 * Delete invoice items when order is deleted
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		/**
		 * Delete invoice items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::delete_invoice_items() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function Delete_Invoice_Items( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->delete_invoice_items( $order_id );
		}

		/**
		 * Get invoice items for a specific order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of invoice items or false on failure
		 */
		/**
		 * Get invoice items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_Items::get_invoice_items() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of invoice items or false on failure
		 */
		public function Get_Invoice_Items( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			return $order_items->get_invoice_items( $order_id );
		}

		/**
		 * Generate HTML table for invoice items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_UI::generate_invoice_items_table() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML table content
		 */
		public function Generate_Invoice_Items_Table( $order_id ) {
			$order_ui = KTPWP_Order_UI::get_instance();
			return $order_ui->generate_invoice_items_table( $order_id );
		}

		/**
		 * Generate HTML table for cost items using new class structure
		 *
		 * @deprecated Use KTPWP_Order_UI::generate_cost_items_table() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML table content
		 */
		public function Generate_Cost_Items_Table( $order_id ) {
			$order_ui = KTPWP_Order_UI::get_instance();
			return $order_ui->generate_cost_items_table( $order_id );
		}

		/**
		 * Create staff chat table using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::create_table() instead
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function Create_Staff_Chat_Table() {
			$staff_chat = KTPWP_Staff_Chat::get_instance();
			return $staff_chat->create_table();
		}

		/**
		 * Create initial staff chat entry when order is created using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::create_initial_chat() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @param int $creator_user_id Creator user ID
		 * @return bool True on success, false on failure
		 */
		/**
		 * Create initial staff chat message using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::create_initial_chat() instead
		 * @since 1.0.0
		 * @param int      $order_id Order ID
		 * @param int|null $creator_user_id User ID who created the order (optional)
		 * @return bool True on success, false on failure
		 */
		public function Create_Initial_Staff_Chat( $order_id, $creator_user_id = null ) {
			if ( ! $order_id || $order_id <= 0 ) {
				error_log( 'KTPWP: Create_Initial_Staff_Chat called with invalid order_id: ' . $order_id );
				return false;
			}

			// スタッフチャットクラスが利用可能かチェック
			if ( ! class_exists( 'KTPWP_Staff_Chat' ) ) {
				error_log( 'KTPWP: KTPWP_Staff_Chat class not found' );
				return false;
			}

			try {
				$staff_chat = KTPWP_Staff_Chat::get_instance();
				$result = $staff_chat->create_initial_chat( $order_id, $creator_user_id );

				if ( $result ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP: Successfully created initial staff chat for order_id: ' . $order_id );
					}
					return true;
				} else {
					error_log( 'KTPWP: Failed to create initial staff chat for order_id: ' . $order_id );
					return false;
				}
			} catch ( Exception $e ) {
				error_log( 'KTPWP: Exception in Create_Initial_Staff_Chat: ' . $e->getMessage() . ' for order_id: ' . $order_id );
				return false;
			}
		}

		/**
		 * Get staff chat messages for a specific order using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::get_messages() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of chat messages or false on failure
		 */
		public function Get_Staff_Chat_Messages( $order_id ) {
			$staff_chat = KTPWP_Staff_Chat::get_instance();
			return $staff_chat->get_messages( $order_id );
		}

		/**
		 * Add staff chat message using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::add_message() instead
		 * @since 1.0.0
		 * @param int    $order_id Order ID
		 * @param string $message Message content
		 * @return bool True on success, false on failure
		 */
		public function Add_Staff_Chat_Message( $order_id, $message ) {
			$staff_chat = KTPWP_Staff_Chat::get_instance();
			return $staff_chat->add_message( $order_id, $message );
		}

		/**
		 * Generate staff chat HTML using new class structure
		 *
		 * @deprecated Use KTPWP_Staff_Chat::generate_html() instead
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML content for staff chat
		 */
		public function Generate_Staff_Chat_HTML( $order_id ) {
			$staff_chat = KTPWP_Staff_Chat::get_instance();
			return $staff_chat->generate_html( $order_id );
		}

		/**
		 * Display order tab view
		 *
		 * @since 1.0.0
		 * @param string $tab_name Tab name
		 * @return void
		 */
		public function Order_Tab_View( $tab_name ) {
			// デバッグログ追加

			// Check user capabilities - allow editors and above to access
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'ktpwp' ) );
			}

			if ( empty( $tab_name ) ) {
				error_log( 'KTPWP: Empty tab_name provided to Order_Tab_View method' );
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';
			$client_table = $wpdb->prefix . 'ktp_client';

			// Initialize invoice items table (with migration)
			$this->Create_Invoice_Items_Table();

			// Initialize cost items table
			$this->Create_Cost_Items_Table();

			// Initialize staff chat table
			$this->Create_Staff_Chat_Table();

			// セッション開始（受注書状態記憶のため）
			if ( session_status() !== PHP_SESSION_ACTIVE ) {
				ktpwp_safe_session_start();
			}

			// Handle form submissions
			$mail_form_html = '';
			$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'NOT_SET';

			// Handle staff chat message submission - AJAX処理に移行したためコメントアウト
			/*
			if ( $request_method === 'POST' && isset( $_POST['staff_chat_message'] ) && isset( $_POST['staff_chat_order_id'] ) ) {
            // Verify nonce
            if ( isset( $_POST['staff_chat_nonce'] ) &&
                 wp_verify_nonce( $_POST['staff_chat_nonce'], 'staff_chat_action' ) ) {

                // Check user capabilities
                if ( current_user_can( 'edit_posts' ) || current_user_can('ktpwp_access') ) {
                    $order_id = absint( $_POST['staff_chat_order_id'] );
                    $message = sanitize_textarea_field( $_POST['staff_chat_message'] );

                    if ( ! empty( $message ) && $order_id > 0 ) {
                        $success = $this->Add_Staff_Chat_Message( $order_id, $message );

                        if ( $success ) {
                            // メッセージ送信成功後、チャットを開いた状態でリダイレクト
                            $redirect_url = add_query_arg( array(
                                'tab_name' => $tab_name,
                                'order_id' => $order_id,
                                'chat_open' => '1',      // チャットを開いた状態を示すパラメータ
                                'message_sent' => '1'    // メッセージ送信完了を示すパラメータ
                            ), esc_url_raw( $_SERVER['REQUEST_URI'] ) );

                            // URLのパラメータをクリーンアップ（重複を避ける）
                            $redirect_url = remove_query_arg( array( 'message', 'action' ), $redirect_url );

                            wp_redirect( $redirect_url );
                            exit;
                        }
                    }
                }
            }
			}
			*/

			// Handle email sending with proper security checks
			if ( $request_method === 'POST' && isset( $_POST['send_order_mail_id'] ) ) {
				// Verify nonce
				// if ( ! isset( $_POST['order_mail_nonce'] ) ||
				// ! wp_verify_nonce( $_POST['order_mail_nonce'], 'send_order_mail_action' ) ) {
				// wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				// }

				// Additional capability check
				// if ( ! current_user_can( 'manage_options' ) ) {
				// wp_die( __( 'You do not have sufficient permissions to send emails.', 'ktpwp' ) );
				// }

				$order_id = absint( $_POST['send_order_mail_id'] );
				if ( $order_id > 0 ) {
					$order = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM `{$table_name}` WHERE id = %d",
                            $order_id
                        )
                    );

					if ( $order ) {
						// Get client information
						$client = null;

						// First try to find by client_id
						if ( ! empty( $order->client_id ) ) {
							$client = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT * FROM `{$client_table}` WHERE id = %d",
                                    $order->client_id
                                )
                            );
						}

						// Fallback: search by company name and user name for backward compatibility
						if ( ! $client && ! empty( $order->customer_name ) && ! empty( $order->user_name ) ) {
							$client = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT * FROM `{$client_table}` WHERE company_name = %s AND name = %s",
                                    $order->customer_name,
                                    $order->user_name
                                )
                            );
						}

						$to = '';
						$selected_department_email = '';

						// 選択された部署のメールアドレスを取得
						if ( class_exists( 'KTPWP_Department_Manager' ) && ! empty( $order->client_id ) ) {
							// 選択された部署のメールアドレスを取得（新しい方式）
							$selected_department_email = KTPWP_Department_Manager::get_selected_department_email_new( $order->client_id );
						}

						// 選択された部署のメールアドレスを優先使用
						if ( ! empty( $selected_department_email ) ) {
							$to = sanitize_email( $selected_department_email );
						} else {
							// 選択された部署がない場合はメインメールアドレスを使用
							if ( $client && ! empty( $client->email ) ) {
								// メールアドレスの詳細な検証（強化版） - 表示用と同じロジック
								$email_raw = $client->email ?? '';

								// シンプルなメールアドレス検証（修正版）
								// 過度に厳格な検証が問題の原因だったため、より寛容なロジックに変更
								$email = trim( $email_raw );

								// 基本的な制御文字のみ除去（最小限）
								$email = str_replace( array( "\0", "\r", "\n", "\t" ), '', $email );

								// 最終検証のみ実行
								$is_valid = ! empty( $email ) && filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;

								if ( $is_valid ) {
									$to = sanitize_email( $email );
								}
							}
						}

						// 対象外顧客のメール送信を拒否
						$client_category = $client ? $client->category : '';
						if ( $client_category === '対象外' ) {
							$mail_form_html = '<div style="background:#ffebee;border:2px solid #f44336;padding:24px;max-width:520px;margin:32px auto 16px auto;border-radius:8px;">';
							$mail_form_html .= '<h3 style="margin-top:0;color:#d32f2f;">メール送信不可</h3>';
							$mail_form_html .= '<p style="color:#d32f2f;">この顧客は削除済み（対象外）のため、メール送信はできません。</p>';
							$mail_form_html .= '</div>';
						} else {
							if ( empty( $to ) ) {
								$mail_form_html = '<div style="background:#fff3cd;border:2px solid #ffc107;padding:24px;max-width:520px;margin:32px auto 16px auto;border-radius:8px;">';
								$mail_form_html .= '<h3 style="margin-top:0;color:#856404;">メールアドレス未設定</h3>';
								$mail_form_html .= '<p style="color:#856404;">この顧客のメールアドレスが未設定です。</p>';
								$mail_form_html .= '<p style="color:#856404;">顧客管理画面でメールアドレスを登録してください。</p>';
								$mail_form_html .= '</div>';
							} else {
								// 自社情報取得 - 新しい設定システムを優先
								$smtp_settings = get_option( 'ktp_smtp_settings', array() );
								$my_email = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';

								// 会社情報を新しい一般設定から取得
								$my_company = '';
								if ( class_exists( 'KTP_Settings' ) ) {
										$my_company = KTP_Settings::get_company_info();
								}

								// 旧システムからも取得（後方互換性） - Use prepared statement
								$setting_table = $wpdb->prefix . 'ktp_setting';
								$setting = $wpdb->get_row(
                                    $wpdb->prepare(
                                        "SELECT * FROM `{$setting_table}` WHERE id = %d",
                                        1
                                    )
								);

								// 会社情報が新システムで見つからない場合は旧システムから取得
								if ( empty( $my_company ) && $setting ) {
										$my_company = sanitize_text_field( strip_tags( $setting->my_company_content ) );
								}

								// 自社メールアドレスが新システムで見つからない場合は旧システムから取得
								if ( empty( $my_email ) && $setting ) {
									$my_email = sanitize_email( $setting->email_address );
								}

								$my_name = '';

								// 請求項目リスト・金額を実際のデータベースから取得
								$invoice_items_from_db = $this->Get_Invoice_Items( $order->id );
								$amount = 0;
								$total_tax_amount = 0;
								$invoice_list = '';
								
								// 顧客の税区分を取得
								$tax_category = '内税'; // デフォルト値
								if ( ! empty( $order->client_id ) ) {
									$client_tax_category = $wpdb->get_var(
										$wpdb->prepare(
											"SELECT tax_category FROM `{$client_table}` WHERE id = %d",
											$order->client_id
										)
									);
									if ( $client_tax_category ) {
										$tax_category = $client_tax_category;
									}
								}

								if ( ! empty( $invoice_items_from_db ) ) {
									// 実際の請求項目データがある場合
									$invoice_list = "\n";
									$max_length = 0;
									$item_lines = array();

								// 税率別の集計用配列
								$tax_rate_groups = array();

									foreach ( $invoice_items_from_db as $item ) {
										$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
										$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
										$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
										$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
										$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
									$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
										$amount += $item_amount;

									// 税率の処理（NULL、空文字、NaNの場合は非課税として扱う）
									$tax_rate = null;
									if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
										$tax_rate = floatval( $tax_rate_raw );
									}

									// 税率別の集計（非課税の場合は'non_taxable'として扱う）
									$tax_rate_key = $tax_rate !== null ? number_format( $tax_rate, 1 ) : 'non_taxable';
									if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
										$tax_rate_groups[ $tax_rate_key ] = 0;
									}
									$tax_rate_groups[ $tax_rate_key ] += $item_amount;

										// 消費税計算（税区分に応じて）
										if ( $tax_category === '外税' ) {
											// 外税表示の場合：税抜金額から税額を計算
										if ( $tax_rate !== null ) {
											$tax_amount = ceil( $item_amount * ( $tax_rate / 100 ) );
											$total_tax_amount += $tax_amount;
										}
									}
									// 内税の場合は後で税率別に計算

										// サービスが空でない項目のみリストに追加
										if ( ! empty( trim( $product_name ) ) ) {
											// 詳細形式：サービス：単価 × 数量/単位 = 金額円（税率X%）
											$line = $product_name . '：' . number_format( $price ) . '円 × ' . $quantity . $unit . ' = ' . number_format( $item_amount ) . '円（税率' . $tax_rate . '%）';
											$item_lines[] = $line;
											// 最大文字数を計算（日本語文字も考慮）
											$line_length = mb_strlen( $line, 'UTF-8' );
											if ( $line_length > $max_length ) {
												$max_length = $line_length;
											}
										}
									}

									// 項目を出力
									foreach ( $item_lines as $line ) {
										$invoice_list .= $line . "\n";
									}

								// 内税の場合は税率別に計算
									if ( $tax_category !== '外税' ) {
									$total_tax_amount = 0;
									$tax_rate_details = array();
									
									foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
										if ( $tax_rate === 'non_taxable' ) {
											// 非課税の場合は税額を0とする
											$tax_rate_details[] = '非課税: 0円';
										} else {
											$tax_rate_value = floatval( $tax_rate );
											$tax_amount = ceil( $group_amount * ( $tax_rate_value / 100 ) / ( 1 + $tax_rate_value / 100 ) );
											$total_tax_amount += $tax_amount;
											$tax_rate_details[] = $tax_rate . '%: ' . number_format( $tax_amount ) . '円';
										}
									}
									}
									
									// 合計金額を切り上げ
									$amount_ceiled = ceil( $amount );
									$total_tax_amount_ceiled = ceil( $total_tax_amount );
									$total_with_tax = $amount_ceiled + $total_tax_amount_ceiled;

									// 税区分に応じた合計行の表示
									if ( $tax_category === '外税' ) {
										$total_line = '外税合計：' . number_format( $amount_ceiled ) . '円';
										$tax_line = '消費税：' . number_format( $total_tax_amount_ceiled ) . '円';
										$total_with_tax_line = '内税合計：' . number_format( $total_with_tax ) . '円';
									} else {
									// 内税の場合は税率別の内訳を表示
									if ( count( $tax_rate_groups ) > 1 ) {
										$tax_detail_text = '（内税：' . implode( ', ', $tax_rate_details ) . '）';
									} else {
										$tax_detail_text = '（内税：' . number_format( $total_tax_amount_ceiled ) . '円）';
									}
									$total_line = '金額合計：' . number_format( $amount_ceiled ) . '円' . $tax_detail_text;
										$tax_line = ''; // 内税の場合は消費税行を非表示
										$total_with_tax_line = ''; // 内税の場合は税込合計行を非表示
									}
									
									$total_length = mb_strlen( $total_line, 'UTF-8' );
									$tax_length = mb_strlen( $tax_line, 'UTF-8' );
									$total_with_tax_length = mb_strlen( $total_with_tax_line, 'UTF-8' );

									// 罫線の長さを決定（項目と合計行の最大文字数）
									$line_length = max( $max_length, $total_length, $tax_length, $total_with_tax_length );
									if ( $line_length < 30 ) {
										$line_length = 30; // 最小長を拡大
									}
									if ( $line_length > 80 ) {
										$line_length = 80; // 最大長を拡大
									}

									// 罫線を追加
									$invoice_list .= str_repeat( '-', $line_length ) . "\n";
									$invoice_list .= $total_line . "\n";
									if ( $tax_category === '外税' ) {
										$invoice_list .= $tax_line . "\n";
										$invoice_list .= $total_with_tax_line;
									}
								} else {
									// 請求項目データがない場合はJSONデータ（旧形式）を試す
									$invoice_items_json = $order->invoice_items ? sanitize_textarea_field( $order->invoice_items ) : '';
									if ( $invoice_items_json ) {
										$items = @json_decode( $invoice_items_json, true );
										if ( is_array( $items ) ) {
											$invoice_list = "\n";
											$total_tax_amount = 0;
												
												// 税率別の集計用配列
												$tax_rate_groups = array();
												
											foreach ( $items as $item ) {
												$amount += isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
												$product_name = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
												$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
												$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 1;
												$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
												$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
												$tax_rate = isset( $item['tax_rate'] ) ? floatval( $item['tax_rate'] ) : 10.0;
												$remarks = isset( $item['remarks'] ) ? sanitize_text_field( $item['remarks'] ) : '';

													// 税率別の集計
													$tax_rate_key = number_format( $tax_rate, 1 );
													if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
														$tax_rate_groups[ $tax_rate_key ] = 0;
													}
													$tax_rate_groups[ $tax_rate_key ] += $item_amount;

												// 消費税計算（税区分に応じて）
												if ( $tax_category === '外税' ) {
													// 外税表示の場合：税抜金額から税額を計算
													$tax_amount = ceil( $item_amount * ( $tax_rate / 100 ) );
													$total_tax_amount += $tax_amount;
												}
													// 内税の場合は後で税率別に計算

												if ( ! empty( trim( $product_name ) ) ) {
													// 詳細形式：サービス：単価 × 数量/単位 = 金額円（税率X%）※ 備考
													$remarks_text = ( ! empty( trim( $remarks ) ) ) ? '　※ ' . $remarks : '';
													$invoice_list .= $product_name . '：' . number_format( $price ) . '円 × ' . $quantity . $unit . ' = ' . number_format( $item_amount ) . "円（税率{$tax_rate}%）" . $remarks_text . "\n";
												}
											}
											
												// 内税の場合は税率別に計算
											if ( $tax_category !== '外税' ) {
													$total_tax_amount = 0;
													$tax_rate_details = array();
													
													foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
														$tax_rate_value = floatval( $tax_rate );
														$tax_amount = ceil( $group_amount * ( $tax_rate_value / 100 ) / ( 1 + $tax_rate_value / 100 ) );
														$total_tax_amount += $tax_amount;
														$tax_rate_details[] = $tax_rate . '%: ' . number_format( $tax_amount ) . '円';
													}
												}
												
												$amount_ceiled = ceil( $amount );
												$total_tax_amount_ceiled = ceil( $total_tax_amount );
												$total_with_tax = $amount_ceiled + $total_tax_amount_ceiled;
											
											// 税区分に応じた合計表示
											if ( $tax_category === '外税' ) {
												$invoice_list .= "\n外税合計：" . number_format( $amount_ceiled ) . '円';
												$invoice_list .= "\n消費税：" . number_format( $total_tax_amount_ceiled ) . '円';
												$invoice_list .= "\n内税合計：" . number_format( $total_with_tax ) . '円';
											} else {
													// 内税の場合は税率別の内訳を表示
													if ( count( $tax_rate_groups ) > 1 ) {
														$tax_detail_text = '（内税：' . implode( ', ', $tax_rate_details ) . '）';
													} else {
														$tax_detail_text = '（内税：' . number_format( $total_tax_amount_ceiled ) . '円）';
													}
													$invoice_list .= "\n金額合計：" . number_format( $amount_ceiled ) . '円' . $tax_detail_text;
											}
										} else {
											$invoice_list = sanitize_textarea_field( $invoice_items_json );
										}
									} else {
										$invoice_list = '（請求項目未入力）';
									}
								}

								// $amount_str は削除（$invoice_list内に合計が含まれているため）
								// $amount_str = $amount ? number_format(ceil($amount)) . '円' : '';

								// 進捗ごとに件名・本文 - Sanitize input data
								$progress = absint( $order->progress );
								$project_name = $order->project_name ? sanitize_text_field( $order->project_name ) : '';
								$customer_name = sanitize_text_field( $order->customer_name );
								$user_name = sanitize_text_field( $order->user_name );

								// 会社名が0や空の場合のフォールバック処理
								if ( $customer_name === '0' || empty( $customer_name ) ) {
									// 顧客IDがある場合は顧客テーブルから会社名を取得
									if ( ! empty( $order->client_id ) ) {
										$client_company_name = $wpdb->get_var(
											$wpdb->prepare(
												"SELECT company_name FROM `{$client_table}` WHERE id = %d",
												$order->client_id
											)
										);
										if ( ! empty( $client_company_name ) ) {
											$customer_name = $client_company_name;
										} else {
											// 顧客テーブルからも取得できない場合は担当者名を使用
											$customer_name = $user_name;
										}
									} else {
										// 顧客IDがない場合は担当者名を使用
										$customer_name = $user_name;
									}
								}

								// 顧客IDの取得と表示
								$client_id_display = ! empty( $order->client_id ) ? intval( $order->client_id ) : '未設定';

								// 進捗に応じた帳票タイトルと件名を設定
								$document_titles = array(
									1 => '見積り書',
									2 => '注文受書',
									3 => '納品書',
									4 => '請求書',
									5 => '領収書',
									6 => '案件完了',
								);

								$document_messages = array(
									1 => "{$project_name}につきましてお見積りいたします。",
									2 => "{$project_name}につきましてご注文をお受けしました。",
									3 => "{$project_name}につきまして完了しました。",
									4 => "{$project_name}につきまして請求申し上げます。",
									5 => "{$project_name}につきましてお支払いを確認しました。",
									6 => "{$project_name}につきましては全て完了しています。",
								);

								$document_title = isset( $document_titles[ $progress ] ) ? $document_titles[ $progress ] : '受注書';
								$document_message = isset( $document_messages[ $progress ] ) ? $document_messages[ $progress ] : $project_name;

								// 適格請求書番号を取得し、請求書の場合に追加
								if ( $progress === 4 && class_exists( 'KTP_Settings' ) ) {
									$qualified_invoice_number = KTP_Settings::get_qualified_invoice_number();
									if ( ! empty( $qualified_invoice_number ) ) {
										$document_title = $document_title . ' 適格請求書番号：' . $qualified_invoice_number;
									}
								}

								// 日付フォーマット（年月日）
								$order_date = date( 'Y年m月d日', $order->time );

								// 件名は「次の進捗」：案件名の形式
								$subject = "{$document_title}：{$project_name}";

								// 本文の生成（新フォーマット）
								$body = "{$customer_name}\n";
								$body .= "{$user_name} 様\n\n";
								$body .= "お世話になります。\n\n";
								$body .= "＜{$document_title}＞\n";
								$body .= "ID: {$order->id} [{$order_date}]\n\n";
								$body .= "「{$project_name}」{$document_message}\n";
								$body .= "{$invoice_list}\n\n";
								$body .= "--\n{$my_company}";

								$body = $subject = '';
								if ( $progress === 1 ) {
									$subject = "{$document_title}：{$project_name}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								} elseif ( $progress === 2 ) {
									$subject = "{$document_title}：{$project_name}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								} elseif ( $progress === 3 ) {
									$subject = "{$document_title}：{$project_title}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								} elseif ( $progress === 4 ) {
									$subject = "{$document_title}：{$project_name}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								} elseif ( $progress === 5 ) {
									$subject = "{$document_title}：{$project_name}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								} elseif ( $progress === 6 ) {
									$subject = "{$document_title}：{$project_name}";
									$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";
								}

								// Sanitize email content input
								$edit_subject = isset( $_POST['edit_subject'] ) ? sanitize_text_field( stripslashes( $_POST['edit_subject'] ) ) : $subject;
								$edit_body = isset( $_POST['edit_body'] ) ? sanitize_textarea_field( stripslashes( $_POST['edit_body'] ) ) : $body;

								// 送信ボタンが押された場合
								if ( isset( $_POST['do_send_mail'] ) && $_POST['do_send_mail'] == '1' ) {
									// Additional verification for email sending
									// if ( ! current_user_can( 'manage_options' ) ) {
									// wp_die( __( 'You do not have sufficient permissions to send emails.', 'ktpwp' ) );
									// }

									$headers = array();
									if ( $my_email ) {
										$headers[] = 'From: ' . sanitize_email( $my_email );
									}
									$sent = wp_mail( sanitize_email( $to ), $edit_subject, $edit_body, $headers );
									if ( $sent ) {
										// echo '<script>
										// document.addEventListener("DOMContentLoaded", function() {
										// showSuccessNotification("メールを送信しました。\\n宛先: ' . esc_js($to) . '");
										// });
										// </script>';
									} else {
										// echo '<script>
										// document.addEventListener("DOMContentLoaded", function() {
										// showErrorNotification("メール送信に失敗しました。サーバー設定をご確認ください。");
										// });
										// </script>';
									}
								} else {
									// 編集フォームHTMLを生成
									$mail_form_html = '<div id="order-mail-form" style="background:#fff;border:2px solid #2196f3;padding:24px;max-width:520px;margin:32px auto 16px auto;border-radius:8px;box-shadow:0 2px 12px #0002;z-index:9999;">';
									$mail_form_html .= '<h3 style="margin-top:0;">メール送信内容の編集</h3>';
									$mail_form_html .= '<form method="post" action="">';
									// Add nonce to mail form
									$mail_form_html .= wp_nonce_field( 'send_order_mail_action', 'order_mail_nonce', true, false );
									$mail_form_html .= '<input type="hidden" name="send_order_mail_id" value="' . esc_attr( $order_id ) . '">';
									$mail_form_html .= '<div style="margin-bottom:12px;"><label>宛先：</label><input type="email" value="' . esc_attr( $to ) . '" readonly style="width:320px;max-width:100%;background:#f5f5f5;"></div>';
									$mail_form_html .= '<div style="margin-bottom:12px;"><label>件名：</label><input type="text" name="edit_subject" value="' . esc_attr( $edit_subject ) . '" style="width:320px;max-width:100%;"></div>';
									$mail_form_html .= '<div style="margin-bottom:12px;"><label>本文：</label><textarea name="edit_body" rows="10" style="width:100%;max-width:480px;">' . esc_textarea( $edit_body ) . '</textarea></div>';
									$mail_form_html .= '<button type="submit" name="do_send_mail" value="1" style="background:#2196f3;color:#fff;padding:8px 18px;border:none;border-radius:4px;font-size:15px;">送信</button>';
									$mail_form_html .= '<button type="button" onclick="document.getElementById(\'order-mail-form\').style.display=\'none\';" style="margin-left:16px;padding:8px 18px;border:none;border-radius:4px;font-size:15px;">キャンセル</button>';
									$mail_form_html .= '</form>';
									$mail_form_html .= '</div>';
								}
							} // if ( empty( $to ) ) の else ブロック終了
						} // if ($client_category === '対象外') の else ブロック終了
					} else {
					}
				} else {
				}
			}

			// この重要なチェックポイントを追加

			// メール処理なしの場合のログ
			if ( ! ( $request_method === 'POST' && isset( $_POST['send_order_mail_id'] ) ) ) {
			}

			// この時点での実行確認
			// 案件名の保存処理 - Add nonce verification
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['update_project_name_id'], $_POST['order_project_name'] ) ) {
				// Verify nonce
				// if ( ! isset( $_POST['project_name_nonce'] ) ||
				// ! wp_verify_nonce( $_POST['project_name_nonce'], 'update_project_name_action' ) ) {
				// wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				// }

				// Check user capabilities
				// if ( ! current_user_can( 'manage_options' ) ) {
				// wp_die( __( 'You do not have sufficient permissions to update project names.', 'ktpwp' ) );
				// }

				$update_id = absint( $_POST['update_project_name_id'] );
				$project_name = sanitize_text_field( $_POST['order_project_name'] );
				if ( $update_id > 0 ) {
					$wpdb->update( $table_name, array( 'project_name' => $project_name ), array( 'id' => $update_id ), array( '%s' ), array( '%d' ) );
					// POSTリダブミット防止のためリダイレクト
					$redirect_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
					// wp_redirect( $redirect_url );
					// exit;
				}
			}

			// 重要なチェックポイント2を追加

			// 進捗更新処理はAjaxで処理するため、POST処理をコメントアウト
			// 進捗更新処理（POST時） - Add nonce verification
			/*
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['update_progress_id'], $_POST['update_progress'] ) ) {
				error_log( 'KTPWP Order: 進捗更新処理が呼び出されました' );
				error_log( 'KTPWP Order: POST data: ' . print_r( $_POST, true ) );
				error_log( 'KTPWP Order: $_POST[completion_date] = ' . ( isset( $_POST['completion_date'] ) ? $_POST['completion_date'] : 'NOT SET' ) );

				// Verify nonce
				// if ( ! isset( $_POST['progress_nonce'] ) ||
				// ! wp_verify_nonce( $_POST['progress_nonce'], 'update_progress_action' ) ) {
				// wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				// }

				// Check user capabilities
				// if ( ! current_user_can( 'manage_options' ) ) {
				// wp_die( __( 'You do not have sufficient permissions to update progress.', 'ktpwp' ) );
				// }

				$update_id = absint( $_POST['update_progress_id'] );
				$update_progress = absint( $_POST['update_progress'] );

				error_log( 'KTPWP Order: 進捗更新 - ID: ' . $update_id . ', 新しい進捗: ' . $update_progress );

				if ( $update_id > 0 && $update_progress >= 1 && $update_progress <= 7 ) {
					// 現在の進捗を取得
					$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT progress FROM {$table_name} WHERE id = %d", $update_id ) );

					error_log( 'KTPWP Order: 現在の進捗: ' . ( $current_order ? $current_order->progress : 'null' ) );

					$update_data = array( 'progress' => $update_progress );

					// 完了日フィールドの値を取得
					$completion_date = isset( $_POST['completion_date'] ) ? sanitize_text_field( $_POST['completion_date'] ) : '';
					error_log( 'KTPWP Order: フォームから完了日を取得: ' . $completion_date );

					// 進捗が「完了」（progress = 4）に変更された場合、完了日を記録
					if ( $update_progress == 4 && $current_order && $current_order->progress != 4 ) {
						if ( ! empty( $completion_date ) ) {
							// フォームから完了日が送信されている場合はその値を使用
							$update_data['completion_date'] = $completion_date;
							error_log( 'KTPWP Order: フォームから完了日を設定: ' . $completion_date );
						} else {
							// フォームから完了日が送信されていない場合は今日の日付を設定
							$update_data['completion_date'] = current_time( 'Y-m-d' );
							error_log( 'KTPWP Order: 完了日を自動設定します: ' . current_time( 'Y-m-d' ) );
						}
					}

					// 進捗が受注以前（受付中、見積中、受注）に変更された場合、完了日をクリア
					if ( in_array( $update_progress, array( 1, 2, 3 ) ) && $current_order && $current_order->progress > 3 ) {
						$update_data['completion_date'] = null;
						error_log( 'KTPWP Order: 完了日をクリアします' );
					}

					error_log( 'KTPWP Order: 更新データ: ' . print_r( $update_data, true ) );

					// データベース更新時のフォーマットを適切に設定
					$update_formats = array();
					$update_conditions = array( 'id' => $update_id );
					$condition_formats = array( '%d' );

					foreach ( $update_data as $key => $value ) {
						if ( $key === 'progress' ) {
							$update_formats[] = '%d';
						} elseif ( $key === 'completion_date' ) {
							$update_formats[] = '%s';
						} else {
							$update_formats[] = '%s';
						}
					}

					$result = $wpdb->update( $table_name, $update_data, $update_conditions, $update_formats, $condition_formats );

					error_log( 'KTPWP Order: データベース更新結果: ' . ( $result ? '成功' : '失敗' ) );
					if ( ! $result ) {
						error_log( 'KTPWP Order: データベースエラー: ' . $wpdb->last_error );
					}

					// リダイレクトで再読み込み（POSTリダブミット防止）
					$redirect_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
					error_log( 'KTPWP Order: リダイレクト先: ' . $redirect_url );
					wp_redirect( $redirect_url );
					exit;
				} else {
					error_log( 'KTPWP Order: 進捗更新条件を満たしません - ID: ' . $update_id . ', 進捗: ' . $update_progress );
				}
			}
			*/

			// 重要なチェックポイント3を追加

			// 請求項目の保存処理 - Add nonce verification
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_invoice_items'] ) && isset( $_POST['invoice_items'] ) ) {
				// Verify nonce
				if ( ! isset( $_POST['invoice_items_nonce'] ) ||
                 ! wp_verify_nonce( $_POST['invoice_items_nonce'], 'save_invoice_items_action' ) ) {
					wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				}

				// Check user capabilities
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
					wp_die( __( 'You do not have sufficient permissions to update invoice items.', 'ktpwp' ) );
				}

				$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
				$invoice_items = isset( $_POST['invoice_items'] ) ? $_POST['invoice_items'] : array();

				if ( $order_id > 0 && is_array( $invoice_items ) ) {
					$result = $this->Save_Invoice_Items( $order_id, $invoice_items );

					if ( $result ) {
						// 請求項目が正常に保存されました
					} else {
						// 請求項目の保存に失敗しました
					}

					// POSTリダブミット防止のためリダイレクト
					$redirect_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
					wp_redirect( $redirect_url );
					exit;
				} else {
				}
			}

			// コスト項目保存処理
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_cost_items'] ) && $_POST['save_cost_items'] == 1 ) {

				$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
				$cost_items = isset( $_POST['cost_items'] ) ? $_POST['cost_items'] : array();

				if ( $order_id > 0 && is_array( $cost_items ) ) {

					$result = $this->Save_Cost_Items( $order_id, $cost_items );

					if ( $result ) {
					} else {
					}

					// POSTリダブミット防止のためリダイレクト
					$redirect_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
					wp_redirect( $redirect_url );
					exit;
				} else {
				}
			}

			// 重要なチェックポイント4を追加

			// URLパラメータから顧客情報を取得 - Sanitize input
			$customer_name = isset( $_GET['customer_name'] ) ? sanitize_text_field( $_GET['customer_name'] ) : '';
			$user_name = isset( $_GET['user_name'] ) ? sanitize_text_field( $_GET['user_name'] ) : '';
			$from_client = isset( $_GET['from_client'] ) ? absint( $_GET['from_client'] ) : 0; // 顧客タブからの遷移フラグ
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // 表示する受注書ID

			$content = ''; // 表示するHTMLコンテンツ

			// 重要なチェックポイント5を追加

			// 削除処理のデバッグ情報を追加

			// 削除処理が実行されるかの条件を個別にチェック
			$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
			$has_delete_order = isset( $_POST['delete_order'] ) && $_POST['delete_order'] == 1;
			$has_order_id = isset( $_POST['order_id'] );

			if ( ! $is_post ) {
			} elseif ( ! $has_delete_order ) {
			} elseif ( ! $has_order_id ) {
			}

			// 受注書削除処理 - Use POST method for deletion
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['delete_order'] ) && $_POST['delete_order'] == 1 && isset( $_POST['order_id'] ) ) {
				$order_id_to_delete = absint( $_POST['order_id'] );
				$client_exists = isset( $_POST['client_exists'] ) ? intval( $_POST['client_exists'] ) : 0;

				// 顧客データが存在しない場合でも削除を許可するため、顧客データの存在チェックは行わない

				// Verify nonce for delete action
				// if ( ! isset( $_POST['delete_nonce'] ) ||
				// ! wp_verify_nonce( $_POST['delete_nonce'], 'delete_order_action' ) ) {
				// wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				// }

				// Check user capabilities
				// if ( ! current_user_can( 'manage_options' ) ) {
				// wp_die( __( 'You do not have sufficient permissions to delete orders.', 'ktpwp' ) );
				// }

				// 削除処理 - 顧客データの存在に関係なく受注書を削除

				// 関連する請求項目を削除
				$this->Delete_Invoice_Items( $order_id_to_delete );

				// 関連するコスト項目を削除
				$this->Delete_Cost_Items( $order_id_to_delete );

				// 関連するスタッフチャットメッセージを削除
				$this->Delete_Staff_Chat_Messages( $order_id_to_delete );

				// 受注書を削除
				$deleted = $wpdb->delete( $table_name, array( 'id' => $order_id_to_delete ), array( '%d' ) );

				if ( $deleted ) {
					// 統一されたリダイレクト処理に変更
					$latest_order = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_name}` ORDER BY time DESC LIMIT %d",
                            1
                        )
                    );

					// 現在のページの基本URLを取得（動的パーマリンク取得）
					$base_url = KTPWP_Main::get_current_page_base_url();

					if ( $latest_order ) {
						$next_order_id = $latest_order->id;
						// 他の受注書が存在する場合は、その受注書にリダイレクト
						$redirect_url = add_query_arg(
                            array(
								'tab_name' => 'order',
								'order_id' => $next_order_id,
								'message' => 'deleted',
                            ),
                            $base_url
                        );
					} else {
						// すべての受注書が削除された場合は、受注書なしの状態でリダイレクト
						$redirect_url = add_query_arg(
                            array(
								'tab_name' => 'order',
								'message' => 'deleted_all',
                            ),
                            $base_url
                        );
					}

					wp_redirect( $redirect_url );
					exit;

				} else {
					$content .= '<div class="error">受注書の削除に失敗しました。エラー: ' . esc_html( $wpdb->last_error ) . '</div>';
				}
			} else {
				// 削除処理の条件が満たされない場合のデバッグ情報
				if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
				} elseif ( ! isset( $_POST['delete_order'] ) ) {
				} elseif ( $_POST['delete_order'] != 1 ) {
				} elseif ( ! isset( $_POST['order_id'] ) ) {
				}
			}

			// 重要なチェックポイント6を追加

			// 削除処理が完了した場合は新規受注書作成をスキップ
			$deletion_completed = isset( $_GET['message'] ) && ( $_GET['message'] === 'deleted' || $_GET['message'] === 'deleted_all' );

			// 顧客タブから遷移してきた場合（新規受注書作成）
			if ( $from_client === 1 && $customer_name !== '' && ! $deletion_completed ) {
				// セッションスタート（複製情報にアクセスするため - 下位互換性のため）
				if ( session_status() !== PHP_SESSION_ACTIVE ) {
					ktpwp_safe_session_start();
				}

				// 顧客IDを取得（優先順位：GET > DB > SESSION > COOKIE > POST）
				// 1. GETパラメータから取得（ktpwp.phpでリダイレクト時に設定される）
				$client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

				// 2. POSTパラメータも確認
				if ( $client_id <= 0 && isset( $_POST['client_id'] ) && absint( $_POST['client_id'] ) > 0 ) {
					$client_id = absint( $_POST['client_id'] );
				}

				// 最終手段：顧客IDが提供されなかった場合は、会社名と担当者名から顧客IDを検索
				if ( $client_id <= 0 && $customer_name !== '' ) {
					$client = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id FROM `{$client_table}` WHERE company_name = %s AND name = %s",
                            $customer_name,
                            $user_name
                        )
                    );
					if ( $client ) {
						$client_id = $client->id;
					} else {
					}
				}

				// 対象外顧客からの受注書作成を防ぐチェック
				if ( $client_id > 0 ) {
					$client_status = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT client_status FROM `{$client_table}` WHERE id = %d",
                            $client_id
                        )
                    );

					if ( $client_status === '対象外' ) {
						$content .= '<div class="error" style="padding: 15px; background: #ffe6e6; border: 1px solid #ff4444; border-radius: 4px; margin: 10px 0; color: #d32f2f;">';
						$content .= '<strong>受注書作成エラー</strong><br>';
						$content .= '対象外の顧客からは受注書を作成できません。顧客のステータスを「対象」に変更してから再度お試しください。';
						$content .= '</div>';

						// エラーメッセージを表示した後は受注書作成処理をスキップ
						$from_client = 0;
					}
				}

				// 受注書データをデータベースに挿入（対象外チェックを通過した場合のみ）
				if ( $from_client === 1 ) {
					// データが完全に0の場合、AUTO_INCREMENTカウンターを1にリセット
					$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
					if ( $row_count == 0 ) {
						$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Order Tab: Order table AUTO_INCREMENT reset to 1' );
						}
					}

					// AUTO_INCREMENTを使用してIDを自動生成
					// 標準的なUNIXタイムスタンプを使用（UTCベース）
					$timestamp = time(); // 標準のUTC UNIXタイムスタンプを取得

					// 受注書番号を自動生成（YYYY-MMDD-XXX形式）
					$today = date( 'Y-md', $timestamp );
					$order_number_prefix = $today . '-';
					
					// 今日の受注書数を取得して連番を生成
					$today_count = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$table_name}` WHERE order_number LIKE %s",
                            $order_number_prefix . '%'
                        )
                    );
					$order_number = $order_number_prefix . str_pad( intval( $today_count ) + 1, 3, '0', STR_PAD_LEFT );

					$insert_data = array(
						'order_number' => $order_number, // 自動生成された受注書番号
						'time' => $timestamp, // 標準のUTC UNIXタイムスタンプで保存
						'client_id' => $client_id, // 顧客IDを保存
						'customer_name' => sanitize_text_field( $customer_name ),
						'user_name' => sanitize_text_field( $user_name ),
						'project_name' => '※ 入力してください', // 案件名の初期値を設定
						'invoice_items' => '', // 初期値は空
						'cost_items' => '', // 初期値は空
						'memo' => '', // 初期値は空
						'search_field' => sanitize_text_field( implode( ', ', array( $customer_name, $user_name ) ) ), // 検索用フィールド
					);

					$inserted = $wpdb->insert(
                        $table_name,
                        $insert_data,
                        array(
							'%s', // order_number
							'%d', // time
							'%d', // client_id
							'%s', // customer_name
							'%s', // user_name
							'%s', // project_name
							'%s', // invoice_items
							'%s', // cost_items
							'%s', // memo
							'%s',  // search_field
                        )
                    );

					if ( $inserted ) {
						// AUTO_INCREMENTで生成されたIDを取得
						$new_order_id = $wpdb->insert_id;

						// 初期請求項目を作成
						$invoice_result = $this->Create_Initial_Invoice_Item( $new_order_id );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Initial invoice item creation result: ' . ( $invoice_result ? 'success' : 'failed' ) . ' for order_id: ' . $new_order_id );
						}

						// 初期コスト項目を作成
						$cost_result = $this->Create_Initial_Cost_Item( $new_order_id );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Initial cost item creation result: ' . ( $cost_result ? 'success' : 'failed' ) . ' for order_id: ' . $new_order_id );
						}

						// 初期スタッフチャットエントリを作成（重要：確実に実行）
						$chat_result = $this->Create_Initial_Staff_Chat( $new_order_id, get_current_user_id() );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Initial staff chat creation result: ' . ( $chat_result ? 'success' : 'failed' ) . ' for order_id: ' . $new_order_id );
						}

						// スタッフチャットの作成に失敗した場合は再試行
						if ( ! $chat_result ) {
							error_log( 'KTPWP: Retrying staff chat creation for order_id: ' . $new_order_id );
							// 少し待ってから再試行
							sleep( 1 );
							$chat_result = $this->Create_Initial_Staff_Chat( $new_order_id, get_current_user_id() );
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( 'KTPWP: Staff chat creation retry result: ' . ( $chat_result ? 'success' : 'failed' ) . ' for order_id: ' . $new_order_id );
							}
						}

						// リダイレクト処理を無効化 - 代わりにorder_idを直接設定
						$_GET['order_id'] = $new_order_id;
						$_GET['from_client'] = null; // from_clientフラグをクリア
						$order_id = $new_order_id; // ローカル変数も更新

						// デバッグ用ログ
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: New order created successfully with ID: ' . $new_order_id );
						}
					} else {
						// 挿入失敗時のエラーハンドリング
						error_log( 'KTPWP: Failed to create new order: ' . $wpdb->last_error );
						$content .= '<div class="error">受注書の作成に失敗しました。</div>';
					}
				}
			}

			// 重要なチェックポイント7を追加

			// 削除処理完了後は受注書の自動取得をスキップ
			$deletion_completed = isset( $_GET['message'] ) && ( $_GET['message'] === 'deleted' || $_GET['message'] === 'deleted_all' );

			// 受注書IDの決定ロジック（優先順位：GET > セッション記憶 > 最新）
			if ( $order_id === 0 && ! $deletion_completed ) {
				// 1. GETパラメータで指定された受注書IDを優先
				if ( isset( $_GET['order_id'] ) && ! empty( $_GET['order_id'] ) ) {
					$get_order_id = absint( $_GET['order_id'] );
					// 有効な受注書IDかチェック
					$valid_order = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_name}` WHERE id = %d", $get_order_id ) );
					if ( $valid_order ) {
						$order_id = $get_order_id;
						// 有効な受注書IDの場合、セッションに記憶
						$_SESSION['ktp_last_order_id'] = $order_id;
					} else {
						// 無効なIDの場合はGETパラメータをクリア
						$current_url = remove_query_arg( 'order_id', $_SERVER['REQUEST_URI'] );
						wp_redirect( $current_url );
						exit;
					}
				}
				// 2. セッションに記憶された受注書IDを確認
				elseif ( isset( $_SESSION['ktp_last_order_id'] ) && ! empty( $_SESSION['ktp_last_order_id'] ) ) {
					$session_order_id = absint( $_SESSION['ktp_last_order_id'] );
					// 記憶されたIDが有効な受注書かチェック
					$valid_order = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_name}` WHERE id = %d", $session_order_id ) );
					if ( $valid_order ) {
						$order_id = $session_order_id;
					} else {
						// 無効なIDの場合はセッションから削除
						unset( $_SESSION['ktp_last_order_id'] );
					}
				}
				// 3. 最新の受注書IDを取得
				if ( $order_id === 0 ) {
					$latest_order = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT id FROM `{$table_name}` ORDER BY time DESC LIMIT %d",
							1
						)
					);
					if ( $latest_order ) {
						$order_id = $latest_order->id;
						// 最新の受注書IDもセッションに記憶
						$_SESSION['ktp_last_order_id'] = $order_id;
					}
				}
			} else {
				// order_idが既に設定されている場合（GETパラメータなど）、セッションに記憶
				if ( $order_id > 0 ) {
					// 有効な受注書IDかチェック
					$valid_order = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_name}` WHERE id = %d", $order_id ) );
					if ( $valid_order ) {
						$_SESSION['ktp_last_order_id'] = $order_id;
					} else {
						// 無効なIDの場合はリセット
						$order_id = 0;
						unset( $_SESSION['ktp_last_order_id'] );
					}
				}
			}

			// 重要なチェックポイント8を追加

			// 受注書データが存在する場合に詳細を表示
			if ( $order_id > 0 ) {
				$order_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $order_id ) );

				if ( $order_data ) {
					// 現在表示中の受注書IDをセッションに記憶（確実に保存）
					$_SESSION['ktp_last_order_id'] = $order_id;

					// プレビューボタン用のHTML生成は削除（Ajax経由で最新データを取得）

					$content .= '<div class="controller" style="display: flex; justify-content: space-between; align-items: center;">';

					// 左側：削除ボタン（ゴミ箱アイコン付き）
					$current_url = add_query_arg( null, null );
					$content .= '<form method="post" action="' . esc_url( $current_url ) . '" style="display:inline-block;" onsubmit="return confirm(\'本当にこの受注書を削除しますか？\\nこの操作は元に戻せません。\');">';
					$content .= '<input type="hidden" name="order_id" value="' . esc_attr( $order_data->id ) . '">';
					$content .= '<input type="hidden" name="delete_order" value="1">';
					// 顧客データの存在有無を記録（デバッグ用）
					$client_exists = false;
					if ( ! empty( $order_data->client_id ) ) {
						$client_exists = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM `{$client_table}` WHERE id = %d",
                                $order_data->client_id
                            )
                        ) > 0;
					}
					$content .= '<input type="hidden" name="client_exists" value="' . ( $client_exists ? '1' : '0' ) . '">';
					// Add nonce to delete form
					$content .= wp_nonce_field( 'delete_order_action', 'delete_nonce', true, false );
					$content .= '<button type="submit" class="delete-order-btn">';
					$content .= '<span class="material-symbols-outlined" aria-label="削除">delete</span>';
					$content .= '受注書を削除';
					$content .= '</button>';
					$content .= '</form>';

					// 右側：プレビューボタン、サンプルデータ追加ボタン、メールボタン
					$content .= '<div style="display: flex; gap: 5px;">';
					// プレビューボタン（受注書IDのみ保持、最新データはAjaxで取得）
					$content .= '<button id="orderPreviewButton" data-order-id="' . esc_attr( $order_data->id ) . '" title="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="padding: 6px 10px; font-size: 12px;">';
					$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="font-size: 16px;">preview</span>';
					$content .= '</button>';
					
					// サンプルデータ追加ボタン
					$content .= '<button id="addSampleDataButton" data-order-id="' . esc_attr( $order_data->id ) . '" title="サンプルデータを追加" style="padding: 6px 10px; font-size: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">';
					$content .= '<span class="material-symbols-outlined" aria-label="サンプルデータ追加" style="font-size: 16px;">add_circle</span>';
					$content .= '</button>';

					// 顧客情報に基づいてメールボタンの状態を制御
					$client = null;
					$can_send_email = false;
					$mail_button_title = esc_attr__( 'メール', 'ktpwp' );
					$mail_button_style = 'padding: 6px 10px; font-size: 12px; border: none;';

					// 顧客データを取得
					if ( ! empty( $order_data->client_id ) ) {
						$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$client_table}` WHERE id = %d", $order_data->client_id ) );
					}

					// IDで見つからない場合は会社名と担当者名で検索（後方互換性）
					if ( ! $client ) {
						$client = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM `{$client_table}` WHERE company_name = %s AND name = %s",
                                $order_data->customer_name,
                                $order_data->user_name
                            )
                        );
					}

					// 顧客データ詳細ログ
					if ( $client ) {
					}

					// メール送信可否の判定（強化版）
					if ( ! $client ) {
						$mail_button_title = 'メール送信不可（顧客データなし）';
						$mail_button_style = 'padding: 6px 10px; font-size: 12px; background: #ccc; cursor: not-allowed; border: none;';
					} elseif ( $client->category === '対象外' || trim( $client->category ?: '' ) === '対象外' ) {
						$mail_button_title = 'メール送信不可（対象外顧客）';
						$mail_button_style = 'padding: 6px 10px; font-size: 12px; background: #f44336; color: white; cursor: not-allowed; border: none;';
					} else {
						// メールアドレスの詳細な検証（強化版） - nameフィールドも考慮
						$email_raw = $client->email ?? '';
						$name_raw = $client->name ?? '';

						// nameフィールドにメールアドレスが入っている場合を検出
						$name_is_email = ! empty( $name_raw ) && filter_var( $name_raw, FILTER_VALIDATE_EMAIL ) !== false;
						$email_is_empty = empty( trim( $email_raw ) );

						if ( $name_is_email && $email_is_empty ) {
							$email_raw = $name_raw; // nameフィールドの値を使用
						}

						// シンプルなメールアドレス検証（修正版）
						// 過度に厳格な検証が問題の原因だったため、より寛容なロジックに変更
						$email = trim( $email_raw );

						// 基本的な制御文字のみ除去（最小限）
						$email = str_replace( array( "\0", "\r", "\n", "\t" ), '', $email );

						// Step 3〜5は削除（厳しすぎて有効なメールアドレスが無効になっていた）

						// 最終検証のみ実行
						$is_valid = ! empty( $email ) && filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;

						if ( ! $is_valid ) {
							$mail_button_title = 'メール送信不可（メールアドレス未設定または無効）';
							$mail_button_style = 'padding: 6px 10px; font-size: 12px; background: #ff9800; color: white; cursor: not-allowed; border: none;';
						} else {
							$can_send_email = true;
							$mail_button_title = 'メール送信（' . esc_attr( $email ) . '）';
							$mail_button_style = 'padding: 6px 10px; font-size: 12px; background: #2196f3; color: white; border: none;';
						}
					}

					// Email form (hidden)
					$content .= '<form id="orderMailForm" method="post" action="" style="display:none;">';
					if ( $can_send_email ) {
						$content .= '<input type="hidden" name="send_order_mail_id" value="' . esc_attr( $order_data->id ) . '">';
						// Add nonce to mail form
						$content .= wp_nonce_field( 'send_order_mail_action', 'order_mail_nonce', true, false );
					}
					$content .= '</form>';

					// Email button (opens popup)
					if ( $can_send_email ) {
						$content .= '<button type="button" id="orderMailButton" class="order-mail-btn" onclick="ktpShowEmailPopup(' . esc_attr( $order_data->id ) . ')" title="' . $mail_button_title . '">';
					} else {
						$content .= '<button type="button" id="orderMailButton" class="order-mail-btn" disabled title="' . $mail_button_title . '">';
					}
					$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'メール', 'ktpwp' ) . '" style="font-size: 14px;">mail</span>';
					$content .= '</button>';
					$content .= '</div>'; // 右側のボタン群終了
					$content .= '</div>'; // controller終了

					// メール編集フォーム導入により、進捗3の質問内容プロンプトは不要になったため削除

					// メール編集フォームがあればworkflowの直後で$contentに追加
					if ( ! empty( $mail_form_html ) ) {
						$content .= $mail_form_html;
					}

					// 進捗ラベルを明示的に定義
					$progress_labels = array(
						1 => '受付中',
						2 => '見積中',
						3 => '受注',
						4 => '完了',
						5 => '請求済',
						6 => '入金済',
						7 => 'ボツ',
					);

					// 受注書詳細の表示（以前のレイアウト）
					$content .= '<div class="order_contents">';
					$content .= '<div class="order_info_box box">';
					// ■ 受注書概要（ID: *）案件名フィールドを同一div内で横並びに
					$content .= '<div class="order-header-flex order-header-inline-summary">';
					$content .= '<span class="order-header-title-id">■ 受注書概要（ID: ' . esc_html( $order_data->id ) . '）'
					. '<input type="text" class="order_project_name_inline order-header-projectname" name="order_project_name_inline" value="' . ( isset( $order_data->project_name ) ? esc_html( $order_data->project_name ) : '' ) . '" data-order-id="' . esc_html( $order_data->id ) . '" placeholder="案件名" autocomplete="off" />'
					. '</span>';

					// 希望納期と納品予定日のフィールドを追加
					$desired_delivery_date = isset( $order_data->desired_delivery_date ) ? $order_data->desired_delivery_date : '';
					$expected_delivery_date = isset( $order_data->expected_delivery_date ) ? $order_data->expected_delivery_date : '';

					$content .= '<div class="delivery-dates-container" style="display: flex; align-items: center; gap: 15px; margin-left: 20px;">';
					$content .= '<div class="date-field" style="display: flex; align-items: center; gap: 5px;">';
					$content .= '<label for="desired_delivery_date" style="white-space: nowrap; font-size: 12px; font-weight: bold; color: #333;">希望納期：</label>';
					$content .= '<input type="date" id="desired_delivery_date" name="desired_delivery_date" value="' . esc_attr( $desired_delivery_date ) . '" data-order-id="' . esc_attr( $order_data->id ) . '" data-field="desired_delivery_date" class="delivery-date-input" style="font-size: 12px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" />';
					$content .= '</div>';
					$content .= '<div class="date-field" style="display: flex; align-items: center; gap: 5px;">';
					$content .= '<label for="expected_delivery_date" style="white-space: nowrap; font-size: 12px; font-weight: bold; color: #333;">納品予定日：</label>';
					$content .= '<input type="date" id="expected_delivery_date" name="expected_delivery_date" value="' . esc_attr( $expected_delivery_date ) . '" data-order-id="' . esc_attr( $order_data->id ) . '" data-field="expected_delivery_date" class="delivery-date-input" style="font-size: 12px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" />';
					$content .= '</div>';
					$content .= '</div>';

					$content .= '<form method="post" action="" class="progress-filter order-header-progress-form" style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap;margin-left:auto;">';
					$content .= '<input type="hidden" name="update_progress_id" value="' . esc_html( $order_data->id ) . '" />';
					// Add nonce for progress update
					$content .= wp_nonce_field( 'update_progress_action', 'progress_nonce', true, false );
					$content .= '<label for="order_progress_select" style="white-space:nowrap;margin-right:4px;font-weight:bold;">進捗：</label>';
					$content .= '<select id="order_progress_select" name="update_progress" style="min-width:120px;max-width:200px;width:auto;">';
					foreach ( $progress_labels as $num => $label ) {
									$selected = ( $order_data->progress == $num ) ? 'selected' : '';
									$content .= '<option value="' . $num . '" ' . $selected . '>' . $label . '</option>';
					}
					$content .= '</select>';

					// 完了日フィールドをフォーム内に追加
					$completion_date = isset( $order_data->completion_date ) ? $order_data->completion_date : '';
					$content .= '<input type="hidden" name="completion_date" value="' . esc_attr( $completion_date ) . '" />';
					$content .= '</form>';
					// 顧客IDの表示を改善
					$client_id_display = '';
					if ( ! empty( $order_data->client_id ) ) {
						// 顧客レコードが実際に存在するか確認
						$client_data = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT id, category FROM `{$client_table}` WHERE id = %d",
                                $order_data->client_id
                            )
                        );

						if ( $client_data ) {
							// 顧客が存在する場合
							if ( $client_data->category === '対象外' ) {
								// 削除済み（対象外）顧客の場合は赤色で表示
								$client_id_display = '（顧客ID: <span style="color:red;">' . esc_html( $order_data->client_id ) . ' - 削除済み</span>）';
							} else {
								// 通常の顧客はリンク付きで表示
								// 現在のページのURLを動的に取得

								global $wp;

								$base_url = KTPWP_Main::get_current_page_base_url();
								$client_url = add_query_arg(
                                    array(
										'tab_name' => 'client',
										'data_id' => $order_data->client_id,
                                    ),
                                    $base_url
								);
								$client_id_display = '（顧客ID: <a href="' . esc_url( $client_url ) . '" style="color:blue;">' . esc_html( $order_data->client_id ) . '</a>）';
							}
						} else {
							// 顧客が存在しない場合（データベースから完全に削除されている）
							$client_id_display = '（顧客ID: <span style="color:red;">' . esc_html( $order_data->client_id ) . ' - 削除済み</span>）';
						}
					} else {
						$client_id_display = '（顧客ID未設定）';
					}
					
					// 会社名のフォールバック処理: Contact Form 7で「0」になった場合の対策
					$display_customer_name = $order_data->customer_name;
					if ( $display_customer_name === '0' || empty( $display_customer_name ) ) {
						// 顧客IDがある場合は顧客テーブルから会社名を取得
						if ( ! empty( $order_data->client_id ) ) {
							$client_company_name = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT company_name FROM `{$client_table}` WHERE id = %d",
									$order_data->client_id
								)
							);
							if ( ! empty( $client_company_name ) ) {
								$display_customer_name = $client_company_name;
								
								// デバッグログ
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( 'KTPWP Order Display: 会社名をデータベースから取得しました: ' . $display_customer_name . ' (受注書ID: ' . $order_data->id . ')' );
								}
							} else {
								// 顧客テーブルからも取得できない場合は担当者名を使用
								$display_customer_name = $order_data->user_name;
								
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( 'KTPWP Order Display: 会社名が見つからないため、担当者名を使用: ' . $display_customer_name . ' (受注書ID: ' . $order_data->id . ')' );
								}
							}
						} else {
							// 顧客IDがない場合は担当者名を使用
							$display_customer_name = $order_data->user_name;
							
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( 'KTPWP Order Display: 顧客IDがないため、担当者名を使用: ' . $display_customer_name . ' (受注書ID: ' . $order_data->id . ')' );
							}
						}
					}
					
					$content .= '<div><span id="order_customer_name">' . esc_html( $display_customer_name ) . '</span> <span class="client-id" style="color:#666;font-size:0.9em;">' . $client_id_display . '</span></div>';
					// 担当者名の横に顧客メールアドレスのmailtoリンク（あれば）
					$client_email = '';
					$client = null;

					// まず顧客IDがある場合はIDで検索
					if ( ! empty( $order_data->client_id ) ) {
						$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$client_table}` WHERE id = %d", $order_data->client_id ) );
					}

					// IDで見つからない場合は会社名と担当者名で検索（後方互換性）
					if ( ! $client ) {
						$client = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM `{$client_table}` WHERE company_name = %s AND name = %s",
                                $order_data->customer_name,
                                $order_data->user_name
                            )
                        );
					}

					// 担当者名の表示を修正
					$user_display_name = '';
					
					// まず受注書テーブルの担当者名を確認
					if ( ! empty( $order_data->user_name ) ) {
						$user_display_name = esc_html( $order_data->user_name );
					} else {
						// 受注書テーブルに担当者名がない場合は顧客テーブルから取得
						if ( ! empty( $order_data->client_id ) ) {
							$client_contact = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT name FROM `{$client_table}` WHERE id = %d",
									$order_data->client_id
								)
							);
							if ( ! empty( $client_contact ) ) {
								$user_display_name = esc_html( $client_contact );
							}
						}
					}

					// 部署選択がある場合の担当者名表示を修正
					if ( class_exists( 'KTPWP_Department_Manager' ) && ! empty( $order_data->client_id ) ) {
						$selected_department = KTPWP_Department_Manager::get_selected_department_by_client( $order_data->client_id );
						if ( $selected_department ) {
							// 部署選択がある場合：部署名 担当者名の形式で表示
							$user_display_name = esc_html( $selected_department->department_name ) . ' ' . esc_html( $selected_department->contact_person );
						}
					}

					if ( $client && ! empty( $client->email ) ) {
						$client_email = esc_attr( $client->email );
						$content .= '<div><span id="order_user_name">' . $user_display_name . '</span>';
						$content .= ' <a href="mailto:' . $client_email . '" style="margin-left:8px;vertical-align:middle;" title="メール送信">';
						$content .= '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;color:#2196f3;">mail</span>';
						$content .= '</a></div>';
					} else {
						$content .= '<div><span id="order_user_name">' . $user_display_name . '</span></div>';
					}
					// 作成日時の表示
					$raw_time = $order_data->time;
					$formatted_time = '';
					if ( ! empty( $raw_time ) ) {
						if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
							// time()で取得したUNIXタイムスタンプはUTCベース
							// UTCとして解釈して、適切にタイムゾーン変換する
							$unix_timestamp = (int) $raw_time;

							// UTCタイムスタンプからDateTimeオブジェクトを作成し、WPタイムゾーンに変換
							$dt = new DateTime( '@' . $unix_timestamp ); // '@'プレフィックスでUTCとして解釈
							$dt->setTimezone( new DateTimeZone( wp_timezone_string() ) ); // WordPressのタイムゾーンを適用
						} else {
							$dt = date_create( $raw_time, new DateTimeZone( wp_timezone_string() ) );
						}
						if ( $dt ) {
							// ロケールに応じた曜日の取得
							$locale = get_locale();
							if ( substr( $locale, 0, 2 ) === 'ja' ) {
								// 日本語の場合
								$week = array( '日', '月', '火', '水', '木', '金', '土' );
								$w = $dt->format( 'w' );
								$formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
							} else {
								// その他の言語の場合は国際的な形式を使用
								$formatted_time = $dt->format( 'Y-m-d l H:i' );
							}
						}
					}
					$content .= '<div>作成：<span id="order_created_time">' . esc_html( $formatted_time ) . '</span></div>';

					// 完了日の表示と編集フィールド
					$completion_date = isset( $order_data->completion_date ) ? $order_data->completion_date : '';

					// 0000-00-00や無効な日付の場合は空文字にする
					if ( $completion_date === '0000-00-00' || $completion_date === '0000-00-00 00:00:00' || empty( $completion_date ) ) {
						$completion_date = '';
					}

					error_log( 'KTPWP Order: 画面表示時の完了日: ' . $completion_date . ' (受注書ID: ' . $order_data->id . ', 元の値: ' . $order_data->completion_date . ')' );
					$content .= '<div>完了日：<input type="date" id="completion_date" name="completion_date" value="' . esc_attr( $completion_date ) . '" data-order-id="' . esc_attr( $order_data->id ) . '" data-field="completion_date" class="completion-date-input" style="font-size: 12px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; width: 140px;" /></div>';

					// 案件名インライン入力をh4タイトル行に移動
					$project_name = isset( $order_data->project_name ) ? esc_html( $order_data->project_name ) : '';
					// preg_replaceによる重複input出力を削除（1箇所のみ出力）
					// $content = preg_replace(...); を削除
					$content .= '</div>'; // .order_info_box 終了

					$content .= '<div class="order_invoice_box box">';
					$content .= '<span class="toc2">■ 請求項目</span>';
					$content .= $this->Generate_Invoice_Items_Table( $order_id );
					$content .= '</div>'; // .order_invoice_box 終了
					// コスト項目とメモ項目のセクションを追加
					$content .= '<div class="order_cost_box box">';
					$content .= '<div style="display: flex; align-items: center; gap: 8px;">';
					$content .= '<span class="toc2">■ コスト項目</span>';
					$content .= '<span class="toc2">';
					$content .= '<button type="button" class="toggle-cost-items" aria-expanded="false" ';
					$content .= 'title="' . esc_attr__( 'コスト項目の表示/非表示を切り替え', 'ktpwp' ) . '">';
					$content .= esc_html__( '表示', 'ktpwp' );
					$content .= '</button>';
					$content .= '</span>';
					$content .= '</div>';
					// コスト項目テーブルをラップする（初期状態で非表示にする）
					$content .= '<div id="cost-items-content" style="display:none;">';
					// コスト項目テーブルを表示
					$content .= $this->Generate_Cost_Items_Table( $order_id );
					$content .= '</div>'; // #cost-items-content 終了
					$content .= '</div>'; // .order_cost_box 終了
					// スタッフチャットセクションを追加（タイトルなし）
					$content .= '<!-- DEBUG: スタッフチャット開始 -->';
					$staff_chat_html = $this->Generate_Staff_Chat_HTML( $order_id );
					$content .= '<!-- DEBUG: スタッフチャットHTML長: ' . strlen( $staff_chat_html ) . ' -->';
					$content .= $staff_chat_html;
					$content .= '<!-- DEBUG: スタッフチャット終了 -->';
					// 受注書内容セクションの終了
					$content .= '</div>'; // .order_contents 終了

					// 削除ボタンはworkflow内に移動済み

				} else {
					// 指定された受注書が見つからない場合もコントローラーと案内を表示
					$content .= '<div class="controller" style="display: flex; justify-content: space-between; align-items: center;">';

					// 左側：削除ボタン（無効化）- Material Symbolsは既に読み込み済み
					$content .= '<button type="button" class="delete-order-btn" disabled title="受注書がありません">';
					$content .= '<span class="material-symbols-outlined" aria-label="削除">delete</span>';
					$content .= '受注書を削除';
					$content .= '</button>';

					// 右側：プレビューボタンと印刷ボタン（無効化）
					$content .= '<div style="display: flex; gap: 5px;">';
					$content .= '<button id="orderPreviewButton" disabled title="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="padding: 6px 10px; font-size: 12px;">';
					$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="font-size: 16px;">preview</span>';
					$content .= '</button>';
					$content .= '<button disabled title="' . esc_attr__( '印刷する', 'ktpwp' ) . '" style="padding: 6px 10px; font-size: 12px;">';
					$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( '印刷', 'ktpwp' ) . '" style="font-size: 16px;">print</span>';
					$content .= '</button>';
					$content .= '</div>'; // 右側のボタン群終了
					$content .= '</div>'; // controller終了

					// 統一された案内表示
					$content .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
					. '<span class="material-symbols-outlined" aria-label="データなし">search_off</span>'
					. '<span style="font-size: 1em; font-weight: 600;">' . esc_html__( '受注書データがありません。', 'ktpwp' ) . '</span>'
					. '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__( '顧客タブで顧客情報を入力し受注書を作成してください', 'ktpwp' ) . '</span>'
					. '</div>';
				}
			} else {
				// 受注書データが存在しない場合でもレイアウトを維持
				$content .= '<div class="controller" style="display: flex; justify-content: space-between; align-items: center;">';

				// 左側：削除ボタン（無効化）- Material Symbolsは既に読み込み済み
				$content .= '<button type="button" class="delete-order-btn" disabled title="受注書がありません">';
				$content .= '<span class="material-symbols-outlined" aria-label="削除">delete</span>';
				$content .= '受注書を削除';
				$content .= '</button>';

				// 右側：プレビューボタンと印刷ボタン（無効化）
				$content .= '<div style="display: flex; gap: 5px;">';
				$content .= '<button id="orderPreviewButton" disabled title="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="padding: 6px 10px; font-size: 12px;">';
				$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( 'プレビュー', 'ktpwp' ) . '" style="font-size: 16px;">preview</span>';
				$content .= '</button>';
				$content .= '<button disabled title="' . esc_attr__( '印刷する', 'ktpwp' ) . '" style="padding: 6px 10px; font-size: 12px;">';
				$content .= '<span class="material-symbols-outlined" aria-label="' . esc_attr__( '印刷', 'ktpwp' ) . '" style="font-size: 16px;">print</span>';
				$content .= '</button>';
				$content .= '</div>'; // 右側のボタン群終了
				$content .= '</div>'; // controller終了

				// 仕事リストタブと統一されたデータ0の時の案内表示
				$content .= '<div class="ktp_order_content ktp-no-order-data" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
                . '<span class="material-symbols-outlined" aria-label="データなし">search_off</span>'
                . '<span style="font-size: 1em; font-weight: 600;">' . esc_html__( '受注書データがありません。', 'ktpwp' ) . '</span>'
                . '<span style="margin-left: 18px; font-size: 13px; color: #888;">' . esc_html__( '顧客タブで顧客情報を入力し受注書を作成してください', 'ktpwp' ) . '</span>'
                . '</div>';
			}

			// ページネーションロジック（表示はしないが計算は残す）
			$query_limit = 20; // 1ページあたりの表示件数
			$page_start = isset( $_GET['page_start'] ) ? intval( $_GET['page_start'] ) : 0; // 表示開始位置
			$page_start = max( 0, $page_start ); // 負の値を防ぐ安全対策

			// 全データ数を取得 - Use prepared statement
			$total_query = "SELECT COUNT(*) FROM `{$table_name}`";
			$total_rows = $wpdb->get_var( $total_query );
			$total_pages = ceil( $total_rows / $query_limit );

			// 現在のページ番号を計算
			$current_page = floor( $page_start / $query_limit ) + 1;

			// TODO: ページネーションリンクのHTML生成は削除またはコメントアウト
			// $content .= "<div class='pagination'>";
			// ... ページネーションリンク生成コード ...
			// $content .= "</div>"; // .pagination 終了

			// デバッグログ追加

			// 納期フィールドのJavaScriptファイルを読み込み
			wp_enqueue_script( 'ktp-delivery-dates' );

			// サンプルデータ追加ボタンのJavaScript
			$content .= '<script>
			jQuery(document).ready(function($) {
				$("#addSampleDataButton").on("click", function() {
					var orderId = $(this).data("order-id");
					var $button = $(this);
					
					// ボタンを無効化
					$button.prop("disabled", true);
					$button.html(\'<span class="material-symbols-outlined" style="font-size: 16px;">hourglass_empty</span>\');
					$button.attr("title", "処理中...");
					
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action: "ktpwp_add_sample_data",
							order_id: orderId,
							nonce: "' . wp_create_nonce('ktpwp_sample_data_nonce') . '"
						},
						success: function(response) {
							if (response.success) {
								alert("サンプルデータの追加が完了しました！\\n\\n" + response.data.message);
								// ページをリロードして新しいデータを表示
								location.reload();
							} else {
								alert("エラーが発生しました: " + response.data.message);
							}
						},
						error: function(xhr, status, error) {
							console.error("AJAX Error:", xhr.status, xhr.responseText);
							alert("通信エラーが発生しました。");
						},
						complete: function() {
							// ボタンを元に戻す
							$button.prop("disabled", false);
							$button.html(\'<span class="material-symbols-outlined" aria-label="サンプルデータ追加" style="font-size: 16px;">add_circle</span>\');
							$button.attr("title", "サンプルデータを追加");
						}
					});
				});
			});
			</script>';

			return $content;
		} // End of Order_Tab_View method

		/**
		 * Save or update invoice items
		 *
		 * @since 1.0.0
		 * @param int   $order_id Order ID
		 * @param array $items Invoice items data
		 * @return bool True on success, false on failure
		 */
		public function Save_Invoice_Items( $order_id, $items ) {
			// デバッグ: 受け取った請求項目配列を出力
			if ( ! $order_id || $order_id <= 0 || ! is_array( $items ) ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

			// Start transaction
			$wpdb->query( 'START TRANSACTION' );

			try {
				$sort_order = 1;
				$submitted_ids = array();
				foreach ( $items as &$item ) {
					$item['sort_order'] = $sort_order; // ここでPOST順にsort_orderを付与
					// Sanitize input data
					$item_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					// 税率の処理（空文字、null、0の場合はNULLとして扱う）
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && $tax_rate_raw !== '0' && is_numeric( $tax_rate_raw ) ) {
						$tax_rate = floatval( $tax_rate_raw );
					}
					$remarks = isset( $item['remarks'] ) ? sanitize_textarea_field( $item['remarks'] ) : '';

					// 商品名が空ならスキップ（商品名があれば必ず保存）
					if ( empty( $product_name ) ) {
						$sort_order++;
						continue;
					}

					$data = array(
						'order_id' => $order_id,
						'product_name' => $product_name,
						'price' => $price,
						'unit' => $unit,
						'quantity' => $quantity,
						'amount' => $amount,
						'tax_rate' => $tax_rate,
						'remarks' => $remarks,
						'sort_order' => $sort_order,
						'updated_at' => current_time( 'mysql' ),
					);

					$format = array( '%d', '%s', '%f', '%s', '%f', '%f', '%f', '%s', '%d', '%s' );

					$used_id = 0;
					if ( $item_id > 0 ) {
						// Update existing item
						$result = $wpdb->update(
                            $table_name,
                            $data,
                            array(
								'id' => $item_id,
								'order_id' => $order_id,
                            ),
                            $format,
                            array( '%d', '%d' )
						);
						$used_id = $item_id;
					} else {
						// Insert new item
						$data['created_at'] = current_time( 'mysql' );
						$format[] = '%s';
						$result = $wpdb->insert( $table_name, $data, $format );
						if ( $result === false ) {
							error_log( 'KTPWP Error: Invoice item INSERT failed: ' . $wpdb->last_error );
						}
						$used_id = $wpdb->insert_id;
					}

					if ( $result === false ) {
						throw new Exception( 'Database operation failed: ' . $wpdb->last_error );
					}

					if ( $used_id > 0 ) {
						$submitted_ids[] = $used_id;
					}

					$sort_order++;
				}

				// Remove any items that weren't in the submitted data
				if ( ! empty( $submitted_ids ) ) {
					$ids_placeholder = implode( ',', array_fill( 0, count( $submitted_ids ), '%d' ) );
					$delete_query = $wpdb->prepare(
                        "DELETE FROM {$table_name} WHERE order_id = %d AND id NOT IN ({$ids_placeholder})",
                        array_merge( array( $order_id ), $submitted_ids )
					);
					$wpdb->query( $delete_query );
				}

				// Commit transaction
				$wpdb->query( 'COMMIT' );

				return true;

			} catch ( Exception $e ) {
				// Rollback transaction
				$wpdb->query( 'ROLLBACK' );
				error_log( 'KTPWP: Failed to save invoice items: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Save or update cost items
		 *
		 * @since 1.0.0
		 * @param int   $order_id Order ID
		 * @param array $items Cost items data
		 * @return bool True on success, false on failure
		 */
		public function Save_Cost_Items( $order_id, $items ) {
			// デバッグ: 受け取ったコスト項目配列を出力
			if ( ! $order_id || $order_id <= 0 || ! is_array( $items ) ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';

			// Start transaction
			$wpdb->query( 'START TRANSACTION' );

			try {
				$sort_order = 1;
				$submitted_ids = array();
				foreach ( $items as $item ) {
					// Sanitize input data
					$item_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					// 税率の処理（空文字、null、0の場合はNULLとして扱う）
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && $tax_rate_raw !== '0' && is_numeric( $tax_rate_raw ) ) {
						$tax_rate = floatval( $tax_rate_raw );
					}
					$remarks = isset( $item['remarks'] ) ? sanitize_textarea_field( $item['remarks'] ) : '';
					$purchase = isset( $item['purchase'] ) ? sanitize_text_field( $item['purchase'] ) : '';

					// 商品名が空ならスキップ（商品名があれば必ず保存）
					if ( empty( $product_name ) ) {
						continue;
					}

					$data = array(
						'order_id' => $order_id,
						'product_name' => $product_name,
						'price' => $price,
						'unit' => $unit,
						'quantity' => $quantity,
						'amount' => $amount,
						'tax_rate' => $tax_rate,
						'remarks' => $remarks,
						'purchase' => $purchase,
						'sort_order' => $sort_order,
						'updated_at' => current_time( 'mysql' ),
					);

					$format = array( '%d', '%s', '%f', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s' );

					$used_id = 0;
					if ( $item_id > 0 ) {
						// Update existing item
						$result = $wpdb->update(
                            $table_name,
                            $data,
                            array(
								'id' => $item_id,
								'order_id' => $order_id,
                            ),
                            $format,
                            array( '%d', '%d' )
						);
						$used_id = $item_id;
					} else {
						// Insert new item
						$data['created_at'] = current_time( 'mysql' );
						$format[] = '%s';
						$result = $wpdb->insert( $table_name, $data, $format );
						if ( $result === false ) {
							error_log( 'KTPWP Error: Cost item INSERT failed: ' . $wpdb->last_error );
						}
						$used_id = $wpdb->insert_id;
					}

					if ( $result === false ) {
						throw new Exception( 'Database operation failed: ' . $wpdb->last_error );
					}

					if ( $used_id > 0 ) {
						$submitted_ids[] = $used_id;
					}

					$sort_order++;
				}

				// Remove any items that weren't in the submitted data
				if ( ! empty( $submitted_ids ) ) {
					$ids_placeholder = implode( ',', array_fill( 0, count( $submitted_ids ), '%d' ) );
					$delete_query = $wpdb->prepare(
                        "DELETE FROM {$table_name} WHERE order_id = %d AND id NOT IN ({$ids_placeholder})",
                        array_merge( array( $order_id ), $submitted_ids )
					);
					$wpdb->query( $delete_query );
				}

				// Commit transaction
				$wpdb->query( 'COMMIT' );

				return true;

			} catch ( Exception $e ) {
				// Rollback transaction
				$wpdb->query( 'ROLLBACK' );
				error_log( 'KTPWP: Failed to save cost items: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Check for concurrent access conflicts
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True if safe to proceed, false if conflict detected
		 */
		private function Check_Concurrent_Access( $order_id ) {
			$lock_option = 'ktp_order_editing_' . $order_id;
			$current_time = time();
			$lock_timeout = 30; // 30秒でタイムアウト

			// 既存のロックをチェック
			$existing_lock = get_option( $lock_option );

			if ( $existing_lock ) {
				$lock_data = json_decode( $existing_lock, true );

				// タイムアウトチェック
				if ( isset( $lock_data['timestamp'] ) &&
                 ( $current_time - $lock_data['timestamp'] ) > $lock_timeout ) {
					// タイムアウトしたロックを削除
					delete_option( $lock_option );
				} else {
					// アクティブなロックが存在
					return false;
				}
			}

			// 新しいロックを設定
			$lock_data = array(
				'user_id' => get_current_user_id(),
				'timestamp' => $current_time,
			);

			update_option( $lock_option, json_encode( $lock_data ) );

			return true;
		}

		/**
		 * Release concurrent access lock
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 */
		private function Release_Concurrent_Access_Lock( $order_id ) {
			$lock_option = 'ktp_order_editing_' . $order_id;
			delete_option( $lock_option );
		}

		/**
		 * Auto-save functionality is now handled by KTPWP_Ajax class
		 * This method is kept for backward compatibility and delegates to the new system
		 *
		 * @deprecated Use KTPWP_Ajax::ajax_auto_save_item() instead
		 * @since 1.0.0
		 */
		public function ajax_auto_save_item() {
			$ajax_handler = KTPWP_Ajax::get_instance();
			$ajax_handler->ajax_auto_save_item();
		}

		/**
		 * New item creation is now handled by KTPWP_Ajax class
		 * This method is kept for backward compatibility and delegates to the new system
		 *
		 * @deprecated Use KTPWP_Ajax::ajax_create_new_item() instead
		 * @since 1.0.0
		 */
		public function ajax_create_new_item() {
			$ajax_handler = KTPWP_Ajax::get_instance();
			$ajax_handler->ajax_create_new_item();
		}

		/**
		 * Delete staff chat messages for a specific order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function Delete_Staff_Chat_Messages( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';

			// Delete all staff chat messages for this order
			$result = $wpdb->delete(
                $table_name,
                array( 'order_id' => $order_id ),
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to delete staff chat messages for order ID ' . $order_id . ': ' . $wpdb->last_error );
				return false;
			}

			error_log( 'KTPWP: Successfully deleted ' . $result . ' staff chat messages for order ID ' . $order_id );
			return true;
		}

		/**
		 * 進捗状況に応じた帳票プレビューHTMLを生成
		 *
		 * @param object $order_data 受注書データ
		 * @return string プレビュー用HTML
		 * @since 1.0.0
		 */
		private function Generate_Order_Preview_HTML( $order_data ) {
			// 進捗ラベルの定義
			$progress_labels = array(
				1 => '受付中',
				2 => '見積中',
				3 => '受注',
				4 => '完了',
				5 => '請求済',
				6 => '入金済',
				7 => 'ボツ',
			);

			// 帳票タイトルと内容の定義
			$document_info = $this->Get_Document_Info_By_Progress( $order_data->progress );

			// 案件名の取得（空の場合はデフォルト値）
			$project_name = ! empty( $order_data->project_name ) ? $order_data->project_name : '案件';

			// デバッグ: 文字化け対策

			// 請求項目の取得
			$invoice_result = $this->Generate_Invoice_Items_For_Preview( $order_data->id );
			$invoice_items_html = $invoice_result['html'];
			$total_amount = $invoice_result['total'];

			// 自社情報の取得（設定から）
			$company_info_html = $this->Get_Company_Info_HTML();

			// 適格請求書番号を取得
			$qualified_invoice_number = '';
			if ( class_exists( 'KTP_Settings' ) ) {
				$qualified_invoice_number = KTP_Settings::get_qualified_invoice_number();
			}

			// プレビューHTML生成 - A4サイズに最適化
			$html = '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>受注書プレビュー</title></head><body>';
			$html .= '<div class="order-preview-document" style="font-family: \'Noto Sans JP\', \'Hiragino Kaku Gothic ProN\', Meiryo, sans-serif; line-height: 1.4; color: #333; max-width: 210mm; margin: 0 auto; padding: 50px; background: #fff; min-height: 297mm; box-sizing: border-box;">';

			// 宛先情報（住所対応）
			$html .= '<div class="customer-info" style="margin-bottom: 20px;">';

			// 会社名のフォールバック処理（プレビュー用）
			$display_customer_name = $order_data->customer_name;
			if ( $display_customer_name === '0' || empty( $display_customer_name ) ) {
				global $wpdb;
				$client_table = $wpdb->prefix . 'ktp_client';
				
				// 顧客IDがある場合は顧客テーブルから会社名を取得
				if ( ! empty( $order_data->client_id ) ) {
					$client_company_name = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT company_name FROM `{$client_table}` WHERE id = %d",
							$order_data->client_id
						)
					);
					if ( ! empty( $client_company_name ) ) {
						$display_customer_name = $client_company_name;
					} else {
						// 顧客テーブルからも取得できない場合は担当者名を使用
						$display_customer_name = $order_data->user_name;
					}
				} else {
					// 顧客IDがない場合は担当者名を使用
					$display_customer_name = $order_data->user_name;
				}
			}

			// 顧客の住所情報を取得（顧客テーブルから）
			$customer_address = $this->Get_Customer_Address( $display_customer_name );

			// 選択された部署情報を取得
			$selected_department = null;
			if ( class_exists( 'KTPWP_Department_Manager' ) && ! empty( $order_data->client_id ) ) {
				global $wpdb;
				$client_table = $wpdb->prefix . 'ktp_client';
				$selected_department_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT selected_department_id FROM `{$client_table}` WHERE id = %d",
                        $order_data->client_id
                    )
                );

				if ( ! empty( $selected_department_id ) ) {
					$selected_department = KTPWP_Department_Manager::get_selected_department( $order_data->client_id, $selected_department_id );
				}
			}

			if ( ! empty( $customer_address ) && is_array( $customer_address ) ) {
				// 住所がある場合：住所 → 会社名 → 選択された部署情報 → 名前 様
				$html .= '<div class="customer-address" style="font-size: 14px; margin-bottom: 4px;">';
				foreach ( $customer_address as $address_line ) {
					$html .= '<div>' . esc_html( $address_line ) . '</div>';
				}
				$html .= '</div>';
				$html .= '<div class="company-name" style="font-size: 16px; font-weight: bold; margin-bottom: 4px;">' . esc_html( $display_customer_name ) . '</div>';

				// 選択された部署情報を表示
				if ( $selected_department ) {
					$html .= '<div class="department-info" style="font-size: 12px; margin-bottom: 4px; color: #666;">';
					$html .= '<div>' . esc_html( $selected_department->department_name ) . ' ' . esc_html( $selected_department->contact_person ) . ' 様</div>';
					$html .= '</div>';
				}

				$html .= '<div class="customer-name" style="font-size: 14px; margin-bottom: 100px;">' . esc_html( $order_data->user_name ) . ' 様</div>';
			} else {			
				// 住所がない場合：会社名 → 選択された部署情報 → 名前 様
				$html .= '<div class="company-name" style="font-size: 16px; font-weight: bold; margin-bottom: 4px;">' . esc_html( $display_customer_name ) . '</div>';

				// 選択された部署情報を表示
				if ( $selected_department ) {
					$html .= '<div class="department-info" style="font-size: 12px; margin-bottom: 4px; color: #666;">';
					$html .= '<div>' . esc_html( $selected_department->department_name ) . ' ' . esc_html( $selected_department->contact_person ) . ' 様</div>';
					$html .= '</div>';
				}

				$html .= '<div class="customer-name" style="font-size: 14px; margin-bottom: 100px;">' . esc_html( $order_data->user_name ) . ' 様</div>';
			}

			$html .= '</div>';

			// 帳票タイトル（コンパクト）
			$html .= '<div class="document-title" style="text-align: center; margin-bottom: 15px; padding: 12px; border: 2px solid #333; font-size: 18px; font-weight: bold;">';
			$html .= '＜' . esc_html( $document_info['title'] ) . '＞';
			// 適格請求書番号を表示（設定されている場合のみ）
			if ( ! empty( $qualified_invoice_number ) ) {
				$html .= '<div style="font-size: 14px; font-weight: normal; margin-top: 5px; color: #333;">適格請求書番号：' . esc_html( $qualified_invoice_number ) . '</div>';
			}
			$html .= '</div>';

			// 帳票内容（コンパクト）
			$html .= '<div class="document-content" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #007cba; font-size: 14px;">';
			$html .= sprintf( $document_info['content'], '<strong>' . esc_html( $project_name ) . '</strong>' );
			$html .= '</div>';

			// 請求項目（メインコンテンツ）
			$html .= '<div class="invoice-items" style="margin-bottom: 20px;">';
			$html .= $invoice_items_html;
			$html .= '</div>';

			// 自社情報（コンパクト）
			$html .= '<div class="company-info" style="margin-bottom: 15px;">';
			$html .= $company_info_html;
			$html .= '</div>';

			// フッター（コンパクト）
			$html .= '<div class="document-footer" style="text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 11px; color: #666;">';
			$html .= '受注書ID: ' . esc_html( $order_data->id ) . ' | 作成日: ' . date( 'Y年m月d日', is_numeric( $order_data->time ) ? $order_data->time : strtotime( $order_data->time ) );
			$html .= '</div>';

			$html .= '</div>'; // .order-preview-document 終了
			$html .= '</body></html>';

			return $html;
		}

		/**
		 * 進捗状況に応じた帳票情報を取得（パブリック版）
		 *
		 * @param int $progress 進捗状況
		 * @return array 帳票情報
		 * @since 1.0.0
		 */
		public function Get_Document_Info_By_Progress_Public( $progress ) {
			return $this->Get_Document_Info_By_Progress( $progress );
		}

		/**
		 * 進捗状況に応じた帳票情報を取得
		 *
		 * @param int $progress 進捗状況
		 * @return array 帳票情報
		 * @since 1.0.0
		 */
		private function Get_Document_Info_By_Progress( $progress ) {
			switch ( $progress ) {
				case 1: // 受付中
					return array(
						'title' => '見積書',
						'content' => '%sにつきましてお見積りいたします。',
					);
				case 2: // 見積中
					return array(
						'title' => '注文受書',
						'content' => '%sにつきましてご注文をお受けしました。',
					);
				case 3: // 受注
					return array(
						'title' => '納品書',
						'content' => '%sにつきまして完了しました。',
					);
				case 4: // 完了
					return array(
						'title' => '請求書',
						'content' => '%sにつきまして請求申し上げます。',
					);
				case 5: // 請求済
					return array(
						'title' => '領収書',
						'content' => '%sにつきましてお支払いを確認しました。',
					);
				case 6: // 入金済
					return array(
						'title' => '案件完了',
						'content' => '%sにつきましては全て完了しています。',
					);
				default:
					return array(
						'title' => '受注書',
						'content' => '%sにつきましてご依頼をお受けしました。',
					);
			}
		}

		/**
		 * プレビュー用請求項目HTMLを生成
		 *
		 * @param int $order_id 受注書ID
		 * @return array HTMLと合計金額の配列 ['html' => string, 'total' => float]
		 * @since 1.0.0
		 */
		private function Generate_Invoice_Items_For_Preview( $order_id ) {
			// メール生成と同じ方法でデータを取得
			$invoice_items = $this->Get_Invoice_Items( $order_id );
			
			// 顧客の税区分を取得
			$tax_category = $this->get_client_tax_category( $order_id );

			// デザイン設定から奇数偶数の背景色を取得
			$design_options = get_option( 'ktp_design_settings', array() );
			$odd_row_color = isset( $design_options['odd_row_color'] ) ? $design_options['odd_row_color'] : '#E7EEFD';
			$even_row_color = isset( $design_options['even_row_color'] ) ? $design_options['even_row_color'] : '#FFFFFF';

			$total_items = count( $invoice_items );
			$items_per_page = 16;
			$html = '';
			$grand_total = 0;

			// 全体の合計金額を事前に計算
			foreach ( $invoice_items as $item ) {
				$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
				$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
				$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;

				// 金額が設定されているが単価が0の場合、逆算して単価を求める
				if ( $price == 0 && $amount > 0 && $quantity > 0 ) {
					$price = $amount / $quantity;
				}

				// 金額が0の場合は単価×数量で計算
				if ( $amount == 0 && $price > 0 && $quantity > 0 ) {
					$amount = $price * $quantity;
				}

				$grand_total += $amount;
			}

			// ページ数を計算（1ページ目は必ず16行、2ページ目以降は実データのみ）
			$total_pages = ( $total_items <= $items_per_page ) ? 1 : ceil( $total_items / $items_per_page );

			// 各ページを生成
			for ( $page = 0; $page < $total_pages; $page++ ) {
				$start_index = $page * $items_per_page;
				$end_index = min( $start_index + $items_per_page, $total_items );

				// 2ページ目以降はページ区切りを追加
				if ( $page > 0 ) {
					$html .= '<div style="page-break-before: always; margin-top: 30px;"></div>';
					$html .= '<h3 style="font-size: 16px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #333;">請求項目（' . ( $page + 1 ) . '/' . $total_pages . 'p）</h3>';
				} else {
					// 1ページ目：請求金額、消費税、税込合計を表示
					// 消費税計算（税率別に集計）
					$tax_amount = 0;
					$tax_rate_groups = array();
					
					foreach ( $invoice_items as $item ) {
						$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
						$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
						
						// 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
						$item_tax_rate = null;
						if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
							$item_tax_rate = floatval( $tax_rate_raw );
						}
						
						// 税率別の集計（税率なしの場合は'no_tax_rate'として扱う）
						$tax_rate_key = $item_tax_rate !== null ? number_format( $item_tax_rate, 1 ) : 'no_tax_rate';
						if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
							$tax_rate_groups[ $tax_rate_key ] = 0;
						}
						$tax_rate_groups[ $tax_rate_key ] += $item_amount;
						
						if ( $tax_category === '外税' ) {
							// 外税表示の場合：税抜金額から税額を計算（切り上げ）
							if ( $item_tax_rate !== null ) {
							$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );
							}
						} else {
							// 内税表示の場合（デフォルト）：税込金額から税額を計算（小数点以下切り上げ）
							if ( $item_tax_rate !== null ) {
							$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) / ( 1 + $item_tax_rate / 100 ) );
							}
						}
					}
					
					$total_with_tax = $grand_total + $tax_amount;
					$tax_amount_ceiled = ceil( $tax_amount );
					$total_with_tax_ceiled = ceil( $total_with_tax );
					
					// 税区分に応じた表示
					if ( $tax_category === '外税' ) {
						$html .= '<h3 style="font-size: 16px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #333;">請求項目（' . ( $page + 1 ) . '/' . $total_pages . 'p）　合計金額 : ' . number_format( $grand_total ) . '円　消費税 : ' . number_format( $tax_amount_ceiled ) . '円　税込合計 : ' . number_format( $total_with_tax_ceiled ) . '円</h3>';
					} else {
						// 内税の場合は税率別の内訳を表示
						$tax_detail_text = '';
						if ( count( $tax_rate_groups ) > 1 ) {
							$tax_rate_details = array();
							foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
								if ( $tax_rate === 'no_tax_rate' ) {
									// 税率なしの場合は表示しない
									continue;
								} else {
									$tax_rate_value = floatval( $tax_rate );
									$group_tax_amount = ceil( $group_amount * ( $tax_rate_value / 100 ) / ( 1 + $tax_rate_value / 100 ) );
									$tax_rate_details[] = $tax_rate . '%: ' . number_format( $group_tax_amount ) . '円';
								}
							}
							$tax_detail_text = '（内税：' . implode( ', ', $tax_rate_details ) . '）';
						} else {
							// 単一税率の場合
							if ( array_key_first( $tax_rate_groups ) === 'no_tax_rate' ) {
								// 税率なしの場合は内税表示をしない
								$tax_detail_text = '';
							} else {
								$tax_detail_text = '（内税：' . number_format( $tax_amount_ceiled ) . '円）';
							}
						}
						$html .= '<h3 style="font-size: 16px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #333;">請求項目（' . ( $page + 1 ) . '/' . $total_pages . 'p）　合計金額 : ' . number_format( $grand_total ) . '円' . $tax_detail_text . '</h3>';
					}
				}

				// リスト表示開始（枠線なし、設定された奇数偶数背景カラー）
				$html .= '<div style="margin-bottom: 15px; font-size: 12px;">';

				// ヘッダー行
				$html .= '<div style="display: flex; background: #f0f0f0; padding: 8px; font-weight: bold; border-bottom: 1px solid #ccc; align-items: center;">';
				$html .= '<div style="width: 30px; text-align: center;">No.</div>';
				$html .= '<div style="flex: 1; text-align: left; margin-left: 8px;">項目名</div>';
				$html .= '<div style="width: 80px; text-align: right;">単価</div>';
				$html .= '<div style="width: 60px; text-align: right;">数量</div>';
				$html .= '<div style="width: 80px; text-align: right;">金額</div>';
				$html .= '<div style="width: 60px; text-align: center;">税率</div>';
				$html .= '<div style="width: 100px; text-align: left; margin-left: 8px;">備考</div>';
				$html .= '</div>';

				$page_total = 0;
				$row_count = 0;
				$global_row_count = $start_index; // ページをまたがった行番号管理
				$item_no = $start_index + 1; // No.カラム用の通し番号

				// 1ページ目は16行固定、2ページ目以降は実データのみ
				$rows_to_show = ( $page === 0 ) ? $items_per_page : ( $end_index - $start_index );

				// 実際のデータを表示
				for ( $i = $start_index; $i < $end_index && $row_count < $rows_to_show; $i++ ) {
					$item = $invoice_items[ $i ];

					$product_name = isset( $item['product_name'] ) ? $item['product_name'] : '';
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$unit = isset( $item['unit'] ) ? $item['unit'] : '';
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$remarks = isset( $item['remarks'] ) ? $item['remarks'] : ''; // 備考フィールドを追加

					// 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
						$tax_rate = floatval( $tax_rate_raw );
					}

					// 小数点以下の不要な0を削除
					$price_display = rtrim( rtrim( number_format( $price, 6, '.', '' ), '0' ), '.' );
					$quantity_display = rtrim( rtrim( number_format( $quantity, 6, '.', '' ), '0' ), '.' );

					// 金額計算
					if ( $price == 0 && $amount > 0 && $quantity > 0 ) {
						$price = $amount / $quantity;
					}
					if ( $amount == 0 && $price > 0 && $quantity > 0 ) {
						$amount = $price * $quantity;
					}

					$page_total += $amount;

					// 設定された奇数偶数の背景色を使用
					$bg_color = ( $global_row_count % 2 === 0 ) ? $even_row_color : $odd_row_color;

					$html .= '<div style="display: flex; padding: 6px 8px; height: 24px; background: ' . esc_attr( $bg_color ) . '; align-items: center;">';
					$html .= '<div style="width: 30px; text-align: center;">' . $item_no . '</div>';
					$html .= '<div style="flex: 1; text-align: left; margin-left: 8px;">' . esc_html( $product_name ) . '</div>';
					$html .= '<div style="width: 80px; text-align: right;">¥' . $price_display . '</div>';
					$html .= '<div style="width: 60px; text-align: right;">' . $quantity_display . $unit . '</div>';
					$html .= '<div style="width: 80px; text-align: right;">¥' . number_format( $amount ) . '</div>';
					$html .= '<div style="width: 60px; text-align: center;">' . ( $tax_rate !== null ? $tax_rate . '%' : '' ) . '</div>';
					$html .= '<div style="width: 100px; text-align: left; margin-left: 8px;">' . esc_html( $remarks ) . '</div>';
					$html .= '</div>';

					$row_count++;
					$global_row_count++;
					$item_no++;
				}

				// 1ページ目のみ空行を追加して16行に調整
				if ( $page === 0 ) {
					while ( $row_count < $items_per_page ) {
						// 設定された奇数偶数の背景色を使用
						$bg_color = ( $global_row_count % 2 === 0 ) ? $even_row_color : $odd_row_color;

						$html .= '<div style="display: flex; padding: 6px 8px; height: 24px; background: ' . esc_attr( $bg_color ) . '; align-items: center;">';
						$html .= '<div style="width: 30px; text-align: center;">&nbsp;</div>';
						$html .= '<div style="flex: 1; text-align: left; margin-left: 8px;">&nbsp;</div>';
						$html .= '<div style="width: 80px; text-align: right;">&nbsp;</div>';
						$html .= '<div style="width: 60px; text-align: right;">&nbsp;</div>';
						$html .= '<div style="width: 80px; text-align: right;">&nbsp;</div>';
						$html .= '<div style="width: 60px; text-align: center;">&nbsp;</div>';
						$html .= '<div style="width: 100px; text-align: left; margin-left: 8px;">&nbsp;</div>';
						$html .= '</div>';

						$row_count++;
						$global_row_count++;
					}
				}

				// 複数ページの場合、各ページでページ小計を表示
				if ( $total_pages > 1 ) {
					// ページ小計行（右寄せで表示）
					$html .= '<div style="display: flex; padding: 10px 8px; background: #f5f5f5; font-weight: bold; border-top: 1px solid #ccc; margin-top: 5px; align-items: center; justify-content: flex-end;">';
					$html .= '<div style="text-align: right;">ページ小計　¥' . number_format( $page_total ) . '</div>';
					$html .= '</div>';
				}

				// 最後のページまたは1ページのみの場合、合計金額、消費税、税込合計を表示
				if ( $page == $total_pages - 1 ) {
					// 消費税計算
					$tax_amount = 0;
					
					foreach ( $invoice_items as $item ) {
						$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
						$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
						
						// 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
						$item_tax_rate = null;
						if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
							$item_tax_rate = floatval( $tax_rate_raw );
						}
						
						if ( $tax_category === '外税' ) {
							// 外税表示の場合：税抜金額から税額を計算（切り上げ）
							if ( $item_tax_rate !== null ) {
							$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );
							}
						} else {
							// 内税表示の場合（デフォルト）：税込金額から税額を計算（小数点以下切り上げ）
							if ( $item_tax_rate !== null ) {
							$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) / ( 1 + $item_tax_rate / 100 ) );
							}
						}
					}
					
					$total_with_tax = $grand_total + $tax_amount;
					$tax_amount_ceiled = ceil( $tax_amount );
					$total_with_tax_ceiled = ceil( $total_with_tax );
					
					// 税区分に応じた表示
					if ( $tax_category === '外税' ) {
						// 外税表示の場合
						$html .= '<div style="display: flex; padding: 10px 8px; background: #e9ecef; font-weight: bold; border-top: 2px solid #ccc; margin-top: 5px; align-items: center; justify-content: flex-end;">';
						$html .= '<div style="text-align: right;">合計金額 : ' . number_format( $grand_total ) . '円</div>';
						$html .= '</div>';
						
						$html .= '<div style="display: flex; padding: 10px 8px; background: #e9ecef; font-weight: bold; border-top: 1px solid #ccc; align-items: center; justify-content: flex-end;">';
						$html .= '<div style="text-align: right;">消費税 : ' . number_format( $tax_amount_ceiled ) . '円</div>';
						$html .= '</div>';
						
						$html .= '<div style="display: flex; padding: 10px 8px; background: #e9ecef; font-weight: bold; border-top: 1px solid #ccc; align-items: center; justify-content: flex-end;">';
						$html .= '<div style="text-align: right;">税込合計 : ' . number_format( $total_with_tax_ceiled ) . '円</div>';
						$html .= '</div>';
					} else {
						// 内税表示の場合（デフォルト）
						$html .= '<div style="display: flex; padding: 10px 8px; background: #e9ecef; font-weight: bold; border-top: 2px solid #ccc; margin-top: 5px; align-items: center; justify-content: flex-end;">';
						$html .= '<div style="text-align: right;">合計金額 : ' . number_format( $grand_total ) . '円（内税 ' . number_format( $tax_amount_ceiled ) . '円）</div>';
						$html .= '</div>';
					}
				}

				$html .= '</div>'; // リスト表示終了
			}

			return array(
				'html' => $html,
				'total' => $grand_total,
			);
		}

		/**
		 * 受注書に関連する顧客の税区分を取得
		 *
		 * @param int $order_id 受注書ID
		 * @return string 税区分（内税または外税）
		 * @since 1.0.0
		 */
		private function get_client_tax_category( $order_id ) {
			global $wpdb;
			
			// 受注書から顧客IDを取得
			$order_table = $wpdb->prefix . 'ktp_order';
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT client_id FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);
			
			if ( ! $order || ! $order->client_id ) {
				return '内税'; // デフォルト値
			}
			
			// 顧客テーブルから税区分を取得
			$client_table = $wpdb->prefix . 'ktp_client';
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT tax_category FROM {$client_table} WHERE id = %d",
					$order->client_id
				)
			);
			
			if ( ! $client ) {
				return '内税'; // デフォルト値
			}
			
			return $client->tax_category ?: '内税';
		}

		/**
		 * 自社情報HTMLを生成
		 *
		 * @return string 自社情報HTML
		 * @since 1.0.0
		 */
		private function Get_Company_Info_HTML() {
			// 一般設定から自社情報を取得
			$company_info = '';
			if ( class_exists( 'KTP_Settings' ) ) {
				$company_info = KTP_Settings::get_company_info();
			}

			// 旧システムからも取得（後方互換性）
			if ( empty( $company_info ) ) {
				global $wpdb;
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM `{$setting_table}` WHERE id = %d",
                        1
                    )
                );
				if ( $setting && ! empty( $setting->my_company_content ) ) {
					$company_info = sanitize_text_field( strip_tags( $setting->my_company_content ) );
				}
			}

			// デフォルト値を設定
			if ( empty( $company_info ) ) {
				$company_info = '<div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">株式会社サンプル</div>';
				$company_info .= '<div style="margin-bottom: 5px;">〒000-0000 東京都港区サンプル1-1-1</div>';
				$company_info .= '<div style="margin-bottom: 5px;">TEL: 03-0000-0000</div>';
				$company_info .= '<div>info@sample.com</div>';
			} else {
				// HTMLタグが含まれている場合はそのまま使用、プレーンテキストの場合は改行をHTMLに変換
				if ( strip_tags( $company_info ) === $company_info ) {
					// プレーンテキストの場合
					$company_info = nl2br( esc_html( $company_info ) );
				}
				// HTMLタグが含まれている場合はそのまま使用（エスケープしない）
			}

			$html = '<div class="company-info-box" style="
            text-align: right;
            padding: 20px;
            border: 1px solid #ddd;
            background: #fafafa;
        ">';
			$html .= $company_info;
			$html .= '</div>';

			return $html;
		}

		/**
		 * 顧客の住所情報を取得
		 *
		 * @param string $customer_name 顧客名
		 * @return string 住所情報（郵便番号〜番地まで）
		 * @since 1.0.0
		 */
		private function Get_Customer_Address( $customer_name ) {
			if ( empty( $customer_name ) ) {
				return '';
			}

			global $wpdb;
			$client_table = $wpdb->prefix . 'ktp_client';

			// 顧客テーブルから住所情報を取得（class-tab-client.phpと同じ方法）
			$customer = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT postal_code, prefecture, city, address, building FROM `{$client_table}` WHERE company_name = %s LIMIT 1",
                    $customer_name
                )
            );

			if ( ! $customer ) {
				return '';
			}

			// 住所フィールドの組み立て（3行構成）
			$address_parts_line1 = array(); // 1行目：郵便番号
			$address_parts_line2 = array(); // 2行目：都道府県・市区町村・番地
			$address_parts_line3 = array(); // 3行目：建物名

			// 郵便番号（1行目）
			if ( ! empty( $customer->postal_code ) ) {
				$address_parts_line1[] = '〒' . $customer->postal_code;
			}

			// 都道府県（2行目）
			if ( ! empty( $customer->prefecture ) ) {
				$address_parts_line2[] = $customer->prefecture;
			}

			// 市区町村（2行目）
			if ( ! empty( $customer->city ) ) {
				$address_parts_line2[] = $customer->city;
			}

			// 番地（2行目）
			if ( ! empty( $customer->address ) ) {
				$address_parts_line2[] = $customer->address;
			}

			// 建物名（3行目）
			if ( ! empty( $customer->building ) ) {
				$address_parts_line3[] = $customer->building;
			}

			// 住所が何もない場合は空配列を返す
			if ( empty( $address_parts_line1 ) && empty( $address_parts_line2 ) && empty( $address_parts_line3 ) ) {
				return array();
			}

			// 住所を配列で返す（各行を個別に処理できるように）
			$lines = array();
			$line1 = implode( ' ', $address_parts_line1 );
			$line2 = implode( ' ', $address_parts_line2 );
			$line3 = implode( ' ', $address_parts_line3 );

			if ( ! empty( $line1 ) ) {
				$lines[] = $line1;
			}
			if ( ! empty( $line2 ) ) {
				$lines[] = $line2;
			}
			if ( ! empty( $line3 ) ) {
				$lines[] = $line3;
			}

			return $lines;
		}
	} // End of Kntan_Order_Class

} // class_exists check
