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
            data += '&ktp_ajax_nonce=' + encodeURIComponent(nonce);
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

    // クッキーユーティリティ
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    function deleteCookie(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    }

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

    // ページ内の全ての要素を確認（デバッグ用）
    var allButtons = document.querySelectorAll('button');
    if (window.ktpDebugMode) console.log('KTPWP: All buttons on page:', allButtons.length);

    var costDetailsEl = document.querySelector('details.order-cost-details');

    if (window.ktpDebugMode) console.log('KTPWP: Cost details element found:', !!costDetailsEl);

    if (costDetailsEl) {
        setupCostDetailsToggle(costDetailsEl);
    } else {
        setTimeout(function () {
            var retry = document.querySelector('details.order-cost-details');
            if (retry) {
                setupCostDetailsToggle(retry);
            }
        }, 2000);
    }

    initOrderAuxSectionDetailsToggles();
    setTimeout(function () {
        initOrderAuxSectionDetailsToggles();
    }, 2000);

    // スタッフチャット（<details> パネル）
    function setupStaffChatPanel(detailsEl, content) {
        if (window.ktpDebugMode) console.log('KTPWP: Setting up staff chat panel');
        if (!detailsEl || !content) return;
        if (detailsEl.dataset.ktpStaffChatInit === '1') return;
        detailsEl.dataset.ktpStaffChatInit = '1';

        content.style.display = 'block';
        window.updateStaffChatButtonText = function () {};

        var currentOrderIdForChatCookie = (typeof getCurrentOrderId === 'function' && getCurrentOrderId())
            || (document.querySelector('input[name="staff_chat_order_id"]') ? document.querySelector('input[name="staff_chat_order_id"]').value : '')
            || (document.querySelector('input[name="order_id"]') ? document.querySelector('input[name="order_id"]').value : '')
            || 'global';
        var staffChatCookieName = 'ktp_staff_chat_toggle_' + currentOrderIdForChatCookie;

        var urlParams = new URLSearchParams(window.location.search);
        var messageSent = urlParams.get('message_sent') === '1';
        if (messageSent) {
            detailsEl.open = true;
            setCookie(staffChatCookieName, '1', 365);
        } else {
            var savedChat = getCookie(staffChatCookieName);
            if (savedChat === '1') {
                detailsEl.open = true;
            } else if (savedChat === '0') {
                detailsEl.open = false;
            }
        }

        var scrollToBottom = function () {
            if (!detailsEl.open) return;

            window.clearScrollTimeouts();

            var chatSection = document.getElementById('staff-chat-details');
            if (chatSection) {
                chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            var scrollMessages = function () {
                if (!detailsEl.open) return false;
                var currentChatContent = document.getElementById('staff-chat-content');
                if (!currentChatContent) return false;
                var messagesContainer = document.getElementById('staff-chat-messages');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    return true;
                }
                currentChatContent.scrollTop = currentChatContent.scrollHeight;
                return true;
            };

            window.scrollTimeouts.push(setTimeout(scrollMessages, 300));
            window.scrollTimeouts.push(setTimeout(scrollMessages, 800));
            window.scrollTimeouts.push(setTimeout(scrollMessages, 1500));
        };

        detailsEl.addEventListener('toggle', function () {
            if (detailsEl.open) {
                window.hideNewMessageNotification();
            }
            setCookie(staffChatCookieName, detailsEl.open ? '1' : '0', 365);
        });

        if (messageSent) {
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

        // スタッフチャットの削除ボタン（投稿者/管理者）
        var messagesContainer = document.getElementById('staff-chat-messages');
        if (messagesContainer) {
            messagesContainer.addEventListener('click', function (e) {
                var target = e.target;
                var deleteBtn = target && target.closest('button.staff-chat-delete');
                if (!deleteBtn || !messagesContainer.contains(deleteBtn)) return;

                var messageId = deleteBtn.getAttribute('data-message-id');
                if (!messageId) return;

                if (!confirm('このメッセージを削除しますか？')) return;

                var url = (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) ? ktpwp_ajax.ajax_url :
                    (typeof ajaxurl !== 'undefined') ? ajaxurl :
                        window.location.origin + '/wp-admin/admin-ajax.php';

                var params = 'action=delete_staff_chat_message&message_id=' + encodeURIComponent(messageId);
                if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.staff_chat) {
                    params += '&_ajax_nonce=' + ktpwp_ajax.nonces.staff_chat;
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.success) {
                                    // DOMから該当メッセージを除去
                                    var wrapper = deleteBtn.closest('.staff-chat-message.scrollable');
                                    var parent = wrapper && wrapper.parentNode;
                                    if (wrapper && parent) {
                                        // 位置を維持したまま、削除ログに置換
                                        var placeholder = document.createElement('div');
                                        parent.insertBefore(placeholder, wrapper);
                                        parent.removeChild(wrapper);

                                        var logDiv = document.createElement('div');
                                        logDiv.className = 'staff-chat-message scrollable';
                                        var deletedText = (resp.data && resp.data.deleted_text) ? resp.data.deleted_text : 'メッセージを削除しました';
                                        var tsSpan = wrapper.querySelector('.staff-chat-timestamp');
                                        var ts = tsSpan ? tsSpan.textContent : '';
                                        logDiv.classList.add('deleted');
                                        logDiv.innerHTML = '<div class="staff-chat-message-header">'
                                            + '<span class="staff-chat-user-name">&nbsp;</span>'
                                            + '<span class="staff-chat-timestamp">' + ts + '</span>'
                                            + '</div>'
                                            + '<div class="staff-chat-message-content">' + deletedText + '</div>';
                                        parent.replaceChild(logDiv, placeholder);
                                        // 件数表示の更新
                                        try { if (typeof updateStaffChatButtonText === 'function') { updateStaffChatButtonText(); } } catch (_) {}
                                        // 最新HTMLを取得して反映（正確な表示へ同期）
                                        fetch(window.location.href, { credentials: 'same-origin' })
                                            .then(function(res){ return res.text(); })
                                            .then(function(html){
                                                var tempDiv = document.createElement('div');
                                                tempDiv.innerHTML = html;
                                                var newMessages = tempDiv.querySelector('#staff-chat-messages');
                                                var messagesContainer = document.getElementById('staff-chat-messages');
                                                if (newMessages && messagesContainer) {
                                                    messagesContainer.innerHTML = newMessages.innerHTML;
                                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                                }
                                            });
                                    }
                                } else {
                                    alert(resp.data || '削除に失敗しました');
                                }
                            } catch (e) {
                                alert('JSON解析エラー: ' + e.message);
                            }
                        } else {
                            alert('サーバーエラーが発生しました');
                        }
                    }
                };
                xhr.send(params);
            });
        }

        if (window.ktpDebugMode) console.log('KTPWP: Staff chat panel setup complete');
    }

    // コスト項目 <details> + クッキーで開閉状態を復元
    function setupCostDetailsToggle(detailsEl) {
        if (!detailsEl || detailsEl.dataset.ktpCostDetailsInit === '1') return;
        detailsEl.dataset.ktpCostDetailsInit = '1';
        var currentOrderIdForCookie = (typeof getCurrentOrderId === 'function' && getCurrentOrderId())
            || (document.querySelector('input[name="order_id"]') ? document.querySelector('input[name="order_id"]').value : '')
            || 'global';
        var costToggleCookieName = 'ktp_cost_toggle_' + currentOrderIdForCookie;
        var savedToggleState = getCookie(costToggleCookieName);
        if (savedToggleState === '1') {
            detailsEl.open = true;
        } else if (savedToggleState === '0') {
            detailsEl.open = false;
        }
        detailsEl.addEventListener('toggle', function () {
            setCookie(costToggleCookieName, detailsEl.open ? '1' : '0', 365);
        });
    }

    // メール履歴・案件ファイルなどの <details>（data-ktp-order-toggle 付き）をクッキーで記憶
    function setupOrderBlockDetailsToggle(detailsEl) {
        if (!detailsEl || detailsEl.dataset.ktpOrderBlockToggleInit === '1') return;
        var toggleKey = detailsEl.getAttribute('data-ktp-order-toggle');
        if (!toggleKey) return;
        detailsEl.dataset.ktpOrderBlockToggleInit = '1';
        var oidAttr = detailsEl.getAttribute('data-ktp-order-id');
        var oid = (oidAttr && oidAttr !== '')
            ? oidAttr
            : ((typeof getCurrentOrderId === 'function' && getCurrentOrderId())
                || (document.querySelector('input[name="order_id"]') ? document.querySelector('input[name="order_id"]').value : '')
                || 'global');
        var cookieName = 'ktp_order_section_' + toggleKey + '_' + oid;
        var saved = getCookie(cookieName);
        if (saved === '1') {
            detailsEl.open = true;
        } else if (saved === '0') {
            detailsEl.open = false;
        }
        detailsEl.addEventListener('toggle', function () {
            setCookie(cookieName, detailsEl.open ? '1' : '0', 365);
        });
    }

    function initOrderAuxSectionDetailsToggles() {
        var list = document.querySelectorAll('details.ktp-order-details-toggle[data-ktp-order-toggle]');
        for (var i = 0; i < list.length; i++) {
            setupOrderBlockDetailsToggle(list[i]);
        }
    }

    // スタッフチャット（<details>）
    var staffChatDetails = document.getElementById('staff-chat-details');
    var staffChatContent = document.getElementById('staff-chat-content');

    if (window.ktpDebugMode) {
        console.log('KTPWP: Staff chat details element:', !!staffChatDetails, 'content:', !!staffChatContent);
    }

    if (staffChatDetails && staffChatContent) {
        setupStaffChatPanel(staffChatDetails, staffChatContent);
    } else {
        setTimeout(function () {
            var d = document.getElementById('staff-chat-details');
            var c = document.getElementById('staff-chat-content');
            if (d && c) {
                setupStaffChatPanel(d, c);
            }
        }, 2000);
    }

    // フォールバック再読込後：クッキー復元の後に開く（記憶と矛盾しないよう cookie も更新）
    if (localStorage.getItem('ktp_scroll_to_chat') === 'true') {
        localStorage.removeItem('ktp_scroll_to_chat');
        var staffD2 = document.getElementById('staff-chat-details');
        if (staffD2) {
            staffD2.open = true;
        }
        var oidChat = (document.querySelector('input[name="staff_chat_order_id"]') && document.querySelector('input[name="staff_chat_order_id"]').value)
            || (document.querySelector('input[name="order_id"]') && document.querySelector('input[name="order_id"]').value)
            || 'global';
        setCookie('ktp_staff_chat_toggle_' + oidChat, '1', 365);
        var staffC2 = document.getElementById('staff-chat-content');
        setTimeout(function () {
            var chatSection = document.querySelector('.staff-chat-title') || document.getElementById('staff-chat-details');
            if (chatSection) {
                chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            var messagesContainer = document.getElementById('staff-chat-messages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else if (staffC2) {
                staffC2.scrollTop = staffC2.scrollHeight;
            }
        }, 500);
    }

    // 受注書イベントリスナーを設定する関数
    function setupOrderEventListeners() {
        var costDetails = document.querySelector('details.order-cost-details');
        if (costDetails) {
            setupCostDetailsToggle(costDetails);
        }

        var staffD = document.getElementById('staff-chat-details');
        var staffC = document.getElementById('staff-chat-content');
        if (staffD && staffC) {
            setupStaffChatPanel(staffD, staffC);
        }

        initOrderAuxSectionDetailsToggles();

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
    if (window.ktpDebugMode) console.log('=== スタッフチャット <details> テスト開始 ===');
    var d = document.getElementById('staff-chat-details');
    if (!d) {
        if (window.ktpDebugMode) console.error('staff-chat-details が見つかりません');
        return false;
    }
    d.open = !d.open;
    if (window.ktpDebugMode) console.log('open:', d.open);
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
    if (window.ktpDebugMode) console.log('=== コスト項目 <details> テスト開始 ===');
    var d = document.querySelector('details.order-cost-details');
    if (!d) {
        if (window.ktpDebugMode) console.error('order-cost-details が見つかりません');
        return false;
    }
    d.open = !d.open;
    if (window.ktpDebugMode) console.log('open:', d.open);
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
