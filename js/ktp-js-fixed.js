// スタッフチャットのtextareaでEnter単独は改行、Ctrl/Cmd+Enterで送信
document.addEventListener("DOMContentLoaded", function () {
  // ページロード時にURLパラメータをチェックして自動スクロール（リロード後も有効にする）
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("message_sent") === "1") {
    setTimeout(function () {
      var chatMessages = document.getElementById("staff-chat-messages");
      if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
        chatMessages.scrollIntoView({ behavior: "smooth", block: "end" });
      }
    }, 200);
  }
  var chatForm = document.getElementById("staff-chat-form");
  var messageInput = document.getElementById("staff-chat-input");
  var submitButton = document.getElementById("staff-chat-submit");
  if (chatForm && messageInput && submitButton) {
    messageInput.addEventListener("keydown", function (e) {
      // Ctrl+EnterまたはCmd+Enterで送信
      if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
        e.preventDefault();
        if (!submitButton.disabled) {
          if (typeof chatForm.requestSubmit === "function") {
            chatForm.requestSubmit();
          } else {
            chatForm.submit();
          }
        }
      }
      // Enter単独は改行（デフォルト動作）
    });
  }
});
document.addEventListener("DOMContentLoaded", function () {
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
    var toggleBtn = document.getElementById("staff-chat-toggle-btn");
    if (!toggleBtn) return;

    var badge = toggleBtn.querySelector(".staff-chat-notification-badge");
    if (badge) {
      badge.remove();
    }
  };

  // コスト項目トグル
  var costToggleBtn = document.querySelector(".toggle-cost-items");
  var costContent = document.getElementById("cost-items-content");
  if (costToggleBtn && costContent) {
    // 初期状態を非表示に設定
    costContent.style.display = "none";
    costToggleBtn.setAttribute("aria-expanded", "false");

    // 項目数を取得してボタンテキストに追加
    var updateCostButtonText = function () {
      var itemCount =
        costContent.querySelectorAll(".cost-items-table tbody tr").length || 0;
      var showLabel = costToggleBtn.dataset.showLabel || "表示";
      var hideLabel = costToggleBtn.dataset.hideLabel || "非表示";
      var isExpanded = costToggleBtn.getAttribute("aria-expanded") === "true";
      costToggleBtn.textContent =
        (isExpanded ? hideLabel : showLabel) + "（" + itemCount + "項目）";
    };

    costToggleBtn.addEventListener("click", function () {
      var expanded = costToggleBtn.getAttribute("aria-expanded") === "true";
      if (expanded) {
        costContent.style.display = "none";
        costToggleBtn.setAttribute("aria-expanded", "false");
      } else {
        costContent.style.display = "";
        costToggleBtn.setAttribute("aria-expanded", "true");
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
  }

  // スタッフチャットトグル（基本的な実装）
  var staffChatToggleBtn = document.querySelector(".toggle-staff-chat");
  var staffChatContent = document.getElementById("staff-chat-content");
  if (staffChatToggleBtn && staffChatContent) {
    // URLパラメータをチェック
    var urlParams = new URLSearchParams(window.location.search);
    var chatShouldBeOpen = urlParams.get("chat_open") !== "0";
    var messageSent = urlParams.get("message_sent") === "1";

    // 初期状態を設定
    if (chatShouldBeOpen) {
      staffChatContent.style.display = "block";
      staffChatToggleBtn.setAttribute("aria-expanded", "true");
    } else {
      staffChatContent.style.display = "none";
      staffChatToggleBtn.setAttribute("aria-expanded", "false");
    }

    // 項目数を取得してボタンテキストに追加
    var updateStaffChatButtonText = function () {
      var scrollableMessages = staffChatContent.querySelectorAll(
        ".staff-chat-message.scrollable"
      );
      var messageCount = scrollableMessages.length || 0;

      var emptyMessage = staffChatContent.querySelector(".staff-chat-empty");
      if (emptyMessage) {
        messageCount = 0;
      }

      var showLabel = staffChatToggleBtn.dataset.showLabel || "表示";
      var hideLabel = staffChatToggleBtn.dataset.hideLabel || "非表示";
      var isExpanded =
        staffChatToggleBtn.getAttribute("aria-expanded") === "true";
      staffChatToggleBtn.textContent =
        (isExpanded ? hideLabel : showLabel) +
        "（" +
        messageCount +
        "メッセージ）";
    };

    staffChatToggleBtn.addEventListener("click", function () {
      var expanded =
        staffChatToggleBtn.getAttribute("aria-expanded") === "true";
      if (expanded) {
        window.clearScrollTimeouts();
        staffChatContent.style.display = "none";
        staffChatToggleBtn.setAttribute("aria-expanded", "false");
      } else {
        staffChatContent.style.display = "block";
        staffChatToggleBtn.setAttribute("aria-expanded", "true");
      }
      updateStaffChatButtonText();
    });

    // 国際化ラベルを設定
    if (window.ktpwpStaffChatShowLabel) {
      staffChatToggleBtn.dataset.showLabel = window.ktpwpStaffChatShowLabel;
    }
    if (window.ktpwpStaffChatHideLabel) {
      staffChatToggleBtn.dataset.hideLabel = window.ktpwpStaffChatHideLabel;
    }

    updateStaffChatButtonText();
  }

  // メッセージフォーム送信の処理（基本的な実装）
  var messageForm = document.getElementById("staff-chat-form");
  var messageInput = document.getElementById("staff-chat-input"); // ←IDを修正
  if (messageForm && messageInput) {
    messageForm.addEventListener("submit", function (e) {
      e.preventDefault();

      var message = messageInput.value.trim();
      if (!message) return;

      // 基本的なフォーム送信処理
      var formData = new FormData(messageForm);

      // Ajax送信処理をここに実装
      // 現在は基本的な処理のみ
      console.log("メッセージ送信:", message);

      // メッセージ送信後にチャットエリアを最下部までスクロール（遅延付き）
      setTimeout(function () {
        var chatMessages = document.getElementById("staff-chat-messages");
        if (chatMessages) {
          chatMessages.scrollTop = chatMessages.scrollHeight;
          // ページ全体もチャットエリアが見えるようにスクロール
          chatMessages.scrollIntoView({ behavior: "smooth", block: "end" });
        }
      }, 100);
    });
  }
});

// 成功通知を表示する関数
window.showSuccessNotification = function (message) {
  var notification = document.createElement("div");
  notification.className = "success-notification";
  notification.style.cssText =
    "position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; z-index: 10000;";
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(function () {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 3000);
};
