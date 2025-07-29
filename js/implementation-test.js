/**
 * KTPWP実装完了テストスクリプト
 * スタッフチャット自動スクロールとオーダー削除機能のテスト
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== KTPWP実装テスト開始 ===');
    
    // テスト1: スタッフチャット自動スクロール機能
    function testStaffChatAutoScroll() {
        console.log('テスト1: スタッフチャット自動スクロール機能');
        
        const chatForm = document.querySelector('form[data-target="staff_chat"]');
        if (chatForm) {
            console.log('✅ スタッフチャットフォームが見つかりました');
            
            const submitButton = chatForm.querySelector('button[type="submit"]');
            const messageInput = chatForm.querySelector('textarea[name="message"]');
            
            if (submitButton && messageInput) {
                console.log('✅ 送信ボタンとメッセージ入力フィールドが見つかりました');
                
                // キーボードショートカットテスト
                messageInput.addEventListener('keydown', function(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                        console.log('✅ キーボードショートカットが検出されました');
                    }
                });
                
                // ボタン状態テスト
                messageInput.addEventListener('input', function() {
                    const isEmpty = this.value.trim() === '';
                    if (submitButton.disabled === isEmpty) {
                        console.log('✅ ボタン状態が正しく更新されています');
                    }
                });
                
            } else {
                console.log('❌ 送信ボタンまたはメッセージ入力フィールドが見つかりません');
            }
        } else {
            console.log('❌ スタッフチャットフォームが見つかりません');
        }
    }
    
    // テスト2: AJAX設定確認
    function testAjaxConfiguration() {
        console.log('テスト2: AJAX設定確認');
        
        if (typeof ktp_ajax_object !== 'undefined') {
            console.log('✅ ktp_ajax_object が定義されています');
            
            if (ktp_ajax_object.ajax_url) {
                console.log('✅ AJAX URLが設定されています:', ktp_ajax_object.ajax_url);
            } else {
                console.log('❌ AJAX URLが設定されていません');
            }
            
            if (ktp_ajax_object.nonce) {
                console.log('✅ Nonceが設定されています');
            } else {
                console.log('❌ Nonceが設定されていません');
            }
            
            if (ktp_ajax_object.current_user) {
                console.log('✅ 現在のユーザー情報が設定されています:', ktp_ajax_object.current_user.display_name);
            } else {
                console.log('❌ 現在のユーザー情報が設定されていません');
            }
        } else {
            console.log('❌ ktp_ajax_object が定義されていません');
        }
    }
    
    // テスト3: オーダー削除フォーム確認
    function testOrderDeletionForm() {
        console.log('テスト3: オーダー削除フォーム確認');
        
        const deleteForm = document.querySelector('form input[name="delete_order"][value="1"]');
        if (deleteForm) {
            const form = deleteForm.closest('form');
            console.log('✅ オーダー削除フォームが見つかりました');
            
            const orderIdInput = form.querySelector('input[name="order_id"]');
            if (orderIdInput) {
                console.log('✅ オーダーID入力フィールドが見つかりました');
            } else {
                console.log('❌ オーダーID入力フィールドが見つかりません');
            }
        } else {
            console.log('❌ オーダー削除フォームが見つかりません（現在のページにオーダーがない可能性があります）');
        }
    }
    
    // テスト4: エスケープ関数確認
    function testEscapeFunction() {
        console.log('テスト4: エスケープ関数確認');
        
        if (typeof escapeHtml === 'function') {
            console.log('✅ escapeHtml関数が定義されています');
            
            // テストケース
            const testCases = [
                { input: '<script>alert("test")</script>', expected: '&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;' },
                { input: 'Hello & World', expected: 'Hello &amp; World' },
                { input: '"quoted"', expected: '&quot;quoted&quot;' }
            ];
            
            let allPassed = true;
            testCases.forEach((testCase, index) => {
                const result = escapeHtml(testCase.input);
                if (result === testCase.expected) {
                    console.log(`✅ エスケープテスト${index + 1}が成功しました`);
                } else {
                    console.log(`❌ エスケープテスト${index + 1}が失敗しました:`, result, 'expected:', testCase.expected);
                    allPassed = false;
                }
            });
            
            if (allPassed) {
                console.log('✅ すべてのエスケープテストが成功しました');
            }
        } else {
            console.log('❌ escapeHtml関数が定義されていません');
        }
    }
    
    // テスト実行
    setTimeout(() => {
        testStaffChatAutoScroll();
        testAjaxConfiguration();
        testOrderDeletionForm();
        testEscapeFunction();
        
        console.log('=== KTPWP実装テスト完了 ===');
        console.log('すべてのテスト結果を確認してください。');
        console.log('問題がある場合は、該当する機能の実装を再確認してください。');
    }, 1000);
});
