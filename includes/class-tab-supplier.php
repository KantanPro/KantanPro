<?php
/**
 * Supplier class for KTPWP plugin
 *
 * Handles supplier data management including table creation,
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

if ( ! class_exists( 'KTPWP_Supplier_Class' ) ) {

/**
 * Supplier class for managing supplier data
 *
 * This class has been refactored to use a delegation pattern for better
 * separation of concerns:
 * - KTPWP_Supplier_Security: Handles security-related operations
 * - KTPWP_Supplier_Data: Handles database operations
 * - KTPWP_Supplier_Class: Main class coordinating UI and business logic
 *
 * @since 1.0.0
 */
class KTPWP_Supplier_Class {

    /**
     * Supplier security instance
     *
     * @var KTPWP_Supplier_Security
     * @since 1.0.0
     */
    private $supplier_security;

    /**
     * Supplier data instance
     *
     * @var KTPWP_Supplier_Data
     * @since 1.0.0
     */
    private $supplier_data;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param KTPWP_Supplier_Security $supplier_security Optional security instance
     * @param KTPWP_Supplier_Data $supplier_data Optional data instance
     */
    public function __construct( $supplier_security = null, $supplier_data = null ) {
        $this->supplier_security = $supplier_security ?: new KTPWP_Supplier_Security();
        $this->supplier_data = $supplier_data ?: new KTPWP_Supplier_Data();
    }

    // -----------------------------
    // Table Operations
    // -----------------------------

    /**
     * Set cookie for supplier data
     *
     * @since 1.0.0
     * @param string $name The name parameter for cookie
     * @return int The query ID
     */
    public function set_cookie( $name ) {
        return $this->supplier_security->set_cookie( $name );
    }

    /**
     * Create supplier table
     *
     * @since 1.0.0
     * @param string $tab_name The table name suffix
     * @return bool True on success, false on failure
     */
    public function create_table( $tab_name ) {
        return $this->supplier_data->create_table( $tab_name );
    }

    // -----------------------------
    // テーブルの操作（更新・追加・削除・検索）
    // -----------------------------

