// 案件名インライン編集・自動保存（日本語入力対応版）
jQuery(document).ready(function($) {
  var compositionStatus = {};
  var saveTimers = {};
  var PROJECT_NAME_PLACEHOLDER = '※ 入力してください';

  function getProjectNameAjaxConfig() {
    var cfg = (typeof ktpwp_inline_edit_nonce !== 'undefined' && ktpwp_inline_edit_nonce) ? ktpwp_inline_edit_nonce : {};
    var url = cfg.ajax_url
      || (typeof ajaxurl !== 'undefined' ? ajaxurl : '')
      || (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url ? ktp_ajax_object.ajax_url : '')
      || (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url ? ktpwp_ajax.ajax_url : '')
      || '/wp-admin/admin-ajax.php';

    return {
      url: url,
      nonce: cfg.nonce || ''
    };
  }

  function isPlaceholderValue(value) {
    return String(value || '').trim() === PROJECT_NAME_PLACEHOLDER;
  }

  function projectNameForSave(value) {
    var text = String(value || '').trim();
    if (text === '') {
      return PROJECT_NAME_PLACEHOLDER;
    }
    return text;
  }

  function projectNameForInputDisplay(value) {
    var text = String(value || '').trim();
    if (text === '') {
      return PROJECT_NAME_PLACEHOLDER;
    }
    return text;
  }

  function saveProjectName($input) {
    var ajaxConfig = getProjectNameAjaxConfig();
    var newName = projectNameForSave($input.val());
    var orderId = $input.data('order-id');

    if (typeof orderId === 'undefined' || orderId === '') {
      return;
    }

    if (!ajaxConfig.nonce) {
      $input.addClass('autosave-error');
      setTimeout(function () { $input.removeClass('autosave-error'); }, 1200);
      alert(typeof ktpwpTranslate === 'function'
        ? ktpwpTranslate('案件名を保存する権限がありません。')
        : '案件名を保存する権限がありません。');
      return;
    }

    if (saveTimers[orderId]) {
      clearTimeout(saveTimers[orderId]);
      delete saveTimers[orderId];
    }

    $.ajax({
      url: ajaxConfig.url,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'ktp_update_project_name',
        order_id: orderId,
        project_name: newName,
        _wpnonce: ajaxConfig.nonce
      },
      success: function(res) {
        if (res && res.success) {
          var savedName = (res.data && typeof res.data.project_name !== 'undefined')
            ? res.data.project_name
            : newName;
          $input.val(projectNameForInputDisplay(savedName));
          $input.addClass('autosaved');
          setTimeout(function(){ $input.removeClass('autosaved'); }, 800);
        } else {
          $input.addClass('autosave-error');
          setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
          var message = (res && res.data) ? res.data : '';
          if (typeof message === 'object' && message && message.message) {
            message = message.message;
          }
          if (message) {
            alert((typeof ktpwpTranslate === 'function' ? ktpwpTranslate('保存エラー: ') : '保存エラー: ') + message);
          }
        }
      },
      error: function(xhr) {
        $input.addClass('autosave-error');
        setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
        if (window.ktpDebugMode) {
          console.error('[KTPWP] project name save failed', xhr.status, xhr.responseText);
        }
      }
    });
  }

  function deferredSave($input) {
    var orderId = $input.data('order-id');
    if (!orderId) {
      return;
    }

    if (saveTimers[orderId]) {
      clearTimeout(saveTimers[orderId]);
    }

    saveTimers[orderId] = setTimeout(function() {
      saveProjectName($input);
      delete saveTimers[orderId];
    }, 300);
  }

  $(document).on('focus', '.order_project_name_inline', function() {
    var $input = $(this);
    if (isPlaceholderValue($input.val())) {
      $input.val('');
    }
  });

  $(document).on('compositionstart', '.order_project_name_inline', function() {
    compositionStatus[$(this).data('order-id')] = true;
  });

  $(document).on('compositionend', '.order_project_name_inline', function() {
    var orderId = $(this).data('order-id');
    compositionStatus[orderId] = false;
    deferredSave($(this));
  });

  $(document).on('blur', '.order_project_name_inline', function() {
    var orderId = $(this).data('order-id');
    if (!compositionStatus[orderId]) {
      deferredSave($(this));
    }
  });

  $(document).on('keydown', '.order_project_name_inline', function(e) {
    var orderId = $(this).data('order-id');

    if (e.key === 'Enter' || e.keyCode === 13) {
      if (compositionStatus[orderId]) {
        return;
      }
      e.preventDefault();
      deferredSave($(this));
      $(this).blur();
    }
  });
});
