/**
 * 納期フィールドのAjax保存機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// 統一されたAJAX設定の取得（handleProgressChange からも参照するためグローバルに定義）
function getAjaxConfigForDeliveryDates() {
    var config = { url: '', nonce: '' };
    if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
        config.url = ktpwp_ajax.ajax_url;
    } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
        config.url = ktp_ajax_object.ajax_url;
    } else if (typeof ajaxurl !== 'undefined') {
        config.url = ajaxurl;
    } else {
        config.url = '/wp-admin/admin-ajax.php';
    }
    // ページで1つに統一（Head の ktpwp_ajax.nonce を最優先）
    if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonce) {
        config.nonce = ktpwp_ajax.nonce;
    } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
        config.nonce = ktp_ajax_object.nonce;
    } else if (typeof ktp_ajax !== 'undefined' && ktp_ajax.nonce) {
        config.nonce = ktp_ajax.nonce;
    } else if (typeof ktp_ajax_nonce !== 'undefined') {
        config.nonce = ktp_ajax_nonce;
    } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.delivery_dates) {
        config.nonce = ktpwp_ajax.nonces.delivery_dates;
    } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
        config.nonce = ktpwp_ajax.nonces.general;
    } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
        config.nonce = ktp_ajax_object.nonce;
    }
    return config;
}

// グローバル関数：受注書詳細での進捗変更処理
window.handleProgressChange = function(selectElement) {
    console.log('[DELIVERY-DATES] handleProgressChange called');
    
    var $select = jQuery(selectElement);
    var $completionInput = jQuery('#completion_date');
    var $form = $select.closest('form');
    
    var newProgress = parseInt($select.val());
    var orderId = $form.find('input[name="update_progress_id"]').val();
    
    console.log('[DELIVERY-DATES] 進捗変更処理:', {
        orderId: orderId,
        newProgress: newProgress,
        hasCompletionInput: $completionInput.length > 0,
        completionInputValue: $completionInput.val()
    });
    
    // 進捗が「完了」（progress = 4）に変更された場合、完了日を自動設定
    if (newProgress === 4 && $completionInput.length > 0) {
        var currentDate = $completionInput.val();
        if (!currentDate) {
            var today = new Date();
            var dateString = today.getFullYear() + '-' + 
                           String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(today.getDate()).padStart(2, '0');
            
            console.log('[DELIVERY-DATES] 完了日を自動設定します:', dateString);
            $completionInput.val(dateString);
            
            // フォーム内の隠し完了日フィールドも更新
            var $hiddenCompletionField = $form.find('input[name="completion_date"]');
            if ($hiddenCompletionField.length > 0) {
                $hiddenCompletionField.val(dateString);
                console.log('[DELIVERY-DATES] フォーム内の隠し完了日フィールドも更新しました:', dateString);
            }
            
            // 視覚的なフィードバック
            $completionInput.css('border-color', '#4CAF50');
            setTimeout(function() {
                $completionInput.css('border-color', '#ddd');
            }, 2000);
        }
    } else if ([1, 2, 3].includes(newProgress) && $completionInput.length > 0) {
        // 進捗が受注以前（受付中、見積中、受注）に変更された場合のみ、完了日をクリア
        console.log('[DELIVERY-DATES] 進捗が受注以前に変更されたため、完了日をクリアします');
        $completionInput.val('');
        
        // フォーム内の隠し完了日フィールドもクリア
        var $hiddenCompletionField = $form.find('input[name="completion_date"]');
        if ($hiddenCompletionField.length > 0) {
            $hiddenCompletionField.val('');
            console.log('[DELIVERY-DATES] フォーム内の隠し完了日フィールドもクリアしました');
        }
    }
    
    // nonceの取得（グローバルに定義した関数を使用）
    var ajaxConfig = getAjaxConfigForDeliveryDates();
    var nonce = ajaxConfig.nonce;
    
    if (!nonce) {
        console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
        alert(ktpwpTranslate('セキュリティトークンが取得できません。ページを再読み込みしてください。'));
        return;
    }
    
    // Ajaxで進捗更新
    console.log('[DELIVERY-DATES] Ajaxで進捗更新を実行します');
    jQuery.ajax({
        url: ajaxConfig.url,
        type: 'POST',
        data: {
            action: 'ktp_update_progress',
            order_id: orderId,
            progress: newProgress,
            completion_date: $completionInput.val(),
            nonce: nonce
        },
        success: function(response) {
            console.log('[DELIVERY-DATES] 進捗更新Ajax応答:', response);
            if (response.success) {
                // 成功時の処理
                console.log('[DELIVERY-DATES] 進捗更新が完了しました');
                
                // 視覚的なフィードバック
                $select.css('border-color', '#4CAF50');
                setTimeout(function() {
                    $select.css('border-color', '');
                }, 2000);
                
                // 完了日フィールドの値を更新（サーバーから返された値で）
                if (response.data && response.data.completion_date) {
                    $completionInput.val(response.data.completion_date);
                    var $hiddenCompletionField = $form.find('input[name="completion_date"]');
                    if ($hiddenCompletionField.length > 0) {
                        $hiddenCompletionField.val(response.data.completion_date);
                    }
                }
            } else {
                // エラー時の処理
                console.error('[DELIVERY-DATES] 進捗更新に失敗しました:', response.data);
                $select.css('border-color', '#f44336');
                setTimeout(function() {
                    $select.css('border-color', '');
                }, 3000);
                
                var errorMessage = '進捗更新に失敗しました';
                if (response.data) {
                    errorMessage = response.data;
                }
                alert(errorMessage);
            }
        },
        error: function(xhr, status, error) {
            // 通信エラー時の処理
            console.error('[DELIVERY-DATES] 進捗更新通信エラー:', error);
            $select.css('border-color', '#f44336');
            setTimeout(function() {
                $select.css('border-color', '');
            }, 3000);
            alert(ktpwpTranslate('進捗更新の通信でエラーが発生しました'));
        }
    });
};

jQuery(document).ready(function($) {
    'use strict';

    // 受注書タブの納期入力・進捗セレクト・バッジが DOM に存在しない場合は、
    // document レベルの change / click 委譲を一切張らずに即 return する。
    // （サービス／協力会社／顧客タブでの無駄なイベント処理・再描画を避けるため）
    if (
        $('.delivery-date-input').length === 0 &&
        $('.completion-date-input').length === 0 &&
        $('.order-created-date-input').length === 0 &&
        $('#completion_date').length === 0 &&
        $('.progress-select').length === 0 &&
        $('#order_progress_select').length === 0 &&
        $('.order-progress-badge').length === 0 &&
        $('.delivery-warning-badge').length === 0
    ) {
        return;
    }

    console.log('[DELIVERY-DATES] 納期警告機能が読み込まれました');
    
    // 統一されたAJAX設定の取得
    function getAjaxConfig() {
        const config = {
            url: '',
            nonce: ''
        };
        
        // URLの取得（優先順位順）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            config.url = ktpwp_ajax.ajax_url;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            config.url = ktp_ajax_object.ajax_url;
        } else if (typeof ajaxurl !== 'undefined') {
            config.url = ajaxurl;
        } else {
            config.url = '/wp-admin/admin-ajax.php';
        }
        
        // nonceはページで1つに統一（Head/TabView の ktpwp_ajax.nonce を最優先。複数出ると検証失敗するため）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonce) {
            config.nonce = ktpwp_ajax.nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            config.nonce = ktp_ajax_object.nonce;
        } else if (typeof ktp_ajax !== 'undefined' && ktp_ajax.nonce) {
            config.nonce = ktp_ajax.nonce;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            config.nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.delivery_dates) {
            config.nonce = ktpwp_ajax.nonces.delivery_dates;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            config.nonce = ktpwp_ajax.nonces.general;
        }
        
        return config;
    }

    // Ajax設定の確認
    if (window.ktpDebugMode) {
        const ajaxConfig = getAjaxConfig();
        console.log('[DELIVERY-DATES] 統一されたAJAX設定:', ajaxConfig);
    }
    
    // 進捗タブのバッジはPHPで正しく出力されているため、ページ読み込み時はAJAXで上書きしない（一瞬消えるのを防ぐ）
    // updateProgressButtonWarning() は納期変更・進捗変更時のみ呼ぶ

    // 受付日（登録日）の変更を監視
    $(document).on('change', '.order-created-date-input', function() {
        var $input = $(this);
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();

        if (!orderId || field !== 'created_at') {
            alert(ktpwpTranslate('受付日の保存に必要な情報が取得できません。ページを再読み込みしてください。'));
            return;
        }

        if (!value) {
            alert(ktpwpTranslate('受付日は空にできません。'));
            return;
        }

        $input.prop('disabled', true);
        $input.css('opacity', '0.6');

        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;

        if (!nonce) {
            alert(ktpwpTranslate('セキュリティトークンが取得できません。ページを再読み込みしてください。'));
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }

        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_update_delivery_date',
                order_id: orderId,
                field: field,
                value: value,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    if (response.data && response.data.display_time) {
                        $('#order_created_time').text(' ' + response.data.display_time);
                    }
                    setTimeout(function() {
                        $input.css('border-color', '');
                        $input.css('background-color', '');
                    }, 3000);
                } else {
                    $input.css('border-color', '#f44336');
                    $input.css('background-color', '#ffebee');
                    var errorMessage = 'エラーが発生しました';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (response.data) {
                        errorMessage = response.data;
                    }
                    alert(ktpwpTranslate('受付日の保存に失敗しました: ') + errorMessage);
                }
            },
            error: function() {
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert(ktpwpTranslate('通信エラーが発生しました'));
            },
            complete: function() {
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
    });

    // 納期フィールドの変更を監視
    $(document).on('change', '.delivery-date-input', function() {
        console.log('[DELIVERY-DATES] 納期フィールドが変更されました');
        var $input = $(this);
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 変更内容:', { 
            orderId: orderId, 
            field: field, 
            value: value,
            inputId: $input.attr('id'),
            inputName: $input.attr('name')
        });
        
        // フィールド名の検証
        if (!field) {
            console.error('[DELIVERY-DATES] エラー: data-field属性が設定されていません');
            alert(ktpwpTranslate('フィールド名が設定されていません。ページを再読み込みしてください。'));
            return;
        }
        
        // 保存中の表示
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        
        // nonceの取得
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            alert(ktpwpTranslate('セキュリティトークンが取得できません。ページを再読み込みしてください。'));
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }
        
        // Ajaxでデータを保存
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_update_delivery_date',
                order_id: orderId,
                field: field,
                value: value,
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 納期Ajax応答:', response);
                if (response.success) {
                    // 成功時の処理
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    
                    // 行の警告マークを更新
                    updateWarningMark($input, value);
                    // 納期変更後は進捗タブのバッジも更新（ユーザー操作時のみ）
                    updateProgressButtonWarning();
                    
                    // 3秒後に元のスタイルに戻す
                    setTimeout(function() {
                        $input.css('border-color', '');
                        $input.css('background-color', '');
                    }, 3000);
                } else {
                    // エラー時の処理
                    $input.css('border-color', '#f44336');
                    $input.css('background-color', '#ffebee');
                    var errorMessage = 'エラーが発生しました';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (response.data) {
                        errorMessage = response.data;
                    }
                    alert(ktpwpTranslate('保存に失敗しました: ') + errorMessage);
                }
            },
            error: function() {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert(ktpwpTranslate('通信エラーが発生しました'));
            },
            complete: function() {
                // 処理完了時の処理
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
    });

    // 完了日フィールドの変更を監視
    $(document).on('change', '.completion-date-input', function() {
        console.log('[DELIVERY-DATES] 完了日フィールドが変更されました');
        var $input = $(this);
        // 受注書詳細の #completion_date は専用ハンドラのみ（二重・三重の Ajax を防ぐ）
        if ($input.attr('id') === 'completion_date') {
            return;
        }
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 完了日変更内容:', { 
            orderId: orderId, 
            field: field, 
            value: value,
            inputId: $input.attr('id'),
            inputName: $input.attr('name')
        });
        
        // フィールド名の検証
        if (!field) {
            console.error('[DELIVERY-DATES] エラー: data-field属性が設定されていません');
            alert(ktpwpTranslate('フィールド名が設定されていません。ページを再読み込みしてください。'));
            return;
        }
        
        // 保存中の表示
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        
        // nonceの取得
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            alert(ktpwpTranslate('セキュリティトークンが取得できません。ページを再読み込みしてください。'));
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }
        
        // Ajaxでデータを保存
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_update_delivery_date',
                order_id: orderId,
                field: field,
                value: value,
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 完了日Ajax応答:', response);
                if (response.success) {
                    // 成功時の処理
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    
                    // 3秒後に元のスタイルに戻す
                    setTimeout(function() {
                        $input.css('border-color', '');
                        $input.css('background-color', '');
                    }, 3000);
                } else {
                    // エラー時の処理
                    $input.css('border-color', '#f44336');
                    $input.css('background-color', '#ffebee');
                    var errorMessage = 'エラーが発生しました';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (response.data) {
                        errorMessage = response.data;
                    }
                    alert(ktpwpTranslate('保存に失敗しました: ') + errorMessage);
                }
            },
            error: function() {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert(ktpwpTranslate('通信エラーが発生しました'));
            },
            complete: function() {
                // 処理完了時の処理
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
    });

    // 受注書概要のメモ（1行・ktp_order.memo、change で保存）
    $(document).on('change', '.ktp-order-summary-memo-input', function() {
        var $input = $(this);
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();
        if (!field || field !== 'memo') {
            return;
        }
        if (!orderId) {
            alert(ktpwpTranslate('受注IDが取得できません。ページを再読み込みしてください。'));
            return;
        }
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        if (!nonce) {
            alert(ktpwpTranslate('セキュリティトークンが取得できません。ページを再読み込みしてください。'));
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_update_delivery_date',
                order_id: orderId,
                field: field,
                value: value,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    setTimeout(function() {
                        $input.css('border-color', '');
                        $input.css('background-color', '');
                    }, 3000);
                } else {
                    $input.css('border-color', '#f44336');
                    $input.css('background-color', '#ffebee');
                    var errorMessage = 'エラーが発生しました';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (response.data) {
                        errorMessage = response.data;
                    }
                    alert(ktpwpTranslate('保存に失敗しました: ') + errorMessage);
                }
            },
            error: function() {
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert(ktpwpTranslate('通信エラーが発生しました'));
            },
            complete: function() {
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
    });

    // 受注書概要：日付のクリア（保存は各 input の change に任せる）
    $(document).on('click', '.ktp-date-clear-btn', function(e) {
        e.preventDefault();
        var tid = $(this).attr('data-target');
        if (!tid) {
            return;
        }
        var el = document.getElementById(tid);
        if (!el) {
            return;
        }
        el.value = '';
        if (typeof Event === 'function') {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (tid === 'completion_date') {
            $('form.ktp-order-summary-progress-form input[name="completion_date"]').val('');
        }
        $(el).trigger('change');
    });

    /**
     * 警告マークを更新する関数
     * 
     * @param {jQuery} $input 納期入力フィールド
     * @param {string} deliveryDate 納品予定日
     */
    function updateWarningMark($input, deliveryDate) {
        var $wrapper = $input.closest('.delivery-input-wrapper');
        var $existingWarning = $wrapper.find('.delivery-warning-mark-row');
        
        // 既存の警告マークを削除
        $existingWarning.remove();
        
        // 納期が設定されている場合のみ警告判定を行う
        if (deliveryDate && deliveryDate.trim() !== '') {
            // 進捗が「受注」かどうかを確認
            var $progressSelect = $input.closest('.ktp_work_list_item').find('.progress-select');
            var currentProgress = parseInt($progressSelect.val());
            
            if (currentProgress === 3) { // 受注
                // 納期警告の判定
                var today = new Date();
                today.setHours(0, 0, 0, 0); // 時間を00:00:00に設定
                var delivery = new Date(deliveryDate);
                delivery.setHours(0, 0, 0, 0); // 時間を00:00:00に設定
                
                var diffTime = delivery.getTime() - today.getTime();
                var diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                // 警告日数を取得（デフォルト3日）
                var warningDays = 3;
                if (typeof ktp_ajax !== 'undefined' && ktp_ajax.settings && ktp_ajax.settings.delivery_warning_days) {
                    warningDays = parseInt(ktp_ajax.settings.delivery_warning_days);
                }
                
                // デバッグ情報をコンソールに出力
                console.log('[DELIVERY-DATES] 警告計算:', {
                    today: today.toISOString().split('T')[0],
                    delivery: delivery.toISOString().split('T')[0],
                    diffDays: diffDays,
                    warningDays: warningDays,
                    shouldWarn: diffDays <= warningDays
                });
                
                // 納期が迫っている、または納期過ぎの場合に警告マークを表示
                if (diffDays <= warningDays) {
                    var rowTitle = diffDays < 0 ? ktpwpTranslate('納期が過ぎています') : ktpwpTranslate('納期が迫っています');
                    $wrapper.append('<span class="delivery-warning-mark-row" title="' + rowTitle + '">!</span>');
                    console.log('[DELIVERY-DATES] 警告マークを表示しました');
                } else {
                    console.log('[DELIVERY-DATES] 警告マークは表示しません（条件不適合）');
                }
            }
        }
        
        // 進捗タブのバッジはここでは更新しない（ページ読み込み時に updateAllWarningMarks → updateWarningMark が複数回呼ばれ、AJAXで上書きされて消えるため）。
        // ユーザーが納期を変更したときは、.delivery-date-input の change ハンドラ内で updateProgressButtonWarning() を呼ぶ。
    }

    /**
     * 進捗ボタンの警告マークを更新する関数
     */
    function updateProgressButtonWarning() {
        console.log('[DELIVERY-DATES] 進捗ボタン警告マーク更新開始');
        
        // nonceの取得
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで受注の納期警告件数を取得
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_get_creating_warning_count',
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 警告件数取得応答:', response);
                
                if (response.success) {
                    var warningCount = response.data.warning_count || 0;
                    var invoiceCount = response.data.invoice_warning_count || 0;
                    var paymentCount = response.data.payment_warning_count || 0;
                    var warningDays = response.data.warning_days;
                    
                    // 受注タブ（progress=3）のバッジを更新
                    var $btn3 = $('.progress-btn').filter(function() { return $(this).data('progress') === 3; });
                    var $badge3 = $btn3.find('.ktp-progress-warning-badge[data-progress="3"]');
                    var title3 = warningCount > 0 ? ktpwpTranslate('納期が迫っている、または過ぎている案件が%d件あります').replace('%d', warningCount) : '';
                    if ($badge3.length) {
                        $badge3.attr('data-count', warningCount).attr('title', title3).text(warningCount > 0 ? String(warningCount) : '');
                    } else if (warningCount > 0) {
                        $btn3.find('.delivery-warning-mark').remove();
                        $btn3.append('<span class="ktp-progress-warning-badge" data-progress="3" data-count="' + warningCount + '" title="' + title3 + '">' + warningCount + '</span>');
                    }
                    
                    // 完了タブ（progress=4）のバッジを更新
                    var $btn4 = $('.progress-btn').filter(function() { return $(this).data('progress') === 4; });
                    var $badge4 = $btn4.find('.ktp-progress-warning-badge[data-progress="4"]');
                    var title4 = invoiceCount > 0 ? ktpwpTranslate('請求日を過ぎている案件が%d件あります').replace('%d', invoiceCount) : '';
                    if ($badge4.length) {
                        $badge4.attr('data-count', invoiceCount).attr('title', title4).text(invoiceCount > 0 ? String(invoiceCount) : '');
                    }

                    // 請求済タブ（progress=5）のバッジを更新
                    var $btn5 = $('.progress-btn').filter(function() { return $(this).data('progress') === 5; });
                    var $badge5 = $btn5.find('.ktp-progress-warning-badge[data-progress="5"]');
                    var title5 = paymentCount > 0 ? ktpwpTranslate('入金予定日を過ぎている案件が%d件あります').replace('%d', paymentCount) : '';
                    if ($badge5.length) {
                        $badge5.attr('data-count', paymentCount).attr('title', title5).text(paymentCount > 0 ? String(paymentCount) : '');
                    }
                } else {
                    var $b3 = $('.progress-btn').filter(function() { return $(this).data('progress') === 3; });
                    var $b4 = $('.progress-btn').filter(function() { return $(this).data('progress') === 4; });
                    var $b5 = $('.progress-btn').filter(function() { return $(this).data('progress') === 5; });
                    if ($b3.find('.ktp-progress-warning-badge[data-progress="3"]').length) $b3.find('.ktp-progress-warning-badge[data-progress="3"]').attr('data-count', '0').attr('title', '').text('');
                    if ($b4.find('.ktp-progress-warning-badge[data-progress="4"]').length) $b4.find('.ktp-progress-warning-badge[data-progress="4"]').attr('data-count', '0').attr('title', '').text('');
                    if ($b5.find('.ktp-progress-warning-badge[data-progress="5"]').length) $b5.find('.ktp-progress-warning-badge[data-progress="5"]').attr('data-count', '0').attr('title', '').text('');
                }
            },
            error: function(xhr, status, error) {
                var $b3 = $('.progress-btn').filter(function() { return $(this).data('progress') === 3; });
                var $b4 = $('.progress-btn').filter(function() { return $(this).data('progress') === 4; });
                var $b5 = $('.progress-btn').filter(function() { return $(this).data('progress') === 5; });
                if ($b3.find('.ktp-progress-warning-badge[data-progress="3"]').length) $b3.find('.ktp-progress-warning-badge[data-progress="3"]').attr('data-count', '0').attr('title', '').text('');
                if ($b4.find('.ktp-progress-warning-badge[data-progress="4"]').length) $b4.find('.ktp-progress-warning-badge[data-progress="4"]').attr('data-count', '0').attr('title', '').text('');
                if ($b5.find('.ktp-progress-warning-badge[data-progress="5"]').length) $b5.find('.ktp-progress-warning-badge[data-progress="5"]').attr('data-count', '0').attr('title', '').text('');
            }
        });
    }

    // ページ読み込み時に既存の警告マークを更新
    function updateAllWarningMarks() {
        console.log('[DELIVERY-DATES] 全警告マーク更新開始');
        
        var inputCount = $('.delivery-date-input').length;
        console.log('[DELIVERY-DATES] 納期入力フィールド数:', inputCount);
        
        $('.delivery-date-input').each(function(index) {
            var $input = $(this);
            var value = $input.val();
            console.log('[DELIVERY-DATES] フィールド', index + 1, ':', value);
            
            if (value) {
                updateWarningMark($input, value);
            }
        });
        
        // ページ読み込み時はPHPのバッジをそのまま使う（updateProgressButtonWarning は呼ばない）
        
        console.log('[DELIVERY-DATES] 全警告マーク更新完了');
    }

    // ページ読み込み完了後に警告マークを更新
    setTimeout(function() {
        console.log('[DELIVERY-DATES] ページ読み込み後の警告マーク更新を開始');
        updateAllWarningMarks();
        
        // 現在の進捗をold-progressとして保存（仕事リスト用）
        $('.progress-select option:selected').each(function() {
            var $option = $(this);
            var currentProgress = parseInt($option.val());
            $option.data('old-progress', currentProgress);
            console.log('[DELIVERY-DATES] 仕事リスト進捗初期化:', currentProgress);
        });
        
        // 受注書詳細での進捗初期化
        $('#order_progress_select option:selected').each(function() {
            var $option = $(this);
            var currentProgress = parseInt($option.val());
            $option.data('old-progress', currentProgress);
            console.log('[DELIVERY-DATES] 受注書詳細進捗初期化:', currentProgress);
        });
        
        // 受注書詳細での完了日フィールドの存在確認
        var $completionInput = $('#completion_date');
        console.log('[DELIVERY-DATES] 受注書詳細完了日フィールド確認:', {
            exists: $completionInput.length > 0,
            id: $completionInput.attr('id'),
            name: $completionInput.attr('name'),
            value: $completionInput.val(),
            orderId: $completionInput.data('order-id'),
            field: $completionInput.data('field')
        });
        
        // 進捗タブバッジはPHPで表示済みのため、ここでは更新しない（消えないようにする）
    }, 100);

    // 進捗プルダウンの変更を監視（仕事リスト用）
    $(document).on('change', '.progress-select', function() {
        console.log('[DELIVERY-DATES] 進捗プルダウンが変更されました（仕事リスト）');
        
        var $select = $(this);
        var $listItem = $select.closest('.ktp_work_list_item');
        var $deliveryInput = $listItem.find('.delivery-date-input');
        var $completionInput = $listItem.find('.completion-date-input');
        
        var newProgress = parseInt($select.val());
        var oldProgress = parseInt($select.find('option:selected').data('old-progress') || newProgress);
        
        console.log('[DELIVERY-DATES] 進捗変更:', {
            oldProgress: oldProgress,
            newProgress: newProgress,
            hasDeliveryInput: $deliveryInput.length > 0,
            hasCompletionInput: $completionInput.length > 0
        });
        
        // 進捗が「完了」（progress = 4）に変更された場合、完了日を自動設定
        if (newProgress === 4 && oldProgress !== 4 && $completionInput.length > 0) {
            console.log('[DELIVERY-DATES] 進捗が完了に変更されたため、完了日を自動設定します');
            var today = new Date();
            var todayStr = today.toISOString().split('T')[0]; // YYYY-MM-DD形式
            $completionInput.val(todayStr);
            
            // 完了日フィールドの変更をトリガーして保存
            $completionInput.trigger('change');
        }
        
        // 進捗が受注以前（受付中、見積中、受注）に変更された場合、完了日をクリア
        if ([1, 2, 3].includes(newProgress) && oldProgress > 3 && $completionInput.length > 0) {
            console.log('[DELIVERY-DATES] 進捗が受注以前に変更されたため、完了日をクリアします');
            $completionInput.val('');
            
            // 完了日フィールドの変更をトリガーして保存
            $completionInput.trigger('change');
        }
        
        // 現在の進捗をold-progressとして保存
        $select.find('option:selected').data('old-progress', newProgress);
        
        // 納期フィールドが存在する場合、行の警告マークを更新
        if ($deliveryInput.length > 0) {
            var deliveryDate = $deliveryInput.val();
            console.log('[DELIVERY-DATES] 納期フィールドあり、更新:', deliveryDate);
            updateWarningMark($deliveryInput, deliveryDate);
        }
        // 進捗変更時は常にタブのバッジをリアルタイム更新（受注・完了の件数が変わるため）
        updateProgressButtonWarning();
    });

    // 受注書詳細での進捗プルダウンの変更を監視
    $(document).on('change', '#order_progress_select', function() {
        console.log('[DELIVERY-DATES] 進捗プルダウンが変更されました（受注書詳細）');
        
        // handleProgressChange関数を呼び出し
        handleProgressChange(this);
    });

    // 納期フィールドのフォーカス時の処理
    $(document).on('focus', '.delivery-date-input', function() {
        $(this).css('border-color', '#1976d2');
    });

    // 納期フィールドのフォーカスアウト時の処理
    $(document).on('blur', '.delivery-date-input', function() {
        $(this).css('border-color', '#ddd');
    });

    // 完了日フィールドのフォーカス時の処理（仕事リスト用）
    $(document).on('focus', '.completion-date-input', function() {
        $(this).css('border-color', '#4caf50');
    });

    // 完了日フィールドのフォーカスアウト時の処理（仕事リスト用）
    $(document).on('blur', '.completion-date-input', function() {
        $(this).css('border-color', '#ddd');
    });

    // 受注書詳細での完了日フィールドのフォーカス時の処理
    $(document).on('focus', '#completion_date', function() {
        $(this).css('border-color', '#4caf50');
    });

    // 受注書詳細での完了日フィールドのフォーカスアウト時の処理
    $(document).on('blur', '#completion_date', function() {
        $(this).css('border-color', '#ddd');
    });
    
    // 受注書詳細の進捗フォームはAJAXで更新するため、通常送信を防止（送信されるとサーバーで未処理のまま再表示され進捗が戻る）
    $(document).on('submit', '.order-header-progress-form', function(e) {
        e.preventDefault();
        console.log('[DELIVERY-DATES] 進捗フォームの送信を防止（進捗はプルダウン変更時にAJAXで保存されます）');
        return false;
    });

    // 完了日フィールドの変更を監視して自動保存（仕事リスト用）
    $(document).on('change', '.completion-date-input', function() {
        var $input = $(this);
        if ($input.attr('id') === 'completion_date') {
            return;
        }
        var orderId = $input.data('order-id');
        var fieldName = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 完了日フィールド変更（仕事リスト）:', {
            orderId: orderId,
            fieldName: fieldName,
            value: value
        });
        
        // nonceの取得
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで完了日を保存
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_auto_save_field',
                order_id: orderId,
                field_name: fieldName,
                field_value: value,
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 完了日保存応答（仕事リスト）:', response);
                if (response.success) {
                    console.log('[DELIVERY-DATES] 完了日が正常に保存されました（仕事リスト）');
                    updateProgressButtonWarning();
                } else {
                    console.error('[DELIVERY-DATES] 完了日の保存に失敗しました（仕事リスト）:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[DELIVERY-DATES] 完了日保存で通信エラーが発生しました（仕事リスト）:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    });

    // 受注書詳細での完了日フィールドの変更を監視して自動保存
    $(document).on('change', '#completion_date', function() {
        var $input = $(this);
        var orderId = $input.data('order-id');
        var fieldName = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 完了日フィールド変更（受注書詳細）:', {
            orderId: orderId,
            fieldName: fieldName,
            value: value,
            inputId: $input.attr('id'),
            inputName: $input.attr('name')
        });
        
        // フォーム内の隠しフィールドも同期
        var $form = $input.closest('form');
        if ($form.length === 0) {
            // 完了日フィールドがフォーム外にある場合、近くのフォームを探す
            $form = $input.closest('.order_contents').find('form');
        }
        var $hiddenCompletionField = $form.find('input[name="completion_date"]');
        if ($hiddenCompletionField.length > 0) {
            $hiddenCompletionField.val(value);
            console.log('[DELIVERY-DATES] フォーム内の隠し完了日フィールドを同期しました:', value);
        }
        
        // nonceの取得
        const ajaxConfig = getAjaxConfig();
        const nonce = ajaxConfig.nonce;
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで完了日を保存
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: {
                action: 'ktp_auto_save_field',
                order_id: orderId,
                field_name: fieldName,
                field_value: value,
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 完了日保存応答（受注書詳細）:', response);
                if (response.success) {
                    console.log('[DELIVERY-DATES] 完了日が正常に保存されました（受注書詳細）');
                } else {
                    console.error('[DELIVERY-DATES] 完了日の保存に失敗しました（受注書詳細）:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[DELIVERY-DATES] 完了日保存で通信エラーが発生しました（受注書詳細）:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    });
}); 