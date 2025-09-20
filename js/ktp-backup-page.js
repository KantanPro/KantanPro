/**
 * バックアップページ用JavaScript
 * リストア機能の確認ダイアログとフォーム処理
 *
 * @package KTPWP
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // リストアフォームの処理
    const restoreForm = document.getElementById('ktp-restore-form');
    const restoreButton = document.getElementById('ktp-restore-button');
    
    if (restoreForm && restoreButton) {
        restoreForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // ファイルが選択されているかチェック
            const fileInput = document.querySelector('input[name="ktp_import_file"]');
            if (!fileInput.files.length) {
                alert('ファイルを選択してください。');
                return;
            }
            
            // 確認ダイアログを表示
            const confirmed = confirm(
                'リストアを実行しますか？\n\n' +
                '⚠️ 注意: 現在のデータは全て削除されます。\n' +
                'この操作は取り消せません。\n\n' +
                '続行する場合は「OK」をクリックしてください。'
            );
            
            if (confirmed) {
                // ボタンを無効化してローディング状態に
                restoreButton.disabled = true;
                restoreButton.textContent = 'リストア中...';
                
                // フォームを送信
                restoreForm.submit();
            }
        });
    }
    
    // URLからパラメータを削除（PHP側の通知表示後に）
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('ktp_action');
    
    if (action === 'restore_success' || action === 'restore_failed') {
        // URLからパラメータを削除
        const url = new URL(window.location);
        url.searchParams.delete('ktp_action');
        window.history.replaceState({}, '', url);
    }
});
