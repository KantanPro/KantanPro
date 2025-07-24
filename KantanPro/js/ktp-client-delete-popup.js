/**
 * 顧客削除選択ポップアップ機能
 * 
 * 削除ボタンクリック時に以下の選択肢を表示：
 * 1. 対象外（受注書は残り顧客データは復元可能）
 * 2. 削除（顧客データは削除されますが受注書は削除されません）
 * 3. 完全削除（顧客データと関連する受注書を完全に削除します）
 */

(function() {
    'use strict';

    // ポップアップのHTMLを生成
    function createDeletePopup(clientId, clientName) {
        const popupId = 'ktp-delete-popup-' + Date.now();
        
        const popupHTML = `
            <div id="${popupId}" class="ktp-delete-popup-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div class="ktp-delete-popup-content" style="
                    background: white;
                    border-radius: 8px;
                    padding: 30px;
                    max-width: 500px;
                    width: 90%;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                    position: relative;
                ">
                    <div class="ktp-delete-popup-header" style="
                        margin-bottom: 20px;
                        text-align: center;
                    ">
                        <h3 style="
                            margin: 0 0 10px 0;
                            color: #d32f2f;
                            font-size: 18px;
                            font-weight: 600;
                        ">
                            <span class="material-symbols-outlined" style="
                                vertical-align: middle;
                                margin-right: 8px;
                                font-size: 20px;
                            ">warning</span>
                            顧客削除の選択
                        </h3>
                        <p style="
                            margin: 0;
                            color: #666;
                            font-size: 14px;
                        ">
                            顧客「${clientName}」の削除方法を選択してください
                        </p>
                    </div>

                    <div class="ktp-delete-popup-options" style="margin-bottom: 25px;">
                        <div class="ktp-delete-option" style="
                            border: 2px solid #e0e0e0;
                            border-radius: 6px;
                            padding: 15px;
                            margin-bottom: 10px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " data-option="soft">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="
                                        font-weight: 600;
                                        color: #333;
                                        margin-bottom: 5px;
                                    ">1. 対象外（推奨）</div>
                                    <div style="
                                        font-size: 13px;
                                        color: #666;
                                        line-height: 1.4;
                                    ">受注書は残り、顧客データは復元可能です</div>
                                </div>
                                <span class="material-symbols-outlined" style="
                                    color: #4caf50;
                                    font-size: 20px;
                                ">check_circle</span>
                            </div>
                        </div>

                        <div class="ktp-delete-option" style="
                            border: 2px solid #e0e0e0;
                            border-radius: 6px;
                            padding: 15px;
                            margin-bottom: 10px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " data-option="delete">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="
                                        font-weight: 600;
                                        color: #333;
                                        margin-bottom: 5px;
                                    ">2. 通常削除</div>
                                    <div style="
                                        font-size: 13px;
                                        color: #666;
                                        line-height: 1.4;
                                    ">顧客データと部署データを削除（受注書は残す）</div>
                                </div>
                                <span class="material-symbols-outlined" style="
                                    color: #ff9800;
                                    font-size: 20px;
                                ">warning</span>
                            </div>
                        </div>

                        <div class="ktp-delete-option" style="
                            border: 2px solid #e0e0e0;
                            border-radius: 6px;
                            padding: 15px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " data-option="complete">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="
                                        font-weight: 600;
                                        color: #333;
                                        margin-bottom: 5px;
                                    ">3. 完全削除</div>
                                    <div style="
                                        font-size: 13px;
                                        color: #666;
                                        line-height: 1.4;
                                    ">顧客データと関連する受注書を完全に削除します</div>
                                </div>
                                <span class="material-symbols-outlined" style="
                                    color: #f44336;
                                    font-size: 20px;
                                ">dangerous</span>
                            </div>
                        </div>
                    </div>

                    <div class="ktp-delete-popup-buttons" style="
                        display: flex;
                        gap: 10px;
                        justify-content: flex-end;
                    ">
                        <button type="button" class="ktp-delete-cancel-btn" style="
                            padding: 10px 20px;
                            border: 1px solid #ccc;
                            background: #f8f9fa;
                            color: #333;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                            transition: all 0.2s ease;
                        ">キャンセル</button>
                        <button type="button" class="ktp-delete-confirm-btn" style="
                            padding: 10px 20px;
                            border: none;
                            background: #dc3545;
                            color: white;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                            transition: all 0.2s ease;
                            opacity: 0.5;
                            pointer-events: none;
                        ">削除実行</button>
                    </div>
                </div>
            </div>
        `;

        return { popupId, popupHTML };
    }

    // オプション選択の処理
    function handleOptionSelection(popupId) {
        const popup = document.getElementById(popupId);
        const options = popup.querySelectorAll('.ktp-delete-option');
        const confirmBtn = popup.querySelector('.ktp-delete-confirm-btn');

        options.forEach(option => {
            option.addEventListener('click', function() {
                // 他のオプションの選択状態をリセット
                options.forEach(opt => {
                    opt.style.borderColor = '#e0e0e0';
                    opt.style.backgroundColor = 'white';
                });

                // 選択されたオプションをハイライト
                this.style.borderColor = '#007bff';
                this.style.backgroundColor = '#f8f9ff';

                // 削除実行ボタンを有効化
                confirmBtn.style.opacity = '1';
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.dataset.selectedOption = this.dataset.option;
            });
        });
    }

    // 削除実行の処理
    function handleDeleteExecution(popupId, clientId, clientName) {
        const popup = document.getElementById(popupId);
        const confirmBtn = popup.querySelector('.ktp-delete-confirm-btn');
        const cancelBtn = popup.querySelector('.ktp-delete-cancel-btn');

        confirmBtn.addEventListener('click', function() {
            const selectedOption = this.dataset.selectedOption;
            
            if (!selectedOption) {
                alert('削除方法を選択してください。');
                return;
            }

            // 選択肢に応じた確認メッセージ
            let confirmMessage = '';
            let isConfirmed = false;

            switch (selectedOption) {
                case 'soft':
                    isConfirmed = confirm('対象外に変更しますか？\n受注書は残り、顧客データは復元可能です。');
                    break;
                case 'delete':
                    confirmMessage = '顧客データと部署データを削除しますか？\n\n⚠️ 注意：\n• 顧客データと部署データが完全に削除されます\n• 受注書は残りますが、顧客情報は失われます\n• この操作は元に戻せません';
                    isConfirmed = confirm(confirmMessage);
                    break;
                case 'complete':
                    confirmMessage = '顧客データと関連する受注書を完全に削除しますか？\n\n🚨 警告：\n• 顧客データが完全に削除されます\n• 関連するすべての受注書が削除されます\n• この操作は元に戻せません\n• データの復元は不可能です';
                    isConfirmed = confirm(confirmMessage);
                    break;
            }

            if (isConfirmed) {
                // 対象外に変更する場合は、送信前に受注書作成ボタンを無効化
                if (selectedOption === 'soft') {
                    const orderButton = document.querySelector('.create-order-btn');
                    if (orderButton) {
                        orderButton.disabled = true;
                        orderButton.style.background = '#ccc';
                        orderButton.style.color = '#888';
                        orderButton.style.cursor = 'not-allowed';
                        orderButton.title = '受注書作成（対象外顧客のため無効）';
                    }
                }

                // フォームを作成して送信
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.style.display = 'none';

                // nonceフィールド
                const nonceField = document.querySelector('input[name="ktp_client_nonce"]');
                if (nonceField) {
                    const nonceInput = document.createElement('input');
                    nonceInput.type = 'hidden';
                    nonceInput.name = 'ktp_client_nonce';
                    nonceInput.value = nonceField.value;
                    form.appendChild(nonceInput);
                }

                // 顧客ID
                const clientIdInput = document.createElement('input');
                clientIdInput.type = 'hidden';
                clientIdInput.name = 'data_id';
                clientIdInput.value = clientId;
                form.appendChild(clientIdInput);

                // 削除タイプ
                const deleteTypeInput = document.createElement('input');
                deleteTypeInput.type = 'hidden';
                deleteTypeInput.name = 'delete_type';
                deleteTypeInput.value = selectedOption;
                form.appendChild(deleteTypeInput);

                // アクション
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'query_post';
                actionInput.value = 'delete';
                form.appendChild(actionInput);

                // 送信
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'send_post';
                submitInput.value = '1';
                form.appendChild(submitInput);

                // 対象外に変更した場合は、送信後に確実にページを再読み込み
                if (selectedOption === 'soft') {
                    // セッションストレージに削除フラグを設定
                    sessionStorage.setItem('ktp_client_deleted', 'true');
                    
                    form.addEventListener('submit', function() {
                        // 送信後に少し待ってからページを再読み込み
                        setTimeout(function() {
                            window.location.reload();
                        }, 100);
                    });
                }
                
                document.body.appendChild(form);
                
                form.submit();
            }
        });

        // キャンセルボタンの処理
        cancelBtn.addEventListener('click', function() {
            document.getElementById(popupId).remove();
        });

        // オーバーレイクリックでのキャンセル
        popup.addEventListener('click', function(e) {
            if (e.target === this) {
                this.remove();
            }
        });
    }

    // 削除ボタンのクリックイベントを設定
    function setupDeleteButtons() {
        document.addEventListener('click', function(e) {
            // 削除ボタン（ゴミ箱アイコン）のクリックを検出
            if (
                e.target.closest('.delete-submit-btn') ||
                (e.target.closest('button') && e.target.closest('button').classList.contains('delete-submit-btn'))
            ) {
                // 顧客タブ（client）の場合のみポップアップを表示
                const currentUrl = window.location.href;
                const urlParams = new URLSearchParams(window.location.search);
                const tabName = urlParams.get('tab_name');
                
                // 顧客タブ以外の場合は通常の削除処理を継続
                if (tabName !== 'client') {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();

                const button = e.target.closest('.delete-submit-btn');
                const form = button.closest('form');
                if (!form) return;
                // 顧客IDを取得
                const clientIdInput = form.querySelector('input[name="data_id"]');
                if (!clientIdInput) return;
                const clientId = clientIdInput.value;
                // 顧客名を取得（ページ内から検索）
                let clientName = '顧客';
                const customerNameElement = document.querySelector('#order_customer_name, .data_detail_title');
                if (customerNameElement) {
                    const nameText = customerNameElement.textContent;
                    const nameMatch = nameText.match(/顧客の詳細.*?（.*?ID:\s*(\d+).*?）/);
                    if (nameMatch) {
                        clientName = '顧客ID: ' + clientId;
                    }
                }
                // ポップアップを表示
                const { popupId, popupHTML } = createDeletePopup(clientId, clientName);
                document.body.insertAdjacentHTML('beforeend', popupHTML);
                // イベントリスナーを設定
                handleOptionSelection(popupId);
                handleDeleteExecution(popupId, clientId, clientName);
            }
        });
    }

    // DOM読み込み完了時に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupDeleteButtons);
    } else {
        setupDeleteButtons();
    }

    // 受注書作成ボタンのクリックを無効化する関数
    function disableOrderButtonClick() {
        const orderButton = document.querySelector('.create-order-btn');
        if (orderButton) {
            // 既存のイベントリスナーを削除
            orderButton.removeEventListener('click', preventOrderButtonClick);
            // 新しいイベントリスナーを追加
            orderButton.addEventListener('click', preventOrderButtonClick);
        }
    }

    // 受注書作成ボタンのクリックを防ぐ関数
    function preventOrderButtonClick(e) {
        const statusElement = document.querySelector('[data-client-status]');
        if (statusElement && statusElement.getAttribute('data-client-status') === '対象外') {
            e.preventDefault();
            e.stopPropagation();
            alert('対象外顧客のため、受注書を作成できません。');
            return false;
        }
    }

    // ページ読み込み完了後にボタン状態を確認
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            checkOrderButtonState();
            disableOrderButtonClick();
            // 定期的チェックを開始（1秒間隔で5回）
            let checkCount = 0;
            const checkInterval = setInterval(function() {
                periodicButtonCheck();
                disableOrderButtonClick();
                checkCount++;
                if (checkCount >= 5) {
                    clearInterval(checkInterval);
                }
            }, 1000);
        });
    } else {
        checkOrderButtonState();
        disableOrderButtonClick();
        // 定期的チェックを開始（1秒間隔で5回）
        let checkCount = 0;
        const checkInterval = setInterval(function() {
            periodicButtonCheck();
            disableOrderButtonClick();
            checkCount++;
            if (checkCount >= 5) {
                clearInterval(checkInterval);
            }
        }, 1000);
    }

    // ページ読み込み後に受注書作成ボタンの状態を確認・修正
    function checkOrderButtonState() {
        // 顧客のステータスを確認するためのAJAXリクエスト
        const urlParams = new URLSearchParams(window.location.search);
        const clientId = urlParams.get('data_id');
        const tabName = urlParams.get('tab_name');
        
        if (tabName === 'client' && clientId) {
            // まず、HTMLに埋め込まれた顧客ステータスを確認
            const statusElement = document.querySelector('[data-client-status]');
            if (statusElement) {
                const clientStatus = statusElement.getAttribute('data-client-status');
                const orderButton = document.querySelector('.create-order-btn');
                
                if (orderButton && clientStatus === '対象外') {
                    // 対象外の場合はボタンを無効化
                    orderButton.disabled = true;
                    orderButton.style.background = '#ccc';
                    orderButton.style.color = '#888';
                    orderButton.style.cursor = 'not-allowed';
                    orderButton.title = '受注書作成（対象外顧客のため無効）';
                    console.log('受注書作成ボタンを無効化しました（対象外顧客）');
                }
            } else {
                // HTMLにステータス情報がない場合は、URLパラメータから確認
                const messageParam = urlParams.get('message');
                if (messageParam === 'deleted') {
                    // 削除処理が完了した場合、ボタンを無効化
                    const orderButton = document.querySelector('.create-order-btn');
                    if (orderButton) {
                        orderButton.disabled = true;
                        orderButton.style.background = '#ccc';
                        orderButton.style.color = '#888';
                        orderButton.style.cursor = 'not-allowed';
                        orderButton.title = '受注書作成（対象外顧客のため無効）';
                        console.log('受注書作成ボタンを無効化しました（削除処理完了後）');
                    }
                }
            }
            
            // セッションストレージから削除フラグを確認
            const wasDeleted = sessionStorage.getItem('ktp_client_deleted');
            if (wasDeleted === 'true') {
                const orderButton = document.querySelector('.create-order-btn');
                if (orderButton) {
                    orderButton.disabled = true;
                    orderButton.style.background = '#ccc';
                    orderButton.style.color = '#888';
                    orderButton.style.cursor = 'not-allowed';
                    orderButton.title = '受注書作成（対象外顧客のため無効）';
                    console.log('受注書作成ボタンを無効化しました（セッションストレージ）');
                }
                // フラグをクリア
                sessionStorage.removeItem('ktp_client_deleted');
            }
        }
    }

    // 定期的にボタン状態をチェックする関数
    function periodicButtonCheck() {
        const urlParams = new URLSearchParams(window.location.search);
        const tabName = urlParams.get('tab_name');
        
        if (tabName === 'client') {
            const orderButton = document.querySelector('.create-order-btn');
            if (orderButton) {
                // ボタンが有効になっている場合、対象外チェックを実行
                if (!orderButton.disabled) {
                    const statusElement = document.querySelector('[data-client-status]');
                    if (statusElement && statusElement.getAttribute('data-client-status') === '対象外') {
                        orderButton.disabled = true;
                        orderButton.style.background = '#ccc';
                        orderButton.style.color = '#888';
                        orderButton.style.cursor = 'not-allowed';
                        orderButton.title = '受注書作成（対象外顧客のため無効）';
                        console.log('定期的チェックで受注書作成ボタンを無効化しました');
                    }
                }
            }
        }
    }

    // 動的に追加された要素にも対応
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // 新しい削除ボタンが追加された場合の処理
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        const deleteButtons = node.querySelectorAll ? node.querySelectorAll('.delete-submit-btn') : [];
                        if (deleteButtons.length > 0) {
                            // 既存のイベントリスナーが重複しないよう、ここでは何もしない
                            // setupDeleteButtons()で全体を監視しているため
                        }
                    }
                });
            }
        });
    });

    // ページ全体を監視
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

})(); 