<?php
// WP-CLIコマンド登録: wp ktp migrate_table
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ktp migrate_table', function() {
        require_once dirname( __DIR__ ) . '/migrate-table-structure.php';
        WP_CLI::success( 'マイグレーション完了' );
    } );
} 