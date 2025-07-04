<?php
/**
 * Handles database operations for KTPWP settings.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KTPWP_Setting_DB {

    /**
     * Create settings table
     *
     * @param string $tab_name Table name suffix
     * @return bool True on success, false on failure
     */
    public static function create_table( $tab_name ) {
        global $wpdb;
        $my_table_version = '1.0.1';
        $table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );
        $charset_collate = $wpdb->get_charset_collate();

        $columns_def = array(
            'id' => 'id mediumint(9) NOT NULL AUTO_INCREMENT',
            'tax_rate' => 'tax_rate varchar(255) DEFAULT "" NOT NULL',
            'closing_date' => 'closing_date varchar(255) DEFAULT "" NOT NULL',
            'invoice' => 'invoice varchar(255) DEFAULT "" NOT NULL',
            'bank_account' => 'bank_account varchar(255) DEFAULT "" NOT NULL',
            'my_company_content' => 'my_company_content longtext DEFAULT "" NOT NULL',
            'template_content' => 'template_content longtext DEFAULT "" NOT NULL',
        );

        $columns_sql = array_values( $columns_def );
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( $table_exists !== $table_name ) {
            $sql = "CREATE TABLE `{$table_name}` (" . implode( ', ', $columns_sql ) . ", PRIMARY KEY (id)) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
            add_option( 'ktp_' . $tab_name . '_table_version', $my_table_version );
            return true;
        }

        return false;
    }

    /**
     * Update settings table
     *
     * @param string $tab_name Table name suffix
     */
    public static function update_table( $tab_name ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_' . $tab_name;
        $my_table_version = '1.0.2';

        if ( get_option( 'ktp_' . $tab_name . '_table_version' ) != $my_table_version ) {
            $columns_def = array(
                'id' => 'id mediumint(9) NOT NULL AUTO_INCREMENT',
                'email_address' => 'email_address varchar(255) DEFAULT "" NOT NULL',
                'tax_rate' => 'tax_rate varchar(255) DEFAULT "" NOT NULL',
                'closing_date' => 'closing_date varchar(255) DEFAULT "" NOT NULL',
                'invoice' => 'invoice varchar(255) DEFAULT "" NOT NULL',
                'bank_account' => 'bank_account varchar(255) DEFAULT "" NOT NULL',
                'my_company_content' => 'my_company_content longtext DEFAULT "" NOT NULL',
                'template_content' => 'template_content longtext DEFAULT "" NOT NULL',
            );

            $existing_columns = $wpdb->get_col( "DESCRIBE $table_name", 0 );
            foreach ( $columns_def as $col_name => $col_def ) {
                if ( ! in_array( $col_name, $existing_columns ) ) {
                    $wpdb->query( "ALTER TABLE $table_name ADD COLUMN $col_def" );
                }
            }

            update_option( 'ktp_' . $tab_name . '_table_version', $my_table_version );
        }
    }
}
