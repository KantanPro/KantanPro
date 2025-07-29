/**
 * コスト項目トグル機能のデバッグ用スクリプト
 * ブラウザのコンソールで実行してテストする
 */

// コスト項目トグル機能のテスト
function testCostToggle() {
  console.log('=== コスト項目トグル機能テスト開始 ===');

  // 要素の存在確認
  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  console.log('トグルボタン要素:', costToggleBtn);
  console.log('コンテンツ要素:', costContent);

  if (!costToggleBtn) {
    console.error('トグルボタンが見つかりません - セレクタ: .toggle-cost-items');
    return false;
  }

  if (!costContent) {
    console.error('コンテンツ要素が見つかりません - ID: cost-items-content');
    return false;
  }

  // 現在の状態を確認
  console.log('現在の表示状態:', costContent.style.display);
  console.log('aria-expanded:', costToggleBtn.getAttribute('aria-expanded'));
  console.log('ボタンテキスト:', costToggleBtn.textContent);

  // 翻訳ラベルの確認
  console.log('翻訳ラベル確認:');
  console.log('- ktpwpCostShowLabel:', window.ktpwpCostShowLabel);
  console.log('- ktpwpCostHideLabel:', window.ktpwpCostHideLabel);

  // クリックイベントのテスト
  console.log('クリックイベントのテスト実行...');
  costToggleBtn.click();

  setTimeout(function () {
    console.log('クリック後の状態:');
    console.log('- 表示状態:', costContent.style.display);
    console.log('- aria-expanded:', costToggleBtn.getAttribute('aria-expanded'));
    console.log('- ボタンテキスト:', costToggleBtn.textContent);
    console.log('=== テスト終了 ===');
  }, 100);

  return true;
}

// 手動でトグル機能を実装（既存の機能が動作しない場合の代替）
function setupCostToggleManual() {
  console.log('手動でコスト項目トグル機能を設定中...');

  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  if (!costToggleBtn || !costContent) {
    console.error('必要な要素が見つかりません');
    return false;
  }

  // 既存のイベントリスナーをクリア
  var newButton = costToggleBtn.cloneNode(true);
  costToggleBtn.parentNode.replaceChild(newButton, costToggleBtn);
  costToggleBtn = newButton;

  // 初期状態を設定
  costContent.style.display = 'none';
  costToggleBtn.setAttribute('aria-expanded', 'false');

  // ボタンテキスト更新関数
  function updateButtonText() {
    var itemCount = costContent.querySelectorAll('.cost-items-table tbody tr').length || 0;
    var isExpanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
    var showLabel = '表示';
    var hideLabel = '非表示';

    costToggleBtn.textContent = (isExpanded ? hideLabel : showLabel) + '（' + itemCount + '項目）';
  }

  // クリックイベントを追加
  costToggleBtn.addEventListener('click', function () {
    var expanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
    console.log('トグルボタンクリック - 現在の状態:', expanded ? '展開' : '閉じている');

    if (expanded) {
      costContent.style.display = 'none';
      costToggleBtn.setAttribute('aria-expanded', 'false');
    } else {
      costContent.style.display = 'block';
      costToggleBtn.setAttribute('aria-expanded', 'true');
    }

    updateButtonText();
    console.log('トグル後の状態:', costToggleBtn.getAttribute('aria-expanded') === 'true' ? '展開' : '閉じている');
  });

  // 初期ボタンテキストを設定
  updateButtonText();

  console.log('手動コスト項目トグル機能を設定しました');
  return true;
}

console.log('コスト項目トグルデバッグスクリプトが読み込まれました');
console.log('使用方法:');
console.log('- testCostToggle() : 既存機能のテスト');
console.log('- setupCostToggleManual() : 手動で機能を設定');
