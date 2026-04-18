# 受注書プレビュー税率列追加完了

## 概要
受注書プレビューの請求項目リストに「税率」列を追加しました。これにより、各項目の税率が一目で確認できるようになりました。

## 実施日
2025年1月31日

## 変更内容

### 1. ヘッダー行の修正
- `includes/class-kantan-order.php`の`Generate_Invoice_Items_For_Preview`メソッド
- ヘッダー行に「税率」列を追加
- 配置：No. → 項目名 → 単価 → 数量 → 金額 → **税率** → 備考

### 2. データ行の修正
- 各請求項目の税率データを取得
- 税率を「X%」の形式で表示
- デフォルト税率：10.00%

### 3. 空行の修正
- 1ページ目の空行にも税率列を追加
- レイアウトの一貫性を保持

## 実装詳細

### ヘッダー行の構造
```html
<div style="display: flex; background: #f0f0f0; padding: 8px; font-weight: bold; border-bottom: 1px solid #ccc; align-items: center;">
    <div style="width: 30px; text-align: center;">No.</div>
    <div style="flex: 1; text-align: left; margin-left: 8px;">項目名</div>
    <div style="width: 80px; text-align: right;">単価</div>
    <div style="width: 60px; text-align: right;">数量</div>
    <div style="width: 80px; text-align: right;">金額</div>
    <div style="width: 60px; text-align: center;">税率</div>
    <div style="width: 100px; text-align: left; margin-left: 8px;">備考</div>
</div>
```

### データ行の構造
```php
$tax_rate = isset( $item['tax_rate'] ) ? floatval( $item['tax_rate'] ) : 10.00;

$html .= '<div style="width: 60px; text-align: center;">' . $tax_rate . '%</div>';
```

## 表示例

### 変更前
```
No. | 項目名 | 単価 | 数量 | 金額 | 備考
1   | ウェブサイト制作 | ¥100,000 | 1式 | ¥100,000 | 基本料金
```

### 変更後
```
No. | 項目名 | 単価 | 数量 | 金額 | 税率 | 備考
1   | ウェブサイト制作 | ¥100,000 | 1式 | ¥100,000 | 10% | 基本料金
```

## 影響範囲

### 対象機能
- 受注書プレビュー画面
- 請求項目リストの表示

### 影響なし
- 請求項目の編集機能
- データベース構造
- その他の帳票（請求書、納品書など）

## 技術仕様

### 税率の取得
- データベースの`ktp_order_invoice_items`テーブルの`tax_rate`カラムから取得
- デフォルト値：10.00%
- 小数点以下2桁まで表示

### レイアウト調整
- 税率列の幅：60px
- 中央揃えで表示
- 既存の列幅を調整して税率列を追加

## 確認方法

1. **受注書管理画面**にアクセス
2. 任意の受注書を選択
3. **受注書プレビュー**をクリック
4. 請求項目リストに「税率」列が表示されていることを確認

## 備考
- 税率列は各項目の税率を個別に表示
- 消費税計算は既存のロジックを維持
- 税区分（内税/外税）による表示の違いは既存通り
- 印刷時も税率列が含まれる 