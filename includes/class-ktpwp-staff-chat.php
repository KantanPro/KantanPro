<?php
/**
 * Staff chat management class for KTPWP plugin
 *
 * Handles staff chat functionality for orders.
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

if ( ! class_exists( 'KTPWP_Staff_Chat' ) ) {

	/**
	 * Staff chat management class
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Staff_Chat {

		/**
		 * Singleton instance
		 *
		 * @since 1.0.0
		 * @var KTPWP_Staff_Chat
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @since 1.0.0
		 * @return KTPWP_Staff_Chat
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			// Private constructor for singleton
		}

		/**
		 * Get the staff chat table schema.
		 *
		 * @return string The SQL for creating the staff chat table.
		 */
		public function get_schema() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
				order_id MEDIUMINT(9) NOT NULL,
				user_id BIGINT(20) UNSIGNED NOT NULL,
				user_display_name VARCHAR(255) NOT NULL DEFAULT '',
				message TEXT NOT NULL,
				is_initial TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY user_id (user_id),
				KEY created_at (created_at)
			) {$charset_collate};";

			return $sql;
		}

		/**
		 * Create staff chat table
		 *
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function create_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$schema = $this->get_schema();
			dbDelta( $schema );
			return true;
		}


		/**
		 * Create initial staff chat entry when order is created
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @param int $user_id User ID (optional, uses current user if not provided)
		 * @return bool True on success, false on failure
		 */
		public function create_initial_chat( $order_id, $user_id = null ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';

			// Use current user if user_id not provided
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			if ( ! $user_id ) {
				return false;
			}

			// Check if initial chat already exists
			$existing_chat = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table_name}` WHERE order_id = %d AND is_initial = 1",
                    $order_id
                )
            );

			if ( $existing_chat > 0 ) {
				return true; // Already exists
			}

			// Get user display name
			$user_info = get_userdata( $user_id );
			if ( ! $user_info ) {
				return false;
			}

			$display_name = $user_info->display_name ? $user_info->display_name : $user_info->user_login;

			// Insert initial chat entry
			$inserted = $wpdb->insert(
                $table_name,
                array(
					'order_id' => $order_id,
					'user_id' => $user_id,
					'user_display_name' => $display_name,
					'message' => '受注書を作成しました。',
					'is_initial' => 1,
					'created_at' => current_time( 'mysql' ),
                ),
                array(
					'%d', // order_id
					'%d', // user_id
					'%s', // user_display_name
					'%s', // message
					'%d', // is_initial
					'%s',  // created_at
                )
			);

			if ( $inserted ) {
				return true;
			} else {
				error_log( 'KTPWP: Failed to create initial staff chat: ' . $wpdb->last_error );
				return false;
			}
		}

		/**
		 * Get staff chat messages for a specific order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array|false Array of chat messages or false on failure
		 */
		public function get_messages( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';

			$messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at ASC",
                    $order_id
                ),
                ARRAY_A
			);

			if ( $messages === false ) {
				error_log( 'KTPWP: Error getting staff chat messages: ' . $wpdb->last_error );
				return false;
			}

			return $messages ? $messages : array();
		}

		/**
		 * Add staff chat message
		 *
		 * @since 1.0.0
		 * @param int    $order_id Order ID
		 * @param string $message Message content
		 * @return bool True on success, false on failure
		 */
		public function add_message( $order_id, $message ) {
			if ( ! $order_id || $order_id <= 0 ) {
				error_log( 'KTPWP StaffChat: add_message failed - invalid order_id: ' . print_r( $order_id, true ) );
				return false;
			}
			if ( empty( trim( $message ) ) ) {
				error_log( 'KTPWP StaffChat: add_message failed - empty message for order_id: ' . print_r( $order_id, true ) );
				return false;
			}

			// Check user permissions
			$current_user_id = get_current_user_id();
			error_log( 'KTPWP StaffChat: add_message debug - current_user_id: ' . print_r( $current_user_id, true ) . ' order_id: ' . print_r( $order_id, true ) );
			if ( ! $current_user_id ) {
				error_log( 'KTPWP StaffChat: add_message failed - no current user for order_id: ' . print_r( $order_id, true ) );
				return false;
			}
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP StaffChat: add_message failed - permission denied for user_id: ' . $current_user_id . ' order_id: ' . print_r( $order_id, true ) );
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';

			// Get user info
			$user_info = get_userdata( $current_user_id );
			if ( ! $user_info ) {
				error_log( 'KTPWP StaffChat: add_message failed - user not found for user_id: ' . $current_user_id . ' order_id: ' . print_r( $order_id, true ) );
				return false;
			}

			$display_name = $user_info->display_name ? $user_info->display_name : $user_info->user_login;

			// デバッグログを追加
			error_log( 'KTPWP StaffChat: add_message start - order_id: ' . $order_id . ', message: ' . $message );
			error_log( 'KTPWP StaffChat: add_message - table_name: ' . $table_name );
			error_log( 'KTPWP StaffChat: add_message - user_id: ' . $current_user_id . ', display_name: ' . $display_name );

			// Start transaction for concurrent access
			$wpdb->query( 'START TRANSACTION' );

			try {
				// Verify order exists
				$order_table = $wpdb->prefix . 'ktp_order';
				$order_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$order_table}` WHERE id = %d",
                        $order_id
                    )
                );

				if ( ! $order_exists ) {
					error_log( 'KTPWP StaffChat: add_message failed - order does not exist: ' . print_r( $order_id, true ) );
					throw new Exception( 'Order does not exist' );
				}

				// Insert message
				$inserted = $wpdb->insert(
                    $table_name,
                    array(
						'order_id' => $order_id,
						'user_id' => $current_user_id,
						'user_display_name' => sanitize_text_field( $display_name ),
						'message' => sanitize_textarea_field( $message ),
						'is_initial' => 0,
						'created_at' => current_time( 'mysql' ),
                    ),
                    array(
						'%d', // order_id
						'%d', // user_id
						'%s', // user_display_name
						'%s', // message
						'%d', // is_initial
						'%s',  // created_at
                    )
				);

				if ( $inserted ) {
					$wpdb->query( 'COMMIT' );
					error_log( 'KTPWP StaffChat: add_message success - order_id: ' . $order_id . ', user_id: ' . $current_user_id );
					return true;
				} else {
					error_log( 'KTPWP StaffChat: add_message failed - DB insert error: ' . $wpdb->last_error . ' order_id: ' . print_r( $order_id, true ) );
					throw new Exception( 'Failed to insert message: ' . $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				error_log( 'KTPWP StaffChat: Exception in add_message: ' . $e->getMessage() . ' order_id: ' . print_r( $order_id, true ) );
				return false;
			}
		}

		/**
		 * Generate staff chat HTML
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML content for staff chat
		 */
		public function generate_html( $order_id ) {
			global $wpdb;

			// Initialize variables
			$header_html = '';
			$scrollable_messages = array();

			// Check if order_id is valid
			if ( ! $order_id || $order_id <= 0 ) {
				return '<div class="order_memo_box box"><p>注文IDが無効です。</p></div>';
			}

			// Ensure table exists
			if ( ! $this->create_table() ) {
				return '<div class="order_memo_box box"><p>データベーステーブルの作成に失敗しました。</p></div>';
			}

			// Get order creation time
			$order_table = $wpdb->prefix . 'ktp_order';
			$order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT time FROM `{$order_table}` WHERE id = %d",
                    $order_id
                )
            );

			// Get chat messages
			$messages = $this->get_messages( $order_id );

			// Create initial chat message if none exist
			if ( empty( $messages ) ) {
				// For orders without existing chat messages, create initial message with current user
				$current_user_id = get_current_user_id();

				if ( $current_user_id && ( current_user_can( 'edit_posts' ) || current_user_can( 'ktpwp_access' ) ) ) {
					$this->create_initial_chat( $order_id, $current_user_id );
					$messages = $this->get_messages( $order_id );
				}
			}

			// Build HTML structure
			$html = '<div class="order_memo_box box">';
			// チャットタイトルとトグルボタンをh4タグなしで表示
			$html .= '<div class="staff-chat-header-row">';
			$html .= '<span class="staff-chat-title">■ スタッフチャット</span>';
			// Check URL parameter for chat open state
			// デフォルトでは表示状態にする（chat_open=0が明示的に指定された場合のみ非表示）
			$chat_should_be_open = ! isset( $_GET['chat_open'] ) || $_GET['chat_open'] !== '0';
			$aria_expanded = $chat_should_be_open ? 'true' : 'false';
			$button_text = $chat_should_be_open ? esc_html__( '非表示', 'ktpwp' ) : esc_html__( '表示', 'ktpwp' );
			// Add toggle button
			$html .= '<button type="button" class="toggle-staff-chat" aria-expanded="' . $aria_expanded . '" ';
			$html .= 'title="' . esc_attr__( 'スタッフチャットの表示/非表示を切り替え', 'ktpwp' ) . '">';
			$html .= $button_text;
			$html .= '</button>';
			$html .= '</div>';

			// Chat content div
			$display_style = $chat_should_be_open ? 'block' : 'none';
			$html .= '<div id="staff-chat-content" class="staff-chat-content" style="display: ' . $display_style . ';">';

			if ( empty( $messages ) ) {
				$html .= '<div class="staff-chat-empty">' . esc_html__( 'メッセージはありません。', 'ktpwp' ) . '</div>';
			} else {
				// Separate fixed header from scrollable messages
				foreach ( $messages as $index => $message ) {
					if ( $index === 0 && intval( $message['is_initial'] ) === 1 ) {
						// First message: fixed header display
						$user_display_name = esc_html( $message['user_display_name'] );
						$order_created_time = '';
						if ( $order && ! empty( $order->time ) ) {
							$order_created_time = date( 'Y/n/j H:i', $order->time );
						}

						// Get WordPress avatar
						$user_id = intval( $message['user_id'] );
						$avatar = get_avatar( $user_id, 32, '', $user_display_name, array( 'class' => 'staff-chat-wp-avatar' ) );
						error_log( 'KTPWP StaffChat: generate_html avatar debug - user_id: ' . print_r( $user_id, true ) . ' avatar_html: ' . print_r( $avatar, true ) );

						$header_html .= '<div class="staff-chat-header-fixed">';
						$header_html .= '<div class="staff-chat-message initial first-line">';
						$header_html .= '<div class="staff-chat-header-line">';
						$header_html .= '<span class="staff-chat-avatar-wrapper">' . $avatar . '</span>';
						$header_html .= '<span class="staff-chat-user-name">' . $user_display_name . '</span>';
						$header_html .= '<span class="staff-chat-order-time">受注書作成：' . esc_html( $order_created_time ) . '</span>';
						$header_html .= '</div>';
						$header_html .= '</div>';
						$header_html .= '</div>';
					} else {
						// Subsequent messages: save for scrollable area
						$scrollable_messages[] = $message;
					}
				}
			}

			// Add fixed header
			$html .= $header_html;

			// Scrollable message display area
			$html .= '<div class="staff-chat-messages" id="staff-chat-messages">';

			if ( ! empty( $scrollable_messages ) ) {
				foreach ( $scrollable_messages as $message ) {
					$created_at = $message['created_at'];
					$user_display_name = esc_html( $message['user_display_name'] );
					$message_content = esc_html( $message['message'] );

					// Format time
					$formatted_time = '';
					if ( ! empty( $created_at ) ) {
						$dt = new DateTime( $created_at );
						$formatted_time = $dt->format( 'Y/n/j H:i' );
					}

					// Get WordPress avatar
					$user_id = intval( $message['user_id'] );
                    $avatar = get_avatar( $user_id, 24, '', $user_display_name, array( 'class' => 'staff-chat-wp-avatar' ) );

                    $current_user_id = get_current_user_id();
                    $is_delete_log = false;
                    if ( isset( $message['message'] ) && is_string( $message['message'] ) ) {
                        // 削除ログ判定: 「〜がメッセージを削除しました」を含む
                        $is_delete_log = ( strpos( $message['message'], 'メッセージを削除しました' ) !== false );
                    }
                    $can_delete = !$is_delete_log && ( ( $current_user_id && intval( $message['user_id'] ) === intval( $current_user_id ) ) || current_user_can( 'manage_options' ) );

                    $wrapper_classes = 'staff-chat-message scrollable' . ( $is_delete_log ? ' deleted' : '' );
                    $html .= '<div class="' . $wrapper_classes . '" data-message-id="' . intval( $message['id'] ) . '">';
					$html .= '<div class="staff-chat-message-header">';
					$html .= '<span class="staff-chat-avatar-wrapper">' . $avatar . '</span>';
					$html .= '<span class="staff-chat-user-name">' . $user_display_name . '</span>';
					$html .= '<span class="staff-chat-timestamp" data-timestamp="' . esc_attr( $created_at ) . '">' . esc_html( $formatted_time ) . '</span>';
                    if ( $can_delete ) {
						$icon_html = '';
						if ( class_exists( 'KTPWP_SVG_Icons' ) ) {
							$icon_html = KTPWP_SVG_Icons::get_icon( 'delete', array( 'class' => 'ktp-svg-icon', 'style' => 'width:16px;height:16px;' ) );
						} else {
							$icon_html = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
						}
						$html .= '<button type="button" class="staff-chat-delete" title="' . esc_attr__( 'このメッセージを削除', 'ktpwp' ) . '" aria-label="' . esc_attr__( 'このメッセージを削除', 'ktpwp' ) . '" data-message-id="' . intval( $message['id'] ) . '" data-order-id="' . intval( $order_id ) . '">' . $icon_html . '</button>';
					}
					$html .= '</div>';
					$html .= '<div class="staff-chat-message-content">' . nl2br( $message_content ) . '</div>';
					$html .= '</div>';
				}
			}

			$html .= '</div>'; // .staff-chat-messages

			// Message input form (for users with edit permissions only)
			$can_edit = current_user_can( 'edit_posts' ) || current_user_can( 'ktpwp_access' );

			if ( $can_edit ) {
				$html .= '<form class="staff-chat-form" method="post" action="" id="staff-chat-form">';
				$html .= '<input type="hidden" name="staff_chat_order_id" value="' . esc_attr( $order_id ) . '">';
				$html .= wp_nonce_field( 'staff_chat_action', 'staff_chat_nonce', true, false );
				$html .= '<div class="staff-chat-input-wrapper">';
				$html .= '<textarea name="staff_chat_message" id="staff-chat-input" class="staff-chat-input" placeholder="' . esc_attr__( 'メッセージを入力してください...', 'ktpwp' ) . '" required></textarea>';
				$html .= '<button type="submit" id="staff-chat-submit" class="staff-chat-submit">' . esc_html__( '送信', 'ktpwp' ) . '</button>';
				$html .= '</div>';
				$html .= '</form>';
			}

			$html .= '</div>'; // .staff-chat-content
			$html .= '</div>'; // .order_memo_box

			// スタッフチャット用AJAX設定を確実に出力
			$html .= $this->get_ajax_config_script();

			return $html;
		}

		/**
		 * Get messages after specified timestamp
		 *
		 * @since 1.0.0
		 * @param int    $order_id Order ID
		 * @param string $last_time Last message timestamp
		 * @return array Array of messages
		 */
		public function get_messages_after( $order_id, $last_time = '' ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return array();
			}

			// Permission check
			if ( ! current_user_can( 'read' ) ) {
				return array();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_staff_chat';

			// Build query with proper escaping
			$sql = "SELECT * FROM `{$table_name}` WHERE order_id = %d";
			$params = array( $order_id );

			if ( ! empty( $last_time ) ) {
				$sql .= ' AND created_at > %s';
				$params[] = sanitize_text_field( $last_time );
			}

			$sql .= ' ORDER BY created_at ASC';

			$messages = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

			if ( ! $messages ) {
				return array();
			}

			// Format messages for AJAX response
			$formatted_messages = array();
			foreach ( $messages as $message ) {
				$formatted_messages[] = array(
					'id'                => intval( $message['id'] ),
					'user_display_name' => esc_html( $message['user_display_name'] ),
					'message'           => esc_html( $message['message'] ),
					'created_at'        => $message['created_at'],
					'timestamp'         => strtotime( $message['created_at'] ),
					'is_initial'        => intval( $message['is_initial'] ),
				);
			}

			return $formatted_messages;
		}

		/**
		 * AJAX設定スクリプトを生成
		 *
		 * @since 1.0.0
		 * @return string JavaScript スクリプト
		 */
		private function get_ajax_config_script() {
			static $script_output = false;

			// 重複出力を防止
			if ( $script_output ) {
				return '';
			}

			// 統一されたナンス管理クラスを使用
			$ajax_data = KTPWP_Nonce_Manager::get_instance()->get_unified_ajax_config();

			$script = '<script type="text/javascript">';
			$script .= 'window.ktpwp_ajax = ' . json_encode( $ajax_data ) . ';';
			$script .= 'window.ktp_ajax_object = ' . json_encode( $ajax_data ) . ';';
			$script .= 'window.ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';';
			$script .= 'console.log("StaffChat: AJAX設定を出力 (unified nonce)", window.ktpwp_ajax);';
			$script .= '</script>';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP StaffChat: AJAX config output with unified nonce: ' . json_encode( $ajax_data ) );
			}

			$script_output = true;

			return $script;
		}

		/**
		 * Delete a staff chat message by its author (or admin)
		 *
		 * @since 1.0.0
		 * @param int $message_id Message ID
		 * @return bool True on success, false on failure
		 */
        public function delete_message_by_author( $message_id ) {
            $message_id = absint( $message_id );
            if ( $message_id <= 0 ) {
                return false;
            }

            $current_user_id = get_current_user_id();
            if ( ! $current_user_id ) {
                return false;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'ktp_order_staff_chat';

            // 対象メッセージ取得（order_id も取得）
            $message = $wpdb->get_row(
                $wpdb->prepare( "SELECT id, user_id, is_initial, order_id FROM `{$table_name}` WHERE id = %d", $message_id ),
                ARRAY_A
            );

            if ( ! $message ) {
                return false;
            }

            if ( intval( $message['is_initial'] ) === 1 ) {
                return false;
            }

            $owner_user_id = intval( $message['user_id'] );
            $has_permission = ( $owner_user_id === intval( $current_user_id ) ) || current_user_can( 'manage_options' );
            if ( ! $has_permission ) {
                return false;
            }

            // 削除と削除履歴追加をトランザクションで実行
            $wpdb->query( 'START TRANSACTION' );
            try {
                $deleted = $wpdb->delete( $table_name, array( 'id' => $message_id ), array( '%d' ) );
                if ( ! $deleted ) {
                    throw new Exception( 'Failed to delete message' );
                }

                // 現在ユーザーの表示名
                $user_info = get_userdata( $current_user_id );
                if ( ! $user_info ) {
                    throw new Exception( 'User not found' );
                }
                $display_name = $user_info->display_name ? $user_info->display_name : $user_info->user_login;

                // 削除履歴のメッセージを追加
                $log_text = sprintf( '%s がメッセージを削除しました', sanitize_text_field( $display_name ) );
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'order_id'          => intval( $message['order_id'] ),
                        'user_id'           => $current_user_id,
                        'user_display_name' => sanitize_text_field( $display_name ),
                        'message'           => $log_text,
                        'is_initial'        => 0,
                        'created_at'        => current_time( 'mysql' ),
                    ),
                    array( '%d', '%d', '%s', '%s', '%d', '%s' )
                );

                if ( ! $inserted ) {
                    throw new Exception( 'Failed to insert delete log' );
                }

                $wpdb->query( 'COMMIT' );
                return true;
            } catch ( Exception $e ) {
                $wpdb->query( 'ROLLBACK' );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP StaffChat: delete_message_by_author failed: ' . $e->getMessage() );
                }
                return false;
            }
        }
	} // End of KTPWP_Staff_Chat class

} // class_exists check
