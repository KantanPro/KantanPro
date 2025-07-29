/**
 * スタッフチャット自動スクロール機能テスト用スクリプト
 * 
 * 使用方法：
 * 1. ブラウザのコンソールでこのスクリプトを読み込み
 * 2. testStaffChatAutoScroll() を実行
 */

window.testStaffChatAutoScroll = function() {
    console.log('=== スタッフチャット自動スクロール機能テスト開始 ===');
    
    // 必要な要素の確認
    var staffChatContent = document.getElementById('staff-chat-content');
    var messagesContainer = document.getElementById('staff-chat-messages');
    var chatForm = document.getElementById('staff-chat-form');
    var messageInput = document.getElementById('staff-chat-input');
    var submitButton = document.getElementById('staff-chat-submit');
    
    console.log('要素チェック:');
    console.log('- スタッフチャットコンテンツ:', !!staffChatContent);
    console.log('- メッセージコンテナ:', !!messagesContainer);
    console.log('- チャットフォーム:', !!chatForm);
    console.log('- メッセージ入力欄:', !!messageInput);
    console.log('- 送信ボタン:', !!submitButton);
    
    if (!staffChatContent || !messagesContainer || !chatForm || !messageInput || !submitButton) {
        console.error('必要な要素が見つかりません。スタッフチャットが表示されているか確認してください。');
        return false;
    }
    
    // チャットが開いているかチェック
    var isOpen = staffChatContent.style.display !== 'none';
    console.log('チャット表示状態:', isOpen ? '開いている' : '閉じている');
    
    // メッセージ数をカウント
    var messageCount = messagesContainer.querySelectorAll('.staff-chat-message.scrollable').length;
    console.log('現在のメッセージ数:', messageCount);
    
    // AJAX設定の確認
    var ajaxConfigExists = typeof ktpwp_ajax !== 'undefined';
    console.log('AJAX設定:', ajaxConfigExists ? '設定済み' : '未設定');
    
    if (ajaxConfigExists) {
        console.log('- AJAX URL:', ktpwp_ajax.ajax_url);
        console.log('- 現在のユーザー:', ktpwp_ajax.current_user || '未設定');
        console.log('- スタッフチャットnonce:', ktpwp_ajax.nonces?.staff_chat ? '設定済み' : '未設定');
    }
    
    // ローカルストレージテスト
    console.log('ローカルストレージテスト:');
    localStorage.setItem('ktp_test_storage', 'test');
    var storageTest = localStorage.getItem('ktp_test_storage') === 'test';
    localStorage.removeItem('ktp_test_storage');
    console.log('- ローカルストレージ:', storageTest ? '利用可能' : '利用不可');
    
    // 自動スクロール関数のテスト
    console.log('自動スクロールテスト:');
    var originalScrollTop = messagesContainer.scrollTop;
    messagesContainer.scrollTop = 0; // 一番上にスクロール
    
    setTimeout(function() {
        // 最下部にスクロール
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        setTimeout(function() {
            var newScrollTop = messagesContainer.scrollTop;
            var scrolled = newScrollTop > originalScrollTop;
            console.log('- スクロール動作:', scrolled ? '正常' : 'エラー');
            console.log('- 元の位置:', originalScrollTop, '新しい位置:', newScrollTop);
            
            // 元の位置に戻す
            messagesContainer.scrollTop = originalScrollTop;
            
            console.log('=== テスト完了 ===');
        }, 500);
    }, 100);
    
    return true;
};

// モックメッセージ送信テスト
window.testMockMessageSend = function() {
    console.log('=== モックメッセージ送信テスト開始 ===');
    
    var messagesContainer = document.getElementById('staff-chat-messages');
    if (!messagesContainer) {
        console.error('メッセージコンテナが見つかりません');
        return false;
    }
    
    // テストメッセージを追加
    var testMessage = 'テストメッセージ - ' + new Date().toLocaleTimeString();
    var currentUser = (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.current_user) ? 
        ktpwp_ajax.current_user : 'テストユーザー';
    var currentTime = new Date().toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'numeric', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    var messageDiv = document.createElement('div');
    messageDiv.className = 'staff-chat-message scrollable test-message';
    messageDiv.style.border = '2px solid #007cba';
    messageDiv.innerHTML = 
        '<div class="staff-chat-message-header">' +
            '<span class="staff-chat-user-name">' + currentUser + '</span>' +
            '<span class="staff-chat-timestamp">' + currentTime + '</span>' +
        '</div>' +
        '<div class="staff-chat-message-content">' + testMessage + '</div>';
    
    messagesContainer.appendChild(messageDiv);
    
    // 自動スクロール
    setTimeout(function() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        console.log('テストメッセージを追加しました:', testMessage);
        
        // ボタンテキストを更新
        if (typeof window.updateStaffChatButtonText === 'function') {
            window.updateStaffChatButtonText();
            console.log('ボタンテキストを更新しました');
        }
        
        // 3秒後にテストメッセージを削除
        setTimeout(function() {
            messageDiv.remove();
            if (typeof window.updateStaffChatButtonText === 'function') {
                window.updateStaffChatButtonText();
            }
            console.log('テストメッセージを削除しました');
        }, 3000);
    }, 100);
    
    console.log('=== モックメッセージ送信テスト完了 ===');
    return true;
};

console.log('スタッフチャット自動スクロール機能テストスクリプトが読み込まれました');
console.log('使用可能な関数:');
console.log('- testStaffChatAutoScroll(): 基本機能テスト');
console.log('- testMockMessageSend(): モックメッセージ送信テスト');
