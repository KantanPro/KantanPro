<?php
error_log('ajax-supplier-cost.php loaded');
/**
 * Ajax: コスト項目用 協力会社・職能リスト取得
 * @package KTPWP
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_ktpwp_get_suppliers_for_cost', function() {
    error_log('ktpwp_get_suppliers_for_cost called');
    if ( ! current_user_can('edit_posts') ) {
        error_log('権限NG: ' . print_r(wp_get_current_user(), true));
        wp_send_json_error('権限がありません');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ktp_supplier';
    $suppliers = $wpdb->get_results("SELECT id, company_name FROM $table WHERE 1 ORDER BY company_name ASC", ARRAY_A);
    error_log('ktpwp_get_suppliers_for_cost result: ' . print_r($suppliers, true));
    wp_send_json($suppliers);
});

add_action('wp_ajax_ktpwp_get_supplier_skills_for_cost', function() {
    error_log('ktpwp_get_supplier_skills_for_cost called');
    error_log('POST: ' . print_r($_POST, true));
    if ( ! current_user_can('edit_posts') ) {
        error_log('権限NG: ' . print_r(wp_get_current_user(), true));
        wp_send_json_error('権限がありません');
    }
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    if (!$supplier_id) {
        error_log('supplier_idが不正: ' . print_r($_POST, true));
        wp_send_json_error('supplier_idが不正です');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ktp_supplier_skills';
    $sql = $wpdb->prepare("
        SELECT 
            id, 
            product_name, 
            CASE 
                WHEN CAST(unit_price AS CHAR) REGEXP '^[0-9]+\\.$' THEN 
                    CAST(unit_price AS UNSIGNED)
                WHEN CAST(unit_price AS CHAR) REGEXP '^[0-9]+\\.0+$' THEN 
                    CAST(unit_price AS UNSIGNED)
                ELSE 
                    TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(unit_price AS DECIMAL(20,10))))
            END as unit_price,
            quantity, 
            unit, 
            frequency, 
            updated_at, 
            created_at 
        FROM $table 
        WHERE supplier_id = %d 
        ORDER BY id ASC
    ", $supplier_id);
    error_log('SQL: ' . $sql);
    $skills = $wpdb->get_results($sql, ARRAY_A);
    error_log('wpdb->last_error: ' . $wpdb->last_error);
    error_log('ktpwp_get_supplier_skills_for_cost result: ' . print_r($skills, true));
    wp_send_json($skills);
});

add_action('wp_ajax_ktpwp_save_supplier_skill_for_cost', function() {
    error_log('ktpwp_save_supplier_skill_for_cost called');
    error_log('POST: ' . print_r($_POST, true));
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('権限がありません');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ktp_supplier_skills';
    $data = array(
        'supplier_id'   => intval($_POST['supplier_id']),
        'product_name'  => sanitize_text_field($_POST['product_name']),
        'unit_price'    => floatval($_POST['unit_price']),
        'quantity'      => intval($_POST['quantity']),
        'unit'          => sanitize_text_field($_POST['unit']),
        'frequency'     => sanitize_text_field($_POST['frequency']),
        'updated_at'    => current_time('mysql'),
    );
    $format = array('%d','%s','%f','%d','%s','%s','%s');
    if (!empty($_POST['id'])) {
        // 更新
        $result = $wpdb->update($table, $data, array('id'=>intval($_POST['id'])), $format, array('%d'));
        error_log('UPDATE result: ' . print_r($result, true));
        error_log('wpdb->last_error: ' . $wpdb->last_error);
        if ($result !== false) {
            wp_send_json_success(['id'=>intval($_POST['id'])]);
        } else {
            wp_send_json_error('更新失敗: ' . $wpdb->last_error);
        }
    } else {
        // 追加
        $data['created_at'] = current_time('mysql');
        $format[] = '%s';
        $result = $wpdb->insert($table, $data, $format);
        error_log('INSERT result: ' . print_r($result, true));
        error_log('wpdb->last_error: ' . $wpdb->last_error);
        if ($result) {
            wp_send_json_success(['id'=>$wpdb->insert_id]);
        } else {
            wp_send_json_error('追加失敗: ' . $wpdb->last_error);
        }
    }
});

add_action('wp_ajax_ktpwp_save_order_cost_item', function() {
    error_log('=== ktpwp_save_order_cost_item called ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('User: ' . print_r(wp_get_current_user(), true));
    
    if ( ! current_user_can('edit_posts') ) {
        error_log('権限NG: ユーザーに権限がありません');
        wp_send_json_error('権限がありません');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ktp_order_cost_items';
    $item_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $force_save = isset($_POST['force_save']) && $_POST['force_save'] ? true : false;
    $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
    
    error_log('Parsed data:');
    error_log('- item_id: ' . $item_id);
    error_log('- order_id: ' . $order_id);
    error_log('- force_save: ' . ($force_save ? 'true' : 'false'));
    error_log('- product_name: ' . $product_name);
    
    // supplier_idカラムの存在確認
    $columns = $wpdb->get_col($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'supplier_id'));
    $has_supplier_id = !empty($columns);
    error_log('Has supplier_id column: ' . ($has_supplier_id ? 'true' : 'false'));
    
    // 既存アイテムのIDを確認（product_nameとorder_idで検索）
    if ($item_id <= 0 && !empty($product_name) && $order_id > 0) {
        $existing_item = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE order_id = %d AND product_name = %s LIMIT 1",
            $order_id, $product_name
        ), ARRAY_A);
        if ($existing_item) {
            $item_id = $existing_item['id'];
            error_log('既存アイテムを発見、ID: ' . $item_id);
        }
    }
    
    error_log('Final item_id: ' . $item_id);
    
    $data = array(
        'order_id'      => $order_id,
        'product_name'  => $product_name,
        'price'         => isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : (isset($_POST['price']) ? floatval($_POST['price']) : 0),
        'quantity'      => isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0,
        'unit'          => isset($_POST['unit']) ? sanitize_text_field($_POST['unit']) : '',
        'amount'        => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
        'remarks'       => isset($_POST['remarks']) ? sanitize_textarea_field($_POST['remarks']) : '',
        'updated_at'    => current_time('mysql'),
    );
    $format = array('%d','%s','%f','%f','%s','%f','%s','%s');
    
    // supplier_idカラムが存在する場合のみ追加
    if ($has_supplier_id) {
        $data['supplier_id'] = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
        $format[] = '%d';
    }
    
    error_log('Data to save: ' . print_r($data, true));
    error_log('Format: ' . print_r($format, true));
    
    if ($item_id > 0) {
        // UPDATE
        error_log('=== UPDATE処理開始 ===');
        $result = $wpdb->update($table, $data, array('id'=>$item_id), $format, array('%d'));
        error_log('UPDATE result: ' . print_r($result, true));
        error_log('wpdb->last_error: ' . $wpdb->last_error);
        error_log('wpdb->last_query: ' . $wpdb->last_query);
        
        if ($result !== false) {
            error_log('UPDATE成功: ID ' . $item_id);
            wp_send_json_success(['id'=>$item_id]);
        } else {
            error_log('UPDATE失敗: ' . $wpdb->last_error);
            wp_send_json_error('更新失敗: ' . $wpdb->last_error);
        }
    } else {
        // INSERT
        error_log('=== INSERT処理開始 ===');
        if ($force_save || !empty($product_name)) {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $result = $wpdb->insert($table, $data, $format);
            error_log('INSERT result: ' . print_r($result, true));
            error_log('wpdb->last_error: ' . $wpdb->last_error);
            error_log('wpdb->last_query: ' . $wpdb->last_query);
            
            if ($result) {
                error_log('INSERT成功: 新規ID ' . $wpdb->insert_id);
                wp_send_json_success(['id'=>$wpdb->insert_id]);
            } else {
                error_log('INSERT失敗: ' . $wpdb->last_error);
                wp_send_json_error('追加失敗: ' . $wpdb->last_error);
            }
        } else {
            error_log('INSERT失敗: 商品名が空');
            wp_send_json_error('商品名が空のため追加されません');
        }
    }
    
    error_log('=== ktpwp_save_order_cost_item 終了 ===');
});
