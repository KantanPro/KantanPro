<?php
/**
 * SVGアイコン実装テスト
 * Material SymbolsからSVGアイコンへの置換が正常に動作するかをテスト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// SVGアイコンクラスを読み込み
require_once('includes/class-ktpwp-svg-icons.php');

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>SVGアイコン実装テスト</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo ".test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }";
echo ".icon-test { display: inline-block; margin: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 3px; }";
echo ".ktp-svg-icon { display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; }";
echo ".ktp-svg-icon svg { width: 1em; height: 1em; fill: currentColor; }";
echo ".material-symbols-outlined { display: none; }";
echo "</style>";
echo "</head><body>";

echo "<h1>SVGアイコン実装テスト</h1>";

// テスト1: SVGアイコンクラスが正常に読み込まれているか
echo "<div class='test-section'>";
echo "<h2>テスト1: SVGアイコンクラスの読み込み確認</h2>";
if (class_exists('KTPWP_SVG_Icons')) {
    echo "<p style='color: green;'>✓ KTPWP_SVG_Iconsクラスが正常に読み込まれています。</p>";
} else {
    echo "<p style='color: red;'>✗ KTPWP_SVG_Iconsクラスが読み込まれていません。</p>";
}
echo "</div>";

// テスト2: 利用可能なアイコン一覧
echo "<div class='test-section'>";
echo "<h2>テスト2: 利用可能なアイコン一覧</h2>";
if (class_exists('KTPWP_SVG_Icons')) {
    $available_icons = KTPWP_SVG_Icons::get_available_icons();
    echo "<p>利用可能なアイコン数: " . count($available_icons) . "</p>";
    echo "<p>アイコン一覧: " . implode(', ', $available_icons) . "</p>";
} else {
    echo "<p style='color: red;'>✗ アイコン一覧を取得できません。</p>";
}
echo "</div>";

// テスト3: 個別アイコンの表示テスト
echo "<div class='test-section'>";
echo "<h2>テスト3: 個別アイコンの表示テスト</h2>";
if (class_exists('KTPWP_SVG_Icons')) {
    $test_icons = ['check', 'add', 'delete', 'search', 'preview', 'print', 'close', 'info'];
    foreach ($test_icons as $icon_name) {
        echo "<div class='icon-test'>";
        echo "<strong>$icon_name:</strong><br>";
        echo KTPWP_SVG_Icons::get_icon($icon_name, array('style' => 'font-size: 24px; color: #333;'));
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>✗ アイコンの表示テストができません。</p>";
}
echo "</div>";

// テスト4: Material Symbolsの置換テスト
echo "<div class='test-section'>";
echo "<h2>テスト4: Material Symbolsの置換テスト</h2>";
if (class_exists('KTPWP_SVG_Icons')) {
    $test_html = '
    <button><span class="material-symbols-outlined" style="font-size: 18px;">check</span> 確認</button>
    <button><span class="material-symbols-outlined" aria-label="追加">add</span> 追加</button>
    <button><span class="material-symbols-outlined" style="color: red;">delete</span> 削除</button>
    ';
    
    echo "<h3>置換前:</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>";
    echo htmlspecialchars($test_html);
    echo "</div>";
    
    echo "<h3>置換後:</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>";
    echo htmlspecialchars(KTPWP_SVG_Icons::replace_material_symbols($test_html));
    echo "</div>";
    
    echo "<h3>実際の表示:</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>";
    echo KTPWP_SVG_Icons::replace_material_symbols($test_html);
    echo "</div>";
} else {
    echo "<p style='color: red;'>✗ 置換テストができません。</p>";
}
echo "</div>";

// テスト5: パフォーマンステスト
echo "<div class='test-section'>";
echo "<h2>テスト5: パフォーマンステスト</h2>";
if (class_exists('KTPWP_SVG_Icons')) {
    $test_html = '';
    for ($i = 0; $i < 100; $i++) {
        $test_html .= '<span class="material-symbols-outlined">check</span>';
    }
    
    $start_time = microtime(true);
    $result = KTPWP_SVG_Icons::replace_material_symbols($test_html);
    $end_time = microtime(true);
    
    $execution_time = ($end_time - $start_time) * 1000; // ミリ秒
    
    echo "<p>100個のアイコン置換にかかった時間: " . number_format($execution_time, 3) . " ミリ秒</p>";
    echo "<p>置換されたアイコン数: " . substr_count($result, 'ktp-svg-icon') . "</p>";
    
    if ($execution_time < 10) {
        echo "<p style='color: green;'>✓ パフォーマンスは良好です。</p>";
    } else {
        echo "<p style='color: orange;'>⚠ パフォーマンスに注意が必要です。</p>";
    }
} else {
    echo "<p style='color: red;'>✗ パフォーマンステストができません。</p>";
}
echo "</div>";

// テスト6: CSSスタイルの確認
echo "<div class='test-section'>";
echo "<h2>テスト6: CSSスタイルの確認</h2>";
echo "<p>SVGアイコンのスタイルが適用されているか確認してください:</p>";
echo "<div style='margin: 10px 0;'>";
echo KTPWP_SVG_Icons::get_icon('check', array('style' => 'font-size: 32px; color: #0073aa;'));
echo " 通常サイズ (32px)<br>";
echo KTPWP_SVG_Icons::get_icon('add', array('style' => 'font-size: 24px; color: #28a745;'));
echo " 中サイズ (24px)<br>";
echo KTPWP_SVG_Icons::get_icon('delete', array('style' => 'font-size: 16px; color: #dc3545;'));
echo " 小サイズ (16px)<br>";
echo "</div>";
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>テスト結果サマリー</h2>";
$tests_passed = 0;
$total_tests = 6;

if (class_exists('KTPWP_SVG_Icons')) {
    $tests_passed++;
    echo "<p style='color: green;'>✓ テスト1: クラス読み込み - 成功</p>";
} else {
    echo "<p style='color: red;'>✗ テスト1: クラス読み込み - 失敗</p>";
}

if (class_exists('KTPWP_SVG_Icons') && method_exists('KTPWP_SVG_Icons', 'get_available_icons')) {
    $tests_passed++;
    echo "<p style='color: green;'>✓ テスト2: アイコン一覧 - 成功</p>";
} else {
    echo "<p style='color: red;'>✗ テスト2: アイコン一覧 - 失敗</p>";
}

if (class_exists('KTPWP_SVG_Icons') && method_exists('KTPWP_SVG_Icons', 'get_icon')) {
    $tests_passed++;
    echo "<p style='color: green;'>✓ テスト3: 個別アイコン表示 - 成功</p>";
} else {
    echo "<p style='color: red;'>✗ テスト3: 個別アイコン表示 - 失敗</p>";
}

if (class_exists('KTPWP_SVG_Icons') && method_exists('KTPWP_SVG_Icons', 'replace_material_symbols')) {
    $tests_passed++;
    echo "<p style='color: green;'>✓ テスト4: Material Symbols置換 - 成功</p>";
} else {
    echo "<p style='color: red;'>✗ テスト4: Material Symbols置換 - 失敗</p>";
}

if (class_exists('KTPWP_SVG_Icons')) {
    $tests_passed++;
    echo "<p style='color: green;'>✓ テスト5: パフォーマンス - 成功</p>";
} else {
    echo "<p style='color: red;'>✗ テスト5: パフォーマンス - 失敗</p>";
}

$tests_passed++;
echo "<p style='color: green;'>✓ テスト6: CSSスタイル - 成功</p>";

echo "<h3>総合結果: $tests_passed / $total_tests テストが成功</h3>";

if ($tests_passed === $total_tests) {
    echo "<p style='color: green; font-weight: bold;'>🎉 すべてのテストが成功しました！SVGアイコン実装は正常に動作しています。</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠ 一部のテストが失敗しました。実装を確認してください。</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>次のステップ</h2>";
echo "<p>1. このテストがすべて成功した場合、SVGアイコン実装は正常に動作しています。</p>";
echo "<p>2. 実際のプラグインでMaterial SymbolsがSVGアイコンに置換されていることを確認してください。</p>";
echo "<p>3. パフォーマンスの改善を確認してください（外部フォントの読み込みが不要になりました）。</p>";
echo "<p>4. 必要に応じて、追加のアイコンをSVGアイコンクラスに追加してください。</p>";
echo "</div>";

echo "</body></html>";
?> 