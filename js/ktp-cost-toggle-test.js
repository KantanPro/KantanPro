document.addEventListener('DOMContentLoaded', function () {
  console.log('Cost toggle script loaded');

  // コスト項目トグル
  var costToggleBtn = document.querySelector('.toggle-cost-items');
  var costContent = document.getElementById('cost-items-content');

  console.log('Toggle button:', costToggleBtn);
  console.log('Cost content:', costContent);

  if (costToggleBtn && costContent) {
    console.log('Setting up cost toggle functionality');

    // 初期状態を非表示に設定
    costContent.style.display = 'none';
    costToggleBtn.setAttribute('aria-expanded', 'false');

    // 項目数を取得してボタンテキストに追加
    var updateCostButtonText = function () {
      var itemCount = costContent.querySelectorAll('.cost-items-table tbody tr').length || 0;
      var showLabel = costToggleBtn.dataset.showLabel || window.ktpwpCostShowLabel || '表示';
      var hideLabel = costToggleBtn.dataset.hideLabel || window.ktpwpCostHideLabel || '非表示';
      var isExpanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
      costToggleBtn.textContent = (isExpanded ? hideLabel : showLabel) + '（' + itemCount + '項目）';
      console.log('Button text updated to:', costToggleBtn.textContent);
    };

    costToggleBtn.addEventListener('click', function () {
      console.log('Cost toggle button clicked');
      var expanded = costToggleBtn.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        costContent.style.display = 'none';
        costToggleBtn.setAttribute('aria-expanded', 'false');
        console.log('Cost content hidden');
      } else {
        costContent.style.display = 'block';
        costToggleBtn.setAttribute('aria-expanded', 'true');
        console.log('Cost content shown');
      }
      updateCostButtonText();
    });

    // 国際化ラベルを設定
    if (window.ktpwpCostShowLabel) {
      costToggleBtn.dataset.showLabel = window.ktpwpCostShowLabel;
    }
    if (window.ktpwpCostHideLabel) {
      costToggleBtn.dataset.hideLabel = window.ktpwpCostHideLabel;
    }

    // 初期状態のボタンテキストを設定
    updateCostButtonText();

    console.log('Cost toggle setup complete');
  } else {
    console.log('Cost toggle elements not found');
  }
});
