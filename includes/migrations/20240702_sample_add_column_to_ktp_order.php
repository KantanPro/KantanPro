<?php
// サンプル: wp_ktp_orderテーブルにsample_columnを追加
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ktpwp migrate_20240702_sample_add_column_to_ktp_order', function() {
        global $wpdb;
        $table = $wpdb->prefix . 'ktp_order';
        $col = 'sample_column';
        $sql = "ALTER TABLE `$table` ADD COLUMN `$col` VARCHAR(255) NULL DEFAULT NULL COMMENT 'サンプルカラム'";
        $existing = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
        if ( in_array( $col, $existing ) ) {
            WP_CLI::success( "$col は既に存在します" );
            return;
        }
        $result = $wpdb->query( $sql );
        if ( $result === false ) {
            WP_CLI::error( "カラム追加失敗: $wpdb->last_error" );
        } else {
            WP_CLI::success( "$col を追加しました" );
        }
    } );
} 