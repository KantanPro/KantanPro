/**
 * KTPWP Donation JavaScript
 * 
 * Handles donation form interactions and Stripe payment processing
 */

(function($) {
    'use strict';

    // Stripe インスタンス
    let stripe = null;
    let elements = null;
    let cardElement = null;
    let currentAmount = 0;
    let isProcessing = false;

    // 初期化
    $(document).ready(function() {
        if (typeof Stripe !== 'undefined' && ktpwp_donation.stripe_publishable_key) {
            initializeStripe();
        }
        
        initializeDonationForm();
    });

    /**
     * Stripe初期化
     */
    function initializeStripe() {
        stripe = Stripe(ktpwp_donation.stripe_publishable_key);
        elements = stripe.elements();
        
        // カード要素の作成
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#333',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a',
                },
            },
        });

        // カード要素をマウント
        cardElement.mount('#ktpwp-card-element');
    }

    /**
     * 寄付フォーム初期化
     */
    function initializeDonationForm() {
        // 金額ボタンのクリック処理
        $('.ktpwp-amount-btn').on('click', function() {
            $('.ktpwp-amount-btn').removeClass('active');
            $(this).addClass('active');
            
            currentAmount = parseInt($(this).data('amount'));
            $('#ktpwp-custom-amount').val('');
            
            console.log('Selected amount:', currentAmount);
        });

        // カスタム金額入力処理
        $('#ktpwp-custom-amount').on('input', function() {
            $('.ktpwp-amount-btn').removeClass('active');
            currentAmount = parseInt($(this).val()) || 0;
            
            console.log('Custom amount:', currentAmount);
        });

        // フォーム送信処理
        $('#ktpwp-donation-form-element').on('submit', function(e) {
            e.preventDefault();
            
            if (isProcessing) {
                return false;
            }
            
            processDonation();
        });
    }

    /**
     * 寄付処理
     */
    function processDonation() {
        if (currentAmount < 100) {
            showMessage('最小寄付額は100円です。', 'error');
            return;
        }

        if (!stripe || !cardElement) {
            showMessage('決済システムが利用できません。', 'error');
            return;
        }

        setProcessingState(true);
        
        // 寄付情報を取得
        const donationData = {
            amount: currentAmount,
            donor_name: $('#ktpwp-donor-name').val().trim(),
            donor_email: $('#ktpwp-donor-email').val().trim(),
            donor_message: $('#ktpwp-donor-message').val().trim(),
            nonce: ktpwp_donation.nonce
        };

        // PaymentIntentを作成
        $.ajax({
            url: ktpwp_donation.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_create_payment_intent',
                ...donationData
            },
            success: function(response) {
                if (response.success) {
                    confirmPayment(response.data.client_secret, response.data.donation_id);
                } else {
                    showMessage(response.data || '決済の準備中にエラーが発生しました。', 'error');
                    setProcessingState(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('PaymentIntent creation error:', error);
                showMessage('決済の準備中にエラーが発生しました。', 'error');
                setProcessingState(false);
            }
        });
    }

    /**
     * 決済確認
     */
    function confirmPayment(clientSecret, donationId) {
        stripe.confirmCardPayment(clientSecret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: $('#ktpwp-donor-name').val().trim(),
                    email: $('#ktpwp-donor-email').val().trim()
                }
            }
        }).then(function(result) {
            if (result.error) {
                // エラーメッセージを表示
                showMessage(result.error.message || '決済中にエラーが発生しました。', 'error');
                setProcessingState(false);
            } else {
                // 決済成功
                if (result.paymentIntent.status === 'succeeded') {
                    confirmDonationSuccess(donationId, result.paymentIntent.id);
                } else {
                    showMessage('決済が完了しませんでした。', 'error');
                    setProcessingState(false);
                }
            }
        });
    }

    /**
     * 寄付成功確認
     */
    function confirmDonationSuccess(donationId, paymentIntentId) {
        $.ajax({
            url: ktpwp_donation.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_confirm_donation',
                donation_id: donationId,
                payment_intent_id: paymentIntentId,
                nonce: ktpwp_donation.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage();
                    resetForm();
                } else {
                    showMessage(response.data || '寄付の確認中にエラーが発生しました。', 'error');
                }
                setProcessingState(false);
            },
            error: function(xhr, status, error) {
                console.error('Donation confirmation error:', error);
                showMessage('寄付の確認中にエラーが発生しました。', 'error');
                setProcessingState(false);
            }
        });
    }

    /**
     * 成功メッセージの表示
     */
    function showSuccessMessage() {
        const successHTML = `
            <div class="ktpwp-donation-success">
                <h4>🎉 ご寄付ありがとうございます！</h4>
                <p>KantanProの継続的な開発にご支援いただき、心から感謝申し上げます。</p>
                <p>いただいたご寄付は以下の用途に大切に使わせていただきます：</p>
                <ul>
                    <li>サーバー運営費</li>
                    <li>開発・保守作業</li>
                    <li>新機能の追加</li>
                    <li>セキュリティアップデート</li>
                </ul>
                <p><strong>寄付金額：¥${currentAmount.toLocaleString()}</strong></p>
                <p>メールアドレスを入力いただいた場合、確認メールをお送りしております。</p>
            </div>
        `;
        
        $('#ktpwp-donation-form-element').html(successHTML);
        
        // 進捗バーの更新（非同期）
        setTimeout(function() {
            updateProgressBar();
        }, 1000);
    }

    /**
     * フォームリセット
     */
    function resetForm() {
        $('#ktpwp-donor-name').val('');
        $('#ktpwp-donor-email').val('');
        $('#ktpwp-donor-message').val('');
        $('#ktpwp-custom-amount').val('');
        $('.ktpwp-amount-btn').removeClass('active');
        currentAmount = 0;
        
        if (cardElement) {
            cardElement.clear();
        }
    }

    /**
     * 進捗バーの更新
     */
    function updateProgressBar() {
        $.ajax({
            url: ktpwp_donation.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_get_donation_progress',
                nonce: ktpwp_donation.nonce
            },
            success: function(response) {
                if (response.success) {
                    const progress = response.data.progress;
                    const total = response.data.total;
                    
                    $('.ktpwp-progress-fill').css('width', progress + '%');
                    $('.ktpwp-donation-progress p').text(
                        '¥' + total.toLocaleString() + ' / ¥' + response.data.goal.toLocaleString()
                    );
                }
            }
        });
    }

    /**
     * メッセージ表示
     */
    function showMessage(message, type) {
        const $messageDiv = $('#ktpwp-donation-messages');
        $messageDiv.removeClass('success error').addClass(type);
        $messageDiv.text(message);
        $messageDiv.show();
        
        // 成功メッセージは自動で非表示
        if (type === 'success') {
            setTimeout(function() {
                $messageDiv.hide();
            }, 5000);
        }
    }

    /**
     * 処理中状態の設定
     */
    function setProcessingState(processing) {
        isProcessing = processing;
        const $form = $('#ktpwp-donation-form-element');
        const $button = $('#ktpwp-donate-btn');
        
        if (processing) {
            $form.addClass('ktpwp-donation-loading');
            $button.prop('disabled', true).text('処理中...');
        } else {
            $form.removeClass('ktpwp-donation-loading');
            $button.prop('disabled', false).text('寄付する');
        }
    }

    /**
     * 金額フォーマット
     */
    function formatAmount(amount) {
        return new Intl.NumberFormat('ja-JP', {
            style: 'currency',
            currency: 'JPY',
            minimumFractionDigits: 0
        }).format(amount);
    }

    /**
     * デバッグ情報
     */
    function debugLog(message, data) {
        if (window.console && console.log) {
            console.log('KTPWP Donation:', message, data);
        }
    }

    // 外部からアクセス可能な関数
    window.ktpwpDonation = {
        updateProgressBar: updateProgressBar,
        resetForm: resetForm,
        showMessage: showMessage
    };

})(jQuery); 