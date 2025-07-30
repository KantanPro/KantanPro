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

// ダミーデータ受注書の作成日時修正コマンド
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command(
        'ktp fix-order-dates',
        function ( $args, $assoc_args ) {
            global $wpdb;
            
            $dry_run = isset($assoc_args['dry-run']);
            
            if ($dry_run) {
                WP_CLI::log('ドライランモード: 実際の更新は行いません');
            }
            
            WP_CLI::log('ダミーデータ受注書の作成日時と顧客情報修正を開始します...');
            WP_CLI::log('対象: timeフィールドが0またはNULL、または顧客情報が空の受注書');
            
            // 対象テーブル
            $table_name = $wpdb->prefix . 'ktp_order';
            
            // timeフィールドが0またはNULL、または顧客情報が空の受注書を取得
            $orders_to_fix = $wpdb->get_results(
                "SELECT id, order_date, order_number, project_name, customer_name, user_name, client_id
                 FROM {$table_name} 
                 WHERE ((time = 0 OR time IS NULL) OR (customer_name = '' OR customer_name IS NULL))
                 AND order_date != '0000-00-00' 
                 AND order_date IS NOT NULL"
            );
            
            if (empty($orders_to_fix)) {
                WP_CLI::success('修正対象の受注書が見つかりませんでした。');
                return;
            }
            
            WP_CLI::log("修正対象受注書数: " . count($orders_to_fix));
            
            $progress = \WP_CLI\Utils\make_progress_bar('受注書の作成日時と顧客情報を修正中', count($orders_to_fix));
            
            $updated_count = 0;
            $error_count = 0;
            
            foreach ($orders_to_fix as $order) {
                // order_dateを基に適切なタイムスタンプを生成
                // 受注日の9:00-18:00の間のランダムな時間を設定
                $order_date = $order->order_date;
                $hour = rand(9, 18);
                $minute = rand(0, 59);
                $second = rand(0, 59);
                
                // 日付文字列からタイムスタンプを生成
                $datetime_string = $order_date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
                $timestamp = strtotime($datetime_string);
                
                if ($timestamp === false) {
                    WP_CLI::warning("受注書ID {$order->id} の日付変換に失敗: {$order_date}");
                    $error_count++;
                    $progress->tick();
                    continue;
                }
                
                // 顧客情報を取得
                $client_info = null;
                if ($order->client_id) {
                    $client_table = $wpdb->prefix . 'ktp_client';
                    $client_info = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT company_name, name FROM {$client_table} WHERE id = %d",
                            $order->client_id
                        )
                    );
                }
                
                // 更新データを準備
                $update_data = array();
                
                // timeフィールドが0またはNULLの場合のみ更新
                if ($order->time == 0 || $order->time === null) {
                    $update_data['time'] = $timestamp;
                }
                
                // 顧客情報があれば設定
                if ($client_info) {
                    $update_data['customer_name'] = $client_info->company_name;
                    $update_data['user_name'] = $client_info->name;
                    $update_data['company_name'] = $client_info->company_name;
                    $update_data['search_field'] = $client_info->company_name . ', ' . $client_info->name;
                }
                
                if ($dry_run) {
                    if (isset($update_data['time'])) {
                        $formatted_date = date('Y-m-d H:i:s', $timestamp);
                        WP_CLI::log("DRY RUN: 受注書ID {$order->id} ({$order->order_number}) の作成日時を {$formatted_date} に設定予定");
                    }
                    if ($client_info) {
                        WP_CLI::log("DRY RUN: 受注書ID {$order->id} ({$order->order_number}) の顧客情報を設定予定");
                        WP_CLI::log("  - 顧客名: {$client_info->company_name}");
                        WP_CLI::log("  - 担当者名: {$client_info->name}");
                    }
                    $updated_count++;
                } else {
                    // 更新データがある場合のみ更新
                    if (!empty($update_data)) {
                        $result = $wpdb->update(
                            $table_name,
                            $update_data,
                            array('id' => $order->id),
                            array_fill(0, count($update_data), '%s'),
                            array('%d')
                        );
                        
                        if ($result === false) {
                            WP_CLI::warning("受注書ID {$order->id} の更新に失敗: " . $wpdb->last_error);
                            $error_count++;
                        } else {
                            $updated_count++;
                        }
                    }
                }
                
                $progress->tick();
            }
            
            $progress->finish();
            
            WP_CLI::log("\n修正完了:");
            WP_CLI::log("- 更新成功: {$updated_count}件");
            WP_CLI::log("- エラー: {$error_count}件");
            
            // 修正後の確認
            $remaining_issues = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE ((time = 0 OR time IS NULL) OR (customer_name = '' OR customer_name IS NULL))
                 AND order_date != '0000-00-00' 
                 AND order_date IS NOT NULL"
            );
            
            WP_CLI::log("修正後、問題のある受注書数: {$remaining_issues}件");
            
            if ($remaining_issues > 0) {
                WP_CLI::warning('まだ修正が必要な受注書が残っています。');
            } else {
                WP_CLI::success('全ての受注書の作成日時と顧客情報が正常に設定されました。');
            }
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
