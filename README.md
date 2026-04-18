# KantanPro (KTPWP) - Version 1.2.50

WordPressで動作する業務管理・受注進捗・請求・顧客・サービス・協力会社・レポート・スタッフチャットまで一元管理できる多機能プラグイン。

- **Requires:** WordPress 5.0+ / PHP 7.4+
- **Tested up to:** 6.9.1
- **License:** GPL v2 or later

固定ページに `[ktpwp_all_tab]` を設置して利用します。詳細は同梱の `readme.txt` を参照してください。

---

## 変更履歴

### Version 1.2.50 - 2026年4月19日

- プラグイン「更新」時に削除確認が出ないよう、`upgrade-plugin` / `update-selected` / `update.php` を明示除外し、カスタム確認は `plugins.php` の削除アクションにのみ表示

### Version 1.2.49 - 2026年4月19日

- プラグイン一覧から「更新」する際に、削除・アンインストール用の確認ダイアログが誤表示されないよう修正（`action=delete` / `action=delete-selected` のときのみカスタム確認を表示）

### Version 1.2.48 - 2026年4月19日

- プラグイン更新後、管理画面に出ていた「データベースの反映だけ未完了／KantanPro設定でデータベースを更新」旨の案内通知を廃止（`ktpwp_upgrade_error` の表示・トランジェント設定を削除）。技術的な失敗内容はオプションへの記録を維持

### Version 1.2.47 - 2026年4月19日

- **アセット読み込みの最適化**（`includes/class-ktpwp-assets.php`）
  - フロント: `ktpwp_should_enqueue_frontend_assets` で読み込み可否をフィルター拡張。KantanPro ページ以外では干渉防止・コンソール抑制・AJAX 設定・SVG スタイルを出力しないようガード
  - 管理画面: KantanPro 関連画面のみ本体 CSS/JS を読み込み（`ktpwp_is_kantanpro_admin_screen` で拡張可能）。不要な管理画面での読み込みを削減
  - 受注書以外のタブでは jQuery UI Sortable を読み込まないよう条件分岐
  - `ktp-cost-items` のバージョンを `filemtime` ベースに変更
  - 全ページ読み込みデバッグを撤去し、本来のページ判定に復帰
  - AJAX 設定の `console.log` は verbose 時のみ

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
