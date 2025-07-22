# 税率NULL値修正完了

## 問題の概要

受注書の請求行で税率を「なし」に設定しても、ページをリロードすると税率が10%に書き換えられてしまう問題がありました。

## 原因

以下の箇所で税率のデフォルト値が10.00に設定されていたため、データベースにNULLが保存されていてもUI表示時に10.00が表示されていました：

1. **PHP側（`includes/class-ktpwp-order-ui.php`）**
   - 空のアイテム配列作成時（110行目）
   - 税率フィールド表示時（210行目）
   - 消費税計算時（240行目、250行目）

2. **JavaScript側（`js/ktp-cost-items.js`）**
   - サービス選択時の税率設定（1180行目、1384行目）

## 修正内容

### 1. PHP側の修正

#### `includes/class-ktpwp-order-ui.php`

**空のアイテム配列作成時の修正（110行目）**
```php
// 修正前
'tax_rate' => 10.00,

// 修正後
'tax_rate' => null,
```

**税率フィールド表示時の修正（210行目）**
```php
// 修正前
$tax_rate = isset( $item['tax_rate'] ) ? floatval( $item['tax_rate'] ) : 10.00;

// 修正後
$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
$tax_rate_display = '';
if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
    $tax_rate_display = floatval( $tax_rate_raw );
}
```

**消費税計算時の修正（240-250行目）**
```php
// 修正前
$item_tax_rate = isset( $item['tax_rate'] ) ? floatval( $item['tax_rate'] ) : 10.00;
$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );

// 修正後
$item_tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
if ( $item_tax_rate_raw !== null && $item_tax_rate_raw !== '' && is_numeric( $item_tax_rate_raw ) ) {
    $item_tax_rate = floatval( $item_tax_rate_raw );
    $tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );
}
```

### 2. JavaScript側の修正

#### `js/ktp-cost-items.js`

**サービス選択時の税率設定修正（1180行目、1384行目）**
```javascript
// 修正前
const taxRate = serviceData.tax_rate || 10.00;

// 修正後
const taxRate = serviceData.tax_rate !== null && serviceData.tax_rate !== undefined && serviceData.tax_rate !== '' ? serviceData.tax_rate : '';
```

## 修正の効果

1. **データベースの整合性**: 税率がNULLの場合はデータベースにNULLとして保存される
2. **UI表示の正確性**: 税率がNULLの場合は空文字列として表示される
3. **消費税計算の正確性**: 税率がNULLの場合は税額計算をスキップする
4. **ユーザビリティの向上**: 税率を「なし」に設定した際の動作が直感的になる

## テスト方法

修正内容をテストするために `test_tax_rate_null_fix.php` ファイルを作成しました。

### テスト手順

1. テストファイル内の `$test_order_id` を実際の受注書IDに変更
2. ブラウザでテストファイルにアクセス
3. 以下の項目を確認：
   - データベースの税率値の状態
   - UI生成時の税率表示
   - 税率NULL設定の動作

## 影響範囲

- **受注書の請求行**: 税率の表示と保存
- **消費税計算**: 税率がNULLの場合の処理
- **サービス選択**: 税率の自動設定

## 注意事項

- 既存のデータで税率が10.00に設定されているものは、手動で「なし」に変更する必要があります
- 消費税計算では、税率がNULLの項目は非課税として扱われます
- この修正により、税率のNULL値が適切に処理されるようになります

## 完了日時

2025年1月31日 