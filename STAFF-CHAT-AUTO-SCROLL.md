# スタッフチャット自動スクロール機能

## 概要
スタッフチャットでメッセージ送信後、ページ全体をリダイレクトすることなく、最新メッセージまで自動でスクロールする機能を実装しました。

## 実装した機能

### 1. AJAX ベースのメッセージ送信
- 従来のPOST送信からAJAX送信に変更
- リダイレクトを行わずに動的にメッセージを追加
- 送信中の視覚的フィードバック（ボタン無効化、透明度変更）

### 2. 動的メッセージ追加
- メッセージ送信成功後、即座にチャットエリアにメッセージを追加
- リアルタイムでメッセージ数を反映
- 自動で最下部までスクロール

### 3. 自動スクロール機能
- メッセージ送信後、自動的にチャットエリアの最下部にスクロール
- ローカルストレージを使ったフォールバック機能
- チャットセクションへのページスクロールも実装

### 4. キーボードショートカット
- `Ctrl+Enter`（Windows/Linux）または `Cmd+Enter`（Mac）で送信
- 入力中のリアルタイム送信ボタン状態更新

## ファイル変更内容

### JavaScript (/js/ktp-js.js)
- `escapeHtml()` 関数を追加
- AJAX フォーム送信処理を実装
- 動的メッセージ追加機能
- 自動スクロール機能
- ローカルストレージによるスクロール状態管理

### PHP (/includes/class-ktpwp-ajax.php)
- 現在のユーザー情報をAJAX設定に追加
- `current_user` プロパティをJavaScriptで利用可能に

### PHP (/includes/class-tab-order.php)
- 従来のPOST送信処理をコメントアウト
- AJAX専用の実装に移行

## 使用方法

### 基本的な使用方法
1. 受注詳細画面でスタッフチャットを開く
2. メッセージを入力
3. 「送信」ボタンをクリックまたは `Ctrl+Enter` で送信
4. 自動的に最新メッセージまでスクロール

### キーボードショートカット
- `Ctrl+Enter` (Windows/Linux) または `Cmd+Enter` (Mac): メッセージ送信

## テスト方法

### ブラウザコンソールでのテスト
```javascript
// 基本機能テスト
testStaffChatAutoScroll();

// モックメッセージ送信テスト
testMockMessageSend();
```

### 手動テスト
1. 受注詳細画面を開く
2. スタッフチャットセクションでメッセージを送信
3. リダイレクトされずに即座にメッセージが表示されることを確認
4. 自動的に最下部にスクロールされることを確認

## トラブルシューティング

### よくある問題

#### メッセージが送信されない
- JavaScriptエラーがないかコンソールを確認
- AJAX設定（`ktpwp_ajax`）が正しく読み込まれているか確認
- ログイン状態を確認

#### 自動スクロールが動作しない
- スタッフチャットが開いた状態になっているか確認
- メッセージコンテナ（`#staff-chat-messages`）が存在するか確認
- ローカルストレージが利用可能か確認

#### 送信ボタンが無効化されたまま
- ページを再読み込み
- ネットワークエラーがないか確認

### デバッグ方法
```javascript
// AJAX設定の確認
console.log(ktpwp_ajax);

// 要素の存在確認
console.log('Form:', document.getElementById('staff-chat-form'));
console.log('Input:', document.getElementById('staff-chat-input'));
console.log('Messages:', document.getElementById('staff-chat-messages'));

// ローカルストレージの確認
console.log('LocalStorage test:', localStorage.getItem('ktp_scroll_to_chat'));
```

## 今後の改善案

1. **リアルタイム更新**: WebSocketを使った他のユーザーからのメッセージのリアルタイム表示
2. **メッセージ履歴**: 無限スクロールによる過去メッセージの読み込み
3. **通知機能**: 新着メッセージの通知バッジ
4. **ファイルアップロード**: メッセージへのファイル添付機能

## 互換性

- モダンブラウザ（Chrome, Firefox, Safari, Edge）
- WordPressの最新バージョン
- jQuery非依存のVanilla JavaScript実装
