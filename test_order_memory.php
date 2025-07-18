<?php
/**
 * 受注書状態記憶機能テスト
 * 
 * このファイルは受注書タブで開いていた受注書の状態を記憶し、
 * 他のタブに移動して戻ってきた時に復元する機能をテストします。
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    ktpwp_safe_session_start();
}

echo "<h1>受注書状態記憶機能テスト</h1>";

// 現在のセッション状態を確認
echo "<h2>現在のセッション状態</h2>";
if (isset($_SESSION['ktp_last_order_id'])) {
    echo "<p>記憶された受注書ID: " . $_SESSION['ktp_last_order_id'] . "</p>";
} else {
    echo "<p>記憶された受注書ID: なし</p>";
}

// ローカルストレージの状態を確認（JavaScriptで実行）
echo "<h2>ローカルストレージ状態</h2>";
echo "<p id='localStorageStatus'>確認中...</p>";

// テスト用の受注書IDを設定
if (isset($_GET['set_order_id'])) {
    $order_id = intval($_GET['set_order_id']);
    $_SESSION['ktp_last_order_id'] = $order_id;
    echo "<p>受注書ID {$order_id} をセッションに保存しました。</p>";
}

// セッションをクリア
if (isset($_GET['clear_session'])) {
    unset($_SESSION['ktp_last_order_id']);
    echo "<p>セッションをクリアしました。</p>";
}

// テストリンク
echo "<h2>テスト操作</h2>";
echo "<p><a href='?set_order_id=1'>受注書ID 1 を記憶</a></p>";
echo "<p><a href='?set_order_id=5'>受注書ID 5 を記憶</a></p>";
echo "<p><a href='?clear_session=1'>セッションをクリア</a></p>";

// JavaScriptでローカルストレージの状態を確認
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var statusElement = document.getElementById('localStorageStatus');
    var savedOrderId = localStorage.getItem('ktp_last_order_id');
    
    if (savedOrderId) {
        statusElement.innerHTML = "記憶された受注書ID: " + savedOrderId;
    } else {
        statusElement.innerHTML = "記憶された受注書ID: なし";
    }
    
    // テスト用のローカルストレージ操作
    window.setLocalStorageOrderId = function(orderId) {
        localStorage.setItem('ktp_last_order_id', orderId);
        statusElement.innerHTML = "受注書ID " + orderId + " をローカルストレージに保存しました。";
    };
    
    window.clearLocalStorage = function() {
        localStorage.removeItem('ktp_last_order_id');
        statusElement.innerHTML = "ローカルストレージをクリアしました。";
    };
});

// テスト用のボタンを追加
document.write('<p><button onclick="setLocalStorageOrderId(10)">受注書ID 10 をローカルストレージに保存</button></p>');
document.write('<p><button onclick="clearLocalStorage()">ローカルストレージをクリア</button></p>');
</script>

<h2>使用方法</h2>
<ol>
    <li>受注書タブで任意の受注書を開く</li>
    <li>他のタブ（顧客、サービスなど）に移動</li>
    <li>受注書タブに戻る</li>
    <li>直前まで開いていた受注書が自動的に表示されることを確認</li>
</ol>

<h2>技術仕様</h2>
<ul>
    <li><strong>セッション記憶</strong>: PHPセッションを使用して受注書IDを記憶</li>
    <li><strong>ローカルストレージ記憶</strong>: JavaScriptのlocalStorageを使用してブラウザ側でも記憶</li>
    <li><strong>優先順位</strong>: GETパラメータ > セッション記憶 > 最新の受注書</li>
    <li><strong>自動復元</strong>: 受注書タブに戻った時に自動的に記憶されたIDを復元</li>
</ul> 