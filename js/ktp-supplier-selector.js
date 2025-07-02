console.log('=== KTP SUPPLIER SELECTOR: SCRIPT STARTED ===');
// 協力会社選択ポップアップの表示関数
(function($) {
    console.log('[SUPPLIER-SELECTOR] ファイル読み込み完了');
    console.log('[SUPPLIER-SELECTOR] jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
    console.log('[SUPPLIER-SELECTOR] ktp_ajax_object:', typeof ktp_ajax_object !== 'undefined' ? 'loaded' : 'not loaded');

    // 即座に関数を定義してグローバルに露出
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
        
        // 職能リストのレンダリング関数（AJAXの外に移動）
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
                $(document).off('click', '.ktp-skill-update-btn');
                $(document).off('click', '.ktp-skill-add-btn');
                
                // 「更新」ボタンのイベントハンドラー
                $(document).on('click', '.ktp-skill-update-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('[SUPPLIER-SELECTOR] 更新ボタンクリック');
                    
                    const skillData = $(this).attr('data-skill');
                    const currentRow = window.ktpCurrentRow;
                    
                    if (skillData && currentRow) {
                        console.log('[SUPPLIER-SELECTOR] 更新処理開始', {
                            skillData: skillData,
                            currentRowExists: currentRow.length > 0
                        });
                        
                        // 更新処理を実行
                        if (typeof window.ktpUpdateCostRowFromSkill === 'function') {
                            window.ktpUpdateCostRowFromSkill(skillData, currentRow);
                        } else {
                            console.error('[SUPPLIER-SELECTOR] ktpUpdateCostRowFromSkill関数が見つかりません');
                        }
                        
                        // ポップアップを閉じる
                        closeSupplierSelector();
                        
                        console.log('[SUPPLIER-SELECTOR] 更新処理完了');
                    } else {
                        console.error('[SUPPLIER-SELECTOR] 更新に必要なデータが不足しています', {
                            skillData: skillData,
                            currentRowExists: currentRow && currentRow.length > 0
                        });
                    }
                });
                
                // 「追加」ボタンのイベントハンドラー
                $(document).on('click', '.ktp-skill-add-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('[SUPPLIER-SELECTOR] 追加ボタンクリック');
                    
                    const skillData = $(this).attr('data-skill');
                    
                    if (skillData) {
                        console.log('[SUPPLIER-SELECTOR] 追加処理開始', {
                            skillData: skillData
                        });
                        
                        // 追加処理を実行
                        if (typeof window.ktpAddCostRowFromSkill === 'function') {
                            window.ktpAddCostRowFromSkill(skillData, null);
                        } else {
                            console.error('[SUPPLIER-SELECTOR] ktpAddCostRowFromSkill関数が見つかりません');
                        }
                        
                        // 追加の場合はポップアップは閉じない（ユーザーが手動で閉じるまで待つ）
                        console.log('[SUPPLIER-SELECTOR] 追加処理完了 - ポップアップは開いたまま');
                    } else {
                        console.error('[SUPPLIER-SELECTOR] 追加に必要なデータが不足しています', {
                            skillData: skillData
                        });
                    }
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
                    
                    // グローバル変数に協力会社IDと協力会社名を保存
                    window.ktpCurrentSupplierId = supplierId;
                    
                    // 選択された協力会社の名前を取得
                    const selectedOption = $(this).find('option:selected');
                    const supplierName = selectedOption.text();
                    window.ktpCurrentSupplierName = supplierName;
                    
                    console.log('[SUPPLIER-SELECTOR] 協力会社情報を設定:', {
                        supplierId: window.ktpCurrentSupplierId,
                        supplierName: window.ktpCurrentSupplierName
                    });
                    
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
    console.log('[SUPPLIER-SELECTOR] ===== ktpAddCostRowFromSkill関数開始 =====');
    
    if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
    
    console.log('[SUPPLIER-SELECTOR] 新規追加処理開始', {
        skill: skill,
        currentRowExists: currentRow && currentRow.length > 0
    });
    
    let table = $('.cost-items-table');
    let $tbody = table.find('tbody');
    
    if (table.length === 0) {
        console.error('[SUPPLIER-SELECTOR] コスト項目テーブルが見つかりません');
        return;
    }
    
    // 新規行を作成
    const $lastRow = $tbody.find('tr').last();
    const newIndex = $tbody.find('tr').length;
    
    console.log('[SUPPLIER-SELECTOR] 新規行作成開始', {
        lastRowExists: $lastRow.length > 0,
        currentRowCount: $tbody.find('tr').length,
        newIndex: newIndex
    });
    
    // 新規行のHTMLを生成
    const purchaseDisplayText = window.ktpCurrentSupplierName && skill.product_name ? 
        `${window.ktpCurrentSupplierName} > ${skill.product_name}` : 
        (window.ktpCurrentSupplierName || '(^^)');
    
    const newRowHtml = `
        <tr class="cost-item-row" data-row-id="0" data-newly-added="true">
            <td class="actions-column">
                <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="協力会社選択">></button>
            </td>
            <td>
                <input type="text" name="cost_items[${newIndex}][product_name]" class="cost-item-input product-name" value="${skill.product_name || ''}">
                <input type="hidden" name="cost_items[${newIndex}][id]" value="0">
            </td>
            <td style="text-align:left;">
                <input type="number" name="cost_items[${newIndex}][price]" class="cost-item-input price" value="${skill.unit_price || ''}" step="0.01" min="0" style="text-align:left;">
            </td>
            <td style="text-align:left;">
                <input type="number" name="cost_items[${newIndex}][quantity]" class="cost-item-input quantity" value="${skill.quantity || 1}" step="0.01" min="0" style="text-align:left;">
            </td>
            <td>
                <input type="text" name="cost_items[${newIndex}][unit]" class="cost-item-input unit" value="${skill.unit || ''}">
            </td>
            <td style="text-align:left;">
                <input type="number" name="cost_items[${newIndex}][amount]" class="cost-item-input amount" value="" step="0.01" min="0" style="text-align:left;" readonly>
            </td>
            <td>
                <input type="text" name="cost_items[${newIndex}][remarks]" class="cost-item-input remarks" value="">
                <input type="hidden" name="cost_items[${newIndex}][sort_order]" value="${newIndex + 1}">
            </td>
            <td>
                <span class="purchase-display">手入力</span>
                <input type="hidden" name="cost_items[${newIndex}][purchase]" value="">
            </td>
        </tr>
    `;
    
    // 新規行を最後の行の後に追加
    if ($lastRow.length > 0) {
        $lastRow.after(newRowHtml);
    } else {
        // テーブルが空の場合はtbodyに直接追加
        $tbody.append(newRowHtml);
    }
    const $newRow = $tbody.find('tr').last();
    
    // 行のインデックスを更新（updateRowIndexes関数がある場合）
    if (typeof updateRowIndexes === 'function') {
        updateRowIndexes(table);
    }
    
    console.log('[SUPPLIER-SELECTOR] 新規行追加完了', {
        newRowIndex: $newRow.index(),
        newRowId: $newRow.attr('data-row-id')
    });
    
    // 金額を計算
    if (typeof calculateAmount === 'function') {
        calculateAmount($newRow);
    }
    
    // 利益表示を更新
    if (typeof updateProfitDisplay === 'function') {
        updateProfitDisplay();
    }
    
    // データベースに保存
    const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
    const supplierId = window.ktpCurrentSupplierId; // 現在選択されている協力会社ID
    
    if (orderId && typeof createNewItem === 'function') {
        console.log('[SUPPLIER-SELECTOR] DB新規作成開始', {
            orderId: orderId,
            supplierId: supplierId,
            productName: skill.product_name,
            unitPrice: skill.unit_price,
            quantity: skill.quantity,
            unit: skill.unit
        });
        
        createNewItem('cost', 'product_name', skill.product_name, orderId, $newRow, function(success, newItemId) {
            if (success && newItemId) {
                console.log('[SUPPLIER-SELECTOR] 新規アイテム作成成功', {
                    newItemId: newItemId
                });
                
                // 各フィールドを個別に保存
                if (typeof autoSaveItem === 'function') {
                    autoSaveItem('cost', newItemId, 'price', skill.unit_price, orderId);
                    autoSaveItem('cost', newItemId, 'quantity', skill.quantity, orderId);
                    autoSaveItem('cost', newItemId, 'unit', skill.unit, orderId);
                    
                    // supplier_idも保存
                    if (supplierId && supplierId > 0) {
                        autoSaveItem('cost', newItemId, 'supplier_id', supplierId, orderId);
                    }
                    
                    // 協力会社名を「仕入」フィールドに保存
                    if (window.ktpCurrentSupplierName) {
                        const purchaseDisplayText = window.ktpCurrentSupplierName && skill.product_name ? 
                            `${window.ktpCurrentSupplierName} > ${skill.product_name}` : 
                            window.ktpCurrentSupplierName;
                        autoSaveItem('cost', newItemId, 'purchase', purchaseDisplayText, orderId);
                    }
                    
                    // 金額も明示的に保存
                    const calculatedAmount = Math.ceil(parseFloat(skill.unit_price || 0) * parseFloat(skill.quantity || 1));
                    autoSaveItem('cost', newItemId, 'amount', calculatedAmount, orderId);
                }
                
                // 保存後に再度金額計算を実行
                setTimeout(function() {
                    if (typeof calculateAmount === 'function') {
                        calculateAmount($newRow);
                    }
                    if (typeof updateProfitDisplay === 'function') {
                        updateProfitDisplay();
                    }
                }, 100);
            } else {
                console.error('[SUPPLIER-SELECTOR] 新規コスト項目のDB作成に失敗しました');
            }
        });
    } else {
        console.warn('[SUPPLIER-SELECTOR] DB新規作成スキップ - 条件未満', {
            orderId: orderId,
            supplierId: supplierId,
            createNewItemExists: typeof createNewItem === 'function'
        });
    }
    
    console.log('[SUPPLIER-SELECTOR] ===== ktpAddCostRowFromSkill関数終了 =====');
};

// コスト項目を更新
window.ktpUpdateCostRowFromSkill = function(skill, currentRow) {
    console.log('[SUPPLIER-SELECTOR] ===== ktpUpdateCostRowFromSkill関数開始 =====');
    let originalValues = {};
    try {
        if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
        console.log('[SUPPLIER-SELECTOR] 更新処理開始', {
            skill: skill,
            currentRowExists: currentRow && currentRow.length > 0
        });
        if (currentRow && currentRow.length > 0) {
            // --- UI更新前の値を保存（ロールバック用） ---
            originalValues = {
                product_name: currentRow.find('.product-name').val(),
                price: currentRow.find('.price').val(),
                quantity: currentRow.find('.quantity').val(),
                unit: currentRow.find('.unit').val()
            };
            // --- UI更新 ---
            currentRow.find('.product-name').val(skill.product_name);
            currentRow.find('.price').val(skill.unit_price);
            currentRow.find('.quantity').val(skill.quantity || 1);
            currentRow.find('.unit').val(skill.unit);
            
            // 協力会社名を「仕入」フィールドに表示
            if (window.ktpCurrentSupplierName) {
                const productName = skill.product_name;
                const purchaseDisplayText = window.ktpCurrentSupplierName && productName ? 
                    `${window.ktpCurrentSupplierName} > ${productName}` : 
                    window.ktpCurrentSupplierName;
                
                // リンク付きの仕入フィールドを更新
                const $purchaseDisplay = currentRow.find('.purchase-display');
                if (purchaseDisplayText.indexOf(' > ') !== -1) {
                    $purchaseDisplay.removeClass('purchase-link').addClass('purchase-link')
                        .attr('data-purchase', purchaseDisplayText)
                        .text(purchaseDisplayText);
                } else {
                    $purchaseDisplay.removeClass('purchase-link')
                        .removeAttr('data-purchase')
                        .text(purchaseDisplayText);
                }
                currentRow.find('input[name*="[purchase]"]').val(purchaseDisplayText);
            }
            // 金額を再計算
            if (typeof calculateAmount === 'function') {
                calculateAmount(currentRow);
            }
            if (typeof updateProfitDisplay === 'function') {
                updateProfitDisplay();
            }
            // --- DB保存 ---
            const itemId = currentRow.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            const supplierId = window.ktpCurrentSupplierId;
            if (orderId && itemId && itemId !== '0' && typeof autoSaveItem === 'function') {
                try {
                    autoSaveItem('cost', itemId, 'product_name', skill.product_name, orderId);
                    autoSaveItem('cost', itemId, 'price', skill.unit_price, orderId);
                    autoSaveItem('cost', itemId, 'quantity', skill.quantity, orderId);
                    autoSaveItem('cost', itemId, 'unit', skill.unit, orderId);
                    
                    // 協力会社名を「仕入」フィールドに保存
                    if (window.ktpCurrentSupplierName) {
                        const purchaseDisplayText = window.ktpCurrentSupplierName && skill.product_name ? 
                            `${window.ktpCurrentSupplierName} > ${skill.product_name}` : 
                            window.ktpCurrentSupplierName;
                        autoSaveItem('cost', itemId, 'purchase', purchaseDisplayText, orderId);
                    }
                    if (supplierId && supplierId > 0) {
                        autoSaveItem('cost', itemId, 'supplier_id', supplierId, orderId);
                    }
                    const calculatedAmount = Math.ceil(parseFloat(skill.unit_price || 0) * parseFloat(skill.quantity || 1));
                    autoSaveItem('cost', itemId, 'amount', calculatedAmount, orderId);
                    // --- 成功時のみ通知 ---
                    if (typeof window.showSuccessNotification === 'function') {
                        window.showSuccessNotification('該当行の更新が完了しました。');
                    }
                } catch (saveError) {
                    // --- DB保存失敗時はUIロールバック ---
                    currentRow.find('.product-name').val(originalValues.product_name);
                    currentRow.find('.price').val(originalValues.price);
                    currentRow.find('.quantity').val(originalValues.quantity);
                    currentRow.find('.unit').val(originalValues.unit);
                    if (typeof calculateAmount === 'function') {
                        calculateAmount(currentRow);
                    }
                    if (typeof window.showErrorNotification === 'function') {
                        window.showErrorNotification('DB保存に失敗しました。\nエラー: ' + saveError.message);
                    } else {
                        alert('DB保存に失敗しました。\nエラー: ' + saveError.message);
                    }
                    return;
                }
            } else {
                // DB保存条件未満
                if (typeof window.showSuccessNotification === 'function') {
                    window.showSuccessNotification('該当行の更新が完了しました。（データベース保存はスキップされました）');
                }
            }
            // --- ポップアップ自動クローズ ---
            if (typeof closeSupplierSelector === 'function') {
                closeSupplierSelector();
            } else {
                $("#ktp-supplier-selector-modal").remove();
            }
        } else {
            // 行が指定されていない場合は新規追加として処理
            window.ktpAddCostRowFromSkill(skill, null);
        }
    } catch (error) {
        console.error('[SUPPLIER-SELECTOR] ktpUpdateCostRowFromSkill関数でエラーが発生:', error);
        alert('該当行の更新に失敗しました。\nエラー: ' + error.message);
    }
    console.log('[SUPPLIER-SELECTOR] ===== ktpUpdateCostRowFromSkill関数終了 =====');
};

    // ページロード時の処理
$(function() {
        console.log('[SUPPLIER-SELECTOR] ページ読み込み完了');
        console.log('[SUPPLIER-SELECTOR] ktpShowSupplierSelector関数の存在確認:', typeof window.ktpShowSupplierSelector);
        
    // コスト項目の並び順を変更する処理を削除
    // データベースから取得された順序を維持するため、JavaScriptでの並び替えは行わない
});

})(jQuery); 