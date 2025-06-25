console.log('=== KTP SUPPLIER SELECTOR: SCRIPT STARTED ===');
// 協力会社選択ポップアップの表示関数
(function($) {
    console.log('[SUPPLIER-SELECTOR] ファイル読み込み完了');
    console.log('[SUPPLIER-SELECTOR] jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
    console.log('[SUPPLIER-SELECTOR] ktp_ajax_object:', typeof ktp_ajax_object !== 'undefined' ? 'loaded' : 'not loaded');

    // 即座に関数を定義
window.ktpShowSupplierSelector = function(currentRow) {
        console.log('[SUPPLIER-SELECTOR] ===== ktpShowSupplierSelector関数開始 =====');
        console.log('[SUPPLIER-SELECTOR] 引数currentRow:', currentRow);
        console.log('[SUPPLIER-SELECTOR] 関数が呼び出されました');
        
        // グローバル変数としてcurrentRowを保持
        window.ktpCurrentRow = currentRow;
        console.log('[SUPPLIER-SELECTOR] window.ktpCurrentRowを設定:', window.ktpCurrentRow);
        
    // 既存のポップアップがあれば削除
    $("#ktp-supplier-selector-modal").remove();
        console.log('[SUPPLIER-SELECTOR] 既存のポップアップを削除しました');

    // 単価の表示形式を整形する関数
    function formatUnitPrice(price) {
        if (typeof price === 'undefined' || price === null) return '0';
        let numPrice = parseFloat(price);
        if (isNaN(numPrice)) return '0';
        let priceStr = String(numPrice);
        if (priceStr.match(/^[0-9]+\.$/)) {
            return priceStr.slice(0, -1);
        }
        return priceStr.replace(/\.0+$/, '').replace(/(\.[0-9]*[1-9])0+$/, '$1');
    }

        // HTMLエスケープ関数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ポップアップHTML
        const popupHtml = `
            <div id="ktp-supplier-selector-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                justify-content: center;
                align-items: center;
            ">
                <div style="
                    background: white;
                    border-radius: 8px;
                    padding: 15px;
                    width: 95%;
                    max-width: 1000px;
                    max-height: 85%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 15px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                    ">
                        <h3 style="margin: 0; color: #333;">協力会社選択</h3>
                        <button type="button" id="ktp-supplier-selector-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="ktp-supplier-selector-content" style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">協力会社一覧を読み込み中...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $("body").append(popupHtml);

        // ポップアップを閉じる関数
        function closeSupplierSelector() {
            $("#ktp-supplier-selector-modal").remove();
            $(document).off('keyup.supplier-selector');
        }

        // 閉じるボタンのイベント
        $("#ktp-supplier-selector-close").on("click.supplier-selector", function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeSupplierSelector();
        });

        // 背景クリックで閉じる
        $("#ktp-supplier-selector-modal").on("click.supplier-selector", function (e) {
            if (e.target === this) {
                closeSupplierSelector();
            }
        });

        // ESCキーで閉じる
        $(document).on('keyup.supplier-selector', function (e) {
            if (e.key === 'Escape') {
                closeSupplierSelector();
            }
        });

        // 協力会社リスト取得
        console.log('[SUPPLIER-SELECTOR] 協力会社リスト取得開始');
    $.ajax({
        url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
        type: 'POST',
        data: {
            action: 'ktpwp_get_suppliers_for_cost'
        },
        dataType: 'json',
        success: function(suppliers) {
                console.log('[SUPPLIER-SELECTOR] 協力会社リスト取得成功:', suppliers);
                console.log('[SUPPLIER-SELECTOR] 協力会社数:', suppliers ? suppliers.length : 0);
                
                // 協力会社選択UI生成（サービス選択と同じスタイル）
                let supplierOptions = '<option value="">協力会社を選択してください</option>';
                suppliers.forEach(function(s) {
                    supplierOptions += '<option value="' + s.id + '">' + escapeHtml(s.company_name) + '</option>';
                });
                
                const supplierSelectorHtml = `
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">協力会社:</label>
                        <select id="ktp-supplier-list" style="
                            width: 100%;
                            padding: 10px 12px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 14px;
                            background: white;
                            box-sizing: border-box;
                        ">
                            ${supplierOptions}
                        </select>
                    </div>
                    <div id="ktp-skill-list-area">
                        <div style="text-align: center; padding: 40px; color: #666;">
                            協力会社を選択すると職能リストが表示されます
                        </div>
                    </div>
                `;
                
                $('#ktp-supplier-selector-content').html(supplierSelectorHtml);
                console.log('[SUPPLIER-SELECTOR] 協力会社選択UIを生成しました');

            // 2. 協力会社選択時に職能リスト取得
            $("#ktp-supplier-list").on('change', function() {
                    console.log('[SUPPLIER-SELECTOR] 協力会社選択イベント発火');
                const supplierId = $(this).val();
                    console.log('[SUPPLIER-SELECTOR] 選択された協力会社ID:', supplierId);
                    
                    if (!supplierId) {
                        console.log('[SUPPLIER-SELECTOR] 協力会社が選択されていません');
                        $('#ktp-skill-list-area').html(`
                            <div style="text-align: center; padding: 40px; color: #666;">
                                協力会社を選択すると職能リストが表示されます
                            </div>
                        `);
                        return;
                    }
                    
                    console.log('[SUPPLIER-SELECTOR] 職能リスト取得開始');
                    
                    // ローディング表示
                    $('#ktp-skill-list-area').html(`
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">職能リストを読み込み中...</div>
                        </div>
                    `);
                    
                $.ajax({
                    url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
                    type: 'POST',
                    data: {
                        action: 'ktpwp_get_supplier_skills_for_cost',
                        supplier_id: supplierId
                    },
                    dataType: 'json',
                    success: function(skills) {
                        console.log('skills ajax response:', skills);
                            console.log('[SUPPLIER-SELECTOR] 職能リスト取得成功:', skills);
                            console.log('[SUPPLIER-SELECTOR] 職能数:', skills ? skills.length : 0);
                            renderSkillList(skills, supplierId);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX ERROR', xhr.status, xhr.responseText);
                            console.error('[SUPPLIER-SELECTOR] 職能リスト取得失敗:', error);
                            $('#ktp-skill-list-area').html(`
                                <div style="text-align: center; padding: 40px; color: #dc3545;">
                                    <div style="font-size: 16px;">職能リストの取得に失敗しました</div>
                                    <div style="font-size: 14px; margin-top: 8px;">通信エラーが発生しました</div>
                                </div>
                            `);
                    }
                });
                });

                // 職能リストのレンダリング関数
                function renderSkillList(skills, supplierId) {
                    let html = '';
                    supplierId = supplierId || 0;
                    if (Array.isArray(skills) && skills.length > 0) {
                        html = `<div style="display: flex; flex-direction: column; width: 100%;">`;
                        html += `
                            <div class="ktp_data_list_box" style="
                                border: 1px solid #e5e7eb; 
                                border-radius: 4px; 
                                overflow: hidden; 
                                margin-bottom: 0;
                                width: 100%;
                                box-sizing: border-box;
                            ">
                        `;

                        skills.forEach(function (skill, index) {
                            const skillId = skill.id;
                            const productName = skill.product_name || '';
                            const unitPrice = formatUnitPrice(skill.unit_price);
                            const quantity = skill.quantity || '';
                            const unit = skill.unit || '';
                            const frequency = skill.frequency || 0;
                            const backgroundColor = index % 2 === 0 ? '#f9fafb' : '#ffffff';
                            const isSmallScreen = window.innerWidth < 600;

                            // スキルデータを安全にJSON化（supplier_idも含める）
                            let skillData;
                            try {
                                skillData = JSON.stringify({
                                    id: skillId,
                                    product_name: productName,
                                    unit_price: unitPrice,
                                    quantity: quantity,
                                    unit: unit,
                                    frequency: frequency,
                                    supplier_id: supplierId
                                });
                            } catch (e) {
                                console.error('[SUPPLIER-SELECTOR] JSON化エラー:', e);
                                skillData = '{}';
                            }

                            console.log('[SUPPLIER-SELECTOR] スキルデータ:', skillData);

                            html += `
                                <div class="ktp_data_list_item" style="
                                    line-height: 1.5;
                                    border-bottom: 1px solid #e5e7eb;
                                    margin: 0;
                                    padding: 16px;
                                    background-color: ${backgroundColor};
                                    transition: background-color 0.2s ease, transform 0.1s ease;
                                    position: relative;
                                    font-size: 14px;
                                    display: flex;
                                    width: 100%;
                                    box-sizing: border-box;
                                    ${isSmallScreen ? 'flex-direction: column;' : 'justify-content: space-between;'}
                                    ${isSmallScreen ? 'align-items: stretch;' : 'align-items: center;'}
                                    gap: 15px;
                                    flex-wrap: nowrap;
                                ">
                                    <div style="
                                        flex: 1; 
                                        min-width: 0;
                                        width: 100%;
                                        ${isSmallScreen ? 'margin-bottom: 10px;' : ''}
                                    ">
                                        <div style="display: flex; align-items: center; gap: ${isSmallScreen ? '8px' : '15px'}; flex-wrap: wrap; line-height: 1.4;">
                                            <strong style="font-size: ${isSmallScreen ? '14px' : '15px'}; color: #1f2937; word-break: break-word; flex-shrink: 0;">
                                                ID: ${skillId} - ${escapeHtml(productName)}
                                            </strong>
                                            <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>単価:</strong> ${unitPrice}円</span>
                                            <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>数量:</strong> ${escapeHtml(quantity)}</span>
                                            <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>単位:</strong> ${escapeHtml(unit)}</span>
                                            <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;" title="アクセス頻度（クリックされた回数）"><strong>頻度:</strong> ${frequency}</span>
                                        </div>
                                    </div>
                                    <div style="
                                        display: flex; 
                                        gap: 8px; 
                                        flex-shrink: 0;
                                        ${isSmallScreen ? 'width: 100%; justify-content: center;' : 'min-width: 160px; justify-content: flex-end;'}
                                    ">
                                        <button type="button" 
                                                class="ktp-skill-add-btn" 
                                                data-skill='${skillData}'
                                                style="
                                                    background: #28a745; 
                                                    color: white; 
                                                    border: none; 
                                                    padding: 8px ${isSmallScreen ? '20px' : '16px'}; 
                                                    border-radius: 4px; 
                                                    cursor: pointer; 
                                                    font-size: 12px;
                                                    font-weight: 500;
                                                    transition: background-color 0.2s ease;
                                                    white-space: nowrap;
                                                    ${isSmallScreen ? 'flex: 1;' : ''}
                                                "
                                                onmouseover="this.style.backgroundColor='#218838'"
                                                onmouseout="this.style.backgroundColor='#28a745'">
                                            追加
                                        </button>
                                        <button type="button" 
                                                class="ktp-skill-update-btn" 
                                                data-skill='${skillData}'
                                                style="
                                                    background: #007bff; 
                                                    color: white; 
                                                    border: none; 
                                                    padding: 8px ${isSmallScreen ? '20px' : '16px'}; 
                                                    border-radius: 4px; 
                                                    cursor: pointer; 
                                                    font-size: 12px;
                                                    font-weight: 500;
                                                    transition: background-color 0.2s ease;
                                                    white-space: nowrap;
                                                    ${isSmallScreen ? 'flex: 1;' : ''}
                                                "
                                                onmouseover="this.style.backgroundColor='#0056b3'"
                                                onmouseout="this.style.backgroundColor='#007bff'">
                                            更新
                                        </button>
                                    </div>
                                </div>
                            `;
                        });

                        html += `</div></div>`;
                    } else {
                        html = `
                            <div class="ktp_data_list_box" style="
                                border: 1px solid #e5e7eb; 
                                border-radius: 4px; 
                                overflow: hidden; 
                                margin-bottom: 0;
                                width: 100%;
                                box-sizing: border-box;
                            ">
                                <div style="text-align: center; padding: 50px 40px; color: #6b7280; background: #f9fafb;">
                                    <div style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">登録されている職能がありません</div>
                                    <div style="font-size: 14px;">協力会社の職能を先に登録してください</div>
                                </div>
                            </div>
                        `;
                    }
                    
                    $('#ktp-skill-list-area').html(html);
                    
                    console.log('[SUPPLIER-SELECTOR] 生成されたHTML:', html);
                    console.log('[SUPPLIER-SELECTOR] 更新ボタンの数:', $('.ktp-skill-update-btn').length);
                    console.log('[SUPPLIER-SELECTOR] 追加ボタンの数:', $('.ktp-skill-add-btn').length);
                    
                    // ボタンの存在確認
                    $('.ktp-skill-update-btn').each(function(index) {
                        console.log(`[SUPPLIER-SELECTOR] 更新ボタン${index + 1}:`, this);
                        console.log(`[SUPPLIER-SELECTOR] 更新ボタン${index + 1}のdata-skill:`, $(this).attr('data-skill'));
                    });
                    
                    // イベントハンドラーを遅延実行で設定
                    setTimeout(function() {
                        console.log('[SUPPLIER-SELECTOR] 遅延実行でイベントハンドラーを設定中...');
                        console.log('[SUPPLIER-SELECTOR] 更新ボタンの数:', $('.ktp-skill-update-btn').length);
                        
                        // 既存のイベントハンドラーを削除
                        jQuery(document).off('click', '.ktp-skill-update-btn');
                        
                        // 新しいイベントハンドラーを設定（委譲）
                        jQuery(document).on('click', '.ktp-skill-update-btn', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            // data-skill属性から値を取得
                let skill = $(this).data('skill');
                            if (typeof skill === 'string') {
                                try {
                                    skill = JSON.parse(skill);
                                } catch (err) {
                                    console.error('[SUPPLIER-SELECTOR] data-skill JSON parse error', err, $(this).attr('data-skill'));
                                    alert('データ取得エラー');
                                    return;
                                }
                            }
                            if (!skill || !skill.product_name) {
                                alert('スキルデータが取得できません');
                                return;
                            }
                            // 1. ポップアップで選択した値をcurrentRowに反映（UI更新）
                            if (currentRow && currentRow.length > 0 && skill) {
                if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
                                currentRow.find('.product-name').val(skill.product_name);
                                currentRow.find('.price').val(skill.unit_price);
                                currentRow.find('.quantity').val(skill.quantity || 1);
                                currentRow.find('.unit').val(skill.unit);
                                // amountも再計算
                                let amount = parseFloat(skill.unit_price) * parseFloat(skill.quantity || 1);
                                currentRow.find('.amount').val(amount);
                                
                                // UI更新後にwindow.ktpUpdateCostRowFromSkillも呼び出し
                                if (typeof window.ktpUpdateCostRowFromSkill === 'function') {
                                    console.log('[SUPPLIER-SELECTOR][DEBUG] ktpUpdateCostRowFromSkillを呼び出し');
                                    window.ktpUpdateCostRowFromSkill(skill, currentRow);
                                }
                            }
                            
                            // 2. skillの値を直接itemDataに設定（UI更新に依存しない）
                            var orderId = $('input[name="order_id"]').val() || 0;
                            var id = currentRow && currentRow.find('input[name*="[id]"]').val() ? currentRow.find('input[name*="[id]"]').val() : (skill.id || '0');
                            
                            const itemData = {
                                id: id,
                                order_id: orderId,
                                supplier_id: skill.supplier_id || 0,
                                product_name: skill.product_name || '',
                                unit_price: skill.unit_price || 0,
                                quantity: skill.quantity || 1,
                                unit: skill.unit || '',
                                amount: parseFloat(skill.unit_price || 0) * parseFloat(skill.quantity || 1)
                            };
                            
                            console.log('[SUPPLIER-SELECTOR][DEBUG] 送信データ（skillベース）:', itemData);
                            console.log('[SUPPLIER-SELECTOR][DEBUG] skillの値:', skill);
                            console.log('[SUPPLIER-SELECTOR][DEBUG] currentRowのID:', currentRow && currentRow.find('input[name*="[id]"]').val());
                            if (!itemData.product_name || itemData.product_name.trim() === '') {
                                alert('商品名が空です。商品名を入力してください。');
                                return;
                            }
                            const ajaxData = {
                    action: 'ktpwp_save_order_cost_item',
                    force_save: true,
                                order_id: itemData.order_id,
                                items: JSON.stringify([itemData])
                            };
                            console.log('[SUPPLIER-SELECTOR][DEBUG] Ajax送信データ:', ajaxData);
                            $.post(ktp_ajax_object.ajax_url, ajaxData, function(response) {
                                console.log('[SUPPLIER-SELECTOR][DEBUG] Ajaxレスポンス:', response);
                                console.log('[SUPPLIER-SELECTOR][DEBUG] レスポンス詳細:', {
                                    success: response.success,
                                    data: response.data,
                                    message: response.data ? response.data.message : 'no message'
                                });
                                if (response && response.success && response.data && response.data.id) {
                                    console.log('[SUPPLIER-SELECTOR] DB保存成功: id=', response.data.id);
                                    // ポップアップを閉じる
                                    closeSupplierSelector();
                                } else {
                                    console.error('[SUPPLIER-SELECTOR][DEBUG] DB保存失敗', response);
                                    alert('DB保存に失敗しました。サーバーレスポンス: ' + JSON.stringify(response));
                                }
                            }).fail(function(xhr, status, error) {
                                console.error('[SUPPLIER-SELECTOR][DEBUG] Ajax通信エラー', status, error, xhr.responseText);
                                alert('Ajax通信エラー: ' + error);
                            });
                        });
                        
                        // イベントハンドラーの設定確認
                        setTimeout(function() {
                            console.log('[SUPPLIER-SELECTOR] イベントハンドラー設定確認');
                            $('.ktp-skill-update-btn').each(function(index) {
                                console.log(`[SUPPLIER-SELECTOR] 更新ボタン${index + 1}:`, this);
                                console.log(`[SUPPLIER-SELECTOR] 更新ボタン${index + 1}のイベント数:`, $._data(this, 'events') ? Object.keys($._data(this, 'events')).length : 0);
                            });
                        }, 100);
                        
                        console.log('[SUPPLIER-SELECTOR] 遅延実行でイベントハンドラー設定完了');
                    }, 200); // 200ms遅延
                }
        },
        error: function(xhr, status, error) {
            console.error('AJAX ERROR', xhr.status, xhr.responseText);
                $('#ktp-supplier-selector-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px;">協力会社リストの取得に失敗しました</div>
                        <div style="font-size: 14px; margin-top: 8px;">通信エラーが発生しました</div>
                    </div>
                `);
        }
    });
};

