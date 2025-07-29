# 自動マイグレーション機能実装完了レポート

## 実装概要
プラグインの有効化・更新時に自動でマイグレーションが実行される機能を実装し、不特定多数のサイトへの配布に対応しました。

## 実装日時
2025年7月15日

## 実装内容

### 1. 税区分マイグレーションの追加

#### 1.1 新しいマイグレーションファイル
**ファイル**: `includes/migrations/20250715_add_tax_category_to_client.php`

**機能**:
- 顧客テーブル（`wp_ktp_client`）に税区分カラム（`tax_category`）を追加
- 税区分は「内税」「外税」のENUM型で定義
- デフォルト値は「内税」
- 既存の顧客データには「内税」を設定

**実装詳細**:
```php
class KTPWP_Migration_20250715_Add_Tax_Category_To_Client {
    public static function up() {
        // 税区分カラムの追加
        $sql = "ALTER TABLE `{$client_table}` ADD COLUMN `tax_category` ENUM('内税', '外税') NOT NULL DEFAULT '内税' COMMENT '税区分（内税・外税）' AFTER `email`";
        
        // 既存データの更新
        $update_result = $wpdb->query( "UPDATE `{$client_table}` SET `tax_category` = '内税' WHERE `tax_category` IS NULL OR `tax_category` = ''" );
    }
    
    public static function needs_migration() {
        // マイグレーションが必要かどうかの判定
    }
}
```

### 2. 自動マイグレーション機能の強化

#### 2.1 クラスベースマイグレーションの自動実行
**ファイル**: `ktpwp.php`

**修正内容**:
- マイグレーションファイルの読み込み時にクラスベースのマイグレーションを自動実行
- クラス名の自動生成とメソッドの存在確認
- エラーハンドリングとログ出力

**実装詳細**:
```php
// クラスベースのマイグレーションを実行
$filename = basename( $file, '.php' );
$class_name = 'KTPWP_Migration_' . str_replace( '-', '_', $filename );

if ( class_exists( $class_name ) && method_exists( $class_name, 'up' ) ) {
    $result = $class_name::up();
    // 結果のログ出力
}
```

#### 2.2 プラグイン有効化時の自動マイグレーション
**修正内容**:
- `ktpwp_plugin_activation`関数に自動マイグレーション実行を追加
- プラグイン有効化時に最新のマイグレーションが実行される

```php
function ktpwp_plugin_activation() {
    // 基本テーブル作成
    ktp_table_setup();
    
    // 自動マイグレーションの実行
    ktpwp_run_auto_migrations();
    
    // バージョン情報の更新
    update_option( 'ktpwp_db_version', KANTANPRO_PLUGIN_VERSION );
}
```

#### 2.3 プラグイン読み込み時の自動マイグレーション
**修正内容**:
- `plugins_loaded`フックに自動マイグレーション実行を追加
- プラグイン更新時に自動でマイグレーションが実行される

```php
// プラグイン読み込み時の自動マイグレーション
add_action( 'plugins_loaded', 'ktpwp_run_auto_migrations', 8 );
```

### 3. 既存のマイグレーション機能

#### 3.1 税率カラム追加マイグレーション
**ファイル**: `includes/migrations/20250131_add_tax_rate_columns.php`

**機能**:
- 請求項目テーブルに税率カラムを追加
- コスト項目テーブルに税率カラムを追加
- サービステーブルに税率カラムを追加
- デフォルト税率設定の追加

#### 3.2 部署テーブル関連マイグレーション
**機能**:
- 部署テーブルの作成
- 選択状態カラムの追加
- 顧客テーブルへの部署IDカラム追加

### 4. マイグレーション実行タイミング

#### 4.1 プラグイン有効化時
- `register_activation_hook`で`ktpwp_plugin_activation`を実行
- 新規インストール時に必要なテーブルとカラムを作成

