<?php
/**
 * 配布環境用自動マイグレーション機能テスト
 * 
 * このファイルは、プラグインの新規インストール・再有効化・アップデート時の
 * 自動マイグレーション機能が正常に動作するかをテストします。
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( '権限がありません' );
}

// ナンスチェック
if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'test_distribution_migration' ) ) {
    wp_die( 'セキュリティチェックに失敗しました' );
}

echo '<div class="wrap">';
echo '<h1>配布環境用自動マイグレーション機能テスト</h1>';

// テスト結果を格納する配列
$test_results = array();

// 1. 新規インストール判定テスト
echo '<h2>1. 新規インストール判定テスト</h2>';
$is_new_installation = ktpwp_is_new_installation();
$test_results['new_installation_detection'] = $is_new_installation;
echo '<p>新規インストール判定: ' . ($is_new_installation ? '新規インストール' : '既存環境') . '</p>';

// 2. マイグレーション必要性判定テスト
echo '<h2>2. マイグレーション必要性判定テスト</h2>';
$needs_migration = ktpwp_needs_migration();
$test_results['migration_needed'] = $needs_migration;
echo '<p>マイグレーション必要性: ' . ($needs_migration ? '必要' : '不要') . '</p>';

// 3. 現在のマイグレーション状態確認
echo '<h2>3. 現在のマイグレーション状態</h2>';
$migration_status = ktpwp_check_migration_status();
echo '<ul>';
echo '<li>現在のDBバージョン: ' . esc_html( $migration_status['current_db_version'] ) . '</li>';
echo '<li>プラグインバージョン: ' . esc_html( $migration_status['plugin_version'] ) . '</li>';
echo '<li>マイグレーション必要: ' . ($migration_status['needs_migration'] ? 'はい' : 'いいえ') . '</li>';
echo '<li>最後のマイグレーション: ' . esc_html( $migration_status['last_migration'] ) . '</li>';
echo '<li>有効化完了: ' . ($migration_status['activation_completed'] ? 'はい' : 'いいえ') . '</li>';
echo '<li>アップデート完了: ' . ($migration_status['upgrade_completed'] ? 'はい' : 'いいえ') . '</li>';
echo '<li>再有効化完了: ' . ($migration_status['reactivation_completed'] ? 'はい' : 'いいえ') . '</li>';
echo '<li>新規インストール完了: ' . ($migration_status['new_installation_completed'] ? 'はい' : 'いいえ') . '</li>';
echo '</ul>';

// 4. マイグレーション進行中フラグの確認
echo '<h2>4. マイグレーション進行中フラグ</h2>';
$migration_in_progress = get_option( 'ktpwp_migration_in_progress', false );
$test_results['migration_in_progress'] = $migration_in_progress;
echo '<p>マイグレーション進行中: ' . ($migration_in_progress ? 'はい' : 'いいえ') . '</p>';

// 5. マイグレーションエラーの確認
echo '<h2>5. マイグレーションエラー確認</h2>';
$migration_error = get_option( 'ktpwp_migration_error', null );
$test_results['migration_error'] = $migration_error;
if ( $migration_error ) {
    echo '<p style="color: red;">マイグレーションエラー: ' . esc_html( $migration_error ) . '</p>';
} else {
    echo '<p style="color: green;">マイグレーションエラー: なし</p>';
}

// 6. 手動マイグレーション実行テスト
if ( isset( $_GET['run_test_migration'] ) && $_GET['run_test_migration'] === '1' ) {
    echo '<h2>6. 手動マイグレーション実行テスト</h2>';
    
    try {
        // マイグレーション実行前の状態を保存
        $before_status = ktpwp_check_migration_status();
        
        // 配布環境用の自動マイグレーションを実行
        ktpwp_distribution_auto_migration();
        
        // マイグレーション実行後の状態を取得
        $after_status = ktpwp_check_migration_status();
        
        $test_results['manual_migration_success'] = true;
        echo '<p style="color: green;">手動マイグレーションが正常に完了しました。</p>';
        echo '<ul>';
        echo '<li>実行前DBバージョン: ' . esc_html( $before_status['current_db_version'] ) . '</li>';
        echo '<li>実行後DBバージョン: ' . esc_html( $after_status['current_db_version'] ) . '</li>';
        echo '<li>マイグレーション必要: ' . ($after_status['needs_migration'] ? 'はい' : 'いいえ') . '</li>';
        echo '</ul>';
        
    } catch ( Exception $e ) {
        $test_results['manual_migration_success'] = false;
        $test_results['manual_migration_error'] = $e->getMessage();
        echo '<p style="color: red;">手動マイグレーションでエラーが発生しました: ' . esc_html( $e->getMessage() ) . '</p>';
    }
} else {
    echo '<h2>6. 手動マイグレーション実行テスト</h2>';
    echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'run_test_migration', '1' ), 'test_distribution_migration' ) ) . '" class="button button-primary">テストマイグレーションを実行</a></p>';
}

// 7. フック登録状況の確認
echo '<h2>7. フック登録状況確認</h2>';
$hooks = array(
    'register_activation_hook' => has_action( 'register_activation_hook', 'ktpwp_comprehensive_activation' ),
    'register_deactivation_hook' => has_action( 'register_deactivation_hook', 'ktpwp_plugin_deactivation' ),
    'upgrader_process_complete' => has_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration' ),
    'admin_init_reactivation' => has_action( 'admin_init', 'ktpwp_check_reactivation_migration' ),
    'admin_init_new_installation' => has_action( 'admin_init', 'ktpwp_detect_new_installation' ),
    'admin_notices' => has_action( 'admin_notices', 'ktpwp_distribution_admin_notices' ),
);

echo '<ul>';
foreach ( $hooks as $hook_name => $priority ) {
    $status = $priority !== false ? '登録済み (優先度: ' . $priority . ')' : '未登録';
    echo '<li>' . esc_html( $hook_name ) . ': ' . $status . '</li>';
    $test_results['hooks'][$hook_name] = $priority !== false;
}
echo '</ul>';

// 8. テスト結果サマリー
echo '<h2>8. テスト結果サマリー</h2>';
echo '<table class="widefat">';
echo '<thead><tr><th>テスト項目</th><th>結果</th><th>詳細</th></tr></thead>';
echo '<tbody>';

foreach ( $test_results as $test_name => $result ) {
    if ( $test_name === 'hooks' ) {
        $hook_results = array_filter( $result );
        $hook_count = count( $hook_results );
        $total_hooks = count( $result );
        $status = $hook_count === $total_hooks ? '成功' : '部分成功';
        $details = $hook_count . '/' . $total_hooks . ' フックが登録済み';
    } elseif ( is_bool( $result ) ) {
        $status = $result ? '成功' : '失敗';
        $details = $result ? '正常' : '異常';
    } elseif ( is_string( $result ) ) {
        $status = empty( $result ) ? '成功' : '失敗';
        $details = empty( $result ) ? 'エラーなし' : 'エラー: ' . $result;
    } else {
        $status = '不明';
        $details = var_export( $result, true );
    }
    
    echo '<tr>';
    echo '<td>' . esc_html( $test_name ) . '</td>';
    echo '<td>' . esc_html( $status ) . '</td>';
    echo '<td>' . esc_html( $details ) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// 9. 推奨アクション
echo '<h2>9. 推奨アクション</h2>';
echo '<ul>';

if ( $migration_status['needs_migration'] ) {
    echo '<li style="color: orange;">⚠️ データベースの更新が必要です。手動でマイグレーションを実行してください。</li>';
}

if ( $migration_error ) {
    echo '<li style="color: red;">❌ マイグレーションエラーが発生しています。エラーの詳細を確認してください。</li>';
}

if ( $migration_in_progress ) {
    echo '<li style="color: blue;">⏳ マイグレーションが進行中です。完了までお待ちください。</li>';
}

if ( ! $migration_status['activation_completed'] ) {
    echo '<li style="color: orange;">⚠️ プラグインの有効化が完了していません。プラグインを再有効化してください。</li>';
}

$all_hooks_registered = isset( $test_results['hooks'] ) && count( array_filter( $test_results['hooks'] ) ) === count( $test_results['hooks'] );
if ( ! $all_hooks_registered ) {
    echo '<li style="color: red;">❌ 一部のフックが正しく登録されていません。プラグインを再有効化してください。</li>';
}

if ( ! $migration_status['needs_migration'] && ! $migration_error && ! $migration_in_progress && $migration_status['activation_completed'] && $all_hooks_registered ) {
    echo '<li style="color: green;">✅ すべてのテストが正常です。プラグインは正常に動作しています。</li>';
}

echo '</ul>';

echo '<p><a href="' . admin_url( 'plugins.php' ) . '" class="button">プラグイン一覧に戻る</a></p>';
echo '</div>';
?>
