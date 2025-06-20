<?php
/**
 * Supplier Skills Class for KTPWP plugin
 *
 * Handles supplier skills/capabilities data management including table creation,
 * data operations (CRUD), and linking with supplier data.
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

if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {

/**
 * Supplier Skills class for managing supplier capabilities/services
 *
 * This class manages the skills/services/products that each supplier can provide.
 * Each skill is linked to a specific supplier ID and automatically managed
 * when suppliers are added or deleted.
 *
 * @since 1.0.0
 */
class KTPWP_Supplier_Skills {

    /**
     * Instance of this class
     *
     * @var KTPWP_Supplier_Skills
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return KTPWP_Supplier_Skills
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
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
        // Hook into supplier deletion to clean up skills
        add_action( 'ktpwp_supplier_deleted', array( $this, 'delete_supplier_skills' ), 10, 1 );
    }

    /**
     * Create supplier skills table
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $my_table_version = '2.0.0'; // Updated for new product structure
        $option_name = 'ktp_supplier_skills_table_version';

        // Check if table needs to be created or updated
        $installed_version = get_option( $option_name );

        if ( $installed_version !== $my_table_version ) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                supplier_id MEDIUMINT(9) NOT NULL,
                product_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商品名',
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '単価',
                quantity INT NOT NULL DEFAULT 1 COMMENT '数量',
                unit VARCHAR(50) NOT NULL DEFAULT '式' COMMENT '単位',
                priority_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id),
                KEY supplier_id (supplier_id),
                KEY is_active (is_active),
                KEY priority_order (priority_order),
                KEY product_name (product_name),
                KEY unit_price (unit_price)
            ) {$charset_collate}";

            // Include upgrade functions
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            if ( function_exists( 'dbDelta' ) ) {
                $result = dbDelta( $sql );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: Supplier Skills table creation result: ' . print_r( $result, true ) );
                }

                if ( ! empty( $result ) ) {
                    update_option( $option_name, $my_table_version );
                    return true;
                }

                error_log( 'KTPWP: Failed to create supplier skills table' );
                return false;
            }

            error_log( 'KTPWP: dbDelta function not available' );
            return false;
        }

        return true;
    }

    /**
     * Get skills for a specific supplier
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @return array Array of skills data
     */
    public function get_supplier_skills( $supplier_id ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
            return array();
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE supplier_id = %d AND is_active = 1 ORDER BY priority_order ASC, product_name ASC",
                $supplier_id
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Get skills for a specific supplier with pagination
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @param int $limit Number of items per page
     * @param int $offset Starting position
     * @return array Array of skills data
     */
    public function get_supplier_skills_paginated( $supplier_id, $limit = 10, $offset = 0 ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
            return array();
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );
        $limit = absint( $limit );
        $offset = absint( $offset );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE supplier_id = %d AND is_active = 1 ORDER BY priority_order ASC, product_name ASC LIMIT %d OFFSET %d",
                $supplier_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Get total count of skills for a specific supplier
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @return int Total count of skills
     */
    public function get_supplier_skills_count( $supplier_id ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
            return 0;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE supplier_id = %d AND is_active = 1",
                $supplier_id
            )
        );

