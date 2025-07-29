# 削除済み職能が協力会社選択ポップアップに表示される問題の修正

## 問題の詳細
受注書の協力会社選択ポップアップで、すでに削除した職能（スキル）が表示されてしまう問題がありました。

## 原因の分析
問題の原因はキャッシュ機能にありました：

1. **協力会社の職能データはキャッシュされる**
   - キャッシュキー: `"supplier_skills_for_cost_{$supplier_id}"`
   - キャッシュ時間: 600秒（10分間）

2. **職能の削除時にキャッシュがクリアされていない**
   - `KTPWP_Supplier_Skills::delete_skill()` メソッドは、データベースからは正常に削除
   - しかし、関連するキャッシュをクリアしていない
   - そのため、削除された職能がキャッシュから返され続ける

3. **キャッシュクリアが必要な操作**
   - 職能の追加 (`add_skill`)
   - 職能の更新 (`update_skill`) 
   - 職能の削除 (`delete_skill`)
   - 協力会社の削除時の全職能削除 (`delete_supplier_skills`)

## 修正内容

### 1. `delete_skill` メソッドの修正
**ファイル**: `includes/class-ktpwp-supplier-skills.php`

削除前にsupplier_idを取得し、削除成功後にキャッシュをクリア：

```php
// 削除前にsupplier_idを取得
$supplier_id = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT supplier_id FROM {$table_name} WHERE id = %d",
        $skill_id
    )
);

// 削除処理
$result = $wpdb->delete(...);

// 削除成功後にキャッシュクリア
if ($supplier_id && function_exists('ktpwp_cache_delete')) {
    ktpwp_cache_delete("supplier_skills_for_cost_{$supplier_id}");
}
```

### 2. `add_skill` メソッドの修正
追加成功後にキャッシュをクリア：

```php
$result = $wpdb->insert(...);

// 追加成功後にキャッシュクリア
if (function_exists('ktpwp_cache_delete')) {
    ktpwp_cache_delete("supplier_skills_for_cost_{$supplier_id}");
}
```

### 3. `update_skill` メソッドの修正
更新前にsupplier_idを取得し、更新成功後にキャッシュをクリア：

```php
// 更新前にsupplier_idを取得
$supplier_id = $wpdb->get_var(...);

$result = $wpdb->update(...);

// 更新成功後にキャッシュクリア
if ($supplier_id && function_exists('ktpwp_cache_delete')) {
    ktpwp_cache_delete("supplier_skills_for_cost_{$supplier_id}");
}
```

### 4. `delete_supplier_skills` メソッドの修正
協力会社削除時の全職能削除後にキャッシュをクリア：

```php
$result = $wpdb->delete(...);

// 削除成功後にキャッシュクリア
if (function_exists('ktpwp_cache_delete')) {
    ktpwp_cache_delete("supplier_skills_for_cost_{$supplier_id}");
}
```

## 修正対象ファイル
- `/includes/class-ktpwp-supplier-skills.php`

## 既存の正常動作
以下は既に正常にキャッシュクリアを行っている：
- `wp_ajax_ktpwp_save_supplier_skill_for_cost` (AJAX経由での職能保存)

## 修正後の動作
1. 職能を削除すると、データベースから削除される
2. 同時にキャッシュも自動でクリアされる
3. 次回の協力会社選択ポップアップ表示時に、最新のデータ（削除済み職能は含まない）が表示される

## テスト方法
1. 協力会社に職能を追加
2. 受注書の協力会社選択ポップアップで確認
3. 職能を削除
4. 再度ポップアップを開いて、削除した職能が表示されないことを確認

## 注意事項
- キャッシュクリア機能は `ktpwp_cache_delete` 関数に依存
- 関数が存在しない場合はキャッシュクリアはスキップされる（エラーは発生しない）
- 修正はバックワード互換性を保っている
