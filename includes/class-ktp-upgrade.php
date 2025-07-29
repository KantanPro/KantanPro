<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KTP_Upgrade {
    public static function migrate_settings() {
        global $wpdb;
        $old_table = $wpdb->prefix . 'ktp_setting';
        $new_option = 'ktp_smtp_settings';

        // 古いテーブルから設定を取得
        $old_settings = $wpdb->get_row( "SELECT * FROM $old_table WHERE id = 1" );

        if ( $old_settings ) {
            $new_settings = array(
                'email_address' => $old_settings->email_address,
                'smtp_host' => $old_settings->smtp_host,
                'smtp_port' => $old_settings->smtp_port,
                'smtp_user' => $old_settings->smtp_user,
                'smtp_pass' => $old_settings->smtp_pass,
                'smtp_secure' => $old_settings->smtp_secure,
                'smtp_from_name' => $old_settings->smtp_from_name,
            );

            // 新しい設定を保存
            update_option( $new_option, $new_settings );

            // 古いテーブルからメール関連のカラムを削除
            try {
                $wpdb->query(
                    "ALTER TABLE {$old_table} 
                    DROP COLUMN email_address,
                    DROP COLUMN smtp_host,
                    DROP COLUMN smtp_port,
                    DROP COLUMN smtp_user,
                    DROP COLUMN smtp_pass,
                    DROP COLUMN smtp_secure,
                    DROP COLUMN smtp_from_name"
                );
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: Error removing old columns: ' . $e->getMessage() ); }
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP: Settings successfully migrated to WordPress options' ); }
        }
    }
}

// アップグレード処理を実行
add_action(
    'init',
    function () {
		// アップグレードが未実行の場合のみ実行
		if ( ! get_option( 'ktp_settings_migrated' ) ) {
			KTP_Upgrade::migrate_settings();
			update_option( 'ktp_settings_migrated', true );
		}
	}
);
