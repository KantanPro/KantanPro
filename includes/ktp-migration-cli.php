<?php
// WP-CLIコマンド登録: wp ktp migrate_table
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command(
        'ktp migrate_table',
        function () {
			require_once dirname( __DIR__ ) . '/migrate-table-structure.php';
			WP_CLI::success( 'マイグレーション完了' );
		}
    );
}

// WP-CLI用マイグレーション一括実行コマンド
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command(
        'ktpwp migrate_all',
        function () {
			$migrations_dir = __DIR__ . '/migrations';
			if ( ! is_dir( $migrations_dir ) ) {
				WP_CLI::warning( 'migrationsディレクトリが存在しません。includes/migrations/ を作成し、マイグレーションファイルを配置してください。' );
				return;
			}
			$files = glob( $migrations_dir . '/*.php' );
			sort( $files );
			foreach ( $files as $file ) {
				require_once $file;
			}
			WP_CLI::success( '全マイグレーションを実行しました。' );
		}
    );
}
