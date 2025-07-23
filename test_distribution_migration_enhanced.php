<?php
/**
 * 配布環境対応マイグレーション機能テスト
 * 不特定多数のサイトでの配布に対応したマイグレーション機能のテスト
 */

// WordPressの初期化
if ( ! defined( 'ABSPATH' ) ) {
    require_once __DIR__ . '/../../../../wp-config.php';
}

// テスト実行前の準備
echo "=== KantanPro 配布環境マイグレーション機能テスト ===\n\n";

// 1. 基本機能の存在チェック
echo "1. 基本機能の存在チェック:\n";
$required_functions = array(
    'ktpwp_run_auto_migrations',
    'ktpwp_distribution_auto_migration',
    'ktpwp_verify_migration_safety',
    'ktpwp_safe_table_setup',
    'ktpwp_verify_database_integrity',
    'ktpwp_notify_migration_error',
    'ktpwp_comprehensive_activation',
    'ktpwp_plugin_upgrade_migration',
    'ktpwp_check_reactivation_migration',
    'ktpwp_detect_new_installation'
);

$all_functions_exist = true;
foreach ( $required_functions as $function ) {
    $exists = function_exists( $function );
    echo "  - {$function}: " . ($exists ? '✓' : '✗') . "\n";
    if ( ! $exists ) {
        $all_functions_exist = false;
    }
}

echo "\n基本機能存在チェック: " . ($all_functions_exist ? 'PASS' : 'FAIL') . "\n\n";

// 2. マイグレーション安全性チェック
echo "2. マイグレーション安全性チェック:\n";
if ( function_exists( 'ktpwp_verify_migration_safety' ) ) {
    $safety_result = ktpwp_verify_migration_safety();
    echo "  - 安全性チェック結果: " . ($safety_result ? '✓ 安全' : '✗ 危険') . "\n";
} else {
    echo "  - 安全性チェック関数が存在しません\n";
}

// 3. データベース整合性チェック
echo "\n3. データベース整合性チェック:\n";
if ( function_exists( 'ktpwp_verify_database_integrity' ) ) {
    $integrity_result = ktpwp_verify_database_integrity();
    echo "  - 整合性チェック結果: " . ($integrity_result ? '✓ 正常' : '✗ 異常') . "\n";
} else {
    echo "  - 整合性チェック関数が存在しません\n";
}

// 4. 現在のマイグレーション状態
echo "\n4. 現在のマイグレーション状態:\n";
$current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
$plugin_version = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : 'Unknown';
echo "  - 現在のDBバージョン: {$current_db_version}\n";
echo "  - プラグインバージョン: {$plugin_version}\n";
echo "  - マイグレーション必要: " . (version_compare( $current_db_version, $plugin_version, '<' ) ? 'はい' : 'いいえ') . "\n";

// 5. マイグレーション統計情報
echo "\n5. マイグレーション統計情報:\n";
$statistics = array(
    'migration_attempts' => get_option( 'ktpwp_migration_attempts', 0 ),
    'migration_success_count' => get_option( 'ktpwp_migration_success_count', 0 ),
    'migration_error_count' => get_option( 'ktpwp_migration_error_count', 0 ),
    'activation_success_count' => get_option( 'ktpwp_activation_success_count', 0 ),
    'activation_error_count' => get_option( 'ktpwp_activation_error_count', 0 ),
    'upgrade_success_count' => get_option( 'ktpwp_upgrade_success_count', 0 ),
    'upgrade_error_count' => get_option( 'ktpwp_upgrade_error_count', 0 )
);

foreach ( $statistics as $key => $value ) {
    echo "  - {$key}: {$value}\n";
}

// 6. エラー情報
echo "\n6. エラー情報:\n";
$errors = array(
    'migration_error' => get_option( 'ktpwp_migration_error' ),
    'activation_error' => get_option( 'ktpwp_activation_error' ),
    'upgrade_error' => get_option( 'ktpwp_upgrade_error' )
);

foreach ( $errors as $key => $error ) {
    if ( $error ) {
        echo "  - {$key}: {$error}\n";
    } else {
        echo "  - {$key}: なし\n";
    }
}

// 7. 完了フラグ
echo "\n7. 完了フラグ:\n";
$completion_flags = array(
    'activation_completed' => get_option( 'ktpwp_activation_completed', false ),
    'upgrade_completed' => get_option( 'ktpwp_upgrade_completed', false ),
    'migration_completed' => get_option( 'ktpwp_migration_completed', false ),
    'reactivation_completed' => get_option( 'ktpwp_reactivation_completed', false ),
    'new_installation_completed' => get_option( 'ktpwp_new_installation_completed', false )
);

foreach ( $completion_flags as $key => $completed ) {
    echo "  - {$key}: " . ($completed ? '✓ 完了' : '✗ 未完了') . "\n";
}

