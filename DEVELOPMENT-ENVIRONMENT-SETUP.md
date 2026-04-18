# KantanPro 開発環境セットアップガイド

## 概要

KantanProプラグインでは、配布先での誤動作を防ぐため、厳格なローカル開発環境判定システムを実装しています。

## 開発環境判定の仕組み

### 判定の優先順位

1. **`KTPWP_DEVELOPMENT_MODE` 定数**による明示的な指定
2. **`WP_ENV` 定数**による指定
3. **厳格なローカル開発環境の判定**
4. **Docker環境でのローカル開発環境マーカー**

### 厳格な判定条件

開発環境として認識されるには、以下の条件を満たす必要があります：

#### 通常のローカル環境
- ホスト名が `localhost`, `127.0.0.1`, `[::1]` のいずれか
- `WP_DEBUG` が `true` に設定されている
- または `.local-development` マーカーファイルが存在する

#### Docker環境
以下の4つの条件のうち3つ以上を満たす必要があります：
1. `WP_DEBUG` が `true` に設定されている
2. ローカル開発を示す環境変数またはファイルが存在する
3. サーバーIPがローカル範囲内（10.x.x.x, 172.16-31.x.x, 192.168.x.x等）
4. ホスト名がローカル開発環境を示している

## 設定方法

### 方法1: wp-config.phpで定数を設定（推奨）

```php
// wp-config.phpに以下を追加
define( 'KTPWP_DEVELOPMENT_MODE', true );
define( 'WP_DEBUG', true );
```

### 方法2: 環境変数を設定

```bash
# .envファイルまたはdocker-compose.ymlで設定
KTPWP_LOCAL_DEV=true
DOCKER_LOCAL_DEV=true
WP_ENV=development
```

### 方法3: マーカーファイルの使用

プラグインディレクトリに `.local-development` ファイルが自動作成されています。
このファイルの存在により、ローカル開発環境として認識されます。

## Docker環境での設定例

### docker-compose.yml
```yaml
version: '3.8'
services:
  wordpress:
    environment:
      - KTPWP_LOCAL_DEV=true
      - DOCKER_LOCAL_DEV=true
      - WP_ENV=development
      - WP_DEBUG=true
```

### Dockerfile
```dockerfile
ENV KTPWP_LOCAL_DEV=true
ENV DOCKER_LOCAL_DEV=true
ENV WP_ENV=development
```

## セキュリティ注意事項

### 本番環境での注意点

1. **`.local-development` ファイルを削除**
   - 本番環境にアップロード時は必ず削除してください
   
2. **定数設定の確認**
   - `KTPWP_DEVELOPMENT_MODE` を `false` に設定するか削除
   - `WP_DEBUG` を `false` に設定
   
3. **環境変数の確認**
   - 開発用の環境変数が設定されていないことを確認

### 自動配布時の対応

`create_release_zip.sh` スクリプトでは、以下のファイルが自動的に除外されます：
- `.local-development`
- `DEVELOPMENT-ENVIRONMENT-SETUP.md`
- その他の開発用ファイル

## トラブルシューティング

### 開発環境で「開発環境」と表示されない場合

1. `WP_DEBUG` が `true` に設定されていることを確認
2. `.local-development` ファイルが存在することを確認
3. ホスト名が適切に設定されていることを確認
4. wp-config.phpで `KTPWP_DEVELOPMENT_MODE` を明示的に設定

### 本番環境で「開発環境」と表示される場合

1. `.local-development` ファイルが存在しないことを確認
2. `KTPWP_DEVELOPMENT_MODE` が `false` または未定義であることを確認
3. `WP_ENV` が `development` に設定されていないことを確認
4. 開発用の環境変数が設定されていないことを確認

## デバッグ方法

開発環境判定の詳細ログを確認するには：

```php
// wp-config.phpに追加
define( 'WP_DEBUG_LOG', true );
```

ログファイル（`/wp-content/debug.log`）に判定結果が記録されます。

## サポート

問題が解決しない場合は、以下の情報と共にサポートまでお問い合わせください：
- WordPress環境の詳細
- wp-config.phpの設定
- エラーログの内容
- 開発環境の構成（Docker等） 