// コスト項目に新規追加
window.ktpAddCostRowFromSkill = function(skill, currentRow) {
    if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
    let table = $('.cost-items-table');
    let $tbody = table.find('tbody');
    let $baseRow = null;
    if (currentRow && currentRow.length > 0) {
        $baseRow = currentRow;
    } else {
        $baseRow = $tbody.find('tr').last();
    }
    let $newRow = $baseRow.length > 0 ? $baseRow.clone() : $('<tr></tr>');
    $newRow.find('.product-name').val(skill.product_name);
    $newRow.find('.price').val(skill.unit_price);
    $newRow.find('.quantity').val(skill.quantity || 1);
    $newRow.find('.unit').val(skill.unit);
    $newRow.find('.amount').val(skill.unit_price);
    $newRow.find('input[name*="[id]"]').val(skill.id || '');
    if ($baseRow.length > 0) {
        $baseRow.after($newRow);
    } else {
        $tbody.prepend($newRow);
    }
    if (typeof calculateAmount === 'function') calculateAmount($newRow);
};

// コスト項目を更新
window.ktpUpdateCostRowFromSkill = function(skill, currentRow) {
        console.log('[SUPPLIER-SELECTOR] ===== ktpUpdateCostRowFromSkill関数開始 =====');
        
    if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
    let table = $('.cost-items-table');
        
        if (currentRow && currentRow.length > 0) {
            console.log('[SUPPLIER-SELECTOR] 既存行を更新');
            
            currentRow.find('.product-name').val(skill.product_name);
            currentRow.find('.price').val(skill.unit_price);
            currentRow.find('.quantity').val(skill.quantity || 1);
            currentRow.find('.unit').val(skill.unit);
            currentRow.find('.amount').val(skill.unit_price);
            currentRow.find('input[name*="[id]"]').val(skill.id || '');
            
            if (typeof calculateAmount === 'function') {
                calculateAmount(currentRow);
            }
        } else {
            console.log('[SUPPLIER-SELECTOR] 現在の行が指定されていないため新規追加として処理');
            if (table.length === 0) {
                console.error('[SUPPLIER-SELECTOR] コスト項目テーブルが見つかりません');
                return;
            }
        let $tbody = table.find('tbody');
        let $newRow = $tbody.find('tr').last().clone();
        $newRow.find('.product-name').val(skill.product_name);
        $newRow.find('.price').val(skill.unit_price);
        $newRow.find('.quantity').val(skill.quantity || 1);
        $newRow.find('.unit').val(skill.unit);
        $newRow.find('.amount').val(skill.unit_price);
        $newRow.find('input[name*="[id]"]').val(skill.id || '');
        $tbody.append($newRow);
            
            if (typeof calculateAmount === 'function') {
                calculateAmount($newRow);
            }
        }
        console.log('[SUPPLIER-SELECTOR] ===== ktpUpdateCostRowFromSkill関数終了 =====');
};

    // ページロード時の処理
$(function() {
        console.log('[SUPPLIER-SELECTOR] ページ読み込み完了');
        console.log('[SUPPLIER-SELECTOR] ktpShowSupplierSelector関数の存在確認:', typeof window.ktpShowSupplierSelector);
        
    let $tbody = $('.cost-items-table').find('tbody');
    let $rows = $tbody.find('tr').get();
    $rows.sort(function(a, b) {
        let idA = parseInt($(a).find('input[name*="[id]"]').val(), 10) || 0;
        let idB = parseInt($(b).find('input[name*="[id]"]').val(), 10) || 0;
        return idA - idB;
    });
    $.each($rows, function(idx, row) {
        $tbody.append(row);
    });
});

})(jQuery); 