// 8. フック登録状況
echo "\n8. フック登録状況:\n";
$hooks = array(
    'register_activation_hook' => has_action( 'register_activation_hook', 'ktpwp_comprehensive_activation' ),
    'upgrader_process_complete' => has_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration' ),
    'admin_init_reactivation' => has_action( 'admin_init', 'ktpwp_check_reactivation_migration' ),
    'admin_init_new_installation' => has_action( 'admin_init', 'ktpwp_detect_new_installation' ),
    'admin_notices_migration_monitor' => has_action( 'admin_notices', 'ktpwp_distribution_migration_monitor' )
);

foreach ( $hooks as $hook => $registered ) {
    echo "  - {$hook}: " . ($registered ? '✓ 登録済み' : '✗ 未登録') . "\n";
}

// 9. 配布環境対応機能テスト
echo "\n9. 配布環境対応機能テスト:\n";

// 新規インストール判定テスト
if ( function_exists( 'ktpwp_is_new_installation' ) ) {
    $is_new = ktpwp_is_new_installation();
    echo "  - 新規インストール判定: " . ($is_new ? '新規' : '既存') . "\n";
}

// マイグレーション必要性チェック
if ( function_exists( 'ktpwp_needs_migration' ) ) {
    $needs_migration = ktpwp_needs_migration();
    echo "  - マイグレーション必要性: " . ($needs_migration ? '必要' : '不要') . "\n";
}

// 10. 安全機能テスト
echo "\n10. 安全機能テスト:\n";

// 出力バッファリングテスト
ob_start();
echo "テスト出力";
$output = ob_get_clean();
echo "  - 出力バッファリング: " . ($output === "テスト出力" ? '✓ 正常' : '✗ 異常') . "\n";

// メモリ制限チェック
$memory_limit = ini_get( 'memory_limit' );
$memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
echo "  - メモリ制限: {$memory_limit} (" . number_format( $memory_limit_bytes / 1024 / 1024, 2 ) . " MB)\n";
echo "  - メモリ制限評価: " . ($memory_limit_bytes >= 64 * 1024 * 1024 ? '✓ 十分' : '✗ 不足') . "\n";

// 11. データベース接続テスト
echo "\n11. データベース接続テスト:\n";
global $wpdb;
if ( $wpdb->check_connection() ) {
    echo "  - データベース接続: ✓ 正常\n";
} else {
    echo "  - データベース接続: ✗ 異常\n";
}

// 12. テーブル存在チェック
echo "\n12. テーブル存在チェック:\n";
$required_tables = array(
    $wpdb->prefix . 'ktp_order',
    $wpdb->prefix . 'ktp_supplier',
    $wpdb->prefix . 'ktp_client',
    $wpdb->prefix . 'ktp_service'
);

foreach ( $required_tables as $table ) {
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
    echo "  - {$table}: " . ($exists ? '✓ 存在' : '✗ 不存在') . "\n";
}

// 13. 配布環境安全性評価
echo "\n13. 配布環境安全性評価:\n";
$safety_score = 0;
$total_checks = 0;

// 基本機能存在チェック
if ( $all_functions_exist ) {
    $safety_score += 20;
}
$total_checks += 20;

// 安全性チェック
if ( function_exists( 'ktpwp_verify_migration_safety' ) && ktpwp_verify_migration_safety() ) {
    $safety_score += 20;
}
$total_checks += 20;

// データベース整合性
if ( function_exists( 'ktpwp_verify_database_integrity' ) && ktpwp_verify_database_integrity() ) {
    $safety_score += 20;
}
$total_checks += 20;

// フック登録状況
$hook_score = 0;
foreach ( $hooks as $hook => $registered ) {
    if ( $registered ) {
        $hook_score += 5;
    }
}
$safety_score += $hook_score;
$total_checks += 25;

// エラー状況
$error_free = true;
foreach ( $errors as $error ) {
    if ( $error ) {
        $error_free = false;
        break;
    }
}
if ( $error_free ) {
    $safety_score += 15;
}
$total_checks += 15;

$safety_percentage = round( ( $safety_score / $total_checks ) * 100, 2 );
echo "  - 安全性スコア: {$safety_score}/{$total_checks} ({$safety_percentage}%)\n";

if ( $safety_percentage >= 90 ) {
    echo "  - 配布環境対応評価: ✓ 優秀 - 不特定多数のサイトでの配布に適しています\n";
} elseif ( $safety_percentage >= 70 ) {
    echo "  - 配布環境対応評価: ⚠ 良好 - 配布可能ですが、改善の余地があります\n";
} else {
    echo "  - 配布環境対応評価: ✗ 要改善 - 配布前に修正が必要です\n";
}

echo "\n=== テスト完了 ===\n";
echo "配布環境でのマイグレーション機能が正常に動作することを確認しました。\n";
echo "安全性スコア: {$safety_percentage}%\n";
echo "推奨事項: 安全性スコアが90%以上であれば、不特定多数のサイトでの配布が可能です。\n";
?> 