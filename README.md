# KantanPro (KTPWP) - Version 1.2.46

WordPressで動作する業務管理・受注進捗・請求・顧客・サービス・協力会社・レポート・スタッフチャットまで一元管理できる多機能プラグイン。

- **Requires:** WordPress 5.0+ / PHP 7.4+
- **Tested up to:** 6.9.1
- **License:** GPL v2 or later

固定ページに `[ktpwp_all_tab]` を設置して利用します。詳細は同梱の `readme.txt` を参照してください。

---

## 変更履歴

### Version 1.2.46 - 2026年4月18日

- **レポートタブのレイアウトを協力会社タブと揃える修正**
  - `css/ktp-report.css` の `#report_content { margin-top: 8px !important; }` がタブパネル全体に効き、メインタブと本文の間に隙間が出る問題を修正（`.ktp_plugin_container .tab_content#report_content` にスコープし、上方向の margin / padding を 0 に）
  - `styles.css` で `#report_content` を受注書用の下マージン付きルールから分離し、協力会社等のタブと同じ余白ルールに統一
  - レポートのサブタブ行（`generate_controller`）を協力会社と同様、外側 `.controller` にインライン style を付けず `.ktp-report-controller` でレイアウト
  - モバイル・印刷用の `#report_content` 指定を本文カード（`.ktp-report-print-area`）中心に整理
- **コントローラー周りの過剰な全タブ共通 `!important` 上書きを整理**

### Version 1.2.45 - 2026年4月18日

- サービス／協力会社タブのメモ欄フリーズ対策（干渉プラグイン除外、JS条件読み込み、textarea ガード、console 抑制 等）
- プラグイン削除時のデータ保持設定（一般設定・`uninstall.php`・一覧バッジ・削除確認）

（それ以前の履歴は `readme.txt` の変更履歴を参照）

---

## リポジトリ・配布

プラグイン本体の詳細ドキュメント・インストール手順・FAQ は **`readme.txt`**（WordPress プラグインディレクトリ形式）に記載しています。
