<?php
/**
 * 部署テーブル作成マイグレーション
 *
 * @package KTPWP
 * @subpackage Migrations
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 部署テーブルを作成するマイグレーションクラス
 */
class KTPWP_Department_Migration {

    /**
     * マイグレーションを実行
     */
    public static function run() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_department';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            client_id mediumint(9) NOT NULL COMMENT '顧客ID',
            department_name varchar(255) NOT NULL COMMENT '部署名',
            contact_person varchar(255) NOT NULL COMMENT '担当者名',
            email varchar(100) NOT NULL COMMENT 'メールアドレス',
            created_at datetime DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY email (email)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $result = dbDelta( $sql );

        if ( ! empty( $result ) ) {
            // マイグレーション完了フラグを設定
            update_option( 'ktp_department_table_version', '1.0.0' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署テーブルが正常に作成されました。' );
            }

            return true;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 部署テーブルの作成に失敗しました。' );
        }

        return false;
    }

    /**
     * マイグレーションが必要かどうかをチェック
     */
    public static function needs_migration() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ktp_department';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        return $table_exists !== $table_name;
    }
}

// マイグレーションクラスのみ定義（自動実行はしない）
// プラグインの有効化時に ktpwp_create_department_table() 関数で実行される
