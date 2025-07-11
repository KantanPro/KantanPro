/**
 * KantanPro Donation Notice Scripts
 *
 * フロントエンドでの寄付通知バナーの表示・非表示とAJAX処理を担当
 *
 * @package KTPWP
 * @subpackage JS
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // 通知バナーの表示
    var $notice = $('#ktpwp-donation-notice');
    
    if ($notice.length) {
        // ページ読み込み後にフェードインで表示
        setTimeout(function() {
            $notice.fadeIn(500);
        }, 1000);
        
        // 閉じるボタンのクリックイベント
        $notice.on('click', '.ktpwp-notice-dismiss-btn', function(e) {
            e.preventDefault();
            dismissNotice();
        });
        
        // 寄付ボタンのクリックイベント（トラッキング用）
        $notice.on('click', '.ktpwp-notice-donate-btn', function(e) {
            // 寄付ページに遷移する前に通知を非表示にする
            $notice.fadeOut(300);
            
            // 寄付への関心を記録
            recordDonationInteraction();
        });
    }
    
    /**
     * 通知を拒否して非表示にする
     */
    function dismissNotice() {
        if (!ktpwp_donation_notice.ajax_url || !ktpwp_donation_notice.nonce) {
            console.error('KantanPro Donation Notice: AJAX設定が見つかりません');
            return;
        }
        
        // 通知をフェードアウト
        $notice.fadeOut(300);
        
        // AJAXでサーバーに拒否を記録
        $.ajax({
            url: ktpwp_donation_notice.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_dismiss_donation_notice',
                nonce: ktpwp_donation_notice.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('寄付通知を非表示にしました');
                } else {
                    console.error('寄付通知の非表示に失敗しました:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('寄付通知の非表示でエラーが発生しました:', error);
            }
        });
    }
    
    /**
     * 寄付への関心を記録（将来的な機能拡張用）
     */
    function recordDonationInteraction() {
        // 寄付ボタンがクリックされたことを記録
        // 将来的に寄付完了率の分析などに使用可能
        if (typeof gtag !== 'undefined') {
            gtag('event', 'donation_interest', {
                'event_category': 'donation',
                'event_label': 'notice_click'
            });
        }
        
        // Console log for debugging
        console.log('寄付への関心が記録されました（管理者ユーザー）');
    }
    
    /**
     * 通知バナーのアクセシビリティ改善
     */
    function enhanceAccessibility() {
        // キーボードナビゲーション対応
        $notice.on('keydown', function(e) {
            if (e.key === 'Escape') {
                dismissNotice();
            }
        });
        
        // フォーカストラップ（通知内での Tab キーの循環）
        $notice.on('keydown', 'a, button', function(e) {
            if (e.key === 'Tab') {
                var $focusable = $notice.find('a, button');
                var $first = $focusable.first();
                var $last = $focusable.last();
                
                if (e.shiftKey) {
                    // Shift + Tab
                    if ($(this).is($first)) {
                        e.preventDefault();
                        $last.focus();
                    }
                } else {
                    // Tab
                    if ($(this).is($last)) {
                        e.preventDefault();
                        $first.focus();
                    }
                }
            }
        });
    }
    
    // アクセシビリティ機能の初期化
    enhanceAccessibility();
    
    /**
     * レスポンシブ対応：画面サイズに応じて通知のスタイルを調整
     */
    function adjustNoticeForScreenSize() {
        var windowWidth = $(window).width();
        
        if (windowWidth < 768) {
            // モバイルサイズでは通知を画面幅いっぱいに表示
            $notice.addClass('ktpwp-notice-mobile');
        } else {
            $notice.removeClass('ktpwp-notice-mobile');
        }
    }
    
    // 画面サイズ変更時のリサイズ処理
    $(window).on('resize', function() {
        adjustNoticeForScreenSize();
    });
    
    // 初期画面サイズチェック
    adjustNoticeForScreenSize();
}); 