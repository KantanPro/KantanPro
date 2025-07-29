# 税率更新修正完了

## 問題の概要

受注書のコスト項目で協力会社選択ポップアップの「更新」時に、税率が正しく保存されない問題がありました。

### 具体的な問題
- 1行目の非課税（税率0）の更新の場合は保存されない
- 1行目の非課税（税率NULL）の更新の場合は保存されない
- その他の税率更新でも不具合が発生

## 原因分析

1. **サーバー側（PHP）の問題**
   - `update_item_field`メソッドで税率がNULLの場合にフォーマット配列に`null`を追加していた
   - MySQLでは`null`フォーマットは無効なため、更新が失敗していた

2. **クライアント側（JavaScript）の問題**
   - 税率の処理で`0`の値が適切に処理されていなかった
   - 非課税（税率0）の場合に空文字として送信されていた

## 修正内容

### 1. サーバー側修正（`includes/class-ktpwp-order-items.php`）

#### `update_item_field`メソッドの税率処理修正
```php
case 'tax_rate':
    // 税率の処理（空文字、null、0の場合はNULLとして扱う）
    if ( $field_value === null || $field_value === '' || $field_value === '0' ) {
        $update_data['tax_rate'] = null;
        // NULL値の場合はフォーマットを指定しない（MySQLが自動的にNULLとして扱う）
    } else {
        $update_data['tax_rate'] = floatval( $field_value );
        $format[] = '%f';
    }
    break;
```

#### `create_new_item`メソッドの税率処理修正
```php
case 'tax_rate':
    // 税率がNULLの場合はフォーマットもNULLのままにする
    if ( $value !== null ) {
        $format[$index] = '%f';
    }
    break;
```

### 2. クライアント側修正（`js/ktp-supplier-selector.js`）

#### UI更新時の税率処理修正
```javascript
// 税率の処理：null、空文字、0の場合は空文字を設定
const taxRateValue = (skill.tax_rate === null || skill.tax_rate === '' || skill.tax_rate === 0) ? '' : Math.round(skill.tax_rate);
currentRow.find('.tax-rate').val(taxRateValue);
```

#### DB保存時の税率処理修正
```javascript
// 税率の保存：null、空文字、0の場合はnullとして送信
const taxRateForDB = (skill.tax_rate === null || skill.tax_rate === '' || skill.tax_rate === 0) ? null : Math.round(skill.tax_rate);
autoSaveItem('cost', itemId, 'tax_rate', taxRateForDB, orderId);
```

### 3. クライアント側修正（`js/ktp-cost-items.js`）

#### 新規作成時の税率処理修正
```javascript
// 税率の処理：null、空文字、0の場合はnullとして送信
const taxRateForDB = (taxRate === null || taxRate === '' || taxRate === 0) ? null : Math.round(taxRate);
autoSaveItem('cost', newItemId, 'tax_rate', taxRateForDB, orderId);
```

## 修正のポイント

### 1. NULL値の適切な処理
- 税率が`null`、空文字`''`、数値`0`の場合は、データベースに`NULL`として保存
- フォーマット配列では`null`値を適切に処理

### 2. 一貫性のある処理
- サーバー側とクライアント側で同じロジックを使用
- 非課税の場合は常に`NULL`として扱う

### 3. 型の統一
- 数値の税率は`floatval()`で統一
- 表示用は`Math.round()`で整数化

## テスト

`test_tax_rate_update.php`ファイルを作成し、以下のケースをテスト：

1. **新規アイテム作成テスト**
   - `null`値
   - 空文字`''`
   - 数値`0`
   - 文字列`'0'`
   - 数値`10`
   - 文字列`'10'`
   - 小数点`10.5`

2. **既存アイテム更新テスト**
   - 上記と同じケースで既存アイテムの更新をテスト

## 期待される動作

### 修正前
- 非課税（税率0）の更新が保存されない
- 税率NULLの更新が保存されない
- データベースに不正な値が保存される

### 修正後
- 非課税（税率0）の更新が正常に保存される
- 税率NULLの更新が正常に保存される
- すべての税率値が適切に処理される
- データベースに正しい値が保存される

## 影響範囲

- 受注書のコスト項目の税率更新機能
- 協力会社選択ポップアップの「更新」機能
- 新規コスト項目作成時の税率設定

## 注意事項

- 既存のデータには影響しません
- 税率の表示と保存の一貫性が保たれます
- 非課税の場合は常に`NULL`として扱われます

## 完了日時

2025年1月31日 