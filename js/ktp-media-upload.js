jQuery(document).ready(function($) {
    
    // メディアアップロードボタンのクリックイベント
    $(document).on('click', '.ktp-upload-image', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var field = button.closest('.ktp-image-upload-field');
        var hiddenInput = field.find('input[type="hidden"]');
        var preview = field.find('.ktp-image-preview');
        var previewImg = field.find('#header_bg_image_preview');
        var removeBtn = field.find('.ktp-remove-image');
        
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
            removeBtn.show();
            
            // ボタンのテキストを変更
            button.text('画像を変更');
        });
        
        // Media Library を開く
        mediaUploader.open();
    });
    
    // 画像削除ボタンのクリックイベント
    $(document).on('click', '.ktp-remove-image', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var field = button.closest('.ktp-image-upload-field');
        var hiddenInput = field.find('input[type="hidden"]');
        var preview = field.find('.ktp-image-preview');
        var uploadBtn = field.find('.ktp-upload-image');
        
        var currentValue = hiddenInput.val();
        
        // 確認ダイアログ
        if (confirm('画像を削除しますか？')) {
            // 数値（添付ファイルID）の場合のみ完全削除
            // 文字列パス（デフォルト画像）の場合はデフォルト値にリセット
            if (isNaN(currentValue) || currentValue === '') {
                // デフォルト画像パスの場合は空にせず、デフォルト値に戻す
                hiddenInput.val('images/default/header_bg_image.png');
                // プレビューを更新（デフォルト画像URLを設定）
                var defaultImageUrl = hiddenInput.data('default-url');
                if (defaultImageUrl) {
                    preview.find('img').attr('src', defaultImageUrl);
                    preview.show();
                }
                uploadBtn.text('画像を変更');
            } else {
                // 添付ファイルIDの場合は完全削除
                hiddenInput.val('');
                preview.hide();
                uploadBtn.text('画像をアップロード');
            }
        }
    });
});
