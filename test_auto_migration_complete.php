<?php
/**
 * KantanPro自動マイグレーション機能テストスクリプト
 * 
 * このスクリプトは、プラグインの新規インストール・再有効化・アップデート時の
 * 自動マイグレーション機能が正常に動作するかをテストします。
 * 
 * 使用方法: ブラウザで直接アクセス
 * 
 * @package KantanPro
 * @since 1.1.1
 */

// WordPress環境を読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// セキュリティチェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このページにアクセスする権限がありません。' );
}

// ヘッダー出力
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>KantanPro - 自動マイグレーション機能テスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-button { 
            background: #0073aa; color: white; padding: 10px 20px; 
            border: none; border-radius: 3px; cursor: pointer; margin: 5px;
        }
        .test-button:hover { background: #005a87; }
        .danger-button { 
            background: #dc3545; color: white; padding: 10px 20px; 
            border: none; border-radius: 3px; cursor: pointer; margin: 5px;
        }
        .danger-button:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>KantanPro - 自動マイグレーション機能テスト</h1>
    
    <div class="section info">
        <h2>テスト概要</h2>
        <p>このページでは、KantanProプラグインの自動マイグレーション機能が正常に動作するかをテストします。</p>
        <p><strong>テスト日時:</strong> <?php echo current_time( 'Y-m-d H:i:s' ); ?></p>
        <p><strong>プラグインバージョン:</strong> <?php echo KANTANPRO_PLUGIN_VERSION; ?></p>
    </div>

    <?php
    // 1. 現在のマイグレーション状態を確認
    echo '<div class="section">';
    echo '<h2>1. 現在のマイグレーション状態</h2>';
    
    $status = ktpwp_check_migration_status();
    
    echo '<p><strong>現在のDBバージョン:</strong> ' . esc_html( $status['current_db_version'] ) . '</p>';
    echo '<p><strong>プラグインバージョン:</strong> ' . esc_html( $status['plugin_version'] ) . '</p>';
    echo '<p><strong>マイグレーション必要:</strong> ' . ( $status['needs_migration'] ? 'はい' : 'いいえ' ) . '</p>';
    echo '<p><strong>最後のマイグレーション:</strong> ' . esc_html( $status['last_migration'] ) . '</p>';
    echo '<p><strong>有効化完了:</strong> ' . ( $status['activation_completed'] ? 'はい' : 'いいえ' ) . '</p>';
    echo '<p><strong>アップデート完了:</strong> ' . ( $status['upgrade_completed'] ? 'はい' : 'いいえ' ) . '</p>';
    
    if ( $status['migration_error'] ) {
        echo '<div class="error">';
        echo '<h3>❌ マイグレーションエラー</h3>';
        echo '<p>' . esc_html( $status['migration_error'] ) . '</p>';
        echo '</div>';
    }
    
    echo '</div>';

    // 2. 適格請求書機能の状態
    echo '<div class="section">';
    echo '<h2>2. 適格請求書機能の状態</h2>';
    
    $qualified_invoice = $status['qualified_invoice'];
    echo '<p><strong>マイグレーション完了:</strong> ' . ( $qualified_invoice['migrated'] ? 'はい' : 'いいえ' ) . '</p>';
    echo '<p><strong>バージョン:</strong> ' . esc_html( $qualified_invoice['version'] ) . '</p>';
    echo '<p><strong>有効:</strong> ' . ( $qualified_invoice['enabled'] ? 'はい' : 'いいえ' ) . '</p>';
    echo '<p><strong>タイムスタンプ:</strong> ' . esc_html( $qualified_invoice['timestamp'] ) . '</p>';
    
    echo '</div>';

    // 3. 手動テスト機能
    echo '<div class="section">';
    echo '<h2>3. 手動テスト機能</h2>';
    
    // 完全マイグレーション実行ボタン
    echo '<form method="post" style="margin: 10px 0;">';
    echo '<input type="hidden" name="run_complete_migration" value="1">';
    echo '<button type="submit" class="test-button">完全マイグレーションを実行</button>';
    echo '</form>';
    
    // 新規インストールシミュレーション
    echo '<form method="post" style="margin: 10px 0;">';
    echo '<input type="hidden" name="simulate_new_installation" value="1">';
    echo '<button type="submit" class="test-button">新規インストールをシミュレート</button>';
    echo '</form>';
    
    // 再有効化シミュレーション
    echo '<form method="post" style="margin: 10px 0;">';
    echo '<input type="hidden" name="simulate_reactivation" value="1">';
    echo '<button type="submit" class="test-button">再有効化をシミュレート</button>';
    echo '</form>';
    
    // アップデートシミュレーション
    echo '<form method="post" style="margin: 10px 0;">';
    echo '<input type="hidden" name="simulate_upgrade" value="1">';
    echo '<button type="submit" class="test-button">アップデートをシミュレート</button>';
    echo '</form>';
    
    // リセット機能（危険）
    echo '<form method="post" style="margin: 10px 0;" onsubmit="return confirm(\'本当にマイグレーション状態をリセットしますか？\');">';
    echo '<input type="hidden" name="reset_migration_state" value="1">';
    echo '<button type="submit" class="danger-button">マイグレーション状態をリセット</button>';
    echo '</form>';
    
    echo '</div>';

    // 4. テスト実行結果
    if ( isset( $_POST['run_complete_migration'] ) ) {
        echo '<div class="section">';
        echo '<h2>4. 完全マイグレーション実行結果</h2>';
        
        $result = ktpwp_run_complete_migration();
        
        if ( $result ) {
            echo '<div class="success">';
            echo '<h3>✅ 完全マイグレーション成功</h3>';
            echo '<p>すべてのマイグレーションが正常に完了しました。</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h3>❌ 完全マイグレーション失敗</h3>';
            echo '<p>マイグレーション中にエラーが発生しました。</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    if ( isset( $_POST['simulate_new_installation'] ) ) {
        echo '<div class="section">';
        echo '<h2>4. 新規インストールシミュレーション結果</h2>';
        
        // 新規インストール状態をシミュレート
        delete_option( 'ktpwp_activation_completed' );
        update_option( 'ktpwp_db_version', '0.0.0' );
        delete_transient( 'ktpwp_new_installation_checked' );
        
        // 新規インストール検出を実行
        ktpwp_detect_new_installation();
        
        echo '<div class="success">';
        echo '<h3>✅ 新規インストールシミュレーション完了</h3>';
        echo '<p>新規インストール状態に設定し、自動マイグレーションを実行しました。</p>';
        echo '</div>';
        echo '</div>';
    }

    if ( isset( $_POST['simulate_reactivation'] ) ) {
        echo '<div class="section">';
        echo '<h2>4. 再有効化シミュレーション結果</h2>';
        
        // 再有効化フラグを設定
        set_transient( 'ktpwp_reactivation_required', true, DAY_IN_SECONDS );
        
        // 再有効化チェックを実行
        ktpwp_check_reactivation_migration();
        
        echo '<div class="success">';
        echo '<h3>✅ 再有効化シミュレーション完了</h3>';
        echo '<p>再有効化状態に設定し、自動マイグレーションを実行しました。</p>';
        echo '</div>';
        echo '</div>';
    }

    if ( isset( $_POST['simulate_upgrade'] ) ) {
        echo '<div class="section">';
        echo '<h2>4. アップデートシミュレーション結果</h2>';
        
        // アップデートをシミュレート
        $hook_extra = array( 'plugin' => 'KantanPro/ktpwp.php' );
        ktpwp_plugin_upgrade_migration( null, $hook_extra );
        
        echo '<div class="success">';
        echo '<h3>✅ アップデートシミュレーション完了</h3>';
        echo '<p>アップデート状態に設定し、自動マイグレーションを実行しました。</p>';
        echo '</div>';
        echo '</div>';
    }

    if ( isset( $_POST['reset_migration_state'] ) ) {
        echo '<div class="section">';
        echo '<h2>4. マイグレーション状態リセット結果</h2>';
        
        // マイグレーション状態をリセット
        delete_option( 'ktpwp_activation_completed' );
        delete_option( 'ktpwp_upgrade_completed' );
        delete_option( 'ktpwp_reactivation_completed' );
        update_option( 'ktpwp_db_version', '0.0.0' );
        delete_option( 'ktpwp_migration_error' );
        delete_transient( 'ktpwp_new_installation_checked' );
        delete_transient( 'ktpwp_reactivation_required' );
        
        echo '<div class="warning">';
        echo '<h3>⚠️ マイグレーション状態リセット完了</h3>';
        echo '<p>マイグレーション状態がリセットされました。次回のプラグイン読み込み時に自動マイグレーションが実行されます。</p>';
        echo '</div>';
        echo '</div>';
    }

    // 5. データベーステーブル状態
    echo '<div class="section">';
    echo '<h2>5. データベーステーブル状態</h2>';
    
    global $wpdb;
    $tables = array(
        'ktp_order',
        'ktp_client', 
        'ktp_service',
        'ktp_supplier',
        'ktp_order_invoice_items',
        'ktp_order_staff_chat',
        'ktp_department',
        'ktp_terms_of_service',
        'ktp_donation'
    );
    
    foreach ( $tables as $table ) {
        $full_table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" ) === $full_table_name;
        
        if ( $exists ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $full_table_name" );
            echo '<p><strong>' . esc_html( $table ) . ':</strong> ✅ 存在 (' . esc_html( $count ) . '件)</p>';
        } else {
            echo '<p><strong>' . esc_html( $table ) . ':</strong> ❌ 不存在</p>';
        }
    }
    
    echo '</div>';

    // 6. 推奨事項
    echo '<div class="section info">';
    echo '<h2>6. 推奨事項</h2>';
    
    echo '<h3>自動マイグレーション機能について:</h3>';
    echo '<ul>';
    echo '<li><strong>新規インストール:</strong> プラグイン有効化時に自動的に完全マイグレーションが実行されます</li>';
    echo '<li><strong>再有効化:</strong> プラグイン無効化後に再有効化すると、自動的にマイグレーションが実行されます</li>';
    echo '<li><strong>アップデート:</strong> プラグイン更新時に自動的にマイグレーションが実行されます</li>';
    echo '<li><strong>プラグイン読み込み時:</strong> 毎回のプラグイン読み込み時に差分マイグレーションがチェックされます</li>';
    echo '</ul>';
    
    echo '<h3>配布時の注意点:</h3>';
    echo '<ul>';
    echo '<li>すべてのマイグレーションファイルが含まれていることを確認してください</li>';
    echo '<li>プラグインのバージョン番号が正しく設定されていることを確認してください</li>';
    echo '<li>テスト環境で新規インストール・再有効化・アップデートをテストしてください</li>';
    echo '</ul>';
    
    echo '</div>';
    ?>

    <div style="margin-top: 30px; text-align: center;">
        <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="test-button">プラグイン一覧に戻る</a>
        <a href="<?php echo admin_url( 'admin.php?page=ktpwp-settings' ); ?>" class="test-button">設定画面へ</a>
    </div>

</body>
</html> 