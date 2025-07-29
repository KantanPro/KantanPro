// テスト用スクリプト: 両方のトグル機能をテスト
console.log('=== KTPWP トグル機能総合テスト ===');

// ページが完全に読み込まれてからテスト
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    console.log('1. 要素の存在確認テスト');

    // コスト項目トグル要素
    var costToggleBtn = document.querySelector('.toggle-cost-items');
    var costContent = document.getElementById('cost-items-content');

    // スタッフチャットトグル要素
    var staffToggleBtn = document.querySelector('.toggle-staff-chat');
    var staffContent = document.getElementById('staff-chat-content');

    console.log('コスト項目トグル要素:');
    console.log('- ボタン:', !!costToggleBtn, costToggleBtn);
    console.log('- コンテンツ:', !!costContent, costContent);

    console.log('スタッフチャットトグル要素:');
    console.log('- ボタン:', !!staffToggleBtn, staffToggleBtn);
    console.log('- コンテンツ:', !!staffContent, staffContent);

    console.log('\n2. 初期状態確認');
    if (costToggleBtn && costContent) {
      console.log('コスト項目:');
      console.log('- 表示状態:', costContent.style.display);
      console.log('- aria-expanded:', costToggleBtn.getAttribute('aria-expanded'));
      console.log('- ボタンテキスト:', costToggleBtn.textContent);
    }

    if (staffToggleBtn && staffContent) {
      console.log('スタッフチャット:');
      console.log('- 表示状態:', staffContent.style.display);
      console.log('- aria-expanded:', staffToggleBtn.getAttribute('aria-expanded'));
      console.log('- ボタンテキスト:', staffToggleBtn.textContent);
    }

    console.log('\n3. グローバルテスト関数の確認');
    console.log('- testCostToggle:', typeof window.testCostToggle);
    console.log('- testStaffChatToggle:', typeof window.testStaffChatToggle);
    console.log('- testAllToggles:', typeof window.testAllToggles);

    console.log('\n=== テスト準備完了 ===');
    console.log('ブラウザコンソールで以下のコマンドを実行してテストできます:');
    console.log('- testCostToggle() : コスト項目トグルをテスト');
    console.log('- testStaffChatToggle() : スタッフチャットトグルをテスト');
    console.log('- testAllToggles() : 両方のトグルをテスト');

  }, 3000);
});
