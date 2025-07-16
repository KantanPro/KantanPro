/**
 * 納期フィールドのAjax保存機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

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
    } else if (newProgress < 4 && $completionInput.length > 0) {
        // 進捗が完了以前に戻された場合、完了日をクリア
        console.log('[DELIVERY-DATES] 進捗が完了以前に戻されたため、完了日をクリアします');
        $completionInput.val('');
        
        // フォーム内の隠し完了日フィールドもクリア
        var $hiddenCompletionField = $form.find('input[name="completion_date"]');
        if ($hiddenCompletionField.length > 0) {
            $hiddenCompletionField.val('');
            console.log('[DELIVERY-DATES] フォーム内の隠し完了日フィールドもクリアしました');
        }
    }
    
    // nonceの取得
    var nonce = null;
    if (typeof ktp_ajax !== 'undefined' && ktp_ajax.progress_nonce) {
        nonce = ktp_ajax.progress_nonce;
    } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
        nonce = ktp_ajax_object.nonce;
    } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonce) {
        nonce = ktpwp_ajax.nonce;
    } else if (typeof ktp_ajax_nonce !== 'undefined') {
        nonce = ktp_ajax_nonce;
    } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
        nonce = ktpwp_ajax_nonce;
    }
    
    if (!nonce) {
        console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
        alert('セキュリティトークンが取得できません。ページを再読み込みしてください。');
        return;
    }
    
    // Ajaxで進捗更新
    console.log('[DELIVERY-DATES] Ajaxで進捗更新を実行します');
    jQuery.ajax({
        url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
              typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
              typeof ajaxurl !== 'undefined' ? ajaxurl : 
              '/wp-admin/admin-ajax.php'),
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
            alert('進捗更新の通信でエラーが発生しました');
        }
    });
};

jQuery(document).ready(function($) {
    'use strict';

    console.log('[DELIVERY-DATES] 納期警告機能が読み込まれました');
    
    // Ajax設定の確認
    if (typeof ktp_ajax !== 'undefined') {
        console.log('[DELIVERY-DATES] Ajax設定確認:', {
            ajax_url: ktp_ajax.ajax_url,
            nonce: ktp_ajax.nonce ? '設定済み' : '未設定',
            settings: ktp_ajax.settings || '未設定'
        });
    } else {
        console.log('[DELIVERY-DATES] 警告: ktp_ajaxオブジェクトが見つかりません');
    }
    
    // 即座に進捗ボタンの警告マークを更新（DOMContentLoaded後）
    console.log('[DELIVERY-DATES] DOMContentLoaded後の進捗ボタン警告マーク更新');
    setTimeout(function() {
        updateProgressButtonWarning();
    }, 50);

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
            alert('フィールド名が設定されていません。ページを再読み込みしてください。');
            return;
        }
        
        // 保存中の表示
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        
        // nonceの取得（複数の変数から取得を試行）
        var nonce = null;
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            nonce = ktp_ajax_object.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        }
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            alert('セキュリティトークンが取得できません。ページを再読み込みしてください。');
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }
        
        // Ajaxでデータを保存
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                  typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
                  typeof ajaxurl !== 'undefined' ? ajaxurl : 
                  '/wp-admin/admin-ajax.php'),
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
                    
                    // 警告マークを更新
                    updateWarningMark($input, value);
                    
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
                    alert('保存に失敗しました: ' + errorMessage);
                }
            },
            error: function() {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert('通信エラーが発生しました');
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
            alert('フィールド名が設定されていません。ページを再読み込みしてください。');
            return;
        }
        
        // 保存中の表示
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        
        // nonceの取得（複数の変数から取得を試行）
        var nonce = null;
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            nonce = ktp_ajax_object.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        }
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            alert('セキュリティトークンが取得できません。ページを再読み込みしてください。');
            $input.prop('disabled', false);
            $input.css('opacity', '1');
            return;
        }
        
        // Ajaxでデータを保存
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                  typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
                  typeof ajaxurl !== 'undefined' ? ajaxurl : 
                  '/wp-admin/admin-ajax.php'),
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
                    alert('保存に失敗しました: ' + errorMessage);
                }
            },
            error: function() {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert('通信エラーが発生しました');
            },
            complete: function() {
                // 処理完了時の処理
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
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
                    shouldWarn: diffDays <= warningDays && diffDays >= 0
                });
                
                // 警告日数以内で納期が迫っている場合、警告マークを表示
                if (diffDays <= warningDays && diffDays >= 0) {
                    $wrapper.append('<span class="delivery-warning-mark-row" title="納期が迫っています">!</span>');
                    console.log('[DELIVERY-DATES] 警告マークを表示しました');
                } else {
                    console.log('[DELIVERY-DATES] 警告マークは表示しません（条件不適合）');
                }
            }
        }
        
        // 納期フィールドの変更時に進捗ボタンの警告マークを即時更新
        console.log('[DELIVERY-DATES] 納期変更により進捗ボタンの警告マークを更新');
        updateProgressButtonWarning();
    }

    /**
     * 進捗ボタンの警告マークを更新する関数
     */
    function updateProgressButtonWarning() {
        console.log('[DELIVERY-DATES] 進捗ボタン警告マーク更新開始');
        
        // nonceの取得（複数の変数から取得を試行）
        var nonce = null;
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            nonce = ktp_ajax_object.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        }
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで受注の納期警告件数を取得
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                  typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
                  typeof ajaxurl !== 'undefined' ? ajaxurl : 
                  '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'ktp_get_creating_warning_count',
                nonce: nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 受注警告件数取得応答:', response);
                
                if (response.success) {
                    var warningCount = response.data.warning_count;
                    var warningDays = response.data.warning_days;
                    
                    console.log('[DELIVERY-DATES] 受注の納期警告件数:', warningCount, '件（警告日数:', warningDays, '日）');
                    
                    // 進捗ボタンの警告マークをチェック
                    var $progressButton = $('.progress-btn').filter(function() {
                        return $(this).data('progress') === 3; // 受注（progress = 3）
                    });
                    
                    console.log('[DELIVERY-DATES] 受注ボタン数:', $progressButton.length);
                    
                    var $existingButtonWarning = $progressButton.find('.delivery-warning-mark');
                    
                    if (warningCount > 0) {
                        // 警告マークがある場合、ボタンにも警告マークを表示
                        if ($existingButtonWarning.length === 0) {
                            $progressButton.append('<span class="delivery-warning-mark" title="納期が迫っている案件があります（' + warningCount + '件）">!</span>');
                            console.log('[DELIVERY-DATES] 進捗ボタンに警告マークを追加しました（' + warningCount + '件）');
                        } else {
                            // 既存の警告マークの件数を更新
                            $existingButtonWarning.attr('title', '納期が迫っている案件があります（' + warningCount + '件）');
                            console.log('[DELIVERY-DATES] 進捗ボタンの警告マークを更新しました（' + warningCount + '件）');
                        }
                    } else {
                        // 警告マークがない場合、ボタンの警告マークを削除
                        $existingButtonWarning.remove();
                        console.log('[DELIVERY-DATES] 進捗ボタンの警告マークを削除しました');
                    }
                } else {
                    console.log('[DELIVERY-DATES] 警告件数取得に失敗:', response.data);
                    // エラー時は既存の警告マークを削除
                    var $progressButton = $('.progress-btn').filter(function() {
                        return $(this).data('progress') === 3;
                    });
                    $progressButton.find('.delivery-warning-mark').remove();
                }
            },
            error: function(xhr, status, error) {
                console.log('[DELIVERY-DATES] 警告件数取得で通信エラーが発生しました:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                // エラー時は既存の警告マークを削除
                var $progressButton = $('.progress-btn').filter(function() {
                    return $(this).data('progress') === 3;
                });
                $progressButton.find('.delivery-warning-mark').remove();
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
        
        // 進捗ボタンの警告マークも更新（常に作成中の警告件数を取得）
        updateProgressButtonWarning();
        
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
        
        // 初期化完了後に進捗ボタンの警告マークを即時更新
        console.log('[DELIVERY-DATES] 初期化完了後の進捗ボタン警告マーク更新');
        updateProgressButtonWarning();
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
        
        // 納期フィールドが存在する場合、警告マークを更新
        if ($deliveryInput.length > 0) {
            var deliveryDate = $deliveryInput.val();
            console.log('[DELIVERY-DATES] 納期フィールドあり、更新:', deliveryDate);
            updateWarningMark($deliveryInput, deliveryDate);
        } else {
            // 納期フィールドがない場合でも、進捗変更時は進捗ボタンの警告マークを更新
            console.log('[DELIVERY-DATES] 進捗変更後のボタン警告マーク更新');
            updateProgressButtonWarning();
        }
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
    
    // 受注書詳細でのフォーム送信前に完了日フィールドを同期
    $(document).on('submit', '.order-header-progress-form', function() {
        console.log('[DELIVERY-DATES] 受注書詳細フォーム送信前の処理');
        
        var $form = $(this);
        var $completionInput = $('#completion_date');
        var $hiddenCompletionField = $form.find('input[name="completion_date"]');
        
        if ($completionInput.length > 0 && $hiddenCompletionField.length > 0) {
            var completionValue = $completionInput.val();
            $hiddenCompletionField.val(completionValue);
            console.log('[DELIVERY-DATES] フォーム送信前に完了日フィールドを同期しました:', completionValue);
        }
    });

    // 完了日フィールドの変更を監視して自動保存（仕事リスト用）
    $(document).on('change', '.completion-date-input', function() {
        var $input = $(this);
        var orderId = $input.data('order-id');
        var fieldName = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 完了日フィールド変更（仕事リスト）:', {
            orderId: orderId,
            fieldName: fieldName,
            value: value
        });
        
        // nonceの取得
        var nonce = null;
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            nonce = ktp_ajax_object.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        }
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで完了日を保存
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                  typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
                  typeof ajaxurl !== 'undefined' ? ajaxurl : 
                  '/wp-admin/admin-ajax.php'),
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
        var nonce = null;
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            nonce = ktp_ajax_object.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        }
        
        if (!nonce) {
            console.error('[DELIVERY-DATES] エラー: nonceが取得できません');
            return;
        }
        
        // Ajaxで完了日を保存
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                  typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax.ajax_url : 
                  typeof ajaxurl !== 'undefined' ? ajaxurl : 
                  '/wp-admin/admin-ajax.php'),
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