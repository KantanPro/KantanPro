// 協力会社選択ポップアップの表示関数
(function() {
    console.log('[SUPPLIER-SELECTOR] ファイル読み込み完了');
    console.log('[SUPPLIER-SELECTOR] jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
    console.log('[SUPPLIER-SELECTOR] ktp_ajax_object:', typeof ktp_ajax_object !== 'undefined' ? 'loaded' : 'not loaded');

    // 即座に関数を定義
    window.ktpShowSupplierSelector = function(currentRow) {
        console.log('[SUPPLIER-SELECTOR] ===== ktpShowSupplierSelector関数開始 =====');
        console.log('[SUPPLIER-SELECTOR] 引数currentRow:', currentRow);
        
        // グローバル変数としてcurrentRowを保持
        window.ktpCurrentRow = currentRow;
        console.log('[SUPPLIER-SELECTOR] window.ktpCurrentRowを設定:', window.ktpCurrentRow);
        
        // 既存のポップアップがあれば削除
        $("#ktp-supplier-selector-modal").remove();

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
        $.ajax({
            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'ktpwp_get_suppliers_for_cost'
            },
            dataType: 'json',
            success: function(suppliers) {
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

                // 協力会社選択時に職能リスト取得
                $("#ktp-supplier-list").on('change', function() {
                    const supplierId = $(this).val();
                    if (!supplierId) {
                        $('#ktp-skill-list-area').html(`
                            <div style="text-align: center; padding: 40px; color: #666;">
                                協力会社を選択すると職能リストが表示されます
                            </div>
                        `);
                        return;
                    }
                    
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
                            renderSkillList(skills);
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX ERROR', xhr.status, xhr.responseText);
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
                function renderSkillList(skills) {
                    let html = '';
                    
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
                                                data-skill='${JSON.stringify(Object.assign({}, skill, {unit_price: unitPrice}))}'
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
                                                data-skill='${JSON.stringify(Object.assign({}, skill, {unit_price: unitPrice}))}'
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
                    
                    // イベントハンドラーを設定
                    console.log('[SUPPLIER-SELECTOR] イベントハンドラーを設定中...');
                    
                    $(document).off('click', '.ktp-skill-update-btn');
                    $(document).on('click', '.ktp-skill-update-btn', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('[SUPPLIER-SELECTOR] 更新ボタンクリック（jQuery版）');
                        
                        let skill = $(this).data('skill');
                        console.log('[SUPPLIER-SELECTOR] 取得したスキルデータ:', skill);
                        
                        if (!skill) {
                            console.error('[SUPPLIER-SELECTOR] スキルデータが取得できません');
                            return;
                        }
                        
                        if (!skill.supplier_id) {
                            skill.supplier_id = $('#ktp-supplier-list').val();
                        }
                        
                        let order_id = $('input[name="order_id"]').val() || $('#order_id').val();
                        skill.order_id = order_id;
                        
                        let targetRow = window.ktpCurrentRow;
                        
                        if (!targetRow || targetRow.length === 0) {
                            console.log('[SUPPLIER-SELECTOR] 対象行が見つからないため新規追加として処理');
                            window.ktpAddCostRowFromSkill(skill, null);
                            targetRow = $('.cost-items-table').find('tbody tr').last();
                        } else {
                            console.log('[SUPPLIER-SELECTOR] 既存行を更新');
                            window.ktpUpdateCostRowFromSkill(skill, targetRow);
                        }
                        
                        const existingId = targetRow.find('input[name*="[id]"]').val();
                        const orderId = $('input[name="order_id"]').val() || $('#order_id').val() || $('input[name*="order_id"]').val();
                        
                        const ajaxData = {
                            action: 'ktpwp_save_order_cost_item',
                            force_save: true,
                            id: existingId || '0',
                            order_id: orderId,
                            supplier_id: skill.supplier_id,
                            product_name: skill.product_name,
                            unit_price: skill.unit_price,
                            quantity: skill.quantity,
                            unit: skill.unit,
                            amount: skill.unit_price * skill.quantity
                        };
                        console.log('[SUPPLIER-SELECTOR] Ajax送信データ:', ajaxData);
                        
                        $.ajax({
                            url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
                            type: 'POST',
                            data: ajaxData,
                            dataType: 'json',
                            success: function(res) {
                                console.log('[SUPPLIER-SELECTOR] Ajax成功:', res);
                                if (res.success) {
                                    skill.id = res.data.id;
                                    targetRow.find('input[name*="[id]"]').val(res.data.id);
                                    
                                    setTimeout(function() {
                                        if (typeof calculateAmount === 'function') {
                                            calculateAmount(targetRow);
                                        }
                                        
                                        if (typeof updateProfitDisplay === 'function') {
                                            updateProfitDisplay();
                                        }
                                    }, 100);
                                    
                                    closeSupplierSelector();
                                    
                                    if (typeof showSuccessNotification === 'function') {
                                        showSuccessNotification('コスト項目を更新しました');
                                    }
                                } else {
                                    console.error('[SUPPLIER-SELECTOR] 保存失敗:', res.data);
                                }
                            },
                            error: function(xhr) {
                                console.error('[SUPPLIER-SELECTOR] Ajax通信エラー:', xhr);
                            }
                        });
                    });
                    
                    console.log('[SUPPLIER-SELECTOR] イベントハンドラー設定完了');
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

})(); 