# 適格請求書ナンバーを考慮した利益計算の実装完了

## 概要

適格請求書ナンバーの有無による仕入税額控除の可否を考慮した利益計算機能を実装しました。これにより、会計処理の正確性が大幅に向上します。

## 実装内容

### 1. PHP側の実装

#### 1.1 協力会社の適格請求書ナンバー取得機能
- **ファイル**: `includes/class-ktpwp-order-ui.php`
- **機能**: 協力会社IDから適格請求書ナンバーを取得
- **メソッド**: `get_supplier_qualified_invoice_number()`

#### 1.2 適格請求書ナンバーを考慮した利益計算
- **ファイル**: `includes/class-ktpwp-order-ui.php`
- **機能**: 適格請求書ナンバーの有無に応じた利益計算
- **メソッド**: `calculate_profit_with_qualified_invoice()`

#### 1.3 Ajaxエンドポイント
- **ファイル**: `includes/class-ktpwp-ajax.php`
- **機能**: 協力会社の適格請求書ナンバーを取得するAjaxエンドポイント
- **メソッド**: `ajax_get_supplier_qualified_invoice_number()`

### 2. JavaScript側の実装

#### 2.1 適格請求書ナンバー取得機能
- **ファイル**: `js/ktp-cost-items.js`
- **機能**: Ajax経由で協力会社の適格請求書ナンバーを取得
- **関数**: `getSupplierQualifiedInvoiceNumber()`

#### 2.2 適格請求書ナンバーを考慮した利益計算
- **ファイル**: `js/ktp-cost-items.js`
- **機能**: 適格請求書ナンバーの有無に応じた利益計算
- **関数**: `calculateProfitWithQualifiedInvoice()`

#### 2.3 利益表示の更新
- **ファイル**: `js/ktp-cost-items.js`
- **機能**: 適格請求書ナンバーを考慮した利益表示
- **関数**: `updateProfitDisplayWithQualifiedInvoice()`

## 計算ロジック

### 適格請求書ナンバーがある場合
- **仕入税額控除**: 可能
- **コスト計算**: 税抜金額のみをコストとする
- **計算式**: `コスト = 税込金額 - (税込金額 × 税率 ÷ (1 + 税率))`

### 適格請求書ナンバーがない場合
- **仕入税額控除**: 不可
- **コスト計算**: 税込金額をそのままコストとする
- **計算式**: `コスト = 税込金額`

## テスト機能

### テストファイル
- **ファイル**: `test_qualified_invoice_profit_calculation.php`
- **機能**: 適格請求書ナンバーを考慮した利益計算のテスト

### テストケース
1. **適格請求書がある場合**: 税抜金額をコストとして計算
2. **適格請求書がない場合**: 税込金額をコストとして計算
3. **混合ケース**: 適格請求書あり・なしの混合計算
4. **実際データテスト**: データベースの実際のデータを使用したテスト

## 使用方法

### 1. テストの実行
```
https://your-site.com/wp-admin/admin.php?page=ktp&run_qualified_invoice_test=1
```

### 2. デバッグモードの有効化
JavaScriptのデバッグモードを有効にすると、詳細な計算ログが表示されます。

## 技術仕様

### データベース
- **テーブル**: `wp_ktp_supplier`
- **カラム**: `qualified_invoice_number` (VARCHAR(100))

### Ajaxエンドポイント
- **アクション**: `ktp_get_supplier_qualified_invoice_number`
- **パラメータ**: `supplier_id`
- **レスポンス**: `qualified_invoice_number`

### セキュリティ
- **権限チェック**: `current_user_can('edit_posts')` または `current_user_can('ktpwp_access')`
- **nonce検証**: `ktp_ajax_nonce`

## 影響範囲

### 修正されたファイル
1. `includes/class-ktpwp-order-ui.php`
2. `includes/class-ktpwp-ajax.php`
3. `js/ktp-cost-items.js`

### 新規作成ファイル
1. `test_qualified_invoice_profit_calculation.php`
2. `QUALIFIED-INVOICE-PROFIT-CALCULATION-IMPLEMENTATION-COMPLETE.md`

## 注意事項

### 1. 後方互換性
- 既存の利益計算ロジックは維持されています
- 適格請求書ナンバーが設定されていない場合は従来通りの計算を行います

### 2. パフォーマンス
- Ajaxリクエストが増加する可能性があります
- デバッグモード時は詳細なログが出力されます

### 3. データ整合性
- 協力会社の適格請求書ナンバーが正しく設定されていることを確認してください
- 税率の設定が正しいことを確認してください

## 今後の拡張予定

### 1. レポート機能
- 適格請求書別の利益レポート
- 仕入税額控除の影響分析

### 2. 設定画面
- 適格請求書ナンバーの一括管理
- 税率の自動設定

### 3. エクスポート機能
- 適格請求書別のCSVエクスポート
- 税務申告用データの出力

## 実装完了日時

- **実装完了**: 2025年1月
- **テスト完了**: 2025年1月
- **ドキュメント作成**: 2025年1月

## 関連ドキュメント

- [適格請求書ナンバー機能の実装](AUTO-MIGRATION-ENHANCEMENT-COMPLETE.md)
- [消費税対応の実装](INVOICE-TAX-IMPLEMENTATION-COMPLETE.md)
- [協力会社管理機能](SUPPLIER-SKILLS-COMPLETE.md) 