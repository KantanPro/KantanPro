/**
 * KTPWP スクリプト - サービス表示修正用
 * CSSの崩れを修正するJavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // KTPサービスリスト関連のクラスを持つ要素を修正
    // 特にktpwp-service-uiクラスから生成されたHTMLとの互換性を確保

    // リスト項目のリンクにスタイルを適用
    const serviceLinks = document.querySelectorAll('.ktp_data_list_box a, .data_list_box a');
    serviceLinks.forEach(link => {
        link.style.textDecoration = 'none';
        link.style.color = 'inherit';
        link.style.display = 'block';
    });

    // サービスリストのボックスに正しいスタイルを適用
    const listBoxes = document.querySelectorAll('.ktp_data_list_box, .data_list_box');
    listBoxes.forEach(box => {
        box.style.border = '1px solid #e0e0e0';
        box.style.borderRadius = '5px';
        box.style.marginBottom = '20px';
        box.style.overflow = 'hidden';
        box.style.width = '100%';
        box.style.display = 'block';
    });

    // タイトル部分のスタイル修正
    const titles = document.querySelectorAll('.data_list_title');
    titles.forEach(title => {
        title.style.backgroundColor = '#f5f5f5';
        title.style.padding = '10px 15px';
        title.style.borderBottom = '1px solid #e0e0e0';
        title.style.fontWeight = 'bold';
        title.style.display = 'flex';
        title.style.justifyContent = 'space-between';
        title.style.alignItems = 'center';
    });

    // リスト項目のスタイル修正
    const listItems = document.querySelectorAll('.ktp_data_list_item, .data_list_item');
    listItems.forEach((item, index) => {
        item.style.lineHeight = '1.5';
        item.style.borderBottom = '1px solid #e5e7eb';
        item.style.margin = '0';
        item.style.padding = '12px 16px';
        item.style.transition = 'background-color 0.2s ease';
        item.style.position = 'relative';
        item.style.fontSize = '14px';

        // 交互に色をつける
        if (index % 2 === 0) {
            item.style.backgroundColor = '#f9fafb';
        } else {
            item.style.backgroundColor = '#ffffff';
        }
    });

    // ホバー効果を追加
    serviceLinks.forEach(link => {
        link.addEventListener('mouseenter', function () {
            const item = this.querySelector('.ktp_data_list_item, .data_list_item');
            if (item) {
                item.style.backgroundColor = '#f0f7ff';
                item.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.03)';
                item.style.zIndex = '1';
            }
        });

        link.addEventListener('mouseleave', function () {
            const item = this.querySelector('.ktp_data_list_item, .data_list_item');
            const index = Array.from(serviceLinks).indexOf(this);
            if (item) {
                if (index % 2 === 0) {
                    item.style.backgroundColor = '#f9fafb';
                } else {
                    item.style.backgroundColor = '#ffffff';
                }
                item.style.boxShadow = 'none';
                item.style.zIndex = 'auto';
            }
        });
    });
});
