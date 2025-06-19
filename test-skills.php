<?php
/**
 * 職能管理機能のテストファイル
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// 必要なファイルを読み込み
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';

// 職能管理クラスのテスト
function test_supplier_skills_class() {
    echo "<h2>職能管理クラスのテスト</h2>\n";
    
    // クラスの存在確認
    if (class_exists('KTPWP_Supplier_Skills')) {
        echo "<p>✓ KTPWP_Supplier_Skills クラスが正常に読み込まれました。</p>\n";
        
        // シングルトンインスタンスの取得
        $skills = KTPWP_Supplier_Skills::get_instance();
        
        if ($skills instanceof KTPWP_Supplier_Skills) {
            echo "<p>✓ シングルトンインスタンスが正常に取得できました。</p>\n";
            
            // HTMLインターフェースの生成テスト
            $html = $skills->render_skills_interface(1);
            
            if (!empty($html)) {
                echo "<p>✓ HTMLインターフェースが正常に生成されました。</p>\n";
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
                echo "<h3>生成されたHTMLの一部:</h3>\n";
                echo "<pre>" . esc_html(substr($html, 0, 300)) . "...</pre>\n";
                echo "</div>\n";
            } else {
                echo "<p>⚠ HTMLインターフェースの生成に問題がありました。</p>\n";
            }
            
            // テストデータでの職能取得
            $test_skills = $skills->get_supplier_skills(1);
            echo "<p>✓ 職能データ取得メソッドが正常に実行されました。（データ数: " . count($test_skills) . "）</p>\n";
            
        } else {
            echo "<p>✗ シングルトンインスタンスの取得に失敗しました。</p>\n";
        }
        
    } else {
        echo "<p>✗ KTPWP_Supplier_Skills クラスが見つかりません。</p>\n";
    }
}

// テスト実行
test_supplier_skills_class();

// 協力会社タブクラスのテスト
function test_supplier_tab_class() {
    echo "<h2>協力会社タブクラスのテスト</h2>\n";
    
    // ファイルの存在確認
    $supplier_file = dirname(__FILE__) . '/includes/class-tab-supplier.php';
    
    if (file_exists($supplier_file)) {
        echo "<p>✓ 協力会社タブクラスファイルが存在します。</p>\n";
        
        // ファイルの読み込み
        require_once $supplier_file;
        
        if (class_exists('KTPWP_Supplier_Class')) {
            echo "<p>✓ KTPWP_Supplier_Class クラスが正常に読み込まれました。</p>\n";
            
            // handle_skills_operations メソッドの存在確認
            if (method_exists('KTPWP_Supplier_Class', 'handle_skills_operations')) {
                echo "<p>✓ handle_skills_operations メソッドが実装されています。</p>\n";
            } else {
                echo "<p>✗ handle_skills_operations メソッドが見つかりません。</p>\n";
            }
            
        } else {
            echo "<p>✗ KTPWP_Supplier_Class クラスが見つかりません。</p>\n";
        }
        
    } else {
        echo "<p>✗ 協力会社タブクラスファイルが見つかりません。</p>\n";
    }
}

// テスト実行
test_supplier_tab_class();

echo "<h2>テスト完了</h2>\n";
echo "<p>上記のテスト結果を確認して、問題がある場合は修正してください。</p>\n";
?>