#### 4.2 プラグイン読み込み時
- `plugins_loaded`フックで`ktpwp_run_auto_migrations`を実行
- プラグイン更新時に差分マイグレーションを実行

#### 4.3 プラグイン更新時
- `upgrader_process_complete`フックで`ktpwp_plugin_upgrade_migration`を実行
- 更新時に必要なマイグレーションを実行

### 5. データベースバージョン管理

#### 5.1 バージョン比較
- 現在のDBバージョンとプラグインバージョンを比較
- DBバージョンが古い場合のみマイグレーションを実行

```php
$current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
$plugin_version = KANTANPRO_PLUGIN_VERSION;

if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {
    // マイグレーション実行
}
```

#### 5.2 バージョン更新
- マイグレーション完了後にDBバージョンを更新
- 重複実行を防止

### 6. エラーハンドリングとログ

#### 6.1 エラーハンドリング
- try-catch文でマイグレーション実行を保護
- エラー発生時のログ出力

#### 6.2 ログ出力
- WP_DEBUG有効時に詳細なログを出力
- マイグレーション実行状況の追跡

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: Successfully executed ' . basename( $file ) );
}
```

## テスト結果

### 1. 自動マイグレーション機能テスト
**ファイル**: `test_auto_migration.php`

**テスト項目**:
- プラグイン有効化フックの確認
- 自動マイグレーション関数の存在確認
- マイグレーションファイルの存在確認
- データベースバージョン管理の確認
- マイグレーションクラスの確認
- フック登録の確認
- 手動マイグレーション実行テスト

**結果**: 全てのテストが合格

### 2. 動作確認項目

#### 2.1 新規インストール
- [x] プラグイン有効化時に必要なテーブルが作成される
- [x] 税率カラムが追加される
- [x] 税区分カラムが追加される
- [x] デフォルト値が正しく設定される

#### 2.2 プラグイン更新
- [x] プラグイン更新時にマイグレーションが実行される
- [x] 既存データが保持される
- [x] 新しいカラムが追加される

#### 2.3 データ整合性
- [x] 既存データの整合性が保たれる
- [x] デフォルト値が正しく設定される
- [x] エラーが発生しない

## 配布準備

### 1. 不特定多数サイトへの対応
- 自動マイグレーション機能により、手動操作不要
- データベース構造の自動更新
- 既存データの保護

### 2. エラー耐性
- エラーハンドリングによる安全な実行
- ログ出力による問題の追跡
- 重複実行の防止

### 3. パフォーマンス
- 必要な場合のみマイグレーション実行
- 効率的なバージョン比較
- 最小限のデータベース操作

## 使用方法

### 1. プラグイン有効化
```php
// 自動的に実行される
register_activation_hook( __FILE__, 'ktpwp_plugin_activation' );
```

### 2. プラグイン更新
```php
// 自動的に実行される
add_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration', 10, 2 );
```

### 3. 手動実行（開発時）
```php
// 手動でマイグレーションを実行
ktpwp_run_auto_migrations();
```

## 今後の拡張

### 1. 新しいマイグレーションの追加
1. `includes/migrations/`ディレクトリに新しいマイグレーションファイルを作成
2. クラス名を`KTPWP_Migration_YYYYMMDD_Description`の形式で命名
3. `up()`メソッドと`needs_migration()`メソッドを実装
4. 自動的に実行される

### 2. ロールバック機能
- `down()`メソッドの実装によりロールバックが可能
- 必要に応じて手動ロールバック機能を追加

### 3. マイグレーション履歴
- 実行済みマイグレーションの記録
- 詳細なログ出力

## 結論

自動マイグレーション機能の実装が完了し、プラグインの有効化・更新時に自動でマイグレーションが実行されるようになりました。これにより、不特定多数のサイトへの配布が可能になり、ユーザーは手動操作なしで最新の機能を利用できます。

**実装完了**: ✅ **自動マイグレーション機能が正常に動作します** 