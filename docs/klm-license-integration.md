# ライセンス認証（KLM 連携）実装・調査ガイド

> 旧 readme.txt から移動した社内向けドキュメント。WordPress.org の readme には掲載しない。

- **対象**: 任意のプラグインから KLM（KantanPro License Manager）へのライセンス認証
- **参考**: [KantanPro公式サイト](https://www.kantanpro.com/)

--- 基本仕様 ---
- **エンドポイント（維持）**: POST `/wp-json/ktp-license/v1/verify`
- **Content-Type**: `application/x-www-form-urlencoded`
- **パラメータ**:
  - `license_key`（必須）
  - `site_url`（任意だが推奨。紐付け比較あり）
  - `plugin_version`（任意）
- **レスポンス（例）**:
  - 成功: `{ success:true, valid:true, message, data }`
  - 失敗: `{ success:false, valid:false, message, error_code }`

--- 実装サンプル（cURL） ---
`--data-urlencode` でURLエンコード必須。記号やパイプ(`|`)が含まれても安全に送信されます。
```bash
curl -sS -X POST \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'license_key=PREFIX-123456-ABCDEFGH-1234' \
  --data-urlencode 'site_url=https://example.com' \
  --data-urlencode 'plugin_version=1.2.3' \
  https://www.kantanpro.com/wp-json/ktp-license/v1/verify
```

--- 失敗しやすいポイント（必読） ---
1) **エンコード形式ミス**
   - JSON送信や未エンコード送信でキーが破損（例: `+`→空白、`|`欠落）
2) **文字種の揺れ**
   - 大文字/小文字、全角/半角、ゼロ幅スペース、Unicodeハイフン（‐, –, —）
   - 原則「英大文字・数字・ハイフン」基準。大文字化・不可視文字除去・ダッシュ統一を推奨
3) **site_url 不一致**
   - 比較は正規化（http/https、www有無、末尾スラッシュ差は無視）。別サイトなら `site_mismatch`
4) **事前バリデーションが厳しすぎる**
   - クライアント側は「非空」または緩和。KLM側に最終判定を委譲
5) **レート制限/タイムアウト**
   - レート制限: 1時間に100回。指数バックオフ/再試行を実装
6) **中継/セキュリティ層**
   - WAF/CDN/プロキシが `|`, `<`, `>` をブロック/改変。ログで到達ペイロードを確認

--- 期待挙動（受け入れ基準） ---
- **生成と検証の一致（100%）**
- **記号を含む既存キー**はKLM側フォールバックで受理可能（`invalid_format`は原則返さない）
- **site_url** は正規化比較で誤検知最小化
- 失敗時は必ず `message` と `error_code` を返却

--- 切り分けのために添えてほしい情報（報告テンプレ） ---
- **license_key**: `XXXX-XXXXXX-XXXX...`（先頭以外は一部マスク可）
- **site_url**: 実際に送った値
- **plugin_version**: 例 `2.3.37`
- **実行時刻**（JST ±10分）
- **送信方式**: `application/x-www-form-urlencoded`（YES/NO）
- **エンコード方法**: `--data-urlencode` 相当（YES/NO）
- **レスポンス原文**（HTTPステータス/JSON）
- **ネットワーク**: CDN/WAF/プロキシの有無

--- デバッグ用（任意） ---
到達と内部判定を可視化します。
```bash
curl -sS -X POST \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'license_key=PREFIX-123456-ABCDEFGH-1234' \
  --data-urlencode 'site_url=https://example.com' \
  https://www.kantanpro.com/wp-json/ktp-license/v1/debug
```

--- 実装チェックリスト ---
- [ ] フォームエンコード（`--data-urlencode`）で送信している
- [ ] 送信前にトリム/大文字化/不可視文字除去を実施
- [ ] 事前バリデーションは「非空のみ」または緩和（KLMへ委譲）
- [ ] レスポンスの `success/valid/error_code` を正しく判定
- [ ] レート制限時のバックオフ/再試行を実装
- [ ] WAF/CDNの例外設定（該当リクエストパス/メソッド/パラメータ）

--- 備考 ---
- `site_url` は KLM側で正規化比較（http/https、www、末尾スラッシュ差は一致扱い）
- 既存キー（記号含む）は KLM側フォールバックで最大限受理（`invalid_format` は原則出ません）

* WordPress 5.0 以上（推奨：最新版）
* PHP 7.4 以上（推奨：PHP 8.0以上）
* MySQL 5.6 以上 または MariaDB 10.0 以上
* 推奨メモリ: 256MB 以上
* 推奨PHP拡張: GD（画像処理用）

