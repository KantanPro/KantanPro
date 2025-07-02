<?php
/**
 * 手動マイグレーション実行ツール
 * 本番環境でマイグレーションが自動実行されない場合の緊急用ツール
 * 
 * 使用方法：
 * 1. このファイルをプラグインルートディレクトリに配置
 * 2. ブラウザで https://yoursite.com/wp-content/plugins/KantanPro/manual-migration.php にアクセス
 * 3. 実行後、必ずこのファイルを削除してください（セキュリティのため）
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// 管理者権限チェック
if (!current_user_can('administrator')) {
    die('このツールは管理者のみ実行可能です。');
}

// セキュリティトークンチェック
$token = isset($_GET['token']) ? $_GET['token'] : '';
$expected_token = md5('ktpwp_migration_' . date('Y-m-d'));

if ($_GET['action'] === 'execute' && $token === $expected_token) {
    
    echo '<h1>KantanPro 手動マイグレーション実行</h1>';
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;">';
    echo '<h2>実行開始...</h2>';
    
    // WordPressデバッグログを有効化
    if (!defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', true);
    }
    
    try {
        // 現在のDBバージョンを表示
        $current_db_version = get_option('ktpwp_db_version', '0.0.0');
        echo '<p><strong>現在のDBバージョン:</strong> ' . esc_html($current_db_version) . '</p>';
        
        // プラグインバージョンを表示
        $plugin_version = defined('KANTANPRO_PLUGIN_VERSION') ? KANTANPRO_PLUGIN_VERSION : '1.2.9(beta)';
        echo '<p><strong>プラグインバージョン:</strong> ' . esc_html($plugin_version) . '</p>';
        
        // 基本テーブル作成
        echo '<h3>基本テーブル作成中...</h3>';
        if (function_exists('ktp_table_setup')) {
            ktp_table_setup();
            echo '<p>✓ 基本テーブル作成完了</p>';
        } else {
            echo '<p>⚠ ktp_table_setup関数が見つかりません</p>';
        }
        
        // マイグレーションファイル実行
        echo '<h3>マイグレーションファイル実行中...</h3>';
        $migrations_dir = __DIR__ . '/includes/migrations';
        
        if (is_dir($migrations_dir)) {
            $files = glob($migrations_dir . '/*.php');
            if ($files) {
                sort($files);
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $filename = basename($file);
                        echo '<p>実行中: ' . esc_html($filename) . '</p>';
                        
                        try {
                            require_once $file;
                            echo '<p>✓ ' . esc_html($filename) . ' 実行完了</p>';
                        } catch (Exception $e) {
                            echo '<p>❌ ' . esc_html($filename) . ' エラー: ' . esc_html($e->getMessage()) . '</p>';
                        }
                    }
                }
            } else {
                echo '<p>⚠ マイグレーションファイルが見つかりません</p>';
            }
        } else {
            echo '<p>⚠ migrationsディレクトリが見つかりません</p>';
        }
        
        // DBバージョン更新
        echo '<h3>DBバージョン更新中...</h3>';
        update_option('ktpwp_db_version', $plugin_version);
        echo '<p>✓ DBバージョンを ' . esc_html($plugin_version) . ' に更新しました</p>';
        
        echo '<h2 style="color: green;">✓ マイグレーション完了！</h2>';
        echo '<p><strong style="color: red;">重要：</strong> セキュリティのため、このファイル（manual-migration.php）を必ず削除してください。</p>';
        
    } catch (Exception $e) {
        echo '<h2 style="color: red;">❌ エラーが発生しました</h2>';
        echo '<p>エラー詳細: ' . esc_html($e->getMessage()) . '</p>';
        echo '<p>WordPressのデバッグログを確認してください。</p>';
    }
    
    echo '</div>';
    echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>';
    
} else {
    // 実行確認画面
    $execute_url = add_query_arg(array(
        'action' => 'execute',
        'token' => $expected_token
    ), $_SERVER['REQUEST_URI']);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>KantanPro 手動マイグレーション</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
            .warning { background: #ffebcd; padding: 20px; border-left: 4px solid #ff9800; margin: 20px 0; }
            .button { background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
            .button:hover { background: #005a87; }
            .danger { background: #ffebee; padding: 20px; border-left: 4px solid #f44336; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>KantanPro 手動マイグレーション</h1>
        
        <div class="warning">
            <h3>⚠ 注意事項</h3>
            <ul>
                <li>このツールは本番環境でマイグレーションが自動実行されない場合の緊急用です</li>
                <li>実行前にデータベースのバックアップを取ることを強く推奨します</li>
                <li>実行後は必ずこのファイルを削除してください</li>
                <li>管理者権限でログインしている必要があります</li>
            </ul>
        </div>
        
        <div class="danger">
            <h3>🚨 セキュリティ警告</h3>
            <p>このファイルは本番環境に残しておくとセキュリティリスクになります。</p>
            <p>マイグレーション完了後は<strong>必ず削除</strong>してください。</p>
        </div>
        
        <h3>実行内容</h3>
        <ol>
            <li>基本テーブルの作成・更新</li>
            <li>マイグレーションファイルの実行</li>
            <li>データベースバージョンの更新</li>
        </ol>
        
        <p>
            <a href="<?php echo esc_url($execute_url); ?>" class="button" onclick="return confirm('本当にマイグレーションを実行しますか？\n\nデータベースのバックアップを取っていることを確認してください。')">
                マイグレーション実行
            </a>
        </p>
        
        <p><a href="<?php echo admin_url(); ?>">管理画面に戻る</a></p>
    </body>
    </html>
    <?php
}
?> 