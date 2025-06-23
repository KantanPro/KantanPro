// 協力会社選択ポップアップの表示関数
window.ktpShowSupplierSelector = function(currentRow) {
    // 既存のポップアップがあれば削除
    $("#ktp-supplier-selector-modal").remove();

    // 1. 協力会社リスト取得（Ajax）
    $.ajax({
        url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
        type: 'POST',
        data: {
            action: 'ktpwp_get_suppliers_for_cost'
        },
        dataType: 'json',
        success: function(suppliers) {
            // ポップアップUI生成
            let supplierOptions = suppliers.map(function(s) {
                return '<option value="' + s.id + '">' + s.company_name + '</option>';
            }).join('');
            const modal = $(
                '<div id="ktp-supplier-selector-modal" style="position:fixed;z-index:9999;top:10%;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #ccc;padding:24px;min-width:400px;box-shadow:0 4px 16px rgba(0,0,0,0.2);">' +
                '<h3>協力会社選択</h3>' +
                '<select id="ktp-supplier-list">' + supplierOptions + '</select>' +
                '<div id="ktp-skill-list-area">職能リストを選択してください</div>' +
                '<button id="ktp-supplier-selector-close">閉じる</button>' +
                '</div>'
            );
            $("body").append(modal);
            $("#ktp-supplier-selector-close").on("click", function() {
                $("#ktp-supplier-selector-modal").remove();
            });

            // 2. 協力会社選択時に職能リスト取得
            $("#ktp-supplier-list").on('change', function() {
                const supplierId = $(this).val();
                if (!supplierId) return;
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
                        let html = '';
                        if (Array.isArray(skills) && skills.length > 0) {
                            html += '<table class="ktp-skill-list-table"><thead><tr><th>商品名</th><th>単価</th><th>数量</th><th>単位</th><th>頻度</th></tr></thead><tbody>';
                            skills.forEach(function(skill) {
                                html += '<tr>' +
                                    '<td>' + (skill.product_name || '') + '</td>' +
                                    '<td>' + (skill.unit_price || '') + '</td>' +
                                    '<td>' + (skill.quantity || '') + '</td>' +
                                    '<td>' + (skill.unit || '') + '</td>' +
                                    '<td>' + (skill.frequency || '') + '</td>' +
                                    '<td><button class="ktp-skill-add-btn" data-skill="' + encodeURIComponent(JSON.stringify(skill)) + '">追加</button></td>' +
                                    '<td><button class="ktp-skill-update-btn" data-skill="' + encodeURIComponent(JSON.stringify(skill)) + '">更新</button></td>' +
                                    '</tr>';
                            });
                            html += '</tbody></table>';
                        } else {
                            html = '<div style="color:#888;">職能がありません</div>';
                        }
                        $('#ktp-skill-list-area').html(html);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX ERROR', xhr.status, xhr.responseText);
                        $('#ktp-skill-list-area').html('<div style="color:red;">職能リストの取得に失敗しました</div>');
                    }
                });
            }).trigger('change');

            // 3. 追加・更新ボタン処理
            $(document).off('click', '.ktp-skill-add-btn');
            $(document).on('click', '.ktp-skill-add-btn', function() {
                let skill = $(this).data('skill');
                if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
                if (!skill.supplier_id) {
                    skill.supplier_id = $('#ktp-supplier-list').val();
                }
                let order_id = $('input[name="order_id"]').val() || $('#order_id').val();
                // skillにorder_idをセット
                skill.order_id = order_id;
                // DB保存Ajax（全項目送信）
                $.ajax({
                    url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
                    type: 'POST',
                    data: Object.assign({
                        action: 'ktpwp_save_order_cost_item',
                        force_save: true // 協力会社職能からの追加・更新時は必ずtrue
                    }, skill),
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            skill.id = res.data.id;
                            window.ktpAddCostRowFromSkill(skill, currentRow);
                            $("#ktp-supplier-selector-modal").remove();
                        } else {
                            alert('保存失敗: ' + (res.data || ''));
                        }
                    },
                    error: function(xhr) {
                        alert('保存通信エラー: ' + xhr.responseText);
                    }
                });
            });
            $(document).off('click', '.ktp-skill-update-btn');
            $(document).on('click', '.ktp-skill-update-btn', function() {
                let skill = $(this).data('skill');
                if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
                if (!skill.supplier_id) {
                    skill.supplier_id = $('#ktp-supplier-list').val();
                }
                let order_id = $('input[name="order_id"]').val() || $('#order_id').val();
                skill.order_id = order_id;
                // DB保存Ajax（全項目送信）
                $.ajax({
                    url: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php'),
                    type: 'POST',
                    data: Object.assign({
                        action: 'ktpwp_save_order_cost_item',
                        force_save: true // 協力会社職能からの更新時も必ずtrue
                    }, skill),
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            skill.id = res.data.id;
                            window.ktpUpdateCostRowFromSkill(skill, currentRow);
                            $("#ktp-supplier-selector-modal").remove();
                        } else {
                            alert('更新失敗: ' + (res.data || ''));
                        }
                    },
                    error: function(xhr) {
                        alert('更新通信エラー: ' + xhr.responseText);
                    }
                });
            });
        },
        error: function(xhr, status, error) {
            console.error('AJAX ERROR', xhr.status, xhr.responseText);
            alert('協力会社リストの取得に失敗しました');
        }
    });
};

// コスト項目に新規追加
window.ktpAddCostRowFromSkill = function(skill, currentRow) {
    if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
    if (!currentRow || currentRow.length === 0) return;
    const table = currentRow.closest('table');
    const newRow = currentRow.clone();
    newRow.find('.product-name').val(skill.product_name);
    newRow.find('.price').val(skill.unit_price);
    newRow.find('.quantity').val(skill.quantity || 1);
    newRow.find('.unit').val(skill.unit);
    newRow.find('.amount').val(skill.unit_price);
    table.find('tbody tr').eq(currentRow.index()).after(newRow);
    if (typeof calculateAmount === 'function') calculateAmount(newRow);
};

// コスト項目を更新
window.ktpUpdateCostRowFromSkill = function(skill, currentRow) {
    if (typeof skill === 'string') skill = JSON.parse(decodeURIComponent(skill));
    if (!currentRow || currentRow.length === 0) return;
    currentRow.find('.product-name').val(skill.product_name);
    currentRow.find('.price').val(skill.unit_price);
    currentRow.find('.quantity').val(skill.quantity || 1);
    currentRow.find('.unit').val(skill.unit);
    currentRow.find('.amount').val(skill.unit_price);
    if (typeof calculateAmount === 'function') calculateAmount(currentRow);
};
