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
    var $thanks = $('#ktpwp-donation-thanks');
    var $checking = $('#ktpwp-donation-checking');
    
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
        
        // 寄付ボタンのクリックイベント（外部リンクにジャンプ）
        $notice.on('click', '.ktpwp-notice-donate-btn', function(e) {
            // 通知を非表示にする
            $notice.fadeOut(300);
            
            // 寄付への関心を記録
            recordDonationInteraction();
            
            // 確認中のアニメーションを表示
            showCheckingAnimation();
            
            // Stripeの反応時間を考慮して、適切なタイミングで寄付完了をチェック
            // ユーザーが寄付ページで寄付を実行する時間を考慮（30秒後）
            setTimeout(function() {
                hideCheckingAnimation();
                checkDonationCompletionOnce();
            }, 30000); // 30秒後にチェック
            
            // リンクのデフォルト動作を許可（新しいタブで開く）
            // e.preventDefault()を削除して、リンクの自然な動作を維持
        });
    }
    
    // 寄付完了メッセージの処理
    if ($thanks.length) {
        // 閉じるボタンのクリックイベント
        $thanks.on('click', '.ktpwp-thanks-close', function(e) {
            e.preventDefault();
            hideThanksMessage();
        });
        
        // キーボードナビゲーション対応
        $thanks.on('keydown', function(e) {
            if (e.key === 'Escape') {
                hideThanksMessage();
            }
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
     * 寄付完了メッセージを表示
     */
    function showThanksMessage() {
        if ($thanks.length) {
            // 少し遅延させてから表示（寄付ボタンクリックのアニメーション完了後）
            setTimeout(function() {
                $thanks.fadeIn(500);
                
                // 5秒後に自動で非表示
                setTimeout(function() {
                    hideThanksMessage();
                }, 5000);
            }, 500);
        }
    }
    
    /**
     * 寄付確認失敗メッセージを表示
     */
    function showDonationNotConfirmedMessage() {
        if ($thanks.length) {
            // メッセージとアイコンを一時的に変更
            var $message = $thanks.find('.ktpwp-thanks-message');
            var $icon = $thanks.find('.ktpwp-thanks-icon');
            var originalMessage = $message.text();
            var originalIcon = $icon.text();
            
            $message.text('寄付は確認できませんでした');
            $icon.text('⚠️');
            $thanks.addClass('ktpwp-thanks-error');
            
            setTimeout(function() {
                $thanks.fadeIn(500);
                
                // 5秒後に自動で非表示
                setTimeout(function() {
                    hideThanksMessage();
                    // メッセージとアイコンを元に戻す
                    $message.text(originalMessage);
                    $icon.text(originalIcon);
                    $thanks.removeClass('ktpwp-thanks-error');
                }, 5000);
            }, 500);
        }
    }
    
    /**
     * 寄付完了メッセージを非表示
     */
    function hideThanksMessage() {
        if ($thanks.length) {
            $thanks.fadeOut(300);
        }
    }
    
    /**
     * 寄付完了を1度だけチェック
     */
    function checkDonationCompletionOnce() {
        console.log('寄付完了を1度だけチェックします');
        
        $.ajax({
            url: ktpwp_donation_notice.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_check_donation_completion',
                nonce: ktpwp_donation_notice.nonce
            },
            success: function(response) {
                if (response.success && response.data.has_donated) {
                    console.log('寄付完了を確認しました');
                    showThanksMessage();
                } else {
                    console.log('寄付完了を確認できませんでした');
                    showDonationNotConfirmedMessage();
                }
            },
            error: function(xhr, status, error) {
                console.error('寄付完了チェックでエラーが発生しました:', error);
            }
        });
    }
    
    /**
     * 確認中のアニメーションを表示
     */
    function showCheckingAnimation() {
        if ($checking.length) {
            $checking.fadeIn(500);
            
            // ページを離れようとした時の警告を表示
            window.addEventListener('beforeunload', preventPageLeave);
        }
    }

    /**
     * 確認中のアニメーションを非表示
     */
    function hideCheckingAnimation() {
        if ($checking.length) {
            $checking.fadeOut(300);
            
            // ページ離脱防止を解除
            window.removeEventListener('beforeunload', preventPageLeave);
        }
    }
    
    /**
     * ページ離脱を防止
     */
    function preventPageLeave(e) {
        e.preventDefault();
        e.returnValue = '確認中です。ページを離れないでください。';
        return '確認中です。ページを離れないでください。';
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
            $thanks.addClass('ktpwp-notice-mobile');
        } else {
            $notice.removeClass('ktpwp-notice-mobile');
            $thanks.removeClass('ktpwp-notice-mobile');
        }
    }
    
    // 画面サイズ変更時のリサイズ処理
    $(window).on('resize', function() {
        adjustNoticeForScreenSize();
    });
    
    // 初期画面サイズチェック
    adjustNoticeForScreenSize();
}); 