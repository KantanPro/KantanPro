# 協力会社選択ポップアップでの税率更新問題修正完了

## 問題の概要

**問題**: 受注書＞コスト項目＞協力会社選択ポップアップで「更新」の場合、協力会社＞職能に設定された税率が正しく入らないケースがある
**詳細**: 1行目以外は税率0の職能は税率NULLで保存されてしまう
**要件**: 1行目以外でも税率0の職能は税率0で保存されなければならない

## 原因の特定

`includes/ajax-supplier-cost.php`の`ktpwp_save_order_cost_item`アクションで、税率（tax_rate）の処理が完全に抜けていました。

### 問題箇所
```php
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
// ← 税率（tax_rate）の処理が抜けている
```

## 修正内容

### 1. 税率処理の追加

**ファイル**: `includes/ajax-supplier-cost.php`

**修正前**:
```php
// 税率の処理が抜けている
```

**修正後**:
```php
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
```

### 2. 保存データへの税率フィールド追加

**修正前**:
```php
$data = array(
    'order_id'      => $order_id,
    'product_name'  => $product_name,
    'price'         => $unit_price,
    'quantity'      => $quantity,
    'unit'          => $unit,
    'amount'        => $amount,
    'remarks'       => $remarks,
    'updated_at'    => current_time( 'mysql' ),
);
$format = array( '%d', '%s', '%f', '%f', '%s', '%f', '%s' );
```

**修正後**:
```php
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
```

## 修正のポイント

### 1. 税率0とNULLの適切な区別
- 税率が0の場合は0として保存（NULLではない）
- 税率が空文字、nullの場合はNULLとして保存
- 数値の場合はfloatval()で変換して保存

### 2. フォーマット配列の動的調整
- 税率がNULLの場合はフォーマット配列の該当位置もnullに設定
- 税率が0の場合はフォーマット配列も'%f'として設定
- これにより、データベースに正しく値が保存される

### 3. JavaScript側との整合性
- JavaScript側では既に税率の適切な処理が実装済み
- PHP側の修正により、フロントエンドとバックエンドの処理が一致

## テスト方法

### 1. テストファイルの実行
```bash
# ブラウザでアクセス
http://your-domain.com/wp-content/plugins/KantanPro/test_supplier_tax_rate_update.php
```

### 2. 手動テスト手順
1. 受注書画面でコスト項目を追加
2. 協力会社選択ポップアップを開く
3. 税率0の職能を選択して「更新」をクリック
4. 税率が正しく0として保存されることを確認

### 3. データベース確認
```sql
-- コスト項目テーブルの税率確認
SELECT id, product_name, tax_rate, 
       CASE WHEN tax_rate IS NULL THEN 'NULL' ELSE 'NOT NULL' END as tax_rate_status
FROM wp_ktp_order_cost_items 
WHERE product_name LIKE '%テスト%' 
ORDER BY id DESC;
```

## 影響範囲

### 修正されたファイル
- `includes/ajax-supplier-cost.php`

### 影響を受ける機能
- 協力会社選択ポップアップでのコスト項目更新
- 税率0の職能からの更新処理

### 影響を受けない機能
- 新規追加処理（既に正しく実装済み）
- 他の税率処理（既存の実装は変更なし）

## 検証結果

### 修正前の問題
- 税率0の職能を選択して更新すると、税率が正しく保存されない
- 1行目以外で税率0が期待される場合にNULLとして処理されてしまう

### 修正後の動作
- 税率0の職能を選択して更新すると、税率が正しく0として保存される
- すべての行で税率の処理が一貫して動作する

## 今後の注意点

1. **税率の0とNULLの区別**: 税率0は0として、未設定はNULLとして適切に区別する
2. **フォーマット配列**: 動的にフォーマット配列を調整する際の注意
3. **データベース整合性**: 税率カラムがNULLを許可する設定になっていることを確認

## 完了日時

**修正完了**: 2025年1月31日
**テスト完了**: 2025年1月31日
**ドキュメント作成**: 2025年1月31日

---

この修正により、協力会社選択ポップアップでの税率更新問題が解決され、税率0の職能が正しく0として保存されるようになりました。 