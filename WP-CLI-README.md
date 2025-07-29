# WP-CLI セットアップガイド for KTPWP

## 概要
このプロジェクトでWP-CLIを使用できるようにセットアップしました。

## 初回セットアップ

### 自動セットアップ（推奨）
```bash
./setup-wp-cli.sh
```

### 手動セットアップ
```bash
# WP-CLIをダウンロード
curl -L https://github.com/wp-cli/wp-cli/releases/download/v2.8.1/wp-cli-2.8.1.phar -o wp-cli.phar
chmod +x wp-cli.phar

# エイリアスを読み込み
source wp-cli-aliases.sh
```

## セットアップ内容
- WP-CLI 2.8.1 の設定ファイル
- 設定ファイル (`wp-cli.yml`) 
- ラッパースクリプト (`wp-cli.sh`)
- エイリアス定義 (`wp-cli-aliases.sh`)
- 自動セットアップスクリプト (`setup-wp-cli.sh`)

## 使用方法

### 基本的な使用方法
```bash
# WordPressのバージョンを確認
php wp-cli.phar --path=/Users/kantanpro/KantanProLoalTest/wp core version

# プラグイン一覧を表示
php wp-cli.phar --path=/Users/kantanpro/KantanProLoalTest/wp plugin list

# テーマ一覧を表示  
php wp-cli.phar --path=/Users/kantanpro/KantanProLoalTest/wp theme list
```

### ラッパースクリプトを使用（推奨）
```bash
# ラッパースクリプトを使用する場合
./wp-cli.sh core version
./wp-cli.sh plugin list
./wp-cli.sh theme list
```

## 主要なWP-CLIコマンド

### コア操作
```bash
# WordPressのバージョン確認
./wp-cli.sh core version

# WordPressの更新チェック
./wp-cli.sh core check-update

# データベースの最適化
./wp-cli.sh db optimize
```

### プラグイン管理
```bash
# プラグイン一覧
./wp-cli.sh plugin list

# プラグインの有効化
./wp-cli.sh plugin activate ktpwp

# プラグインの無効化
./wp-cli.sh plugin deactivate plugin-name
```

### データベース操作
```bash
# データベースの検索・置換
./wp-cli.sh search-replace 'old-url.com' 'new-url.com'

# データベースのエクスポート
./wp-cli.sh db export backup.sql

# データベースのインポート
./wp-cli.sh db import backup.sql
```

### ユーザー管理
```bash
# ユーザー一覧
./wp-cli.sh user list

# 管理者ユーザーの作成
./wp-cli.sh user create admin admin@example.com --role=administrator

# パスワードの変更
./wp-cli.sh user update admin --user_pass=newpassword
```

### キャッシュ操作
```bash
# オブジェクトキャッシュのクリア
./wp-cli.sh cache flush

# リライトルールの更新
./wp-cli.sh rewrite flush
```

## 注意事項

1. **Docker環境**: このプロジェクトはDocker環境で動作しているため、WP-CLIを使用する前にDockerコンテナが起動していることを確認してください。

2. **データベース接続**: データベース接続エラーが発生する場合は、`wp-cli.yml`のデータベース設定を環境に合わせて調整してください。

3. **パス設定**: すべてのコマンドはWordPressのルートディレクトリ (`/Users/kantanpro/KantanProLoalTest/wp`) を参照しています。

## トラブルシューティング

### データベース接続エラーが発生する場合
1. Dockerコンテナが起動していることを確認
2. `wp-cli.yml`のデータベース設定を確認
3. 直接wp-config.phpの設定を使用する場合は、wp-cli.ymlの`core config`セクションをコメントアウト

###権限エラーが発生する場合
```bash
chmod +x wp-cli.phar
chmod +x wp-cli.sh
```

### パスエラーが発生する場合
絶対パスを使用してください：
```bash
php /Users/kantanpro/KantanProLoalTest/wp/wp-content/plugins/KTPWP/wp-cli.phar --path=/Users/kantanpro/KantanProLoalTest/wp [command]
```

## よく使用するコマンド例

```bash
# プラグインの状態確認
./wp-cli.sh plugin status ktpwp

# オプションの確認
./wp-cli.sh option get siteurl
./wp-cli.sh option get home

# 投稿の確認
./wp-cli.sh post list --post_type=post --posts_per_page=5

# メディアの確認  
./wp-cli.sh media list
```
