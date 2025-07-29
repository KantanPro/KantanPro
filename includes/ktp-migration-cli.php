<?php
// WordPressの初期化
if ( ! defined( 'ABSPATH' ) ) {
    // 現在のディレクトリがWordPressのルートディレクトリかチェック
    if ( file_exists( __DIR__ . '/../../../../wp-config.php' ) ) {
        require_once __DIR__ . '/../../../../wp-config.php';
    } elseif ( file_exists( __DIR__ . '/../../../wp-config.php' ) ) {
        require_once __DIR__ . '/../../../wp-config.php';
    } else {
        echo "WordPressのルートディレクトリが見つかりません。\n";
        echo "現在のディレクトリ: " . __DIR__ . "\n";
        exit( 1 );
    }
}

// コマンドライン引数を取得
$command = isset( $argv[1] ) ? $argv[1] : '';

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

// 消費税対応マイグレーションの実行
if ( $command === 'tax-rate-migration' ) {
    echo "消費税対応マイグレーションを実行します...\n";
    
    // マイグレーションファイルを読み込み
    require_once plugin_dir_path( __FILE__ ) . 'migrations/20250131_add_tax_rate_columns.php';
    
    // マイグレーションを実行
    $result = KTPWP_Migration_20250131_Add_Tax_Rate_Columns::up();
    
    if ( $result ) {
        echo "✅ 消費税対応マイグレーションが正常に完了しました。\n";
        echo "- ktp_order_invoice_itemsテーブルにtax_rateカラムを追加\n";
        echo "- ktp_order_cost_itemsテーブルにtax_rateカラムを追加\n";
        echo "- ktp_serviceテーブルにtax_rateカラムを追加\n";
        echo "- 一般設定に税率設定を追加\n";
    } else {
        echo "❌ 消費税対応マイグレーションでエラーが発生しました。\n";
        echo "エラーログを確認してください。\n";
    }
    
    exit;
}

// 消費税対応マイグレーションのロールバック
if ( $command === 'tax-rate-migration-rollback' ) {
    echo "消費税対応マイグレーションをロールバックします...\n";
    
    // マイグレーションファイルを読み込み
    require_once plugin_dir_path( __FILE__ ) . 'migrations/20250131_add_tax_rate_columns.php';
    
    // マイグレーションをロールバック
    $result = KTPWP_Migration_20250131_Add_Tax_Rate_Columns::down();
    
    if ( $result ) {
        echo "✅ 消費税対応マイグレーションのロールバックが正常に完了しました。\n";
        echo "- tax_rateカラムを削除\n";
        echo "- 税率設定を削除\n";
    } else {
        echo "❌ 消費税対応マイグレーションのロールバックでエラーが発生しました。\n";
        echo "エラーログを確認してください。\n";
    }
    
    exit;
}
