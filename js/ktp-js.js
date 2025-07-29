document.addEventListener('DOMContentLoaded', function () {
    // デバッグモードの設定
    window.ktpDebugMode = window.ktpDebugMode || false;
    
    // =============================
    // 受注書状態記憶機能
    // =============================
    
    // 現在のタブ名を取得
    function getCurrentTabName() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('tab_name') || 'list';
    }
    
    // 現在の受注書IDを取得
    function getCurrentOrderId() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('order_id');
    }
    
    // 受注書IDをローカルストレージに保存
    function saveOrderId(orderId) {
        if (orderId && orderId !== '') {
            localStorage.setItem('ktp_last_order_id', orderId);
            if (window.ktpDebugMode) {
                console.log('KTPWP: 受注書IDを保存しました:', orderId);
            }
        }
    }
    
    // 保存された受注書IDを取得
    function getSavedOrderId() {
        return localStorage.getItem('ktp_last_order_id');
    }
    
    // 受注書タブに戻った時に記憶されたIDを復元
    function restoreOrderId() {
        var currentTab = getCurrentTabName();
        var currentOrderId = getCurrentOrderId();
        
        if (window.ktpDebugMode) {
            console.log('KTPWP: restoreOrderId - currentTab:', currentTab, 'currentOrderId:', currentOrderId);
        }
        
        // 受注書タブで、かつ現在のURLにorder_idが指定されていない場合
        if (currentTab === 'order' && !currentOrderId) {
            var savedOrderId = getSavedOrderId();
            if (window.ktpDebugMode) {
                console.log('KTPWP: restoreOrderId - savedOrderId:', savedOrderId);
            }
            
            if (savedOrderId) {
                // 記憶された受注書IDが有効かどうかを確認
                // まず、現在のページに受注書データが表示されているかチェック
                var orderContent = document.querySelector('.ktp_order_content');
                var noOrderData = document.querySelector('.ktp-no-order-data');
                var hasOrderData = orderContent && orderContent.innerHTML.trim() !== '' && !noOrderData;
                
                if (window.ktpDebugMode) {
                    console.log('KTPWP: restoreOrderId - hasOrderData:', hasOrderData, 'noOrderData:', !!noOrderData);
                }
                
                // 受注書データが既に表示されている場合はリロードしない
                if (hasOrderData) {
                    if (window.ktpDebugMode) {
                        console.log('KTPWP: 受注書データが既に表示されているため、リロードをスキップします');
                    }
                    return false;
                }
                
                // Ajaxで受注書データを取得して表示（ページリロードを避ける）
                if (window.ktpDebugMode) {
                    console.log('KTPWP: 記憶された受注書IDをAjaxで復元します:', savedOrderId);
                }
                
                // URLを更新（履歴に追加しない）
                var newUrl = new URL(window.location);
                newUrl.searchParams.set('order_id', savedOrderId);
                newUrl.searchParams.set('tab_name', 'order');
                window.history.replaceState({}, '', newUrl.toString());
                
                // Ajaxで受注書データを取得
                loadOrderDataAjax(savedOrderId);
                return true;
            }
        }
        return false;
    }
    
    // Ajaxで受注書データを取得する関数
    function loadOrderDataAjax(orderId) {
        if (!orderId) return;
        
        // Ajax設定を取得
        var ajaxUrl = '';
        var nonce = '';
        
        if (typeof ktpwp_ajax !== 'undefined') {
            ajaxUrl = ktpwp_ajax.ajax_url;
            nonce = ktpwp_ajax.nonce;
        } else if (typeof ktp_ajax_object !== 'undefined') {
            ajaxUrl = ktp_ajax_object.ajax_url;
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        }
        
        if (!ajaxUrl) {
            if (window.ktpDebugMode) {
                console.error('KTPWP: Ajax URLが取得できません');
            }
            return;
        }
        
        // Ajaxリクエストを送信
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            // 受注書データを表示
                            updateOrderDisplay(response.data);
                            if (window.ktpDebugMode) {
                                console.log('KTPWP: 受注書データをAjaxで正常に取得しました');
                            }
                        } else {
                            if (window.ktpDebugMode) {
                                console.error('KTPWP: 受注書データの取得に失敗しました:', response);
                            }
                            // 失敗した場合は無効なIDとしてクリア
                            localStorage.removeItem('ktp_last_order_id');
                        }
                    } catch (e) {
                        if (window.ktpDebugMode) {
                            console.error('KTPWP: レスポンスの解析に失敗しました:', e);
                        }
                        localStorage.removeItem('ktp_last_order_id');
                    }
                } else {
                    if (window.ktpDebugMode) {
                        console.error('KTPWP: Ajaxリクエストが失敗しました:', xhr.status);
                    }
                    localStorage.removeItem('ktp_last_order_id');
                }
            }
        };
        
        var data = 'action=ktp_get_order_data&order_id=' + encodeURIComponent(orderId);
        if (nonce) {
            data += '&nonce=' + encodeURIComponent(nonce);
        }
        
        xhr.send(data);
    }
    
    // 受注書表示を更新する関数
    function updateOrderDisplay(orderData) {
        // 受注書コンテンツエリアを取得
        var orderContent = document.querySelector('.ktp_order_content');
        if (!orderContent) {
            // コンテンツエリアがない場合は、ページ全体を更新
            window.location.reload();
            return;
        }
        
        // 受注書データを表示
        if (orderData.html) {
            orderContent.innerHTML = orderData.html;
        }
        
        // イベントリスナーを再設定
        if (typeof setupOrderEventListeners === 'function') {
            setupOrderEventListeners();
        }
    }
    
    // 無効な受注書IDをローカルストレージからクリアする関数
    function clearInvalidOrderId() {
        var noOrderData = document.querySelector('.ktp-no-order-data');
        if (noOrderData) {
            // 受注書データが存在しない場合、記憶されたIDをクリア
            localStorage.removeItem('ktp_last_order_id');
            if (window.ktpDebugMode) {
                console.log('KTPWP: 無効な受注書IDをローカルストレージからクリアしました');
            }
        }
    }
    
    // 受注書IDが無効な場合の処理を監視する関数
    function monitorInvalidOrderId() {
        // URLパラメータにorder_idがあるが、データが表示されていない場合
        var currentOrderId = getCurrentOrderId();
        if (currentOrderId) {
            var noOrderData = document.querySelector('.ktp-no-order-data');
            if (noOrderData) {
                // 無効なIDが指定されている場合、ローカルストレージからクリア
                localStorage.removeItem('ktp_last_order_id');
                if (window.ktpDebugMode) {
                    console.log('KTPWP: 無効な受注書IDを検出し、ローカルストレージからクリアしました:', currentOrderId);
                }
                
                // URLからorder_idパラメータを削除（履歴に追加しない）
                var newUrl = new URL(window.location);
                newUrl.searchParams.delete('order_id');
                window.history.replaceState({}, '', newUrl.toString());
                
                if (window.ktpDebugMode) {
                    console.log('KTPWP: URLから無効な受注書IDを削除しました');
                }
            }
        }
    }
    
    // ページ読み込み時に無効なIDをクリア
    if (getCurrentTabName() === 'order') {
        // 少し遅延してからクリア処理を実行（DOMの読み込みを待つ）
        setTimeout(function() {
            clearInvalidOrderId();
            monitorInvalidOrderId();
        }, 100);
        
        // 受注書IDの復元を試行
        restoreOrderId();
    }
    
    // タブ切り替え時の受注書ID保存
    var tabLinks = document.querySelectorAll('.tab_item');
    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('click', function() {
            var href = this.getAttribute('href');
            if (href) {
                var url = new URL(href, window.location.origin);
                var tabName = url.searchParams.get('tab_name');
                
                // 受注書タブから他のタブに移動する場合、現在の受注書IDを保存
                if (getCurrentTabName() === 'order' && tabName !== 'order') {
                    var currentOrderId = getCurrentOrderId();
                    if (currentOrderId) {
                        saveOrderId(currentOrderId);
                    }
                }
            }
        });
    });
    
    // 受注書タブ内での受注書切り替え時にもIDを保存
    document.addEventListener('click', function(e) {
        // 受注書リストのリンクをクリックした場合
        if (e.target.closest('a[href*="tab_name=order"]')) {
            var link = e.target.closest('a[href*="tab_name=order"]');
            var href = link.getAttribute('href');
            if (href) {
                var url = new URL(href, window.location.origin);
                var orderId = url.searchParams.get('order_id');
                if (orderId) {
                    saveOrderId(orderId);
                }
            }
        }
    });
    
    // =============================
    // 古いグローバル行追加・削除機能は無効化
    // 専用のハンドラ（ktp-invoice-items.js、ktp-cost-items.js）を使用
    // =============================
    // 注意: 以下のコードは無効化されています。
    // 請求項目・コスト項目は専用のJavaScriptファイルで処理されます。
    /*
    document.body.addEventListener('click', function(e) {
        // 行追加
        if (e.target && e.target.classList.contains('btn-add-row')) {
            var tr = e.target.closest('tr');
            if (!tr) return;
            var table = tr.closest('table');
            if (!table) return;
            var tbody = table.querySelector('tbody');
            if (!tbody) return;
            // 新しい行を複製して追加（最終行の内容をコピーして空欄化）
            var newRow = tr.cloneNode(true);
            // 各inputの値をリセット
            newRow.querySelectorAll('input').forEach(function(input) {
                if (input.type === 'number') input.value = 0;
                else input.value = '';
            });
            tbody.insertBefore(newRow, tr.nextSibling);
            // フォーカスを新しい行の最初のinputへ
            var firstInput = newRow.querySelector('input');
            if (firstInput) firstInput.focus();
            e.preventDefault();
        }
        // 行削除
        if (e.target && e.target.classList.contains('btn-delete-row')) {
            var tr = e.target.closest('tr');
            if (!tr) return;
            var table = tr.closest('table');
            if (!table) return;
            var tbody = table.querySelector('tbody');
            if (!tbody) return;
            // 最低1行は残す
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
            } else {
                // 1行しかない場合は値だけリセット
                tr.querySelectorAll('input').forEach(function(input) {
                    if (input.type === 'number') input.value = 0;
                    else input.value = '';
                });
            }
            e.preventDefault();
        }
    });
    */
    if (window.ktpDebugMode) {
        if (window.ktpDebugMode) console.log('KTPWP: DOM loaded, initializing toggle functionality');
    }

    // HTMLエスケープ関数
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;'}[c];
        });
    }

    // グローバルスコープに追加
    window.escapeHtml = escapeHtml;

    // スクロールタイマーを保存する変数（グローバルスコープ）
    window.scrollTimeouts = [];

    // スクロールタイマーをクリアする関数（グローバルスコープ）
    window.clearScrollTimeouts = function () {
        window.scrollTimeouts.forEach(function (timeout) {
            clearTimeout(timeout);
        });
        window.scrollTimeouts = [];
    };

    // 通知バッジを削除（グローバルスコープ）
    window.hideNewMessageNotification = function () {
        var toggleBtn = document.getElementById('staff-chat-toggle-btn');
        if (!toggleBtn) return;

        var badge = toggleBtn.querySelector('.staff-chat-notification-badge');
        if (badge) {
            badge.remove();
        }
    };

    // コスト項目トグル - 詳細デバッグ
    if (window.ktpDebugMode) {
        if (window.ktpDebugMode) console.log('KTPWP: Searching for cost toggle elements...');
    }

    // 全ての .toggle-cost-items 要素を検索
    var allToggleButtons = document.querySelectorAll('.toggle-cost-items');
    if (window.ktpDebugMode) {
        if (window.ktpDebugMode) console.log('KTPWP: Found toggle buttons:', allToggleButtons.length, allToggleButtons);
    }

    // 全ての cost-items-content 要素を検索
    var allCostContents = document.querySelectorAll('#cost-items-content');
    if (window.ktpDebugMode) console.log('KTPWP: Found cost contents:', allCostContents.length, allCostContents);

    // ページ内の全ての要素を確認（デバッグ用）
    var allButtons = document.querySelectorAll('button');
    if (window.ktpDebugMode) console.log('KTPWP: All buttons on page:', allButtons.length);

    var costToggleBtn = document.querySelector('.toggle-cost-items');
    var costContent = document.getElementById('cost-items-content');

    if (window.ktpDebugMode) console.log('KTPWP: Cost toggle elements found:', {
        button: !!costToggleBtn,
        content: !!costContent,
        buttonElement: costToggleBtn,
        contentElement: costContent
    }); if (costToggleBtn && costContent) {
        setupCostToggle(costToggleBtn, costContent);
    } else {
        if (window.ktpDebugMode) console.log('KTPWP: Cost toggle elements not found - button:', !!costToggleBtn, 'content:', !!costContent);

        // 要素が見つからない場合、少し待ってから再試行
        setTimeout(function () {
            if (window.ktpDebugMode) console.log('KTPWP: Retrying to find cost toggle elements...');
            var retryToggleBtn = document.querySelector('.toggle-cost-items');
            var retryCostContent = document.getElementById('cost-items-content');

            if (window.ktpDebugMode) console.log('KTPWP: Retry results:', {
                button: !!retryToggleBtn,
                content: !!retryCostContent
            });

            if (retryToggleBtn && retryCostContent) {
                if (window.ktpDebugMode) console.log('KTPWP: Elements found on retry, setting up functionality...');
                setupCostToggle(retryToggleBtn, retryCostContent);
            } else {
                // HTML構造をデバッグ
                if (window.ktpDebugMode) console.log('KTPWP: Page HTML structure debug:');
                var orderBoxes = document.querySelectorAll('.order_cost_box, .box');
                if (window.ktpDebugMode) console.log('KTPWP: Found boxes:', orderBoxes.length, orderBoxes);

                var h4Elements = document.querySelectorAll('h4');
                if (window.ktpDebugMode) console.log('KTPWP: Found h4 elements:', h4Elements.length);
                h4Elements.forEach(function (h4, index) {
                    if (window.ktpDebugMode) console.log('KTPWP: H4 #' + index + ':', h4.textContent);
                });
            }
        }, 2000);
    }

    // スタッフチャットトグル機能をセットアップする関数
    function setupStaffChatToggle(toggleBtn, content) {
        if (window.ktpDebugMode) console.log('KTPWP: Setting up staff chat toggle functionality');

        // URLパラメータでチャットを開く状態を確認
        var urlParams = new URLSearchParams(window.location.search);
        var chatShouldBeOpen = urlParams.get('chat_open') !== '0';
        var messageSent = urlParams.get('message_sent') === '1';
        var shouldOpenChat = chatShouldBeOpen;

        // 自動スクロール関数
        var scrollToBottom = function () {
            if (!content || content.style.display === 'none') {
                return;
            }

            if (toggleBtn && toggleBtn.getAttribute('aria-expanded') !== 'true') {
                return;
            }

            window.clearScrollTimeouts();

            // チャットセクションまでページをスクロール
            var chatSection = document.querySelector('.order_memo_box h4');
            if (chatSection && chatSection.textContent.includes('スタッフチャット')) {
                chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // メッセージエリアのスクロール処理
            var scrollMessages = function () {
                var currentChatContent = document.getElementById('staff-chat-content');
                if (!currentChatContent || currentChatContent.style.display === 'none') {
                    return false;
                }

                var messagesContainer = document.getElementById('staff-chat-messages');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    return true;
                } else {
                    if (currentChatContent) {
                        currentChatContent.scrollTop = currentChatContent.scrollHeight;
                        return true;
                    }
                }
                return false;
            };

            // 複数回試行してスクロール
            window.scrollTimeouts.push(setTimeout(function () {
                scrollMessages();
            }, 300));

            window.scrollTimeouts.push(setTimeout(function () {
                scrollMessages();
            }, 800));

            window.scrollTimeouts.push(setTimeout(function () {
                scrollMessages();
            }, 1500));
        };

        // 初期状態を設定
        if (shouldOpenChat) {
            content.style.display = 'block';
            toggleBtn.setAttribute('aria-expanded', 'true');

            if (messageSent) {
                scrollToBottom();

                // スクロール実行後、URLからパラメータを削除
                var newUrl = new URL(window.location);
                newUrl.searchParams.delete('message_sent');
                newUrl.searchParams.delete('chat_open');
                window.history.replaceState({}, '', newUrl);
            }
        } else {
            content.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        // 項目数を取得してボタンテキストに追加
        var updateStaffChatButtonText = function () {
            var scrollableMessages = content.querySelectorAll('.staff-chat-message.scrollable');
            var messageCount = scrollableMessages.length || 0;

            var emptyMessage = content.querySelector('.staff-chat-empty');
            if (emptyMessage) {
                messageCount = 0;
            }

            var showLabel = toggleBtn.dataset.showLabel || window.ktpwpStaffChatShowLabel || '表示';
            var hideLabel = toggleBtn.dataset.hideLabel || window.ktpwpStaffChatHideLabel || '非表示';
            var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            var buttonText = (isExpanded ? hideLabel : showLabel) + '（' + messageCount + 'メッセージ）';
            toggleBtn.textContent = buttonText;
            if (window.ktpDebugMode) console.log('KTPWP: Staff chat button text updated to:', buttonText);
        };

        // グローバルスコープにも追加
        window.updateStaffChatButtonText = updateStaffChatButtonText;

        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.ktpDebugMode) console.log('KTPWP: Staff chat toggle button clicked');

            var expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                window.clearScrollTimeouts();
                content.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
                if (window.ktpDebugMode) console.log('KTPWP: Staff chat content hidden');
            } else {
                content.style.display = 'block';
                toggleBtn.setAttribute('aria-expanded', 'true');
                window.hideNewMessageNotification();
                if (window.ktpDebugMode) console.log('KTPWP: Staff chat content shown');
            }
            updateStaffChatButtonText();
        });

        // 国際化ラベルを設定
        if (typeof window.ktpwpStaffChatShowLabel !== 'undefined') {
            toggleBtn.dataset.showLabel = window.ktpwpStaffChatShowLabel;
        }
        if (typeof window.ktpwpStaffChatHideLabel !== 'undefined') {
            toggleBtn.dataset.hideLabel = window.ktpwpStaffChatHideLabel;
        }

        // 初期状態のボタンテキストを設定
        updateStaffChatButtonText();

        // ページ読み込み完了後の処理
        if (shouldOpenChat && messageSent) {
            window.addEventListener('load', function () {
                setTimeout(function () {
                    scrollToBottom();

                    var newUrl = new URL(window.location);
                    newUrl.searchParams.delete('message_sent');
                    newUrl.searchParams.delete('chat_open');
                    window.history.replaceState({}, '', newUrl);
                }, 1000);
            });
        }

        // スタッフチャットフォームの送信処理を追加
        var chatForm = document.getElementById('staff-chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', function (e) {
                e.preventDefault(); // デフォルトのフォーム送信を防ぐ

                var messageInput = document.getElementById('staff-chat-input');
                var submitButton = document.getElementById('staff-chat-submit');
                var orderId = document.querySelector('input[name="staff_chat_order_id"]')?.value;

                if (!messageInput || messageInput.value.trim() === '') {
                    messageInput.focus();
                    return false;
                }

                if (!orderId) {
                    return false;
                }

                // 送信ボタンを一時的に無効化
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = '送信中...';
                    submitButton.style.opacity = '0.6';
                }

                // メッセージ入力欄も一時的に無効化
                if (messageInput) {
                    messageInput.disabled = true;
                    messageInput.style.opacity = '0.6';
                }

                // AJAX でメッセージを送信
                var xhr = new XMLHttpRequest();
                var url = (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) ? ktpwp_ajax.ajax_url :
                    (typeof ajaxurl !== 'undefined') ? ajaxurl :
                        window.location.origin + '/wp-admin/admin-ajax.php';
                var params = 'action=send_staff_chat_message&order_id=' + orderId + '&message=' + encodeURIComponent(messageInput.value.trim());

                // nonceを追加
                if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.staff_chat) {
                    params += '&_ajax_nonce=' + ktpwp_ajax.nonces.staff_chat;
                }

                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

                // 送信パラメータをデバッグ出力
                if (window.ktpDebugMode) console.log('[KTPWPスタッフチャット送信] url:', url);
                if (window.ktpDebugMode) console.log('[KTPWPスタッフチャット送信] params:', params);
                if (window.ktpDebugMode) console.log('[KTPWPスタッフチャット送信] order_id:', orderId, 'message:', messageInput.value.trim());
                if (typeof ktpwp_ajax !== 'undefined') {
                    if (window.ktpDebugMode) console.log('[KTPWPスタッフチャット送信] ktpwp_ajax:', ktpwp_ajax);
                }

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        // 送信ボタンとメッセージ入力欄を復元
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = '送信';
                            submitButton.style.opacity = '1';
                        }
                        if (messageInput) {
                            messageInput.disabled = false;
                            messageInput.style.opacity = '1';
                        }

                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    // メッセージをクリア
                                    messageInput.value = '';
                                    updateSubmitButton();

                                    // 最新のチャットHTMLを取得し、アバター付きで上書き
                                    fetch(window.location.href, { credentials: 'same-origin' })
                                        .then(function(res) { return res.text(); })
                                        .then(function(html) {
                                            var tempDiv = document.createElement('div');
                                            tempDiv.innerHTML = html;
                                            var newMessages = tempDiv.querySelector('#staff-chat-messages');
                                            var messagesContainer = document.getElementById('staff-chat-messages');
                                            if (newMessages && messagesContainer) {
                                                messagesContainer.innerHTML = newMessages.innerHTML;
                                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                                updateStaffChatButtonText();
                                            } else {
                                                // fallback: reload
                                                localStorage.setItem('ktp_scroll_to_chat', 'true');
                                                window.location.reload();
                                            }
                                        });
                                    return;
                                } else {
                                    alert('メッセージの送信に失敗しました: ' + (response.data || '不明なエラー'));
                                }
                            } catch (e) {
                                alert('JSON解析エラー: ' + e.message);
                            }
                        } else {
                            let msg = 'サーバーエラーが発生しました';
                            if (xhr.responseText) {
                                try {
                                    const resp = JSON.parse(xhr.responseText);
                                    if (resp && resp.data) msg += '\n' + resp.data;
                                } catch(e) {
                                    msg += '\n' + xhr.responseText;
                                }
                            }
                            alert(msg);
                            if (window.ktpDebugMode) console.error('スタッフチャット送信エラー詳細:', xhr.responseText);
                        }
                    }
                };
                xhr.send(params);
            });
            
            // テキストエリアでのキーボード操作を追加
            var messageInput = document.getElementById('staff-chat-input');
            var submitButton = document.getElementById('staff-chat-submit');
            
            if (messageInput && submitButton) {
                // 送信ボタンの状態を更新する関数
                function updateSubmitButton() {
                    var hasContent = messageInput.value.trim().length > 0;
                    submitButton.disabled = !hasContent;
                }
                
                // 入力時にボタン状態を更新
                messageInput.addEventListener('input', updateSubmitButton);
                
                // キーボードショートカット
                messageInput.addEventListener('keydown', function (e) {
                    // Ctrl+Enter または Cmd+Enter で送信
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                        if (!submitButton.disabled) {
                            e.preventDefault();
                            chatForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                        }
                    }
                });
                
                // 初期状態を設定
                updateSubmitButton();
            }
        }

        if (window.ktpDebugMode) console.log('KTPWP: Staff chat toggle setup complete');
    }

    // コスト項目トグル機能をセットアップする関数
    function setupCostToggle(toggleBtn, content) {
        if (window.ktpDebugMode) console.log('KTPWP: Setting up cost toggle functionality');

        // 初期状態を非表示に設定
        content.style.display = 'none';
        toggleBtn.setAttribute('aria-expanded', 'false');

        // 項目数を取得してボタンテキストに追加
        var updateCostButtonText = function () {
            var itemCount = content.querySelectorAll('.cost-items-table tbody tr').length || 0;
            var showLabel = toggleBtn.dataset.showLabel || window.ktpwpCostShowLabel || '表示';
            var hideLabel = toggleBtn.dataset.hideLabel || window.ktpwpCostHideLabel || '非表示';
            var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            var buttonText = (isExpanded ? hideLabel : showLabel) + '（' + itemCount + '項目）';
            toggleBtn.textContent = buttonText;
            if (window.ktpDebugMode) console.log('KTPWP: Button text updated to:', buttonText);
        };

        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.ktpDebugMode) console.log('KTPWP: Cost toggle button clicked');

            var expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                content.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
                if (window.ktpDebugMode) console.log('KTPWP: Cost content hidden');
            } else {
                content.style.display = 'block';
                toggleBtn.setAttribute('aria-expanded', 'true');
                if (window.ktpDebugMode) console.log('KTPWP: Cost content shown');
            }
            updateCostButtonText();
        });

        // 国際化ラベルを設定
        if (typeof window.ktpwpCostShowLabel !== 'undefined') {
            toggleBtn.dataset.showLabel = window.ktpwpCostShowLabel;
        }
        if (typeof window.ktpwpCostHideLabel !== 'undefined') {
            toggleBtn.dataset.hideLabel = window.ktpwpCostHideLabel;
        }

        // 初期状態のボタンテキストを設定
        updateCostButtonText();

        if (window.ktpDebugMode) console.log('KTPWP: Cost toggle setup complete');
    }

    // スタッフチャットトグル
    var staffChatToggleBtn = document.querySelector('.toggle-staff-chat');
    var staffChatContent = document.getElementById('staff-chat-content');

    if (window.ktpDebugMode) console.log('KTPWP: Searching for staff chat toggle elements...');
    if (window.ktpDebugMode) console.log('KTPWP: Staff chat toggle button:', !!staffChatToggleBtn, staffChatToggleBtn);
    if (window.ktpDebugMode) console.log('KTPWP: Staff chat content:', !!staffChatContent, staffChatContent);

    // ページ読み込み時にローカルストレージをチェックして自動スクロール
    if (localStorage.getItem('ktp_scroll_to_chat') === 'true') {
        localStorage.removeItem('ktp_scroll_to_chat');
        
        // チャットを開く
        if (staffChatContent && staffChatToggleBtn) {
            staffChatContent.style.display = 'block';
            staffChatToggleBtn.setAttribute('aria-expanded', 'true');
            
            // 自動スクロール実行
            setTimeout(function () {
                // チャットセクションまでページをスクロール
                var chatSection = document.querySelector('.staff-chat-title');
                if (!chatSection) {
                    chatSection = document.querySelector('.order_memo_box');
                }
                if (chatSection) {
                    chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                
                // メッセージエリアを最下部にスクロール
                var messagesContainer = document.getElementById('staff-chat-messages');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                } else {
                    // fallback: staff-chat-contentをスクロール
                    if (staffChatContent) {
                        staffChatContent.scrollTop = staffChatContent.scrollHeight;
                    }
                }
            }, 500);
        }
    }

    if (staffChatToggleBtn && staffChatContent) {
        setupStaffChatToggle(staffChatToggleBtn, staffChatContent);
    } else {
        if (window.ktpDebugMode) console.log('KTPWP: Staff chat toggle elements not found');

        // 要素が見つからない場合、少し待ってから再試行
        setTimeout(function () {
            if (window.ktpDebugMode) console.log('KTPWP: Retrying to find staff chat toggle elements...');
            var retryStaffToggleBtn = document.querySelector('.toggle-staff-chat');
            var retryStaffContent = document.getElementById('staff-chat-content');

            if (retryStaffToggleBtn && retryStaffContent) {
                if (window.ktpDebugMode) console.log('KTPWP: Staff chat elements found on retry');
                setupStaffChatToggle(retryStaffToggleBtn, retryStaffContent);
            }
        }, 2000);
    }

    // 受注書イベントリスナーを設定する関数
    function setupOrderEventListeners() {
        // コスト項目トグルの再設定
        var costToggleBtn = document.querySelector('.toggle-cost-items');
        var costContent = document.getElementById('cost-items-content');
        if (costToggleBtn && costContent) {
            setupCostToggle(costToggleBtn, costContent);
        }
        
        // スタッフチャットトグルの再設定
        var staffChatToggleBtn = document.querySelector('.toggle-staff-chat');
        var staffChatContent = document.getElementById('staff-chat-content');
        if (staffChatToggleBtn && staffChatContent) {
            setupStaffChatToggle(staffChatToggleBtn, staffChatContent);
        }
        
        // その他の受注書関連イベントリスナーを再設定
        if (typeof ktpInvoiceSetupEventListeners === 'function') {
            ktpInvoiceSetupEventListeners();
        }
        
        if (typeof ktpCostSetupEventListeners === 'function') {
            ktpCostSetupEventListeners();
        }
    }
});

