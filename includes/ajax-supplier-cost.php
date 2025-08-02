<?php
/**
 * Ajax: コスト項目用 協力会社・職能リスト取得
 *
 * @package KTPWP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
    'wp_ajax_ktpwp_get_suppliers_for_cost',
    function () {
		// デバッグログ開始
		error_log('[SUPPLIER-COST-AJAX] 協力会社リスト取得開始');
		error_log('[SUPPLIER-COST-AJAX] REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
		error_log('[SUPPLIER-COST-AJAX] POST data: ' . print_r($_POST, true));
		error_log('[SUPPLIER-COST-AJAX] Current user ID: ' . get_current_user_id());
		error_log('[SUPPLIER-COST-AJAX] Current user capabilities: ' . print_r(wp_get_current_user()->allcaps, true));
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			error_log('[SUPPLIER-COST-AJAX] 権限エラー: ユーザーに権限がありません');
			wp_send_json_error( '権限がありません' );
		}
		
		try {
			global $wpdb;
			$table = $wpdb->prefix . 'ktp_supplier';
			
			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
			if ( ! $table_exists ) {
				error_log('[SUPPLIER-COST-AJAX] テーブルが存在しません: ' . $table );
				wp_send_json( array() );
			}
			
			$query = "SELECT id, company_name FROM $table WHERE 1 ORDER BY company_name ASC";
			error_log('[SUPPLIER-COST-AJAX] 実行クエリ: ' . $query );
			
			$suppliers = $wpdb->get_results( $query, ARRAY_A );
			error_log('[SUPPLIER-COST-AJAX] クエリ結果: ' . print_r( $suppliers, true ) );
			
			if ( $wpdb->last_error ) {
				error_log('[SUPPLIER-COST-AJAX] データベースエラー: ' . $wpdb->last_error );
				wp_send_json_error( 'データベースエラー: ' . $wpdb->last_error );
			}
			
			error_log('[SUPPLIER-COST-AJAX] 協力会社リスト取得成功: ' . count( $suppliers ) . '件' );
			wp_send_json( $suppliers );
			
		} catch ( Exception $e ) {
			error_log('[SUPPLIER-COST-AJAX] 例外発生: ' . $e->getMessage() );
			wp_send_json_error( '協力会社リストの取得中にエラーが発生しました: ' . $e->getMessage() );
		}
	}
);

add_action(
    'wp_ajax_ktpwp_get_supplier_skills_for_cost',
    function () {
		// デバッグログ開始
		error_log('[SUPPLIER-COST-AJAX] 職能リスト取得開始');
		error_log('[SUPPLIER-COST-AJAX] REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
		error_log('[SUPPLIER-COST-AJAX] POST data: ' . print_r($_POST, true));
		error_log('[SUPPLIER-COST-AJAX] Current user ID: ' . get_current_user_id());
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			error_log('[SUPPLIER-COST-AJAX] 権限エラー: ユーザーに権限がありません');
			wp_send_json_error( '権限がありません' );
		}
		
		$supplier_id = isset( $_POST['supplier_id'] ) ? intval( $_POST['supplier_id'] ) : 0;
		if ( ! $supplier_id ) {
			error_log('[SUPPLIER-COST-AJAX] supplier_idが不正: ' . $supplier_id );
			wp_send_json_error( 'supplier_idが不正です' );
		}
		
		try {
			global $wpdb;
			$table = $wpdb->prefix . 'ktp_supplier_skills';
			
			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
			if ( ! $table_exists ) {
				error_log('[SUPPLIER-COST-AJAX] テーブルが存在しません: ' . $table );
				wp_send_json( array() );
			}
			
			$sql = $wpdb->prepare(
				"
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
				tax_rate,
				frequency, 
				updated_at, 
				created_at 
			FROM $table 
			WHERE supplier_id = %d 
			ORDER BY id ASC
		",
				$supplier_id
			);
			
			error_log('[SUPPLIER-COST-AJAX] 実行クエリ: ' . $sql );
			
			$skills = $wpdb->get_results( $sql, ARRAY_A );
			error_log('[SUPPLIER-COST-AJAX] クエリ結果: ' . print_r( $skills, true ) );
			
			if ( $wpdb->last_error ) {
				error_log('[SUPPLIER-COST-AJAX] データベースエラー: ' . $wpdb->last_error );
				wp_send_json_error( 'データベースエラー: ' . $wpdb->last_error );
			}
			
			error_log('[SUPPLIER-COST-AJAX] 職能リスト取得成功: ' . count( $skills ) . '件' );
			wp_send_json( $skills );
			
		} catch ( Exception $e ) {
			error_log('[SUPPLIER-COST-AJAX] 例外発生: ' . $e->getMessage() );
			wp_send_json_error( '職能リストの取得中にエラーが発生しました: ' . $e->getMessage() );
		}
	}
);

add_action(
    'wp_ajax_ktpwp_save_supplier_skill_for_cost',
    function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( '権限がありません' );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_supplier_skills';
		// Handle tax_rate - allow NULL values
		$tax_rate = isset( $_POST['tax_rate'] ) ? $_POST['tax_rate'] : '';
		$tax_rate = ( $tax_rate === '' || $tax_rate === null ) ? null : floatval( $tax_rate );

		$data = array(
			'supplier_id'   => intval( $_POST['supplier_id'] ),
			'product_name'  => sanitize_text_field( $_POST['product_name'] ),
			'unit_price'    => floatval( $_POST['unit_price'] ),
			'quantity'      => intval( $_POST['quantity'] ),
			'unit'          => sanitize_text_field( $_POST['unit'] ),
			'tax_rate'      => $tax_rate,
			'frequency'     => sanitize_text_field( $_POST['frequency'] ),
			'updated_at'    => current_time( 'mysql' ),
		);
		
		// Prepare format array based on whether tax_rate is NULL
		$format = array( '%d', '%s', '%f', '%d', '%s', '%f', '%s', '%s' );
		if ( $data['tax_rate'] === null ) {
			$format[5] = null; // tax_rate position
		}
		if ( ! empty( $_POST['id'] ) ) {
			// 更新
			$result = $wpdb->update( $table, $data, array( 'id' => intval( $_POST['id'] ) ), $format, array( '%d' ) );
			if ( $result !== false ) {
				// 関連キャッシュを削除
				$supplier_id = intval( $_POST['supplier_id'] );
				ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
				
				wp_send_json_success( array( 'id' => intval( $_POST['id'] ) ) );
			} else {
				wp_send_json_error( '更新失敗: ' . $wpdb->last_error );
			}
		} else {
			// 追加
			$data['created_at'] = current_time( 'mysql' );
			$format[] = '%s';
			$result = $wpdb->insert( $table, $data, $format );
			if ( $result ) {
				// 関連キャッシュを削除
				$supplier_id = intval( $_POST['supplier_id'] );
				ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
				
				wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
			} else {
				wp_send_json_error( '追加失敗: ' . $wpdb->last_error );
			}
		}
	}
);

add_action(
    'wp_ajax_ktpwp_save_order_cost_item',
    function () {
		// 権限チェック
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( '権限がありません' );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ktp_order_cost_items';

		// items配列で送信された場合の対応
		$item = null;
		if ( isset( $_POST['items'] ) ) {
			$items = json_decode( stripslashes( $_POST['items'] ), true );
			if ( is_array( $items ) && isset( $items[0] ) ) {
				$item = $items[0];
			}
		}
		// items配列がなければ従来通り個別キーで取得
		if ( ! $item ) {
			$item = $_POST;
		}

		// 入力データの取得と検証
		$item_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		$order_id = isset( $item['order_id'] ) ? intval( $item['order_id'] ) : 0;
		$force_save = isset( $_POST['force_save'] ) && $_POST['force_save'] ? true : false;
		$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
		$supplier_id = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
		$unit_price = isset( $item['unit_price'] ) ? floatval( $item['unit_price'] ) : 0;
		$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
		$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
		$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
		$remarks = isset( $item['remarks'] ) ? sanitize_textarea_field( $item['remarks'] ) : '';
		
		// 税率の処理 - 0とNULLを適切に区別
		$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
		$tax_rate = null;
		if ( $tax_rate_raw !== null && $tax_rate_raw !== '' ) {
			if ( is_numeric( $tax_rate_raw ) ) {
				$tax_rate = floatval( $tax_rate_raw );
				// 税率0は0として保存（NULLではない）
			}
		} else {
			// 空文字またはnullの場合はNULLとして保存
			$tax_rate = null;
		}

		// 必須項目の検証
		if ( empty( $product_name ) ) {
			wp_send_json_error( '商品名は必須です' );
			return;
		}

		if ( $order_id <= 0 ) {
			wp_send_json_error( '注文IDが無効です' );
			return;
		}

		// テーブルの存在確認
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
		if ( ! $table_exists ) {
			wp_send_json_error( 'データベーステーブルが存在しません' );
			return;
		}

		// supplier_idカラムの存在確認
		$columns = $wpdb->get_col( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'supplier_id' ) );
		$has_supplier_id = ! empty( $columns );

		// 既存アイテムのIDを確認（product_nameとorder_idで検索）
		if ( $item_id <= 0 && ! empty( $product_name ) && $order_id > 0 ) {
			$existing_item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM `{$table}` WHERE order_id = %d AND product_name = %s LIMIT 1",
                    $order_id,
                    $product_name
                ),
                ARRAY_A
            );
			if ( $existing_item ) {
				$item_id = $existing_item['id'];
			}
		}

		// さらに、item_idが指定されていても、実際にそのレコードが存在するか確認
		if ( $item_id > 0 ) {
			$existing_item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM `{$table}` WHERE id = %d AND order_id = %d LIMIT 1",
                    $item_id,
                    $order_id
                ),
                ARRAY_A
            );
			if ( ! $existing_item ) {
				$item_id = 0; // 新規追加として処理
			}
		}

		// 強制的な既存レコード検索（order_id + product_name）
		if ( $item_id <= 0 && ! empty( $product_name ) && $order_id > 0 ) {
			$existing_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, product_name FROM `{$table}` WHERE order_id = %d",
                    $order_id
                ),
                ARRAY_A
            );

			foreach ( $existing_items as $existing ) {
				if ( $existing['product_name'] === $product_name ) {
					$item_id = $existing['id'];
					break;
				}
			}
		}

		// 保存データの準備
		$data = array(
			'order_id'      => $order_id,
			'product_name'  => $product_name,
			'price'         => $unit_price,
			'quantity'      => $quantity,
			'unit'          => $unit,
			'amount'        => $amount,
			'tax_rate'      => $tax_rate,
			'remarks'       => $remarks,
			'updated_at'    => current_time( 'mysql' ),
		);
		$format = array( '%d', '%s', '%f', '%f', '%s', '%f', ( $tax_rate !== null ? '%f' : null ), '%s', '%s' );

		// supplier_idカラムが存在する場合のみ追加
		if ( $has_supplier_id ) {
			$data['supplier_id'] = $supplier_id > 0 ? $supplier_id : null;
			$format[] = '%d';
			error_log( 'supplier_idを追加: ' . $data['supplier_id'] );
		}

		error_log( 'Data to save: ' . print_r( $data, true ) );
		error_log( 'Format: ' . print_r( $format, true ) );

		if ( $item_id > 0 ) {
			// UPDATE処理
			error_log( '=== UPDATE処理開始 ===' );
			error_log( 'UPDATE条件: id = ' . $item_id );

			$result = $wpdb->update( $table, $data, array( 'id' => $item_id ), $format, array( '%d' ) );
			error_log( 'UPDATE result: ' . print_r( $result, true ) );
			error_log( 'wpdb->last_error: ' . $wpdb->last_error );
			error_log( 'wpdb->last_query: ' . $wpdb->last_query );

			if ( $result !== false && $result > 0 ) {
				error_log( 'UPDATE成功: ID ' . $item_id );
				wp_send_json_success(
                    array(
						'id' => $item_id,
						'message' => '更新しました',
                    )
                );
			} else {
				error_log( 'UPDATE失敗: 0行更新またはエラー' );
				// レコードの存在確認
				$existing_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d", $item_id ) );
				if ( ! $existing_record ) {
					error_log( 'レコードが存在しないためINSERTを試行' );
					// INSERT処理にフォールバック
					$data['created_at'] = current_time( 'mysql' );
					$format[] = '%s';
					$result = $wpdb->insert( $table, $data, $format );
					if ( $result ) {
						error_log( 'INSERT成功: 新規ID ' . $wpdb->insert_id );
						wp_send_json_success(
                            array(
								'id' => $wpdb->insert_id,
								'message' => '新規追加しました',
                            )
                        );
					} else {
						error_log( 'INSERT失敗: ' . $wpdb->last_error );
						wp_send_json_error( '新規追加失敗: ' . $wpdb->last_error );
					}
				} else {
					error_log( 'レコードは存在するが更新条件に合致しない' );
					wp_send_json_error( '更新失敗: レコードは存在するが更新条件に合致しません' );
				}
			}
		} else {
			// INSERT処理
			error_log( '=== INSERT処理開始 ===' );
			if ( $force_save || ! empty( $product_name ) ) {
				$data['created_at'] = current_time( 'mysql' );
				$format[] = '%s';
				error_log( 'INSERT実行' );
				error_log( 'INSERT data: ' . print_r( $data, true ) );

				$result = $wpdb->insert( $table, $data, $format );
				error_log( 'INSERT result: ' . print_r( $result, true ) );
				error_log( 'wpdb->last_error: ' . $wpdb->last_error );
				error_log( 'wpdb->last_query: ' . $wpdb->last_query );
				error_log( 'wpdb->insert_id: ' . $wpdb->insert_id );

				if ( $result ) {
					error_log( 'INSERT成功: 新規ID ' . $wpdb->insert_id );
					wp_send_json_success(
                        array(
							'id' => $wpdb->insert_id,
							'message' => '追加しました',
                        )
                    );
				} else {
					error_log( 'INSERT失敗: ' . $wpdb->last_error );
					wp_send_json_error( '追加失敗: ' . $wpdb->last_error );
				}
			} else {
				error_log( 'INSERT失敗: 商品名が空' );
				wp_send_json_error( '商品名が空のため追加されません' );
			}
		}

		error_log( '=== ktpwp_save_order_cost_item 終了 ===' );
	}
);
