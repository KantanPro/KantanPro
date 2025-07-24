// 案件名インライン編集・自動保存（日本語入力対応版）
jQuery(document).ready(function($) {
  // 日本語入力状態とタイマーを管理
  var compositionStatus = {};
  var saveTimers = {};
  
  // 保存処理を関数化
  function saveProjectName($input) {
    var newName = $input.val();
    var orderId = $input.data('order-id');
    if (typeof orderId === 'undefined' || orderId === '') return;
    
    // 既存のタイマーをクリア
    if (saveTimers[orderId]) {
      clearTimeout(saveTimers[orderId]);
      delete saveTimers[orderId];
    }
    
    // Ajax保存処理
    $.ajax({
      url: (typeof ajaxurl !== 'undefined' ? ajaxurl : (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '')),
      type: 'POST',
      data: {
        action: 'ktp_update_project_name',
        order_id: orderId,
        project_name: newName,
        _wpnonce: (typeof ktpwp_inline_edit_nonce !== 'undefined' ? ktpwp_inline_edit_nonce.nonce : '')
      },
      success: function(res) {
        if (res && res.success) {
          $input.addClass('autosaved');
          setTimeout(function(){ $input.removeClass('autosaved'); }, 800);
        } else {
          $input.addClass('autosave-error');
          setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
          if (res && res.data) {
            alert('保存エラー: ' + res.data);
          }
        }
      },
      error: function() {
        $input.addClass('autosave-error');
        setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
      }
    });
  }

  // 遅延保存処理（重複防止）
  function deferredSave($input) {
    var orderId = $input.data('order-id');
    if (!orderId) return;
    
    // 既存のタイマーをクリア
    if (saveTimers[orderId]) {
      clearTimeout(saveTimers[orderId]);
    }
    
    // 新しいタイマーをセット（300ms後に保存）
    saveTimers[orderId] = setTimeout(function() {
      saveProjectName($input);
      delete saveTimers[orderId];
    }, 300);
  }

  // 日本語入力開始
  $(document).on('compositionstart', '.order_project_name_inline', function() {
    var orderId = $(this).data('order-id');
    compositionStatus[orderId] = true;
  });

  // 日本語入力終了
  $(document).on('compositionend', '.order_project_name_inline', function() {
    var orderId = $(this).data('order-id');
    compositionStatus[orderId] = false;
    // IME確定後に遅延保存
    deferredSave($(this));
  });

  // blur時の保存（日本語入力中でない場合のみ）
  $(document).on('blur', '.order_project_name_inline', function() {
    var orderId = $(this).data('order-id');
    // 日本語入力中の場合は保存しない（compositionendで処理）
    if (!compositionStatus[orderId]) {
      deferredSave($(this));
    }
  });

  // Enterキー押下時の処理（日本語入力対応）
  $(document).on('keydown', '.order_project_name_inline', function(e) {
    var orderId = $(this).data('order-id');
    
    if (e.key === 'Enter' || e.keyCode === 13) {
      // 日本語入力中の場合は変換確定のEnterなので保存しない
      if (compositionStatus[orderId]) {
        return; // 変換確定はcompositionendで処理
      }
      
      // 通常のEnterキーの場合は保存してフォーカスを外す
      e.preventDefault();
      deferredSave($(this));
      $(this).blur();
    }
  });
});