// グローバル関数：スタッフチャットトグルをテスト
window.testStaffChatToggle = function () {
    if (window.ktpDebugMode) console.log('=== スタッフチャットトグルテスト開始 ===');

    var staffToggleBtn = document.querySelector('.toggle-staff-chat');
    var staffContent = document.getElementById('staff-chat-content');

    if (window.ktpDebugMode) console.log('トグルボタン:', staffToggleBtn);
    if (window.ktpDebugMode) console.log('コンテンツ:', staffContent);

    if (!staffToggleBtn) {
        if (window.ktpDebugMode) console.error('スタッフチャットトグルボタンが見つかりません');
        return false;
    }

    if (!staffContent) {
        if (window.ktpDebugMode) console.error('スタッフチャットコンテンツが見つかりません');
        return false;
    }

    // 現在の状態を表示
    if (window.ktpDebugMode) console.log('現在の状態:', {
        display: staffContent.style.display,
        ariaExpanded: staffToggleBtn.getAttribute('aria-expanded'),
        buttonText: staffToggleBtn.textContent
    });

    // クリックをシミュレート
    if (window.ktpDebugMode) console.log('クリックをシミュレート...');
    staffToggleBtn.click();

    // クリック後の状態を表示
    if (window.ktpDebugMode) console.log('クリック後の状態:', {
        display: staffContent.style.display,
        ariaExpanded: staffToggleBtn.getAttribute('aria-expanded'),
        buttonText: staffToggleBtn.textContent
    });

    if (window.ktpDebugMode) console.log('=== テスト完了 ===');
    return true;
};

