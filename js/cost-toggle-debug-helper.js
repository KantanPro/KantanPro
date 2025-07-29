// コスト項目トグルのデバッグとテスト用ヘルパー関数

// コンソールでのテスト用関数
window.testCostToggle = function () {
  console.log('=== コスト項目トグルテスト開始 ===');

  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  console.log('トグルボタン:', costToggleBtn);
  console.log('コンテンツ:', costContent);

  if (!costToggleBtn) {
    console.error('トグルボタンが見つかりません');
    return false;
  }

  if (!costContent) {
    console.error('コストコンテンツが見つかりません');
    return false;
  }

  // 現在の状態を表示
  console.log('現在の状態:', {
    display: costContent.style.display,
    ariaExpanded: costToggleBtn.getAttribute('aria-expanded'),
    buttonText: costToggleBtn.textContent
  });

  // クリックをシミュレート
  console.log('クリックをシミュレート...');
  costToggleBtn.click();

  // クリック後の状態を表示
  console.log('クリック後の状態:', {
    display: costContent.style.display,
    ariaExpanded: costToggleBtn.getAttribute('aria-expanded'),
    buttonText: costToggleBtn.textContent
  });

  console.log('=== テスト完了 ===');
  return true;
};

// 手動でトグル機能を設定する関数（緊急時用）
window.setupCostToggleManual = function () {
  console.log('手動でコスト項目トグルを設定中...');

  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  if (!costToggleBtn || !costContent) {
    console.error('必要な要素が見つかりません');
    return false;
  }

  // 既存のイベントリスナーを削除するため、ボタンを複製
  var newBtn = costToggleBtn.cloneNode(true);
  costToggleBtn.parentNode.replaceChild(newBtn, costToggleBtn);
  costToggleBtn = newBtn;

  // 初期状態を設定
  costContent.style.display = 'none';
  costToggleBtn.setAttribute('aria-expanded', 'false');

  // クリックイベントを追加
  costToggleBtn.addEventListener('click', function (e) {
    e.preventDefault();
    var expanded = costToggleBtn.getAttribute('aria-expanded') === 'true';

    if (expanded) {
      costContent.style.display = 'none';
      costToggleBtn.setAttribute('aria-expanded', 'false');
      costToggleBtn.textContent = '表示';
    } else {
      costContent.style.display = 'block';
      costToggleBtn.setAttribute('aria-expanded', 'true');
      costToggleBtn.textContent = '非表示';
    }

    console.log('トグル実行:', {
      display: costContent.style.display,
      ariaExpanded: costToggleBtn.getAttribute('aria-expanded')
    });
  });

  costToggleBtn.textContent = '表示';
  console.log('手動設定完了');
  return true;
};

// ページロード後に自動実行されるデバッグ情報
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    console.log('=== コスト項目トグル デバッグ情報 ===');
    console.log('利用可能なテスト関数:');
    console.log('- testCostToggle(): トグル機能をテスト');
    console.log('- setupCostToggleManual(): 手動で機能を設定');

    var costToggleBtn = document.querySelector('.toggle-cost-items');
    var costContent = document.getElementById('cost-items-content');

    console.log('要素の存在確認:', {
      toggleButton: !!costToggleBtn,
      costContent: !!costContent
    });

    if (costToggleBtn && costContent) {
      console.log('初期状態:', {
        buttonText: costToggleBtn.textContent,
        contentDisplay: costContent.style.display,
        ariaExpanded: costToggleBtn.getAttribute('aria-expanded')
      });
    }

    console.log('翻訳ラベル:', {
      showLabel: window.ktpwpCostShowLabel,
      hideLabel: window.ktpwpCostHideLabel
    });

    console.log('================================');
  }, 1000);
});