        return $count ? intval( $count ) : 0;
    }

    /**
     * Add a product for a supplier
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @param string $product_name Product name
     * @param float $unit_price Unit price
     * @param int $quantity Quantity (default: 1)
     * @param string $unit Unit (default: '式')
     * @return int|false Product ID on success, false on failure
     */
    public function add_skill( $supplier_id, $product_name, $unit_price = 0, $quantity = 1, $unit = '式' ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 || empty( $product_name ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );

        // Sanitize product data
        $sanitized_data = array(
            'supplier_id' => $supplier_id,
            'product_name' => sanitize_text_field( $product_name ),
            'unit_price' => floatval( $unit_price ),
            'quantity' => absint( $quantity ) ?: 1,
            'unit' => sanitize_text_field( $unit ) ?: '式',
            'priority_order' => 0,
            'is_active' => 1,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        );

        $result = $wpdb->insert(
            $table_name,
            $sanitized_data,
            array( '%d', '%s', '%f', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to add supplier product: ' . $wpdb->last_error );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a product
     *
     * @since 1.0.0
     * @param int $skill_id Product ID
     * @param string $product_name Product name
     * @param float $unit_price Unit price
     * @param int $quantity Quantity
     * @param string $unit Unit
     * @return bool True on success, false on failure
     */
    public function update_skill( $skill_id, $product_name, $unit_price = 0, $quantity = 1, $unit = '式' ) {
        global $wpdb;

        if ( empty( $skill_id ) || $skill_id <= 0 || empty( $product_name ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $skill_id = absint( $skill_id );

        // Sanitize product data
        $sanitized_data = array(
            'product_name' => sanitize_text_field( $product_name ),
            'unit_price' => floatval( $unit_price ),
            'quantity' => absint( $quantity ) ?: 1,
            'unit' => sanitize_text_field( $unit ) ?: '式',
            'updated_at' => current_time( 'mysql' )
        );

        $result = $wpdb->update(
            $table_name,
            $sanitized_data,
            array( 'id' => $skill_id ),
            array( '%s', '%f', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to update supplier product: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    /**
     * Delete a product (soft delete by setting is_active to 0)
     *
     * @since 1.0.0
     * @param int $skill_id Product ID
     * @return bool True on success, false on failure
     */
    public function delete_skill( $skill_id ) {
        global $wpdb;

        if ( empty( $skill_id ) || $skill_id <= 0 ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $skill_id = absint( $skill_id );

        $result = $wpdb->update(
            $table_name,
            array( 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $skill_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to delete supplier skill: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    /**
     * Delete all products for a supplier (called when supplier is deleted)
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @return bool True on success, false on failure
     */
    public function delete_supplier_skills( $supplier_id ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );

        $result = $wpdb->delete(
            $table_name,
            array( 'supplier_id' => $supplier_id ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to delete supplier skills: ' . $wpdb->last_error );
            return false;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP: Deleted {$result} skills for supplier ID {$supplier_id}" );
        }

        return true;
    }

    /**
     * Get product by ID
     *
     * @since 1.0.0
     * @param int $skill_id Product ID
     * @return array|null Product data or null if not found
     */
    public function get_skill( $skill_id ) {
        global $wpdb;

        if ( empty( $skill_id ) || $skill_id <= 0 ) {
            return null;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $skill_id = absint( $skill_id );

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d AND is_active = 1",
                $skill_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Generate HTML for skills management interface
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @return string HTML content
     */
    public function render_skills_interface( $supplier_id ) {
        if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
            return '';
        }

        // ページネーション設定
        // 一般設定から表示件数を取得（設定クラスが利用可能な場合）
        if ( class_exists( 'KTP_Settings' ) ) {
            $query_limit = KTP_Settings::get_work_list_range();
        } else {
            $query_limit = 10; // フォールバック値（職能リストは10件）
        }

        $current_page = isset( $_GET['skills_page'] ) ? absint( $_GET['skills_page'] ) : 1;
        $current_page = max( 1, $current_page );
        $offset = ( $current_page - 1 ) * $query_limit;

        // 職能データを取得（ページネーション付き）
        $skills = $this->get_supplier_skills_paginated( $supplier_id, $query_limit, $offset );
        $total_skills = $this->get_supplier_skills_count( $supplier_id );
        $total_pages = ceil( $total_skills / $query_limit );

        $supplier_id = absint( $supplier_id );

        $html = '';

        // Add new product form - 1行バー型
        $html .= '<div class="add-skill-form" style="margin-bottom: 20px;">';
        $html .= '<form method="post" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        $html .= wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
        $html .= '<input type="hidden" name="skills_action" value="add_skill">';
        $html .= '<input type="hidden" name="supplier_id" value="' . $supplier_id . '">';
        
        // 商品名フィールド
        $html .= '<div style="flex: 2; min-width: 120px;">';
        $html .= '<input type="text" name="product_name" required placeholder="商品名" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        $html .= '</div>';
        
        // 単価フィールド
        $html .= '<div style="flex: 1; min-width: 80px;">';
        $html .= '<input type="number" name="unit_price" min="0" step="0.01" placeholder="単価" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        $html .= '</div>';
        
        // 数量フィールド
        $html .= '<div style="flex: 0.8; min-width: 60px;">';
        $html .= '<input type="number" name="quantity" min="1" value="1" placeholder="数量" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        $html .= '</div>';
        
        // 単位フィールド
        $html .= '<div style="flex: 0.8; min-width: 60px;">';
        $html .= '<input type="text" name="unit" value="式" placeholder="単位" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        $html .= '</div>';
        
        // 追加ボタン
        $html .= '<div style="flex: 0 0 auto;">';
        $html .= '<button type="submit" style="background: #28a745; color: white; border: none; padding: 9px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; justify-content: center; white-space: nowrap; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor=\'#218838\'" onmouseout="this.style.backgroundColor=\'#28a745\'">';
        $html .= '+';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';

        // 職能リストを追加フォームの後に表示（協力会社リストと同じスタイルに統一）
        if ( ! empty( $skills ) ) {
            $html .= '<div class="ktp_data_skill_list_box" style="margin-top: 15px;">';
            
            $skill_index = 0;
            foreach ( $skills as $skill ) {
                $skill_id = esc_attr( $skill['id'] );
                $product_name = esc_html( $skill['product_name'] );
                $unit_price = number_format( floatval( $skill['unit_price'] ), 2 );
                $quantity = absint( $skill['quantity'] );
                $unit = esc_html( $skill['unit'] );

                $html .= '<div class="ktp_data_list_item skill-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; line-height: 1.2; min-height: 48px;">';
                
                // 商品情報を完全に1行で表示
                $html .= '<div style="flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">';
                $html .= '<span style="font-weight: 600; color: #2c3e50; flex-shrink: 0;">' . $product_name . '</span>';
                $html .= '<span style="color: #666; font-size: 13px; font-weight: normal; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">';
                $html .= '単価: <strong>' . $unit_price . '円</strong> | ';
                $html .= '数量: <strong>' . $quantity . '</strong> | ';
                $html .= '単位: <strong>' . $unit . '</strong>';
                $html .= '</span>';
                $html .= '</div>';
                
                // 削除ボタン
                $html .= '<div class="delete-skill-btn-container" style="flex-shrink: 0; margin-left: 12px;">';
                $html .= '<button type="button" class="delete-skill-btn" data-skill-id="' . $skill_id . '" ';
                $html .= 'style="background: #dc3545; color: white; border: none; padding: 6px 8px; border-radius: 3px; cursor: pointer; font-size: 12px; transition: background-color 0.2s ease;" ';
                $html .= 'title="削除" onmouseover="this.style.backgroundColor=\'#c82333\'" onmouseout="this.style.backgroundColor=\'#dc3545\'">';
                $html .= '<span class="material-symbols-outlined" style="font-size: 16px;">delete</span>';
                $html .= '</button>';
                $html .= '</div>';
                
                $html .= '</div>';
                $skill_index++;
            }
            $html .= '</div>';
            
            // ページネーションを職能リストの下に表示
            if ( $total_pages > 1 ) {
                $html .= $this->render_skills_pagination( $current_page, $total_pages, $total_skills, $supplier_id );
            }
        } else {
            $html .= '<div class="ktp_data_list_item" style="color: #666; font-style: italic; margin-top: 15px; padding: 20px; text-align: center; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;">';
            $html .= '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle; margin-right: 8px; color: #999;">info</span>';
            $html .= 'まだ商品・サービスが登録されていません。';
            $html .= '</div>';
        }

        // Add JavaScript for delete functionality
        $nonce_field = wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
        $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".delete-skill-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    const skillId = this.getAttribute("data-skill-id");
                    if (confirm("この商品・サービスを削除しますか？")) {
                        const form = document.createElement("form");
                        form.method = "post";
                        form.innerHTML = `
                            ' . str_replace(array("\r", "\n"), '', $nonce_field) . '
                            <input type="hidden" name="skills_action" value="delete_skill">
                            <input type="hidden" name="skill_id" value="${skillId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        </script>';

        return $html;
    }

    /**
     * Render pagination for skills list
     *
     * @since 1.0.0
     * @param int $current_page Current page
     * @param int $total_pages Total pages
     * @param int $total_skills Total skills count
     * @param int $supplier_id Supplier ID
     * @return string HTML content for pagination
     */
    private function render_skills_pagination( $current_page, $total_pages, $total_skills, $supplier_id ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        // 現在のURLを取得
        global $wp;
        $current_page_id = get_queried_object_id();
        $base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );
        
        $html = '<div class="ktp-skills-pagination" style="
            margin-top: 15px;
            padding: 15px 20px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        ">';

        // 左側：情報表示
        $page_start = ( $current_page - 1 ) * ( class_exists( 'KTP_Settings' ) ? KTP_Settings::get_work_list_range() : 10 ) + 1;
        $page_end = min( $total_skills, $current_page * ( class_exists( 'KTP_Settings' ) ? KTP_Settings::get_work_list_range() : 10 ) );
        
        $html .= '<div class="pagination-info" style="
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        ">';
        $html .= "商品・サービス {$page_start} - {$page_end} / {$total_skills}";
        $html .= '</div>';

        // 右側：ページネーションボタン
        $html .= '<div class="pagination-buttons" style="
            display: flex;
            align-items: center;
            gap: 4px;
        ">';

        // 前のページボタン
        if ( $current_page > 1 ) {
            $prev_page = $current_page - 1;
            $prev_url = add_query_arg( array(
                'data_id' => $supplier_id,
                'query_post' => 'update',
                'skills_page' => $prev_page
            ), $base_page_url );
            
            $html .= '<a href="' . esc_url( $prev_url ) . '" class="ktp-skills-pagination-btn" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                min-width: 40px;
                background: #0073aa;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
            " onmouseover="this.style.background=\'#005a87\'" onmouseout="this.style.background=\'#0073aa\'">
                <span style="font-size: 12px;">◀</span>
            </a>';
        } else {
            $html .= '<span class="ktp-skills-pagination-btn disabled" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                min-width: 40px;
                background: #e5e7eb;
                color: #9ca3af;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
            ">
                <span style="font-size: 12px;">◀</span>
            </span>';
        }

        // ページ番号ボタン（簡略版：現在のページ周辺のみ表示）
        $start_page = max( 1, $current_page - 2 );
        $end_page = min( $total_pages, $current_page + 2 );

        for ( $i = $start_page; $i <= $end_page; $i++ ) {
            if ( $i == $current_page ) {
                $html .= '<span class="ktp-skills-pagination-btn current" style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 8px 12px;
                    min-width: 40px;
                    background: #0073aa;
                    color: white;
                    border-radius: 4px;
                    font-size: 14px;
                    font-weight: 600;
                ">' . $i . '</span>';
            } else {
                $page_url = add_query_arg( array(
                    'data_id' => $supplier_id,
                    'query_post' => 'update',
                    'skills_page' => $i
                ), $base_page_url );
                
                $html .= '<a href="' . esc_url( $page_url ) . '" class="ktp-skills-pagination-btn" style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 8px 12px;
                    min-width: 40px;
                    background: #f8f9fa;
                    color: #374151;
                    text-decoration: none;
                    border: 1px solid #d1d5db;
                    border-radius: 4px;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                " onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'#f8f9fa\'">' . $i . '</a>';
            }
        }

        // 次のページボタン
        if ( $current_page < $total_pages ) {
            $next_page = $current_page + 1;
            $next_url = add_query_arg( array(
                'data_id' => $supplier_id,
                'query_post' => 'update',
                'skills_page' => $next_page
            ), $base_page_url );
            
            $html .= '<a href="' . esc_url( $next_url ) . '" class="ktp-skills-pagination-btn" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                min-width: 40px;
                background: #0073aa;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
            " onmouseover="this.style.background=\'#005a87\'" onmouseout="this.style.background=\'#0073aa\'">
                <span style="font-size: 12px;">▶</span>
            </a>';
        } else {
            $html .= '<span class="ktp-skills-pagination-btn disabled" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                min-width: 40px;
                background: #e5e7eb;
                color: #9ca3af;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
            ">
                <span style="font-size: 12px;">▶</span>
            </span>';
        }

        $html .= '</div>'; // pagination-buttons
        $html .= '</div>'; // ktp-skills-pagination

        return $html;
    }

}

} // End class_exists check
