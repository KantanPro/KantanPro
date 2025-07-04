<?php
/**
 * WordPress設定確認スクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>WordPress設定確認</h1>";

echo "<h2>1. 基本設定</h2>";
echo "<p><strong>サイトURL:</strong> " . get_site_url() . "</p>";
echo "<p><strong>ホームURL:</strong> " . get_home_url() . "</p>";
echo "<p><strong>プラグインディレクトリ:</strong> " . WP_PLUGIN_DIR . "</p>";
echo "<p><strong>プラグインURL:</strong> " . plugins_url() . "</p>";

echo "<h2>2. 現在のディレクトリ</h2>";
echo "<p><strong>現在のディレクトリ:</strong> " . __DIR__ . "</p>";
echo "<p><strong>WordPressルート:</strong> " . ABSPATH . "</p>";

echo "<h2>3. プラグイン情報</h2>";
$plugin_dir = dirname(__FILE__);
$plugin_url = plugins_url('', __FILE__);
echo "<p><strong>プラグインディレクトリ:</strong> {$plugin_dir}</p>";
echo "<p><strong>プラグインURL:</strong> {$plugin_url}</p>";

echo "<h2>4. テストスクリプトのURL</h2>";
echo "<p>以下のURLでテストスクリプトにアクセスできます：</p>";
echo "<ul>";
echo "<li><a href='{$plugin_url}/test-department-selection.php' target='_blank'>部署選択テスト</a></li>";
echo "<li><a href='{$plugin_url}/debug-department-selection.php' target='_blank'>部署選択デバッグ</a></li>";
echo "<li><a href='{$plugin_url}/check-plugin-status.php' target='_blank'>プラグイン状態確認</a></li>";
echo "</ul>";

echo "<h2>5. 直接アクセス用URL</h2>";
echo "<p>ブラウザのアドレスバーに以下のURLをコピーしてアクセスしてください：</p>";
echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace;'>";
echo htmlspecialchars($plugin_url . '/test-department-selection.php');
echo "</div>";

echo "<h2>6. 権限確認</h2>";
if (current_user_can('edit_posts') || current_user_can('ktpwp_access')) {
    echo "<p style='color: green;'>✅ 権限があります</p>";
} else {
    echo "<p style='color: red;'>❌ 権限がありません</p>";
}

echo "<h2>7. ファイル存在確認</h2>";
$files_to_check = array(
    'test-department-selection.php',
    'debug-department-selection.php',
    'check-plugin-status.php'
);

foreach ($files_to_check as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        echo "<p style='color: green;'>✅ {$file} - 存在します</p>";
    } else {
        echo "<p style='color: red;'>❌ {$file} - 存在しません</p>";
    }
}

echo "<h2>8. 推奨アクション</h2>";
echo "<ol>";
echo "<li>上記の「テストスクリプトのURL」のリンクをクリックしてください</li>";
echo "<li>または、直接アクセス用URLをブラウザのアドレスバーにコピーしてアクセスしてください</li>";
echo "<li>権限エラーが表示される場合は、WordPressにログインしてからアクセスしてください</li>";
echo "</ol>";
?> 