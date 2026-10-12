/* class-ktpwp-settings.php から切り出し（以前は PHP の nowdoc で返していた） */
function testNoticeDisplay() {
    var $preview = jQuery('#ktpwp-notice-preview');
    $preview.fadeOut(300, function() {
        setTimeout(function() {
            $preview.fadeIn(500);
        }, 100);
    });
}

function testNoticeDismiss() {
    var $preview = jQuery('#ktpwp-notice-preview');
    $preview.fadeOut(300);
}
