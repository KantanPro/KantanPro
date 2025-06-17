<?php
/**
 * KantanPro プラグインリファレンスキャッシュクリア用スクリプト
 * 
 * 使用方法: php clear-reference-cache.php
 */

// WordPressの読み込み
require_once '../../../wp-config.php';
require_once ABSPATH . 'wp-includes/wp-db.php';

// プラグインファイルを読み込み
if (!defined('KANTANPRO_PLUGIN_VERSION')) {
    require_once __DIR__ . '/ktpwp.php';
}

echo "KantanPro プラグインリファレンスキャッシュクリア開始...\n";

// キャッシュクリア実行
if (class_exists('KTPWP_Plugin_Reference')) {
    $result = KTPWP_Plugin_Reference::clear_all_cache();
    if ($result) {
        echo "✓ キャッシュクリア完了\n";
        echo "現在のバージョン: " . KANTANPRO_PLUGIN_VERSION . "\n";
    } else {
        echo "✗ キャッシュクリアに失敗\n";
    }
} else {
    echo "✗ KTPWP_Plugin_Reference クラスが見つかりません\n";
}

// 個別にオプションを確認・更新
echo "\n設定確認・更新:\n";

// バージョン情報の更新
update_option('ktpwp_reference_version', KANTANPRO_PLUGIN_VERSION);
echo "✓ バージョン情報を更新: " . KANTANPRO_PLUGIN_VERSION . "\n";

// 最終更新時刻の更新
update_option('ktpwp_reference_last_updated', current_time('timestamp'));
echo "✓ 最終更新時刻を更新\n";

// リフレッシュフラグを設定
update_option('ktpwp_reference_needs_refresh', true);
echo "✓ リフレッシュフラグを設定\n";

// 個別のセクションキャッシュをクリア
$sections = array('overview', 'tabs', 'shortcodes', 'settings', 'security', 'troubleshooting');
$cleared = 0;
foreach ($sections as $section) {
    if (delete_transient("ktpwp_reference_content_{$section}")) {
        $cleared++;
    }
}
echo "✓ {$cleared}個のセクションキャッシュをクリア\n";

// メインキャッシュをクリア
delete_transient('ktpwp_reference_cache');
echo "✓ メインキャッシュをクリア\n";

echo "\nキャッシュクリア作業完了！\n";
echo "ブラウザでプラグインリファレンスを開いて、バージョン情報が正しく表示されることを確認してください。\n";
