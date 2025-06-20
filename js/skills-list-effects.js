/**
 * 職能リストのホバー効果
 * 
 * @package KTPWP
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[SKILLS-LIST] 職能リストホバー効果の初期化');

    // 職能リストアイテムのホバー効果を適用
    function applySkillsListHoverEffects() {
        const skillItems = document.querySelectorAll('.ktp_data_skill_list_box .skill-item');
        
        if (skillItems.length === 0) {
            console.log('[SKILLS-LIST] 職能リストアイテムが見つかりません');
            return;
        }

        console.log('[SKILLS-LIST] 職能リストアイテムにホバー効果を適用:', skillItems.length + '個');

        skillItems.forEach((item, index) => {
            // 元の背景色を保存
            const originalBg = index % 2 === 0 ? '#f9fafb' : '#ffffff';
            
            // ホバー効果を追加
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f0f7ff';
                this.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.03)';
                this.style.transform = 'translateY(-1px)';
                this.style.zIndex = '1';
            });

            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = originalBg;
                this.style.boxShadow = 'none';
                this.style.transform = 'translateY(0)';
                this.style.zIndex = 'auto';
            });
        });
    }

    // 初期読み込み時に適用
    applySkillsListHoverEffects();

    // MutationObserverで動的に追加された職能リストにも対応
    const observer = new MutationObserver(function(mutations) {
        let shouldReapply = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // 職能リストアイテムが追加された場合
                        if (node.classList && node.classList.contains('skill-item') ||
                            node.querySelector && node.querySelector('.skill-item')) {
                            shouldReapply = true;
                        }
                    }
                });
            }
        });
        
        if (shouldReapply) {
            console.log('[SKILLS-LIST] 新しい職能リストアイテムが検出されました。ホバー効果を再適用します。');
            setTimeout(applySkillsListHoverEffects, 100);
        }
    });

    // 監視を開始
    const targetNode = document.body;
    observer.observe(targetNode, {
        childList: true,
        subtree: true
    });
});
