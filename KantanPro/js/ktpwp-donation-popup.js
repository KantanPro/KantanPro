/**
 * KantanPro Donation Popup Scripts
 *
 * 寄付フォームポップアップの表示・非表示とStripe決済処理を担当
 *
 * @package KTPWP
 * @subpackage JS
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // ポップアップ要素
    var $popup = $('#ktpwp-donation-popup');
    var $overlay = $('.ktpwp-popup-overlay');
    var $content = $('.ktpwp-popup-content');
    var $closeBtn = $('.ktpwp-popup-close');
    var $openBtn = $('#ktpwp-open-donation-popup');

    // Stripe関連
    var stripe = null;
    var cardElement = null;
    var selectedAmount = 0;

    // 初期化
    initDonationPopup();

    /**
     * ポップアップの初期化
     */
    function initDonationPopup() {
        // Stripeの初期化
        if (typeof Stripe !== 'undefined' && ktpwp_donation && ktpwp_donation.stripe_publishable_key) {
            stripe = Stripe(ktpwp_donation.stripe_publishable_key);
            initStripeElements();
        }

        // イベントリスナーの設定
        setupEventListeners();
    }

    /**
     * Stripe Elementsの初期化
     */
    function initStripeElements() {
        var elements = stripe.elements();
        
        // カード要素の作成
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '14px',
                    color: '#374151',
                    '::placeholder': {
                        color: '#9ca3af',
                    },
                },
            },
        });

        // カード要素をDOMにマウント
        cardElement.mount('#ktpwp-card-element');

        // カード要素のエラーハンドリング
        cardElement.on('change', function(event) {
            if (event.error) {
                showMessage('error', 'カード情報にエラーがあります: ' + event.error.message);
            } else {
                clearMessages();
            }
        });
    }

    /**
     * イベントリスナーの設定
     */
    function setupEventListeners() {
        // ポップアップを開く
        $openBtn.on('click', function(e) {
            e.preventDefault();
            openPopup();
        });

        // ポップアップを閉じる
        $closeBtn.on('click', function(e) {
            e.preventDefault();
            closePopup();
        });

        // オーバーレイクリックで閉じる
        $overlay.on('click', function(e) {
            if (e.target === this) {
                closePopup();
            }
        });

        // ESCキーで閉じる
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $popup.is(':visible')) {
                closePopup();
            }
        });

        // 金額ボタンのクリック
        $(document).on('click', '.ktpwp-amount-btn', function() {
            var amount = parseInt($(this).data('amount'));
            selectAmount(amount);
            // カスタム金額入力をクリア
            $('#ktpwp-custom-amount').val('');
        });

        // カスタム金額入力
        $('#ktpwp-custom-amount').on('input', function() {
            var amount = parseInt($(this).val()) || 0;
            if (amount >= 100) {
                selectCustomAmount(amount);
            } else {
                // 100円未満の場合は選択状態をクリア
                clearAmountSelection();
            }
        });

        // カスタム金額入力にフォーカスした時にボタン選択をクリア
        $('#ktpwp-custom-amount').on('focus', function() {
            $('.ktpwp-amount-btn').removeClass('selected');
        });

        // フォーム送信
        $(document).on('submit', '#ktpwp-donation-form-element', function(e) {
            e.preventDefault();
            processDonation();
        });
    }

    /**
     * ポップアップを開く
     */
    function openPopup() {
        $popup.fadeIn(300);
        $('body').addClass('ktpwp-popup-open');
        
        // フォーカスを閉じるボタンに設定
        setTimeout(function() {
            $closeBtn.focus();
        }, 300);
    }

    /**
     * ポップアップを閉じる
     */
    function closePopup() {
        $popup.fadeOut(300);
        $('body').removeClass('ktpwp-popup-open');
        
        // フォームをリセット
        resetForm();
    }

    /**
     * 金額を選択（ボタンから）
     */
    function selectAmount(amount) {
        selectedAmount = amount;
        
        // 金額ボタンの選択状態を更新
        $('.ktpwp-amount-btn').removeClass('selected');
        $('.ktpwp-amount-btn[data-amount="' + amount + '"]').addClass('selected');
        
        // カスタム金額入力をクリア
        $('#ktpwp-custom-amount').val('');
        
        // 寄付ボタンのテキストを更新
        $('#ktpwp-donate-btn').text('¥' + amount.toLocaleString() + ' 寄付する');
    }

    /**
     * カスタム金額を選択
     */
    function selectCustomAmount(amount) {
        selectedAmount = amount;
        
        // 金額ボタンの選択状態をクリア
        $('.ktpwp-amount-btn').removeClass('selected');
        
        // 寄付ボタンのテキストを更新
        $('#ktpwp-donate-btn').text('¥' + amount.toLocaleString() + ' 寄付する');
        
        // デバッグログ
        if (typeof console !== 'undefined') {
            console.log('カスタム金額選択:', amount);
        }
    }

    /**
     * 金額選択をクリア
     */
    function clearAmountSelection() {
        selectedAmount = 0;
        $('.ktpwp-amount-btn').removeClass('selected');
        $('#ktpwp-donate-btn').text('寄付する');
    }

    /**
     * 寄付処理
     */
    function processDonation() {
        if (selectedAmount < 100) {
            showMessage('error', '寄付金額は100円以上でお願いします。');
            return;
        }

        if (!stripe || !cardElement) {
            showMessage('error', '決済システムの初期化に失敗しました。');
            return;
        }

        // ボタンを無効化
        var $submitBtn = $('#ktpwp-donate-btn');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('処理中...');

        // 寄付者情報を取得
        var donorName = $('#ktpwp-donor-name').val() || '';
        var donorEmail = $('#ktpwp-donor-email').val() || '';
        var donorMessage = $('#ktpwp-donor-message').val() || '';

        // Payment Intentを作成
        $.ajax({
            url: ktpwp_donation.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_create_payment_intent',
                nonce: ktpwp_donation.nonce,
                amount: selectedAmount,
                donor_name: donorName,
                donor_email: donorEmail,
                donor_message: donorMessage
            },
            success: function(response) {
                if (response.success) {
                    confirmPayment(response.data.client_secret, donorName, donorEmail, donorMessage);
                } else {
                    showMessage('error', response.data.message || '決済の準備に失敗しました。');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showMessage('error', '通信エラーが発生しました。');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * 決済確認
     */
    function confirmPayment(clientSecret, donorName, donorEmail, donorMessage) {
        stripe.confirmCardPayment(clientSecret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: donorName,
                    email: donorEmail
                }
            }
        }).then(function(result) {
            if (result.error) {
                showMessage('error', '決済に失敗しました: ' + result.error.message);
                $('#ktpwp-donate-btn').prop('disabled', false).text('寄付する');
            } else {
                // 決済成功時の処理
                completeDonation(result.paymentIntent.id, donorName, donorEmail, donorMessage);
            }
        });
    }

    /**
     * 寄付完了処理
     */
    function completeDonation(paymentIntentId, donorName, donorEmail, donorMessage) {
        $.ajax({
            url: ktpwp_donation.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_confirm_donation',
                nonce: ktpwp_donation.nonce,
                payment_intent_id: paymentIntentId,
                donor_name: donorName,
                donor_email: donorEmail,
                donor_message: donorMessage
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', 'ご寄付ありがとうございます！');
                    setTimeout(function() {
                        closePopup();
                    }, 2000);
                } else {
                    showMessage('error', response.data.message || '寄付の処理に失敗しました。');
                    $('#ktpwp-donate-btn').prop('disabled', false).text('寄付する');
                }
            },
            error: function() {
                showMessage('error', '通信エラーが発生しました。');
                $('#ktpwp-donate-btn').prop('disabled', false).text('寄付する');
            }
        });
    }

    /**
     * メッセージを表示
     */
    function showMessage(type, message) {
        var $messages = $('#ktpwp-donation-messages');
        var $message = $('<div class="ktpwp-message ' + type + '">' + message + '</div>');
        
        $messages.append($message);
        
        // 自動で消去（エラーメッセージ以外）
        if (type !== 'error') {
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    /**
     * メッセージをクリア
     */
    function clearMessages() {
        $('#ktpwp-donation-messages').empty();
    }

    /**
     * フォームをリセット
     */
    function resetForm() {
        selectedAmount = 0;
        $('.ktpwp-amount-btn').removeClass('selected');
        $('#ktpwp-custom-amount').val('');
        $('#ktpwp-donor-name').val('');
        $('#ktpwp-donor-email').val('');
        $('#ktpwp-donor-message').val('');
        $('#ktpwp-donate-btn').prop('disabled', false).text('寄付する');
        clearMessages();
        
        // Stripeカード要素をクリア
        if (cardElement) {
            cardElement.clear();
        }
    }
}); 