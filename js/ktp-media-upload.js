jQuery(document).ready(function($) {
    
    // メディアアップロードボタンのクリックイベント
    $(document).on('click', '.ktp-upload-image', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var field = button.closest('.ktp-image-upload-field');
        var hiddenInput = field.find('input[type="hidden"]');
        var preview = field.find('.ktp-image-preview');
        var previewImg = field.find('#header_bg_image_preview');

        // WordPress Media Library を開く
        var mediaUploader = wp.media({
            title: 'ヘッダー背景画像を選択',
            button: {
                text: '画像を選択'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // 画像が選択された時の処理
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // フィールドに値をセット
            hiddenInput.val(attachment.id);
            
            // プレビュー画像を更新
            previewImg.attr('src', attachment.url);
            preview.show();

            // ボタンのテキストを変更
            button.text(ktpwpTranslate('画像を変更'));
        });
        
        // Media Library を開く
        mediaUploader.open();
    });
});
