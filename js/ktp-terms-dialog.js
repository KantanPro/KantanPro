/* class-ktpwp-terms-of-service.php から切り出し（以前は PHP の nowdoc で返していた） */
jQuery(document).ready(function($) {
    $('#ktpwp-terms-dialog').show();
    
    // チェックボックスの状態に応じてボタンの有効/無効を切り替え
    $('#ktpwp-terms-checkbox').change(function() {
        $('#ktpwp-start-usage').prop('disabled', !this.checked);
    });
    
    // 利用開始するボタンクリック
    $('#ktpwp-start-usage').click(function() {
        if (!$('#ktpwp-terms-checkbox').is(':checked')) {
            return;
        }
        
        $.ajax({
            url: ( window.ktpwpTermsDialog && window.ktpwpTermsDialog.ajaxUrl ) || '',
            type: 'POST',
            data: {
                action: 'ktpwp_terms_agreement',
                nonce: ( window.ktpwpTermsDialog && window.ktpwpTermsDialog.nonce ) || ''
            },
            success: function(response) {
                if (response.success) {
                    $('#ktpwp-terms-dialog').hide();
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(( window.ktpwpTermsDialog && window.ktpwpTermsDialog.errorMessage ) || '');
            }
        });
    });
    

});
