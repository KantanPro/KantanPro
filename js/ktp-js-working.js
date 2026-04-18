document.addEventListener('DOMContentLoaded', function () {
  console.log('KTPWP: DOM loaded, initializing toggle functionality');

  // スクロールタイマーを保存する変数（グローバルスコープ）
  window.scrollTimeouts = [];

  // スクロールタイマーをクリアする関数（グローバルスコープ）
  window.clearScrollTimeouts = function () {
    window.scrollTimeouts.forEach(function (timeout) {
      clearTimeout(timeout);
    });
    window.scrollTimeouts = [];
  };

  // 通知バッジを削除（グローバルスコープ）
  window.hideNewMessageNotification = function () {
    var toggleBtn = document.getElementById('staff-chat-toggle-btn');
    if (!toggleBtn) return;

    var badge = toggleBtn.querySelector('.staff-chat-notification-badge');
    if (badge) {
      badge.remove();
    }
  };

  // コスト項目トグル
  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  console.log('KTPWP: Cost toggle elements found:', {
    button: !!costToggleBtn,
    content: !!costContent
  });

  if (costToggleBtn && costContent) {
    console.log('KTPWP: Setting up cost toggle functionality');

    // 初期状態を非表示に設定
    costContent.style.display = 'none';
    costToggleBtn.setAttribute('aria-expanded', 'false');

    // 項目数を取得してボタンテキストに追加
    var updateCostButtonText = function () {
      var itemCount = costContent.querySelectorAll('.cost-items-table tbody tr').length || 0;
      var showLabel = costToggleBtn.dataset.showLabel || window.ktpwpCostShowLabel || '表示';
      var hideLabel = costToggleBtn.dataset.hideLabel || window.ktpwpCostHideLabel || '非表示';
      var isExpanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
      var buttonText = (isExpanded ? hideLabel : showLabel) + '（' + itemCount + '項目）';
      costToggleBtn.textContent = buttonText;
      console.log('KTPWP: Button text updated to:', buttonText);
    };

    costToggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      console.log('KTPWP: Cost toggle button clicked');

      var expanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        costContent.style.display = 'none';
        costToggleBtn.setAttribute('aria-expanded', 'false');
        console.log('KTPWP: Cost content hidden');
      } else {
        costContent.style.display = 'block';
        costToggleBtn.setAttribute('aria-expanded', 'true');
        console.log('KTPWP: Cost content shown');
      }
      updateCostButtonText();
    });

    // 国際化ラベルを設定
    if (typeof window.ktpwpCostShowLabel !== 'undefined') {
      costToggleBtn.dataset.showLabel = window.ktpwpCostShowLabel;
    }
    if (typeof window.ktpwpCostHideLabel !== 'undefined') {
      costToggleBtn.dataset.hideLabel = window.ktpwpCostHideLabel;
    }

    // 初期状態のボタンテキストを設定
    updateCostButtonText();

    console.log('KTPWP: Cost toggle setup complete');
  } else {
    console.log('KTPWP: Cost toggle elements not found - button:', !!costToggleBtn, 'content:', !!costContent);
  }

  console.log('KTPWP: Initialization complete');
});

// 成功通知を表示する関数
window.showSuccessNotification = function (message) {
  var notification = document.createElement('div');
  notification.className = 'success-notification';
  notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; z-index: 10000; font-size: 14px;';
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(function () {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 3000);
};
