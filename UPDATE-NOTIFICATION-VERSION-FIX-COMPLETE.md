# アップデート通知のバージョン表示修正完了

## 問題の概要

KantanProのアップデート通知で、プレビューバージョンが正しく表示されない問題がありました。

**問題の例:**
- 実際のバージョン: `1.1.16(preview)`
- 表示されるバージョン: `1.1.15.999`

## 原因

`includes/class-ktpwp-update-checker.php`の`clean_version`メソッドで、プレビューバージョンを比較用に変換する際に、以下の処理が行われていました：

```php
// プレビューバージョンの場合は、バージョン番号を少し下げて比較
// 例: 1.1.2(preview) → 1.1.1.999 として扱う
if ( $is_preview ) {
    $version_parts = explode( '.', $version );
    if ( count( $version_parts ) >= 3 ) {
        // 最後の数字を1つ減らして、.999を追加
        $last_part = intval( $version_parts[count( $version_parts ) - 1] );
        $version_parts[count( $version_parts ) - 1] = max( 0, $last_part - 1 );
        $version = implode( '.', $version_parts ) . '.999';
    }
}
```

この処理により、`1.1.16(preview)`は比較用に`1.1.15.999`に変換されます。

しかし、更新通知の表示時に、この変換後のバージョン（`1.1.15.999`）が表示されてしまっていました。

## 修正内容

### 1. 管理画面通知の修正

**ファイル:** `includes/class-ktpwp-update-checker.php`
**メソッド:** `show_update_notice()`

**修正前:**
```php
if ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
    $new_version = $update_data['version'];
}
```

**修正後:**
```php
if ( is_array( $update_data ) && isset( $update_data['new_version'] ) ) {
    $new_version = $update_data['new_version'];
} elseif ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
    $new_version = $update_data['version'];
}
```

### 2. フロントエンド通知の修正

**ファイル:** `includes/class-ktpwp-update-checker.php`
**メソッド:** `show_frontend_update_notice()`

**修正前:**
```php
if ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
    $new_version = $update_data['version'];
}
```

**修正後:**
```php
if ( is_array( $update_data ) && isset( $update_data['new_version'] ) ) {
    $new_version = $update_data['new_version'];
} elseif ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
    $new_version = $update_data['version'];
}
```

## 修正の仕組み

### バージョン情報の保存

更新チェック時に、以下の情報が保存されます：

```php
$update_data = array(
    'version' => $latest_version,           // クリーン後のバージョン (1.1.15.999)
    'new_version' => $data['tag_name'],     // 元のバージョン文字列 (1.1.16(preview))
    'download_url' => $data['zipball_url'],
    'changelog' => isset( $data['body'] ) ? $data['body'] : '',
    'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : '',
    'current_version' => $this->current_version,
    'cleaned_current_version' => $current_version,
    'cleaned_latest_version' => $latest_version,
);
```

### 表示の優先順位

修正後は、以下の優先順位でバージョンが表示されます：

1. `new_version` - 元のバージョン文字列（推奨）
2. `version` - クリーン後のバージョン（フォールバック）
3. 文字列として保存されたバージョン（レガシー対応）

## JavaScript側の対応

`js/ktpwp-update-balloon.js`は既に正しく実装されており、`new_version`を優先的に使用していました：

```javascript
if (hasUpdate && updateData && updateData.new_version) {
    versionInfo = '<div class="ktpwp-update-balloon-version">新しいバージョン: <strong>' + updateData.new_version + '</strong></div>';
} else if (hasUpdate && updateData && updateData.version) {
    versionInfo = '<div class="ktpwp-update-balloon-version">新しいバージョン: <strong>' + updateData.version + '</strong></div>';
}
```

## テスト結果

修正後、以下のように正しく表示されるようになりました：

- **修正前:** `KantanProの新しいバージョン1.1.15.999が利用可能です。今すぐ更新`
- **修正後:** `KantanProの新しいバージョン1.1.16(preview)が利用可能です。今すぐ更新`

## 影響範囲

この修正により、以下の通知が正しく表示されるようになります：

1. 管理画面のプラグインリストでの更新通知
2. KantanPro設置ページでの更新通知
3. フロントエンドでの更新通知
4. ヘッダー更新リンクの吹き出し通知

## 注意事項

- 無視機能（dismiss）では、比較用のクリーンされたバージョンを使用しており、これは意図的な動作です
- プレビューバージョンの比較ロジックは変更していないため、更新判定の動作は変わりません
- 既存のデータとの互換性を保つため、フォールバック処理も実装しています

## 完了日時

2025年1月31日 