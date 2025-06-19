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
        $my_table_version = '1.0.0';
        $option_name = 'ktp_supplier_skills_table_version';

        // Check if table needs to be created or updated
        $installed_version = get_option( $option_name );

        if ( $installed_version !== $my_table_version ) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                supplier_id MEDIUMINT(9) NOT NULL,
                skill_name VARCHAR(255) NOT NULL DEFAULT '',
                skill_description TEXT NOT NULL DEFAULT '',
                price INT(10) DEFAULT 0 NOT NULL,
                unit VARCHAR(50) NOT NULL DEFAULT '',
                category VARCHAR(100) NOT NULL DEFAULT '',
                priority_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id),
                KEY supplier_id (supplier_id),
                KEY is_active (is_active),
                KEY priority_order (priority_order)
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
                "SELECT * FROM {$table_name} WHERE supplier_id = %d AND is_active = 1 ORDER BY priority_order ASC, skill_name ASC",
                $supplier_id
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Add a skill for a supplier
     *
     * @since 1.0.0
     * @param int $supplier_id Supplier ID
     * @param string $skill_name Skill name
     * @param string $skill_description Skill description
     * @param float $price Price
     * @param string $unit Unit
     * @param string $category Category
     * @return int|false Skill ID on success, false on failure
     */
    public function add_skill( $supplier_id, $skill_name, $skill_description = '', $price = 0, $unit = '', $category = '' ) {
        global $wpdb;

        if ( empty( $supplier_id ) || $supplier_id <= 0 || empty( $skill_name ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $supplier_id = absint( $supplier_id );

        // Sanitize skill data
        $sanitized_data = array(
            'supplier_id' => $supplier_id,
            'skill_name' => sanitize_text_field( $skill_name ),
            'skill_description' => sanitize_textarea_field( $skill_description ),
            'price' => absint( $price ),
            'unit' => sanitize_text_field( $unit ),
            'category' => sanitize_text_field( $category ),
            'priority_order' => 0,
            'is_active' => 1,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        );

        $result = $wpdb->insert(
            $table_name,
            $sanitized_data,
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to add supplier skill: ' . $wpdb->last_error );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a skill
     *
     * @since 1.0.0
     * @param int $skill_id Skill ID
     * @param string $skill_name Skill name
     * @param string $skill_description Skill description
     * @param float $price Price
     * @param string $unit Unit
     * @param string $category Category
     * @return bool True on success, false on failure
     */
    public function update_skill( $skill_id, $skill_name, $skill_description = '', $price = 0, $unit = '', $category = '' ) {
        global $wpdb;

        if ( empty( $skill_id ) || $skill_id <= 0 || empty( $skill_name ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        $skill_id = absint( $skill_id );

        // Sanitize skill data
        $sanitized_data = array(
            'skill_name' => sanitize_text_field( $skill_name ),
            'skill_description' => sanitize_textarea_field( $skill_description ),
            'price' => absint( $price ),
            'unit' => sanitize_text_field( $unit ),
            'category' => sanitize_text_field( $category ),
            'updated_at' => current_time( 'mysql' )
        );

        $result = $wpdb->update(
            $table_name,
            $sanitized_data,
            array( 'id' => $skill_id ),
            array( '%s', '%s', '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            error_log( 'KTPWP: Failed to update supplier skill: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    /**
     * Delete a skill (soft delete by setting is_active to 0)
     *
     * @since 1.0.0
     * @param int $skill_id Skill ID
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
     * Delete all skills for a supplier (called when supplier is deleted)
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
     * Get skill by ID
     *
     * @since 1.0.0
     * @param int $skill_id Skill ID
     * @return array|null Skill data or null if not found
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

        $skills = $this->get_supplier_skills( $supplier_id );
        $supplier_id = absint( $supplier_id );

        $html = '<div class="supplier-skills-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">';
        $html .= '<h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">ID：' . $supplier_id . ' 協力会社の職能・サービス一覧</h4>';

        if ( ! empty( $skills ) ) {
            $html .= '<div class="skills-list" style="margin-bottom: 15px;">';
            foreach ( $skills as $skill ) {
                $skill_id = esc_attr( $skill['id'] );
                $skill_name = esc_html( $skill['skill_name'] );
                $skill_description = esc_html( $skill['skill_description'] );
                $price = number_format( intval( $skill['price'] ) );
                $unit = esc_html( $skill['unit'] );
                $category = esc_html( $skill['category'] );

                $html .= '<div class="skill-item" style="padding: 10px; margin-bottom: 10px; background: white; border: 1px solid #ddd; border-radius: 3px; position: relative;">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start;">';
                $html .= '<div style="flex: 1;">';
                $html .= '<strong style="color: #333; font-size: 14px;">' . $skill_name . '</strong>';
                if ( ! empty( $category ) ) {
                    $html .= ' <span style="color: #666; font-size: 12px;">(' . $category . ')</span>';
                }
                $html .= '<br>';
                if ( ! empty( $skill_description ) ) {
                    $html .= '<div style="color: #666; font-size: 12px; margin: 5px 0;">' . $skill_description . '</div>';
                }
                $html .= '<div style="color: #333; font-size: 13px; margin-top: 5px;">';
                $html .= '価格: ' . $price . '円';
                if ( ! empty( $unit ) ) {
                    $html .= ' / ' . $unit;
                }
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div style="margin-left: 10px;">';
                $html .= '<button type="button" class="delete-skill-btn" data-skill-id="' . $skill_id . '" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;" title="削除">';
                $html .= '<span class="material-symbols-outlined" style="font-size: 14px;">delete</span>';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div style="color: #666; font-style: italic; margin-bottom: 15px;">まだ職能・サービスが登録されていません。</div>';
        }

        // Add new skill form
        $html .= '<div class="add-skill-form" style="border-top: 1px solid #ddd; padding-top: 15px;">';
        $html .= '<h5 style="margin: 0 0 10px 0; color: #333;">新しい職能・サービスを追加</h5>';
        $html .= '<form method="post" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
        $html .= wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
        $html .= '<input type="hidden" name="skills_action" value="add_skill">';
        $html .= '<input type="hidden" name="supplier_id" value="' . $supplier_id . '">';
        
        $html .= '<div>';
        $html .= '<label style="font-size: 12px; color: #666;">職能・サービス名 *</label>';
        $html .= '<input type="text" name="skill_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">';
        $html .= '</div>';
        
        $html .= '<div>';
        $html .= '<label style="font-size: 12px; color: #666;">カテゴリー</label>';
        $html .= '<input type="text" name="category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;" placeholder="例：デザイン、開発、制作">';
        $html .= '</div>';
        
        $html .= '<div>';
        $html .= '<label style="font-size: 12px; color: #666;">価格（円）</label>';
        $html .= '<input type="number" name="skill_price" min="0" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;" placeholder="0">';
        $html .= '</div>';
        
        $html .= '<div>';
        $html .= '<label style="font-size: 12px; color: #666;">単位</label>';
        $html .= '<input type="text" name="unit" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;" placeholder="例：時間、件、式">';
        $html .= '</div>';
        
        $html .= '<div style="grid-column: 1 / -1;">';
        $html .= '<label style="font-size: 12px; color: #666;">説明</label>';
        $html .= '<textarea name="skill_description" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px; resize: vertical;" placeholder="職能・サービスの詳細説明"></textarea>';
        $html .= '</div>';
        
        $html .= '<div style="grid-column: 1 / -1; text-align: right; margin-top: 10px;">';
        $html .= '<button type="submit" style="background: #0073aa; color: white; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer; font-size: 14px;">';
        $html .= '<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">add</span>';
        $html .= '追加';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';

        $html .= '</div>';

        // Add JavaScript for delete functionality
        $nonce_field = wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
        $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".delete-skill-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    const skillId = this.getAttribute("data-skill-id");
                    if (confirm("この職能・サービスを削除しますか？")) {
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

}

} // End class_exists check
