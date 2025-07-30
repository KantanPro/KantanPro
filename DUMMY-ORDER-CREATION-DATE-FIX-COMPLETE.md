# ダミーデータ受注書の作成日時修正完了

## 問題の概要

ダミーデータツールで作成された受注書で、手動入力と異なる項目が複数存在する問題が発生していました。

### 問題の詳細
- **ID 385の受注書**: 手動で作成された受注書で、以下の項目が正しく設定されている
  - `time`: 正しいタイムスタンプ（1753862824）が設定されており、作成日が正常に表示される
  - `customer_name`: 顧客名が設定されている
  - `user_name`: 担当者名が設定されている
  - `company_name`: 会社名が設定されている
  - `search_field`: 検索用フィールドが設定されている

- **ID 367-384の受注書**: ダミーデータツールで作成された受注書で、以下の項目が不適切
  - `time`: `0`になっており、作成日が表示されない
  - `customer_name`: 空文字
  - `user_name`: NULL
  - `company_name`: NULL
  - `search_field`: NULL

### 表示例
```
■ 受注書概要（ID: 380）
希望納期：
納品予定日：
進捗：受付中見積中受注完了請求済入金済ボツ
有限会社サンプルE （顧客ID: 125）
作成：  ← 作成日が空
完了日：
```

## 原因

ダミーデータ作成スクリプト（`create_dummy_data.php`）で、受注書作成時に以下の問題がありました：

1. **作成日時の問題**: `time`フィールドに`time()`を設定していましたが、実際のデータベースでは`time`フィールドが`0`になっていました
2. **顧客情報の未設定**: `customer_name`、`user_name`、`company_name`、`search_field`が適切に設定されていませんでした
3. **手動入力との差異**: 手動で作成された受注書と異なるデータ構造になっていました

## 解決策

### 1. 既存データの修正

WP-CLIコマンド `wp ktp fix-order-dates` を作成し、既存のダミーデータ受注書の複数項目を修正しました。

**修正内容:**
- `time`フィールドが`0`または`NULL`の受注書を検出
- `order_date`を基に適切なタイムスタンプを生成（受注日の9:00-18:00の間のランダムな時間）
- `time`フィールドを更新
- 顧客情報を取得して`customer_name`、`user_name`、`company_name`、`search_field`を設定

**実行結果:**
```
修正対象受注書数: 18
修正完了:
- 更新成功: 18件
- エラー: 0件
修正後、timeフィールドが0またはNULLの受注書数: 0件
Success: 全ての受注書の作成日時が正常に設定されました。
```

### 2. 今後のダミーデータ作成の修正

`create_dummy_data.php`を修正し、今後作成されるダミーデータに正しい項目が設定されるようにしました。

**修正内容:**
```php
// 顧客情報を取得
$client_info = null;
if ($client_id) {
    $client_table = $wpdb->prefix . 'ktp_client';
    $client_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT company_name, name FROM {$client_table} WHERE id = %d",
            $client_id
        )
    );
}

// 顧客情報を設定
$customer_name = $client_info ? $client_info->company_name : '';
$user_name = $client_info ? $client_info->name : '';
$company_name = $client_info ? $client_info->company_name : '';
$search_field = $client_info ? $client_info->company_name . ', ' . $client_info->name : '';

// order_dateを基に適切なタイムスタンプを生成
$hour = rand(9, 18);
$minute = rand(0, 59);
$second = rand(0, 59);
$datetime_string = $order_date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
$order_timestamp = strtotime($datetime_string);

if ($order_timestamp === false) {
    $order_timestamp = time(); // フォールバック
}
```

## 実装ファイル

### 1. WP-CLIコマンド
- **ファイル**: `includes/ktp-migration-cli.php`
- **コマンド**: `wp ktp fix-order-dates`
- **オプション**: `--dry-run` (実際の更新を行わず確認のみ)

### 2. マイグレーションファイル
- **ファイル**: `includes/migrations/20250730_fix_dummy_order_creation_dates.php`
- **用途**: 手動実行用のマイグレーションスクリプト

### 3. ダミーデータ作成スクリプト修正
- **ファイル**: `create_dummy_data.php`
- **修正箇所**: 受注書作成時の`time`フィールド設定

## 使用方法

### 既存データの修正
```bash
# ドライラン（確認のみ）
docker exec -it ktpwp_copy_wordpress php /var/www/html/wp-content/plugins/KantanPro/wp-cli.phar ktp fix-order-dates --dry-run --allow-root

# 実際の修正実行
docker exec -it ktpwp_copy_wordpress php /var/www/html/wp-content/plugins/KantanPro/wp-cli.phar ktp fix-order-dates --allow-root
```

### 今後のダミーデータ作成
修正された`create_dummy_data.php`を使用することで、新しく作成されるダミーデータには正しい作成日時と顧客情報が設定されます。

## 確認方法

受注書詳細画面で、以下のように作成日が表示されることを確認できます：

```
■ 受注書概要（ID: 380）
希望納期：
納品予定日：
進捗：受付中見積中受注完了請求済入金済ボツ
有限会社サンプルE （顧客ID: 125）
作成：2024/7/30（火） 18:56  ← 作成日が正常に表示
完了日：
```

## 技術的詳細

### 作成日時の表示ロジック
`includes/class-tab-order.php`の1742行目付近で、以下のロジックにより作成日時が表示されます：

```php
// 作成日時の表示
$raw_time = $order_data->time;
$formatted_time = '';
if ( ! empty( $raw_time ) ) {
    if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
        // time()で取得したUNIXタイムスタンプはUTCベース
        $unix_timestamp = (int) $raw_time;
        $dt = new DateTime( '@' . $unix_timestamp );
        $dt->setTimezone( new DateTimeZone( wp_timezone_string() ) );
        
        // 日本語形式で表示
        $week = array( '日', '月', '火', '水', '木', '金', '土' );
        $w = $dt->format( 'w' );
        $formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
    }
}
$content .= '<div>作成：<span id="order_created_time">' . esc_html( $formatted_time ) . '</span></div>';
```

### データベース構造
`wp_ktp_order`テーブルの`time`フィールド：
- **型**: `bigint`
- **用途**: 受注書作成時のUNIXタイムスタンプ
- **表示**: 日本語形式（例：2024/7/30（火） 18:56）

## 完了日時

**2025年7月30日 17:33**

全てのダミーデータ受注書の作成日時と顧客情報が正常に設定され、受注書詳細画面で作成日と顧客情報が正しく表示されるようになりました。手動入力データと同様の構造になりました。 