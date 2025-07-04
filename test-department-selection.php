<?php
/**
 * 部署選択解除テストスクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    die('権限がありません');
}

global $wpdb;

echo "<h1>部署選択解除テスト</h1>";

// 1. 現在の状態確認
echo "<h2>1. 現在の状態確認</h2>";
$client_table = $wpdb->prefix . 'ktp_client';
$department_table = $wpdb->prefix . 'ktp_department';

$client_id = 1; // テスト用顧客ID
$client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$client_table} WHERE id = %d", $client_id));
$departments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$department_table} WHERE client_id = %d", $client_id));

echo "<p><strong>顧客情報:</strong></p>";
echo "<ul>";
echo "<li>ID: {$client->id}</li>";
echo "<li>会社名: {$client->company_name}</li>";
echo "<li>選択された部署ID: " . ($client->selected_department_id ?: 'NULL') . "</li>";
echo "</ul>";

echo "<p><strong>部署一覧:</strong></p>";
if ($departments) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>部署名</th><th>担当者</th><th>メール</th><th>選択状態</th></tr>";
    foreach ($departments as $dept) {
        $is_selected = ($dept->id == $client->selected_department_id);
        $status = $is_selected ? "選択済み" : "未選択";
        $status_color = $is_selected ? "green" : "red";
        echo "<tr>";
        echo "<td>{$dept->id}</td>";
        echo "<td>{$dept->department_name}</td>";
        echo "<td>{$dept->contact_person}</td>";
        echo "<td>{$dept->email}</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>部署が登録されていません。</p>";
}

// 2. 部署選択解除テスト
echo "<h2>2. 部署選択解除テスト</h2>";

if (isset($_GET['test_clear']) && $_GET['test_clear'] === '1') {
    echo "<h3>部署選択解除を実行中...</h3>";
    
    try {
        // 部署選択解除を実行
        if (class_exists('KTPWP_Department_Manager')) {
            // 現在選択されている部署IDを取得
            $current_selection = $wpdb->get_var($wpdb->prepare(
                "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                $client_id
            ));
            
            echo "<p>現在選択されている部署ID: " . ($current_selection ?: 'NULL') . "</p>";
            
            if ($current_selection) {
                // 部署選択解除を実行
                $result = KTPWP_Department_Manager::update_department_selection($current_selection, false);
                
                if ($result) {
                    echo "<p style='color: green;'>✅ 部署選択解除が成功しました。</p>";
                    
                    // 更新後の状態を確認
                    $updated_selection = $wpdb->get_var($wpdb->prepare(
                        "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                        $client_id
                    ));
                    
                    echo "<p>更新後の選択された部署ID: " . ($updated_selection ?: 'NULL') . "</p>";
                    
                    if (empty($updated_selection)) {
                        echo "<p style='color: green;'>✅ 部署選択が正しく解除されました。</p>";
                    } else {
                        echo "<p style='color: red;'>❌ 部署選択が解除されていません。</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ 部署選択解除に失敗しました。</p>";
                }
            } else {
                echo "<p>現在選択されている部署がありません。</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ KTPWP_Department_Managerクラスが見つかりません。</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ エラーが発生しました: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>部署選択解除をテストするには、<a href='?test_clear=1'>ここをクリック</a>してください。</p>";
}

// 3. 手動で部署選択を設定
echo "<h2>3. 手動で部署選択を設定</h2>";

if (isset($_GET['set_selection']) && !empty($_GET['set_selection'])) {
    $department_id = intval($_GET['set_selection']);
    echo "<h3>部署ID {$department_id} を選択中...</h3>";
    
    try {
        if (class_exists('KTPWP_Department_Manager')) {
            $result = KTPWP_Department_Manager::update_department_selection($department_id, true);
            
            if ($result) {
                echo "<p style='color: green;'>✅ 部署選択が成功しました。</p>";
                
                // 更新後の状態を確認
                $updated_selection = $wpdb->get_var($wpdb->prepare(
                    "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                    $client_id
                ));
                
                echo "<p>更新後の選択された部署ID: " . ($updated_selection ?: 'NULL') . "</p>";
            } else {
                echo "<p style='color: red;'>❌ 部署選択に失敗しました。</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ KTPWP_Department_Managerクラスが見つかりません。</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ エラーが発生しました: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>部署を選択するには、以下のリンクをクリックしてください：</p>";
    if ($departments) {
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li><a href='?set_selection={$dept->id}'>{$dept->department_name}</a></li>";
        }
        echo "</ul>";
    }
}

// 4. データベース直接操作テスト
echo "<h2>4. データベース直接操作テスト</h2>";

if (isset($_GET['direct_clear']) && $_GET['direct_clear'] === '1') {
    echo "<h3>データベース直接操作で部署選択を解除中...</h3>";
    
    try {
        // 直接データベースを更新
        $result = $wpdb->update(
            $client_table,
            array('selected_department_id' => null),
            array('id' => $client_id),
            array(null),
            array('%d')
        );
        
        if ($result !== false) {
            echo "<p style='color: green;'>✅ データベース直接更新が成功しました。</p>";
            
            // 更新後の状態を確認
            $updated_selection = $wpdb->get_var($wpdb->prepare(
                "SELECT selected_department_id FROM {$client_table} WHERE id = %d",
                $client_id
            ));
            
            echo "<p>更新後の選択された部署ID: " . ($updated_selection ?: 'NULL') . "</p>";
        } else {
            echo "<p style='color: red;'>❌ データベース直接更新に失敗しました。</p>";
            echo "<p>エラー: " . $wpdb->last_error . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ エラーが発生しました: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>データベース直接操作で部署選択を解除するには、<a href='?direct_clear=1'>ここをクリック</a>してください。</p>";
}

// 5. ページリロードテスト
echo "<h2>5. ページリロードテスト</h2>";
echo "<p><a href='?tab_name=client' target='_blank'>顧客ページを新しいタブで開く</a></p>";
echo "<p>部署選択を解除した後、このリンクで顧客ページを開いて状態を確認してください。</p>";

echo "<h2>6. 推奨アクション</h2>";
echo "<ol>";
echo "<li>まず「部署選択解除テスト」を実行して、部署選択が正しく解除されるか確認</li>";
echo "<li>解除が成功したら、顧客ページを開いて状態を確認</li>";
echo "<li>問題が続く場合は、ブラウザの開発者ツールでコンソールエラーを確認</li>";
echo "<li>JavaScriptの処理が正しく動作しているか確認</li>";
echo "</ol>";

echo "<h2>7. デバッグ情報</h2>";
echo "<p>WordPressデバッグログを確認して、部署選択関連のエラーがないかチェックしてください。</p>";
echo "<p>ブラウザの開発者ツールでコンソールログを確認して、JavaScriptのエラーがないかチェックしてください。</p>";
?> 