<?php
/**
 * Handles template processing for KTPWP settings.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KTPWP_Setting_Template {

    /**
     * Generate template preview with placeholder replacements
     *
     * @param string $template_content The template content
     * @param array  $customer_data Customer data for replacements
     * @return string Processed template preview
     */
    public static function generate_preview( $template_content, $customer_data ) {
        $replace_words = array(
            '_%customer%_' => $customer_data['customer'] ?? 'ダミー顧客名',
            '_%postal_code%_' => $customer_data['postal_code'] ?? '123-4567',
            '_%prefecture%_' => $customer_data['prefecture'] ?? '東京都',
            '_%city%_' => $customer_data['city'] ?? '千代田区',
            '_%address%_' => $customer_data['address'] ?? '1-2-3',
            '_%building%_' => $customer_data['building'] ?? 'サンプルビル',
            '_%user_name%_' => $customer_data['user_name'] ?? '担当 太郎',
        );

        // nullや非文字列の場合は空文字列に変換
        $template_content = is_string( $template_content ) ? $template_content : '';

        return strtr( $template_content, $replace_words );
    }

    /**
     * Save template content to the database
     *
     * @param string $table_name The database table name
     * @param string $new_template_content The new template content
     * @return bool True on success, false on failure
     */
    public static function save_template( $table_name, $new_template_content ) {
        global $wpdb;

        $result = $wpdb->update(
            $table_name,
            array( 'template_content' => $new_template_content ),
            array( 'id' => 1 )
        );

        return $result !== false;
    }
}