    /**
     * Update supplier table data
     *
     * @since 1.0.0
     * @param string $tab_name Table name suffix
     * @return void
     */
    public function Update_Table( $tab_name ) {
        // Enhanced debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        }

        // Only proceed if POST data exists
        if ( ! empty( $_POST ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            }
            
            // Handle skills operations first
            $this->handle_skills_operations( $_POST );
            
            // Then handle regular supplier data updates
            $this->supplier_data->update_table( $tab_name, $_POST );
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            }
        }
    }

    /**
     * Handle search and other operations for supplier data
     *
     * @since 1.0.0
     * @param string $query_post The query type
     * @param string $tab_name The table name suffix
     * @param array $post_data POST data array (optional)
     * @return void
     */
    public function handle_operations( $query_post, $tab_name, $post_data = null ) {
        global $wpdb;

        // セキュリティチェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'ktpwp' ) );
        }

        // 入力値の検証
        if ( empty( $query_post ) || empty( $tab_name ) ) {
            error_log( 'KTPWP: Invalid parameters in handle_operations' );
            return;
        }

        $table_name = $wpdb->prefix . 'ktp_' . sanitize_text_field( $tab_name );

        // POST データが提供されていない場合は $_POST を使用
        if ( $post_data === null ) {
            $post_data = $_POST;
        }

        // 必要な変数を初期化
        $company_name = isset($post_data['company_name']) ? sanitize_text_field($post_data['company_name']) : '';
        $user_name = isset($post_data['user_name']) ? sanitize_text_field($post_data['user_name']) : '';
        $email = isset($post_data['email']) ? sanitize_email($post_data['email']) : '';
        $url = isset($post_data['url']) ? esc_url_raw($post_data['url']) : '';
        $representative_name = isset($post_data['representative_name']) ? sanitize_text_field($post_data['representative_name']) : '';
        $phone = isset($post_data['phone']) ? sanitize_text_field($post_data['phone']) : '';
        $postal_code = isset($post_data['postal_code']) ? sanitize_text_field($post_data['postal_code']) : '';
        $prefecture = isset($post_data['prefecture']) ? sanitize_text_field($post_data['prefecture']) : '';
        $city = isset($post_data['city']) ? sanitize_text_field($post_data['city']) : '';
        $address = isset($post_data['address']) ? sanitize_text_field($post_data['address']) : '';
        $building = isset($post_data['building']) ? sanitize_text_field($post_data['building']) : '';
        $closing_day = isset($post_data['closing_day']) ? sanitize_text_field($post_data['closing_day']) : '';
        $payment_month = isset($post_data['payment_month']) ? sanitize_text_field($post_data['payment_month']) : '';
        $payment_day = isset($post_data['payment_day']) ? sanitize_text_field($post_data['payment_day']) : '';
        $payment_method = isset($post_data['payment_method']) ? sanitize_text_field($post_data['payment_method']) : '';
        $tax_category = isset($post_data['tax_category']) ? sanitize_text_field($post_data['tax_category']) : '';
        $memo = isset($post_data['memo']) ? sanitize_textarea_field($post_data['memo']) : '';
        $category = isset($post_data['category']) ? sanitize_text_field($post_data['category']) : '';

        // search_field の値を構築
        $search_field_value = implode(', ', [
            current_time( 'mysql' ),
            $company_name,
            $user_name,
            $email,
            $url,
            $representative_name,
            $phone,
            $postal_code,
            $prefecture,
            $city,
            $address,
            $building,
            $closing_day,
            $payment_month,
            $payment_day,
            $payment_method,
            $tax_category,
            $memo,
            $category
        ]);

        // 検索
        if( $query_post == 'search' ){

        // SQLクエリを準備（search_fieldを検索対象にする）
        $search_query = $post_data['search_query'];
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE search_field LIKE %s", '%' . $wpdb->esc_like($search_query) . '%'));

        // 検索結果が1つある場合の処理
        if (count($results) == 1) {
            // 検索結果のIDを取得
            $id = $results[0]->id;
            // 頻度の値を+1する
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_name SET frequency = frequency + 1 WHERE ID = %d",
                    $id
                )
            );
            // 検索後に更新モードにする
            $action = 'update';
            $data_id = $id;
            // 現在のURLを取得
            global $wp;
            $current_page_id = get_queried_object_id();
            $base_page_url = get_permalink($current_page_id);
            if (!$base_page_url) {
                $base_page_url = home_url(add_query_arg(array(), $wp->request));
            }
            // 新しいパラメータを追加
            $redirect_url = add_query_arg([
                'tab_name' => $tab_name,
                'data_id' => $data_id,
                'message' => 'found'
            ], $base_page_url);

            $cookie_name = 'ktp_' . $tab_name . '_id';
            setcookie($cookie_name, $data_id, time() + (86400 * 30), "/");

            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showInfoNotification("' . esc_js(esc_html__('検索結果を表示しています。', 'ktpwp')) . '");
                    setTimeout(function() {
                        window.location.href = "' . esc_js($redirect_url) . '";
                    }, 1000);
                });
            </script>';
            exit;
        }
        // 検索結果が複数ある場合の処理
        elseif (count($results) > 1) {
            // 得意先タブと同じく、base_page_urlを使い絶対パスでリンク生成
            global $wp;
            $current_page_id = get_queried_object_id();
            $base_page_url = add_query_arg(array('page_id' => $current_page_id), home_url($wp->request));
            $search_results_html = "<div class='data_contents'><div class='search_list_box'><div class='data_list_title'>■ 検索結果が複数あります！</div><ul>";
            foreach ($results as $row) {
                $id = esc_html($row->id);
                $company_name = esc_html($row->company_name);
                $category = esc_html($row->category);
                $link_url = esc_url(add_query_arg(array('tab_name' => $tab_name, 'data_id' => $id, 'query_post' => 'update'), $base_page_url));
                $search_results_html .= "<li style='text-align:left;'><a href='{$link_url}' style='text-align:left;'>ID：{$id} 会社名：{$company_name} カテゴリー：{$category}</a></li>";
            }
            $search_results_html .= "</ul></div></div>";
            $search_results_html_js = json_encode($search_results_html);
            $close_redirect_url = esc_url(add_query_arg(array('tab_name' => $tab_name, 'query_post' => 'srcmode'), $base_page_url));
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var searchResultsHtml = $search_results_html_js;
                var popup = document.createElement('div');
                popup.innerHTML = searchResultsHtml;
                popup.style.position = 'fixed';
                popup.style.top = '50%';
                popup.style.left = '50%';
                popup.style.transform = 'translate(-50%, -50%)';
                popup.style.backgroundColor = '#fff';
                popup.style.padding = '20px';
                popup.style.zIndex = '1000';
                popup.style.width = '80%';
                popup.style.maxWidth = '600px';
                popup.style.border = '1px solid #ccc';
                popup.style.borderRadius = '5px';
                popup.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                document.body.appendChild(popup);
                // ポップアップを閉じるためのボタンを追加
                var closeButton = document.createElement('button');
                closeButton.textContent = '閉じる';
                closeButton.style.fontSize = '0.8em';
                closeButton.style.color = 'black';
                closeButton.style.display = 'block';
                closeButton.style.margin = '10px auto 0';
                closeButton.style.padding = '10px';
                closeButton.style.backgroundColor = '#cdcccc';
                closeButton.style.borderRadius = '5px';
                closeButton.style.borderColor = '#999';
                closeButton.onclick = function() {
                    document.body.removeChild(popup);
                    // 元の検索モードに戻るために特定のURLにリダイレクト（srcmodeに戻す）
                    location.href = '" . $close_redirect_url . "';
                };
                popup.appendChild(closeButton);
            });
            </script>";
        }
        // 検索結果が0件の場合の処理
        else {
            // サプライヤも得意先タブと同じくセッションメッセージ＋リダイレクト方式に統一
            if (!session_id()) {
                session_start();
            }
            $_SESSION['ktp_search_message'] = '検索結果がありませんでした。';
            // 検索語とno_results=1を付与してsrcmodeにリダイレクト
            global $wp;
            $current_page_id = get_queried_object_id();
            $base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );
            $search_query_encoded = urlencode($post_data['search_query']);
            $redirect_url_base = strtok($base_page_url, '?');
            $query_string = "?page_id=" . $current_page_id . "&tab_name=" . $tab_name . "&query_post=srcmode&search_query=" . $search_query_encoded . "&no_results=1";
            $redirect_url = $redirect_url_base . $query_string;
            header("Location: " . $redirect_url);
            exit;
        }

        // ロックを解除する
        $wpdb->query("UNLOCK TABLES;");
        // exit; を削除し、通常の画面描画を続行
        }

        // 追加
        elseif( $query_post == 'insert' ) {

            $insert_result = $wpdb->insert(
                $table_name,
                    array(
                        'time' => current_time( 'mysql' ),
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
                        'memo' => $memo,
                        'category' => $category,
                        'search_field' => $search_field_value
                )
            );
            if($insert_result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Insert error: ' . $wpdb->last_error); }
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showErrorNotification("追加に失敗しました。SQLエラー: ' . esc_js($wpdb->last_error) . '");
                });
                </script>';
                $wpdb->query("UNLOCK TABLES;");
            } else {
                $wpdb->query("UNLOCK TABLES;");
                $action = 'update';
                // 追加直後のIDを $wpdb->insert_id から取得する
                $data_id = $wpdb->insert_id;


                // 追加後のリダイレクト処理
                $cookie_name = 'ktp_' . $tab_name . '_id';
                setcookie($cookie_name, $data_id, time() + (86400 * 30), "/");

                global $wp;
                $current_page_id = get_queried_object_id();
                $base_page_url = get_permalink($current_page_id);
                if (!$base_page_url) {
                    $base_page_url = home_url(add_query_arg(array(), $wp->request));
                }
                $redirect_url = add_query_arg([
                    'tab_name' => $tab_name,
                    'data_id' => $data_id,
                    'message' => 'added'
                ], $base_page_url);

                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showSuccessNotification("' . esc_js(esc_html__('新しい協力会社を追加しました。', 'ktpwp')) . '");
                        setTimeout(function() {
                            window.location.href = "' . esc_js($redirect_url) . '";
                        }, 1000);
                    });
                </script>';
                exit;
                exit;
            }

        }

        // 複製
        elseif( $query_post == 'duplication' ) {
            // データのIDを取得
            $data_id = absint( $post_data['data_id'] );

            if ( $data_id <= 0 ) {
                error_log( 'KTPWP: Invalid data_id for duplication' );
                return;
            }

            // データを取得
            $data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table_name, $data_id ), ARRAY_A );

            if ( ! $data ) {
                error_log( 'KTPWP: Data not found for duplication, ID: ' . $data_id );
                return;
            }

            // 会社名の最後に#を追加
            $data['company_name'] .= '#';

            // IDを削除
            unset( $data['id'] );

            // 頻度を0に設定
            $data['frequency'] = 0;

            // search_fieldの値を更新
            $data['search_field'] = implode(', ', [
                $data['time'],
                $data['company_name'],
                $data['name'],
                $data['email'],
                $data['url'],
                $data['representative_name'],
                $data['phone'],
                $data['postal_code'],
                $data['prefecture'],
                $data['city'],
                $data['address'],
                $data['building'],
                $data['closing_day'],
                $data['payment_month'],
                $data['payment_day'],
                $data['payment_method'],
                $data['tax_category'],
                $data['memo'],
                $data['category']
            ]);

            // データを挿入
            $insert_result = $wpdb->insert($table_name, $data);
            if($insert_result === false) {
                error_log('Duplication error: ' . $wpdb->last_error);
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showErrorNotification("複製に失敗しました。SQLエラー: ' . esc_js($wpdb->last_error) . '");
                });
                </script>';
            } else {
                $new_data_id = $wpdb->insert_id;
                $wpdb->query("UNLOCK TABLES;");
                global $wp;
                $current_page_id = get_queried_object_id();
                $base_page_url = get_permalink($current_page_id);
                if (!$base_page_url) {
                    $base_page_url = home_url(add_query_arg(array(), $wp->request));
                }
                $redirect_url = add_query_arg([
                    'tab_name' => $tab_name,
                    'data_id' => $new_data_id,
                    'message' => 'duplicated'
                ], $base_page_url);
                $cookie_name = 'ktp_' . $tab_name . '_id';
                setcookie($cookie_name, $new_data_id, time() + (86400 * 30), "/");

                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showSuccessNotification("' . esc_js(esc_html__('複製しました。', 'ktpwp')) . '");
                        setTimeout(function() {
                            window.location.href = "' . esc_js($redirect_url) . '";
                        }, 1000);
                    });
                </script>';
            }
        }

        // どの処理にも当てはまらない場合はロック解除
        else {
            // ロックを解除する
            $wpdb->query("UNLOCK TABLES;");
        }
    }

    // -----------------------------
    // テーブルの表示
    // -----------------------------

    function View_Table( $name ) {
        global $wpdb, $wp; // $wp をグローバルに追加

        // ベースURLの構築
        $current_url_path = home_url( $wp->request );
        $base_url_params = [];

        if ( get_queried_object_id() ) {
            $base_url_params['page_id'] = get_queried_object_id();
        }
        if ( isset( $_GET['page'] ) ) {
            $base_url_params['page'] = sanitize_text_field( $_GET['page'] );
        }
        $base_url_params['tab_name'] = $name;
        $base_page_url = add_query_arg( $base_url_params, $current_url_path );

        // フォームアクション用のベースURL (ページネーションパラメータ等は含めない)
        $form_action_base_url = $base_page_url;

        // --- DBエラー表示（セッションから） ---
        if (!session_id()) { session_start(); }
        if (isset($_SESSION['ktp_db_error_message'])) {
            echo '<div class="ktp-db-error" style="background:#ffeaea;color:#b30000;padding:14px 20px;margin:18px 0 20px 0;border:2px solid #b30000;border-radius:7px;font-weight:bold;font-size:1.1em;">'
                . '<span style="font-size:1.2em;">⚠️ <b>DBエラー</b></span><br>'
                . $_SESSION['ktp_db_error_message']
                . '</div>';
            unset($_SESSION['ktp_db_error_message']);
        }
        // --- ここまで ---

        // URL パラメータからのメッセージ表示処理を追加
        if (isset($_GET['message'])) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const messageType = "' . esc_js($_GET['message']) . '";
                switch (messageType) {
                    case "updated":
                        showSuccessNotification("' . esc_js(__('更新しました。', 'ktpwp')) . '");
                        break;
                    case "added":
                        showSuccessNotification("' . esc_js(__('新しい協力会社を追加しました。', 'ktpwp')) . '");
                        break;
                    case "deleted":
                        showSuccessNotification("' . esc_js(__('削除しました。', 'ktpwp')) . '");
                        break;
                    case "duplicated":
                        showSuccessNotification("' . esc_js(__('複製しました。', 'ktpwp')) . '");
                        break;
                    case "skill_added":
                        showSuccessNotification("' . esc_js(__('商品・サービスを追加しました。', 'ktpwp')) . '");
                        break;
                    case "skill_deleted":
                        showSuccessNotification("' . esc_js(__('商品・サービスを削除しました。', 'ktpwp')) . '");
                        break;
                    case "found":
                        showInfoNotification("' . esc_js(__('検索結果を表示しています。', 'ktpwp')) . '");
                        break;
                    case "not_found":
                        showWarningNotification("' . esc_js(__('該当する協力会社が見つかりませんでした。', 'ktpwp')) . '");
                        break;
                }
                // URLからmessageパラメータを削除
                if (window.history.replaceState) {
                    var currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.has("message")) {
                        currentUrl.searchParams.delete("message");
                        window.history.replaceState({ path: currentUrl.href }, "", currentUrl.href);
                    }
                }
            });
            </script>';
        }

        // $search_results_listの使用前に初期化
        if (!isset($search_results_list)) {
            $search_results_list = '';
        }

        // -----------------------------
        // リスト表示
        // -----------------------------

        // テーブル名
        $table_name = $wpdb->prefix . 'ktp_' . $name;

        // ソート順の取得（デフォルトはIDの降順）
        $sort_by = 'id';
        $sort_order = 'DESC';

        if (isset($_GET['sort_by'])) {
            $sort_by = sanitize_text_field($_GET['sort_by']);
            // 安全なカラム名のみ許可（SQLインジェクション対策）
            $allowed_columns = array('id', 'company_name', 'frequency', 'time', 'category');
            if (!in_array($sort_by, $allowed_columns)) {
                $sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
            }
        }

        if (isset($_GET['sort_order'])) {
            $sort_order_param = strtoupper(sanitize_text_field($_GET['sort_order']));
            // ASCかDESCのみ許可
            $sort_order = ($sort_order_param === 'ASC') ? 'ASC' : 'DESC';
        }

        // 現在のページのURLを生成
        global $wp;
        $current_page_id = get_queried_object_id();
        // home_url() と $wp->request を使用して、現在のURLを取得し、page_idを追加
        $base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );

            // -----------------------------
        // ページネーションリンク
        // -----------------------------

        // 表示範囲
        // 一般設定から表示件数を取得（設定クラスが利用可能な場合）
        if (class_exists('KTP_Settings')) {
            $query_limit = KTP_Settings::get_work_list_range();
        } else {
            $query_limit = 20; // フォールバック値
        }

        // ソートプルダウンを追加
        $sort_dropdown = '';

        // 現在のURLからソート用プルダウンのアクションURLを生成
        $sort_url = add_query_arg(array('tab_name' => $name), $base_page_url);

        // ソート用プルダウンのHTMLを構築
        $sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
            '<form method="get" action="' . esc_url($sort_url) . '" style="display:flex;align-items:center;">';

        // 現在のGETパラメータを維持するための隠しフィールド
        foreach ($_GET as $key => $value) {
            if ($key !== 'sort_by' && $key !== 'sort_order') {
                $sort_dropdown .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }

        $sort_dropdown .=
            '<select id="sort-select" name="sort_by" style="margin-right:5px;">' .
            '<option value="id" ' . selected($sort_by, 'id', false) . '>' . esc_html__('ID', 'ktpwp') . '</option>' .
            '<option value="company_name" ' . selected($sort_by, 'company_name', false) . '>' . esc_html__('会社名', 'ktpwp') . '</option>' .
            '<option value="frequency" ' . selected($sort_by, 'frequency', false) . '>' . esc_html__('頻度', 'ktpwp') . '</option>' .
            '<option value="time" ' . selected($sort_by, 'time', false) . '>' . esc_html__('登録日', 'ktpwp') . '</option>' .
            '<option value="category" ' . selected($sort_by, 'category', false) . '>' . esc_html__('カテゴリー', 'ktpwp') . '</option>' .
            '</select>' .
            '<select id="sort-order" name="sort_order">' .
            '<option value="ASC" ' . selected($sort_order, 'ASC', false) . '>' . esc_html__('昇順', 'ktpwp') . '</option>' .
            '<option value="DESC" ' . selected($sort_order, 'DESC', false) . '>' . esc_html__('降順', 'ktpwp') . '</option>' .
            '</select>' .
            '<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__('適用', 'ktpwp') . '">' .
            '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
            '</button>' .
            '</form></div>';

        // リスト表示部分の開始 - ktp_data_contentsを開始
        $results_h = <<<END
        <div class="ktp_data_contents">
            <div class="ktp_data_list_box">
            <div class="data_list_title">■ 協力会社リスト {$sort_dropdown}</div>
        END;

       // スタート位置を決める
       $page_stage = $_GET['page_stage'] ?? '';
       $page_start = $_GET['page_start'] ?? 0;
       $flg = $_GET['flg'] ?? '';
       if ($page_stage == '') {
           $page_start = 0;
       }

// 全データ数を取得
        $total_query = "SELECT COUNT(*) FROM {$table_name}";
        $total_rows = $wpdb->get_var($total_query);
        $total_pages = ceil($total_rows / $query_limit);

        // 現在のページ番号を計算
        $current_page = floor($page_start / $query_limit) + 1;

        // データを取得（選択されたソート順で）
        $sort_column = esc_sql($sort_by); // SQLインジェクション対策
        $sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策
        $query = $wpdb->prepare("SELECT * FROM {$table_name} ORDER BY {$sort_column} {$sort_direction} LIMIT %d, %d", $page_start, $query_limit);
        $post_row = $wpdb->get_results($query);
       if( $post_row ){
           foreach ($post_row as $row){
               $id = esc_html($row->id);
               $time = esc_html($row->time);
               $company_name = esc_html($row->company_name);
               $user_name = esc_html($row->name);
               $email = esc_html($row->email);
               $url = esc_html($row->url);
               $representative_name = esc_html($row->representative_name);
               $phone = esc_html($row->phone);
               $postal_code = esc_html($row->postal_code);
               $prefecture = esc_html($row->prefecture);
               $city = esc_html($row->city);
               $address = esc_html($row->address);
               $building = esc_html($row->building);
               $closing_day = esc_html($row->closing_day);
               $payment_month = esc_html($row->payment_month);
               $payment_day = esc_html($row->payment_day);
               $payment_method = esc_html($row->payment_method);
               $tax_category = esc_html($row->tax_category);
               $memo = esc_html($row->memo);
               $category = esc_html($row->category);
               $frequency = esc_html($row->frequency);                // リスト項目
                $cookie_name = 'ktp_' . $name . '_id';

                $query_args = array(
                    'tab_name' => $name,
                    'data_id' => $id,
                    'page_start' => $page_start,
                    'page_stage' => $page_stage,
                    // 'flg' => $flg, // 必要に応じて維持
                );
                // 現在のソート順を維持
                if (isset($_GET['sort_by'])) $query_args['sort_by'] = $_GET['sort_by'];
                if (isset($_GET['sort_order'])) $query_args['sort_order'] = $_GET['sort_order'];

                $item_link_url = esc_url(add_query_arg($query_args, $base_page_url));
                $results[] = <<<END
                <a href="{$item_link_url}" onclick="document.cookie = '{$cookie_name}=' + {$id};">
                    <div class="ktp_data_list_item">ID: $id $company_name : $category : 頻度($frequency)</div>
                </a>
                END;

           }
           $query_max_num = $wpdb->num_rows;
       } else {
           $results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%); border-radius: 6px; margin: 15px 0; color: #333333; font-weight: 500; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08); display: flex; align-items: center; font-size: 14px;">'
               . '<span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">search_off</span>'
               . esc_html__('データーがありません。', 'ktpwp')
               . '<span style="margin-left: 16px; font-size: 13px; color: #888;">'
               . esc_html__('フォームに入力して更新してください。', 'ktpwp')
               . '</span>'
               . '</div>';
       }

       $results_f = "<div class=\"pagination\">";

        // 最初へリンク
        if ($current_page > 1) {
            $first_start = 0; // 最初のページ
            $first_page_link_args = array('tab_name' => $name, 'page_start' => $first_start, 'page_stage' => 2, 'flg' => $flg);
            // 現在のソート順を維持
            if (isset($_GET['sort_by'])) $first_page_link_args['sort_by'] = $_GET['sort_by'];
            if (isset($_GET['sort_order'])) $first_page_link_args['sort_order'] = $_GET['sort_order'];
            $first_page_link_url = esc_url(add_query_arg($first_page_link_args, $base_page_url));
            $results_f .= <<<END
            <a href="{$first_page_link_url}">|<</a>
            END;
        }

        // 前へリンク
        if ($current_page > 1) {
            $prev_start = ($current_page - 2) * $query_limit;
            $prev_page_link_args = array('tab_name' => $name, 'page_start' => $prev_start, 'page_stage' => 2, 'flg' => $flg);
            // 現在のソート順を維持
            if (isset($_GET['sort_by'])) $prev_page_link_args['sort_by'] = $_GET['sort_by'];
            if (isset($_GET['sort_order'])) $prev_page_link_args['sort_order'] = $_GET['sort_order'];
            $prev_page_link_url = esc_url(add_query_arg($prev_page_link_args, $base_page_url));
            $results_f .= <<<END
            <a href="{$prev_page_link_url}"><</a>
            END;
        }

        // 現在のページ範囲表示と総数
        $page_end = min($total_rows, $current_page * $query_limit);
        $page_start_display = ($current_page - 1) * $query_limit + 1;
        $results_f .= "<div class='stage'> $page_start_display ~ $page_end / $total_rows</div>";

        // 次へリンク（現在のページが最後のページより小さい場合のみ表示）
        if ($current_page < $total_pages) {
            $next_start = $current_page * $query_limit;
            $query_args_next = array(
                'tab_name' => $name,
                'page_start' => $next_start,
                'page_stage' => 2,
                'flg' => $flg
            );
            // 現在のソート順を維持
            if (isset($_GET['sort_by'])) $query_args_next['sort_by'] = $_GET['sort_by'];
            if (isset($_GET['sort_order'])) $query_args_next['sort_order'] = $_GET['sort_order'];
            $next_page_link_url = esc_url(add_query_arg($query_args_next, $base_page_url));
            $results_f .= <<<END
            <a href="{$next_page_link_url}">></a>
            END;
        }

        // 最後へリンク
        if ($current_page < $total_pages) {
            $last_start = ($total_pages - 1) * $query_limit; // 最後のページ
            $query_args_last = array(
                'tab_name' => $name,
                'page_start' => $last_start,
                'page_stage' => 2,
                'flg' => $flg
            );
            // 現在のソート順を維持
            if (isset($_GET['sort_by'])) $query_args_last['sort_by'] = $_GET['sort_by'];
            if (isset($_GET['sort_order'])) $query_args_last['sort_order'] = $_GET['sort_order'];
            $last_page_link_url = esc_url(add_query_arg($query_args_last, $base_page_url));
            $results_f .= <<<END
             <a href="{$last_page_link_url}">>>|</a>
            END;
        }


        $results_f .= "</div>";

        // -----------------------------
        // 詳細表示(GET) - ID取得処理を先に実行
        // -----------------------------

        // 現在表示中の詳細
        $cookie_name = 'ktp_' . $name . '_id';
        $query_id = null;

        // アクションを取得（POST優先、なければGET、なければ'update'）
        $action = 'update';
        if (isset($_POST['query_post'])) {
            $action = sanitize_text_field($_POST['query_post']);
        } elseif (isset($_GET['query_post'])) {
            $action = sanitize_text_field($_GET['query_post']);
        }

        // 安全性確保: GETリクエストの場合は危険なアクションを実行しない
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, ['delete', 'insert', 'search', 'duplicate', 'istmode', 'srcmode'])) {
            $action = 'update';
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
        }

        // 詳細表示ID取得処理（ページネーションメッセージ用）
        if ($action !== 'istmode') {
            if (isset($_GET['data_id']) && $_GET['data_id'] !== '') {
                $query_id = filter_input(INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT);
                // クッキーも即時更新（追加直後やURL遷移時に常に最新IDを保持）
                setcookie($cookie_name, $query_id, time() + (86400 * 30), "/");
            } else if (isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] !== '') {
                $cookie_id = filter_input(INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT);
                // クッキーIDがDBに存在するかチェック
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id));
                if ($exists) {
                    $query_id = $cookie_id;
                } else {
                    // 存在しなければ最大ID
                    $max_id_row = $wpdb->get_row("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1");
                    $query_id = $max_id_row ? $max_id_row->id : '';
                }
            } else {
                // data_id未指定時は必ずID最大の協力会社を表示
                $max_id_row = $wpdb->get_row("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1");
                $query_id = $max_id_row ? $max_id_row->id : '';
            }
        } else {
            // 追加モードの場合はIDを取得しない
            $query_id = '';
        }

        // データを取得し変数に格納（$query_idは既に取得済み）
        if ($action !== 'istmode' && $query_id) {
            $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $query_id);
            $post_row = $wpdb->get_results($query);
            if (!$post_row || count($post_row) === 0) {
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
                $category = '';
                // $post_row を空配列にして以降のフォーム生成処理を通す
                $post_row = [];
                // リスト部分にだけ「データがありません」メッセージを出す（デザインは既に統一済み）
                $results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%); border-radius: 6px; margin: 15px 0; color: #333333; font-weight: 500; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08); display: flex; align-items: center; font-size: 14px;">'
                    . '<span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">search_off</span>'
                    . esc_html__('データーがありません。', 'ktpwp')
                    . '</div>';
            }
            foreach ($post_row as $row){
                $data_id = esc_html($row->id);
                $time = esc_html($row->time);
                $company_name = esc_html($row->company_name);
                $user_name = esc_html($row->name);
                $email = esc_html($row->email);
                $url = esc_html($row->url);
                $representative_name = esc_html($row->representative_name);
                $phone = esc_html($row->phone);
                $postal_code = esc_html($row->postal_code);
                $prefecture = esc_html($row->prefecture);
                $city = esc_html($row->city);
                $address = esc_html($row->address);
                $building = esc_html($row->building);
                $closing_day = esc_html($row->closing_day);
                $payment_month = esc_html($row->payment_month);
                $payment_day = esc_html($row->payment_day);
                $payment_method = esc_html($row->payment_method);
                $tax_category = esc_html($row->tax_category);
                $memo = esc_html($row->memo);
                $category = esc_html($row->category);
            }
        } else {
            // 追加モードの場合は全ての変数を空で初期化
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
            $category = '';
        }

        // データ取得完了後、協力会社ID表示メッセージを生成
        if ($query_id) {
            $display_company_name = isset($company_name) && !empty($company_name) ? $company_name : '未設定';
            $current_id_message = '<div class="data_skill_list_title">'
                . '<span class="material-symbols-outlined" style="color: #4caf50;">business</span>'
                . esc_html($display_company_name) . '（ID: ' . esc_html($query_id) . '）の商品・サービス'
                . '</div>';
        } else {
            $current_id_message = '<div style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
                . '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
                . ($action === 'istmode' ? '新規追加モード' : '協力会社データがありません')
                . '</div>';
        }

        // data_listに協力会社ID表示メッセージを追加 - 協力会社リストBOXを継続（職能セクションを含むため）
        $data_list = $results_h . implode($results) . $results_f . $current_id_message;

        // 表示するフォーム要素を定義
        $fields = [
            // 'ID' => ['type' => 'text', 'name' => 'data_id', 'readonly' => true],
            '会社名' => ['type' => 'text', 'name' => 'company_name', 'required' => true, 'placeholder' => '必須 法人名または屋号'],
            '名前' => ['type' => 'text', 'name' => 'user_name', 'placeholder' => '担当者名'],
            'メール' => ['type' => 'email', 'name' => 'email'],
            'URL' => ['type' => 'text', 'name' => 'url', 'placeholder' => 'https://....'],
            '代表者名' => ['type' => 'text', 'name' => 'representative_name', 'placeholder' => '代表者名'],
            '電話番号' => ['type' => 'text', 'name' => 'phone', 'pattern' => '\d*', 'placeholder' => '半角数字 ハイフン不要'],
            '郵便番号' => ['type' => 'text', 'name' => 'postal_code', 'pattern' => '[0-9]*', 'placeholder' => '半角数字 ハイフン不要'],
            '都道府県' => ['type' => 'text', 'name' => 'prefecture'],
            '市区町村' => ['type' => 'text', 'name' => 'city'],
            '番地' => ['type' => 'text', 'name' => 'address'],
            '建物名' => ['type' => 'text', 'name' => 'building'],
            '締め日' => ['type' => 'select', 'name' => 'closing_day', 'options' => ['5日', '10日', '15日', '20日', '25日', '末日', 'なし'], 'default' => 'なし'],
            '支払月' => ['type' => 'select', 'name' => 'payment_month', 'options' => ['今月', '翌月', '翌々月', 'その他'], 'default' => 'その他'],
            '支払日' => ['type' => 'select', 'name' => 'payment_day', 'options' => ['即日', '5日', '10日', '15日', '20日', '25日', '末日'], 'default' => '即日'],
            '支払方法' => ['type' => 'select', 'name' => 'payment_method', 'options' => ['銀行振込（後）','銀行振込（前）', 'クレジットカード', '現金集金'], 'default' => '銀行振込（前）'],
            '税区分' => ['type' => 'select', 'name' => 'tax_category', 'options' => ['外税', '内税'], 'default' => '内税'],
            'メモ' => ['type' => 'textarea', 'name' => 'memo'],
            'カテゴリー' => [
                'type' => 'text',
                'name' => 'category',
                'options' => '一般',
                'suggest' => true,
            ],
        ];

        // フォーム表示用のアクション（istmode:追加、srcmode:検索、update:更新）
        $form_action = $action;
        if ($action === 'istmode' || $action === 'srcmode') {
            $data_id = ''; // 追加モードの場合はdata_idを空に
        }

        // 空のフォームを表示(追加モードの場合)
        if ($action === 'istmode') {
            // 追加モードは data_id を空にする
            $data_id = '';
            // 詳細表示部分の開始
            $data_title = '<div class="data_detail_box">' .
                          '<div class="data_detail_title">■ ' . esc_html__('協力会社追加中', 'ktpwp') . '</div>';
            // 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
            $data_forms = <<<END
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var postalCode = document.querySelector('input[name="postal_code"]');
                var prefecture = document.querySelector('input[name="prefecture"]');
                var city = document.querySelector('input[name="city"]');
                var address = document.querySelector('input[name="address"]');
                postalCode.addEventListener('blur', function() {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + postalCode.value);
                    xhr.addEventListener('load', function() {
                        var response = JSON.parse(xhr.responseText);
                        if (response.results) {
                            var data = response.results[0];
                            prefecture.value = data.address1;
                            city.value = data.address2 + data.address3; // 市区町村と町名を結合
                            address.value = ''; // 番地は空欄に
                        }
                    });
                    xhr.send();
                });
            });
            </script>
            END;
            // 空のフォームフィールドを生成
            $data_forms .= '<form method="post" action="' . esc_url($form_action_base_url) . '">';
            if (function_exists('wp_nonce_field')) { $data_forms .= wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false); }
            foreach ($fields as $label => $field) {
                $value = '';
                $pattern = isset($field['pattern']) ? " pattern=\"{$field['pattern']}\"" : '';
                $required = isset($field['required']) && $field['required'] ? ' required' : '';
                $fieldName = $field['name'];
                $placeholder = isset($field['placeholder']) ? " placeholder=\"{$field['placeholder']}\"" : '';
                if ($field['type'] === 'textarea') {
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}>{$value}</textarea></div>";
                } elseif ($field['type'] === 'select') {
                    $options = '';
                    foreach ($field['options'] as $option) {
                        $selected = $value === $option ? ' selected' : '';
                        $options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
                    }
                    $default = isset($field['default']) ? $field['default'] : '';
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$fieldName}\"{$required}><option value=\"\">{$default}</option>{$options}</select></div>";
                } else {
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <input type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
                }
            }
            $data_forms .= "<div class='button'>";
            // 追加実行ボタン
            $data_forms .= "<input type='hidden' name='query_post' value='insert'>";
            $data_forms .= "<input type='hidden' name='data_id' value=''>";
            $data_forms .= "<button type='submit' name='send_post' title='追加実行'><span class='material-symbols-outlined'>select_check_box</span></button>";
            // キャンセルボタン（JavaScriptでリダイレクト）
            global $wp;
            $current_page_id = get_queried_object_id();
            $base_page_url = get_permalink( $current_page_id );
            if ( ! $base_page_url ) {
                $base_page_url = home_url( add_query_arg( array(), $wp->request ) );
            }
            $cancel_url = add_query_arg( array( 'tab_name' => $name ), $base_page_url );
            $data_forms .= "<button type='button' onclick='window.location.href=\"" . esc_js( $cancel_url ) . "\"' title='キャンセル'><span class='material-symbols-outlined'>disabled_by_default</span></button>";
            $data_forms .= "<div class=\"add\"></div>";
            $data_forms .= '</div>';
            $data_forms .= '</form>';
        }

        // 空のフォームを表示(検索モードの場合)
        elseif ($action === 'srcmode') {
            // 表題
            $data_title = '<div class="data_detail_box search-mode">' .
                          '<div class="data_detail_title">■ ' . esc_html__('協力会社の詳細（検索モード）', 'ktpwp') . '</div>';

            // 検索モード用のフォーム（得意先タブと同じ構造・装飾に）
            $data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
            $data_forms .= '<form method="post" action="' . esc_url($form_action_base_url) . '">';
            $data_forms .= function_exists('wp_nonce_field') ? wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false) : '';
            // 検索クエリの値を取得（POSTが優先、次にGET）
            $search_query_value = '';
            if (isset($_POST['search_query'])) {
                $search_query_value = esc_attr($_POST['search_query']);
            } elseif (isset($_GET['search_query'])) {
                $search_query_value = esc_attr(urldecode($_GET['search_query']));
            }
            $data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
            $data_forms .= '<input type="text" name="search_query" placeholder="フリーワード検索" value="' . $search_query_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
            $data_forms .= '</div>';

            // 検索結果がない場合のメッセージ表示
            if ((isset($_POST['query_post']) && $_POST['query_post'] === 'search' && empty($search_results_list)) ||
                (isset($_GET['no_results']) && $_GET['no_results'] === '1')) {
                $no_results_id = 'no-results-' . uniqid();
                $data_forms .= '<div id="' . $no_results_id . '" class="no-results" style="
                    padding: 15px 20px !important;
                    background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%) !important;
                    border-radius: 6px !important;
                    margin: 15px 0 !important;
                    color: #333333 !important;
                    font-weight: 500 !important;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.08) !important;
                    display: flex !important;
                    align-items: center !important;
                    font-size: 14px !important;
                    opacity: 1;
                    transition: opacity 0.3s ease-in-out !important;
                ">
                <span style="
                    margin-right: 10px !important;
                    color: #ff6b8b !important;
                    font-size: 18px !important;
                " class="material-symbols-outlined">search_off</span>
                検索結果が見つかりませんでした。別のキーワードをお試しください。
                </div>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var noResultsEl = document.getElementById("' . $no_results_id . '");
                    if (noResultsEl) {
                        // 4秒後に非表示にする
                        setTimeout(function() {
                            noResultsEl.style.opacity = "0";
                            setTimeout(function() {
                                if (noResultsEl.parentNode) {
                                    noResultsEl.style.display = "none";
                                }
                            }, 300);
                        }, 4000);
                    }
                });
                </script>';
            }

            // ボタンを横並びにするためのラップクラスを追加
            $data_forms .= '<div class="button-group" style="display: flex !important; justify-content: space-between !important; margin-top: 20px !important;">';

            // 検索実行ボタン
            $data_forms .= '<input type="hidden" name="query_post" value="search">';
            $data_forms .= '<button type="submit" name="send_post" title="検索実行" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
            $data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
            $data_forms .= '検索実行';
            $data_forms .= '</button>';
            $data_forms .= '</form>';

            // 検索モードのキャンセルボタン（独立したフォーム）
            $data_forms .= '<form method="post" action="' . esc_url($form_action_base_url) . '" style="margin: 0 !important;">';
            $data_forms .= function_exists('wp_nonce_field') ? wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false) : '';
            $data_forms .= '<input type="hidden" name="query_post" value="update">';
            $data_forms .= '<button type="submit" name="send_post" title="キャンセル" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
            $data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
            $data_forms .= 'キャンセル';
            $data_forms .= '</button>';
            $data_forms .= '</form>';

            $data_forms .= '</div>'; // ボタンラップクラスの閉じタグ
            $data_forms .= '</div>'; // search-mode-formの閉じタグ
        }

        // 追加・検索 以外なら更新フォームを表示
        elseif ($action !== 'srcmode' && $action !== 'istmode') {

            // 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
            $data_forms = <<<END
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var postalCode = document.querySelector('input[name="postal_code"]');
                var prefecture = document.querySelector('input[name="prefecture"]');
                var city = document.querySelector('input[name="city"]');
                var address = document.querySelector('input[name="address"]');
                if (postalCode) {
                    postalCode.addEventListener('blur', function() {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + postalCode.value);
                        xhr.addEventListener('load', function() {
                            var response = JSON.parse(xhr.responseText);
                            if (response.results) {
                                var data = response.results[0];
                                if (prefecture) prefecture.value = data.address1;
                                if (city) city.value = data.address2 + data.address3; // 市区町村と町名を結合
                                if (address) address.value = ''; // 番地は空欄に
                            }
                        });
                        xhr.send();
                    });
                }
            });
            </script>
            END;

            // ボタングループHTML生成
            $button_group_html = '<div class="button-group" style="display: flex; gap: 10px; margin-left: auto;">';

            // 削除ボタン
            $button_group_html .= '<form method="post" action="" style="margin: 0;">';
            $button_group_html .= wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false);
            $button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr($query_id) . '">';
            $button_group_html .= '<input type="hidden" name="query_post" value="delete">';
            $button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__('削除する', 'ktpwp') . '" onclick="return confirm(\'' . esc_js(__('本当に削除しますか？', 'ktpwp')) . '\')" class="button-style delete-submit-btn">';
            $button_group_html .= '<span class="material-symbols-outlined">delete</span>';
            $button_group_html .= '</button>';
            $button_group_html .= '</form>';

            // 追加モードボタン
            $add_action = 'istmode';
            $button_group_html .= '<form method="post" action="" style="margin: 0;">';
            $button_group_html .= wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false);
            $button_group_html .= '<input type="hidden" name="data_id" value="">';
            $button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr($add_action) . '">';
            $button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__('追加する', 'ktpwp') . '" class="button-style add-submit-btn">';
            $button_group_html .= '<span class="material-symbols-outlined">add</span>';
            $button_group_html .= '</button>';
            $button_group_html .= '</form>';

            // 検索モードボタン
            $search_action = 'srcmode';
            $button_group_html .= '<form method="post" action="" style="margin: 0;">';
            $button_group_html .= wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false);
            $button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr($search_action) . '">';
            $button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__('検索する', 'ktpwp') . '" class="button-style search-mode-btn">';
            $button_group_html .= '<span class="material-symbols-outlined">search</span>';
            $button_group_html .= '</button>';
            $button_group_html .= '</form>';

            $button_group_html .= '</div>'; // ボタングループ終了

            // 表題にボタングループを含める
            $data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
            <div>■ 協力会社の詳細（ ID: ' . esc_html($query_id) . ' ）</div>' . $button_group_html . '</div>';

            // メイン更新フォーム
            $data_forms .= '<form method="post" action="' . esc_url($form_action_base_url) . '">';
            if (function_exists('wp_nonce_field')) {
                $data_forms .= wp_nonce_field('ktp_supplier_action', 'ktp_supplier_nonce', true, false);
            }

            foreach ($fields as $label => $field) {
                $value = ($action === 'istmode') ? '' : (isset(${$field['name']}) ? ${$field['name']} : '');
                $pattern = isset($field['pattern']) ? " pattern=\"{$field['pattern']}\"" : '';
                $required = isset($field['required']) && $field['required'] ? ' required' : '';
                $placeholder = isset($field['placeholder']) ? " placeholder=\"{$field['placeholder']}\"" : '';

                if ($field['type'] === 'textarea') {
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$field['name']}\"{$pattern}{$required}>{$value}</textarea></div>";
                } elseif ($field['type'] === 'select') {
                    $options = '';
                    foreach ($field['options'] as $option) {
                        // 追加モードでは何も選択しない
                        $selected = ($action === 'istmode') ? '' : ($value === $option ? ' selected' : '');
                        $options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
                    }
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$field['name']}\"{$required}>{$options}</select></div>";
                } else {
                    $data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <input type=\"{$field['type']}\" name=\"{$field['name']}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
                }
            }

            // hidden data_id は常に現在表示中のID（$query_id）
            $data_forms .= "<input type=\"hidden\" name=\"data_id\" value=\"{$query_id}\">";
            $data_forms .= "<input type=\"hidden\" name=\"query_post\" value=\"update\">";

            // 検索リストを生成
            $data_forms .= $search_results_list;
            $data_forms .= "<div class='button'>";
            // 更新ボタンのみ残す
            $data_forms .= '<button type="submit" name="send_post" title="更新する"><span class="material-symbols-outlined">cached</span></button>';
            $data_forms .= "<div class=\"add\"></div>";
            $data_forms .= '</div>';
            $data_forms .= '</form>';
        }

        $data_forms .= '</div>'; // フォームを囲む<div>タグの終了

        // スキル管理インターフェースを表示（協力会社が選択されている場合のみ）
        $skills_section = '';
        if ($query_id && is_numeric($query_id) && $query_id > 0) {
            // スキル管理クラスをロード
            if (!class_exists('KTPWP_Supplier_Skills')) {
                require_once dirname(__FILE__) . '/class-ktpwp-supplier-skills.php';
            }
            
            $skills_manager = KTPWP_Supplier_Skills::get_instance();
            if ($skills_manager) {
                // 協力会社の職能追加フォームと職能リストを協力会社リストBOX内に配置
                $skills_section = $skills_manager->render_skills_interface($query_id);
            }
        }
        
        // 協力会社リストBOXの終了
        $skills_section .= '</div>'; // ktp_data_list_boxの終了

        // 詳細表示部分の終了
        $div_end = <<<END
            </div> <!-- data_detail_boxの終了 -->
        </div> <!-- ktp_data_contentsの終了 -->
        END;

        // -----------------------------
        // テンプレート印刷
        // -----------------------------

        // Print_Classのパスを指定
        require_once( dirname( __FILE__ ) . '/class-print.php' );

        // データを指定

        $data_src = [
            'company_name'        => $company_name,
            'name'                => $user_name,
            'representative_name' => $representative_name,
            'postal_code'         => $postal_code,
            'prefecture'          => $prefecture,
            'city'                => $city,
            'address'             => $address,
            'building'            => $building,
        ];

        $customer = $data_src['company_name'];
        $user_name = $data_src['name'];
        $representative_name = $data_src['representative_name'];
        $postal_code = $data_src['postal_code'];
        $prefecture = $data_src['prefecture'];
        $city = $data_src['city'];
        $address = $data_src['address'];
        $building = $data_src['building'];

        $data = [
            'postal_code' => "$postal_code",
            'prefecture' => "$prefecture",
            'city' => "$city",
            'address' => "$address",
            'building' => "$building",
            'customer' => "$customer",
            'user_name' => "$user_name",
        ];

        $print_html = new Print_Class($data);
        $print_html = $print_html->generateHTML();

        // PHP
        $print_html = json_encode($print_html);  // JSON形式にエンコード

        // JavaScript
        $print = <<<END
        <script>
            var isPreviewOpen = false;            function printContent() {
                var printContent = $print_html;
                var printWindow = window.open('', '_blank');
                printWindow.document.open();
                printWindow.document.write('<html><head><title>印刷</title></head><body>');
                printWindow.document.write(printContent);
                printWindow.document.write('<script>window.onafterprint = function(){ window.close(); }<\/script>');
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.print();  // Add this line

                // 印刷後、プレビューが開いていれば閉じる
                if (isPreviewOpen) {
                    togglePreview();
                }
            }

            function togglePreview() {
                var previewWindow = document.getElementById('previewWindow');
                var previewButton = document.getElementById('previewButton');
                if (isPreviewOpen) {
                    previewWindow.style.display = 'none';
                    previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="プレビュー">preview</span>';
                    isPreviewOpen = false;
                } else {
                    var printContent = $print_html;
                    previewWindow.innerHTML = printContent;
                    previewWindow.style.display = 'block';
                    previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="閉じる">close</span>';
                    isPreviewOpen = true;
                }
            }

            // about:blankを閉じる
            // window.onafterprint = function() {
            //     window.close();
            // }

        </script>        <div class="controller">
            <div class="printer">
                <button id="previewButton" onclick="togglePreview()" title="プレビュー">
                    <span class="material-symbols-outlined" aria-label="プレビュー">preview</span>
                </button>
                <button onclick="printContent()" title="印刷する">
                    <span class="material-symbols-outlined" aria-label="印刷">print</span>
                </button>
            </div>        </div>
        <div class="workflow">
        </div>
        <div id="previewWindow" style="display: none;"></div>
        END;

        // コンテンツを返す - 協力会社職能BOXを協力会社リストBOXの内部に配置
        $content = $print . $data_list . $skills_section . $data_title . $data_forms . $search_results_list . $div_end;
        return $content;

    }

    /**
     * Handle skills operations (add, delete, etc.)
     *
     * @since 1.0.0
     * @param array $post_data POST data array
     * @return void
     */
    private function handle_skills_operations( $post_data ) {
        // Check if this is a skills operation
        if ( ! isset( $post_data['skills_action'] ) ) {
            return;
        }

        // Security check - verify nonce
        if ( ! isset( $post_data['ktp_skills_nonce'] ) ||
             ! wp_verify_nonce( $post_data['ktp_skills_nonce'], 'ktp_skills_action' ) ) {
            // Display security error message and stop execution
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("セキュリティチェックに失敗しました。ページを更新して再度お試しください。");
            });
            </script>';
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'edit_posts' ) ) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("この操作を行う権限がありません。");
            });
            </script>';
            return;
        }

        $action = sanitize_key( $post_data['skills_action'] );

        switch ( $action ) {
            case 'add_skill':
                $this->handle_add_skill( $post_data );
                break;
            case 'delete_skill':
                $this->handle_delete_skill( $post_data );
                break;
        }
    }

    /**
     * Handle adding a new skill
     *
     * @since 1.0.0
     * @param array $post_data POST data array
     * @return void
     */
    private function handle_add_skill( $post_data ) {
        // Load skills manager
        if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
            require_once dirname( __FILE__ ) . '/class-ktpwp-supplier-skills.php';
        }

        $skills_manager = KTPWP_Supplier_Skills::get_instance();
        if ( ! $skills_manager ) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("スキル管理システムが利用できません。");
            });
            </script>';
            return;
        }

        // Sanitize input data
        $supplier_id = isset( $post_data['supplier_id'] ) ? absint( $post_data['supplier_id'] ) : 0;
        $product_name = isset( $post_data['product_name'] ) ? sanitize_text_field( $post_data['product_name'] ) : '';
        $unit_price = isset( $post_data['unit_price'] ) ? floatval( $post_data['unit_price'] ) : 0;
        $quantity = isset( $post_data['quantity'] ) ? absint( $post_data['quantity'] ) : 1;
        $unit = isset( $post_data['unit'] ) ? sanitize_text_field( $post_data['unit'] ) : '式';

        // Validate required fields
        if ( empty( $supplier_id ) || empty( $product_name ) ) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("必要な情報が不足しています。");
            });
            </script>';
            return;
        }

        // Add the skill
        $result = $skills_manager->add_skill( $supplier_id, $product_name, $unit_price, $quantity, $unit );

        if ( $result ) {
            // POSTデータ重複送信防止のためリダイレクト
            $redirect_url = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
            $redirect_url = add_query_arg( 'message', 'skill_added', $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        } else {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("商品・サービスの追加に失敗しました。");
            });
            </script>';
        }
    }

    /**
     * Handle deleting a skill
     *
     * @since 1.0.0
     * @param array $post_data POST data array
     * @return void
     */
    private function handle_delete_skill( $post_data ) {
        // Load skills manager
        if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
            require_once dirname( __FILE__ ) . '/class-ktpwp-supplier-skills.php';
        }

        $skills_manager = KTPWP_Supplier_Skills::get_instance();
        if ( ! $skills_manager ) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("スキル管理システムが利用できません。");
            });
            </script>';
            return;
        }

        // Sanitize input data
        $skill_id = isset( $post_data['skill_id'] ) ? absint( $post_data['skill_id'] ) : 0;

        // Validate required fields
        if ( empty( $skill_id ) ) {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("削除する商品・サービスが指定されていません。");
            });
            </script>';
            return;
        }

        // Delete the skill
        $result = $skills_manager->delete_skill( $skill_id );

        if ( $result ) {
            // POSTデータ重複送信防止のためリダイレクト
            $redirect_url = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
            $redirect_url = add_query_arg( 'message', 'skill_deleted', $redirect_url );
            wp_redirect( $redirect_url );
            exit;
        } else {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("商品・サービスの削除に失敗しました。");
            });
            </script>';
        }
    }

}
} // class_exists