// グローバル関数：両方のトグルをテスト
window.testAllToggles = function () {
    if (window.ktpDebugMode) console.log('=== 全トグル機能テスト開始 ===');

    var costResult = window.testCostToggle();
    var staffResult = window.testStaffChatToggle();

    if (window.ktpDebugMode) console.log('テスト結果:');
    if (window.ktpDebugMode) console.log('- コスト項目トグル:', costResult ? '成功' : '失敗');
    if (window.ktpDebugMode) console.log('- スタッフチャットトグル:', staffResult ? '成功' : '失敗');

    if (costResult && staffResult) {
        window.showSuccessNotification('全てのトグル機能が正常に動作しています');
    } else {
        if (window.ktpDebugMode) console.error('一部のトグル機能に問題があります');
    }

    if (window.ktpDebugMode) console.log('=== 全テスト完了 ===');
    return costResult && staffResult;
};

// グローバル関数：コスト項目トグルをテスト
window.testCostToggle = function () {
    if (window.ktpDebugMode) console.log('=== コスト項目トグルテスト開始 ===');

    var costToggleBtn = document.querySelector('.toggle-cost-items');
    var costContent = document.getElementById('cost-items-content');

    if (window.ktpDebugMode) console.log('トグルボタン:', costToggleBtn);
    if (window.ktpDebugMode) console.log('コンテンツ:', costContent);

    if (!costToggleBtn) {
        if (window.ktpDebugMode) console.error('トグルボタンが見つかりません');
        return false;
    }

    if (!costContent) {
        if (window.ktpDebugMode) console.error('コストコンテンツが見つかりません');
        return false;
    }

    // 現在の状態を表示
    if (window.ktpDebugMode) console.log('現在の状態:', {
        display: costContent.style.display,
        ariaExpanded: costToggleBtn.getAttribute('aria-expanded'),
        buttonText: costToggleBtn.textContent
    });

    // クリックをシミュレート
    if (window.ktpDebugMode) console.log('クリックをシミュレート...');
    costToggleBtn.click();

    // クリック後の状態を表示
    if (window.ktpDebugMode) console.log('クリック後の状態:', {
        display: costContent.style.display,
        ariaExpanded: costToggleBtn.getAttribute('aria-expanded'),
        buttonText: costToggleBtn.textContent
    });

    if (window.ktpDebugMode) console.log('=== テスト完了 ===');
    return true;
};

// 成功通知を表示する関数
window.showSuccessNotification = function (message) {
    var notification = document.createElement('div');
    notification.className = 'success-notification';
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; z-index: 10000; font-size: 14px;';
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(function () {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
};
