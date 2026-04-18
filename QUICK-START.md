# KTPWP - WP-CLI クイックスタートガイド

## 🚀 初回セットアップ

### 1. WP-CLIをダウンロード
```bash
# 自動セットアップスクリプトを実行
./setup-wp-cli.sh
```

または手動でダウンロード：
```bash
curl -L https://github.com/wp-cli/wp-cli/releases/download/v2.8.1/wp-cli-2.8.1.phar -o wp-cli.phar
chmod +x wp-cli.phar
```

### 2. エイリアスを読み込む（推奨）
```bash
source wp-cli-aliases.sh
```

### 3. 基本コマンドをテスト
```bash
# WordPress情報を確認
wp --info

# WordPressバージョンを確認  
wp-version

# 利用可能なエイリアスを表示
wp-help-aliases
```

## 📋 よく使用するコマンド

### 基本情報の確認
```bash
wp-version          # WordPressバージョン
wp-plugins          # プラグイン一覧
wp-themes           # テーマ一覧
wp-users            # ユーザー一覧
```

### プラグイン管理
```bash
wp plugin status ktpwp                    # KTPWPプラグインの状態
wp plugin activate ktpwp                 # プラグインを有効化
wp plugin deactivate ktpwp               # プラグインを無効化
wp-plugin-active                         # 有効なプラグイン一覧
wp-plugin-inactive                       # 無効なプラグイン一覧
```

### データベース操作
```bash
wp-dbsize           # データベースサイズ確認
wp-dbcheck          # データベース整合性チェック
wp-dboptimize       # データベース最適化
wp db export backup.sql                  # データベースバックアップ
```

### 設定管理
```bash
wp option get siteurl                     # サイトURL確認
wp option get home                        # ホームURL確認
wp option list | grep ktp                # KTPWP関連オプション確認
```

### キャッシュとリライト
```bash
wp-flush            # キャッシュクリア + リライトルール更新
wp cache flush      # オブジェクトキャッシュクリア
wp rewrite flush    # リライトルール更新
```

## 🛠️ 開発者向けコマンド

### デバッグモード
```bash
wp-debug-on         # デバッグモードオン
wp-debug-off        # デバッグモードオフ
wp config get WP_DEBUG                   # 現在のデバッグ設定確認
```

### 投稿とメディア
```bash
wp post list --post_type=post --posts_per_page=5   # 最新の投稿5件
wp media list                                       # メディア一覧
wp post create --post_title="テスト投稿" --post_content="テスト内容"
```

### ユーザー管理
```bash
wp user create testuser test@example.com --role=editor  # ユーザー作成
wp user update admin --user_pass=newpassword           # パスワード変更
wp user list --role=administrator                      # 管理者ユーザー一覧
```

## 🔧 トラブルシューティング

### データベース接続エラーが発生する場合
1. Dockerコンテナが起動していることを確認
2. `wp-cli.yml`のデータベース設定を確認
3. 以下のコマンドで接続テスト：
```bash
wp db check
```

### より詳細なデバッグが必要な場合
```bash
wp --debug [コマンド]    # デバッグ情報付きで実行
```

## 📁 ファイル構成

- `wp-cli.phar` - WP-CLI本体
- `wp-cli.yml` - 設定ファイル
- `wp-cli.sh` - ラッパースクリプト
- `wp-cli-aliases.sh` - エイリアス定義
- `WP-CLI-README.md` - 詳細なドキュメント

## 💡 ヒント

1. **毎回エイリアスを読み込むのが面倒な場合**:
   ```bash
   echo 'source /Users/kantanpro/KantanProLoalTest/wp/wp-content/plugins/KTPWP/wp-cli-aliases.sh' >> ~/.zshrc
   ```

2. **直接WP-CLIを使用したい場合**:
   ```bash
   php wp-cli.phar --path=/Users/kantanpro/KantanProLoalTest/wp [コマンド]
   ```

3. **設定ファイルをカスタマイズしたい場合**:
   `wp-cli.yml`を編集してデータベース設定やその他のオプションを調整

## 🎯 次のステップ

- データベースが利用可能になったら、より高度なコマンドを試してみてください
- プラグイン開発時の自動化スクリプトにWP-CLIを統合
- バックアップとデプロイメントプロセスでWP-CLIを活用

---
**セットアップ完了日**: 2025年6月10日  
**WP-CLIバージョン**: 2.8.1  
**対象WordPressバージョン**: 6.8.1
