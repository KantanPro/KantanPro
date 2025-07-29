/**
 * KantanPro License Manager JavaScript
 *
 * Handles license verification and management functionality.
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * License Manager class
     */
    class KTPLicenseManager {
        constructor() {
            this.init();
        }

        /**
         * Initialize the license manager
         */
        init() {
            this.bindEvents();
            this.updateLicenseStatus();
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // License verification form submission
            $(document).on('submit', '#ktp-license-form', this.handleLicenseVerification.bind(this));
            
            // License key input validation
            $(document).on('input', '#ktp_license_key', this.validateLicenseKey.bind(this));
            
            // Show/hide license key
            $(document).on('click', '.ktp-toggle-license-key', this.toggleLicenseKey.bind(this));
            
            // Refresh license status
            $(document).on('click', '.ktp-refresh-license', this.refreshLicenseStatus.bind(this));
        }

        /**
         * Handle license verification form submission
         */
        handleLicenseVerification(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitButton = $form.find('input[type="submit"]');
            const $statusMessage = $('.ktp-license-status-message');
            
            const licenseKey = $('#ktp_license_key').val().trim();
            
            if (!licenseKey) {
                this.showMessage('ライセンスキーを入力してください。', 'error');
                return;
            }

            // Show loading state
            $submitButton.prop('disabled', true).val('認証中...');
            this.showMessage('ライセンスを認証中です...', 'info');

            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ktpwp_verify_license',
                    license_key: licenseKey,
                    nonce: ktp_license_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('ライセンスが正常に認証されました。', 'success');
                        this.updateLicenseStatus();
                        
                        // Reload page after successful activation
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showMessage(response.data || 'ライセンスの認証に失敗しました。', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('通信エラーが発生しました。', 'error');
                    console.error('License verification error:', error);
                },
                complete: () => {
                    $submitButton.prop('disabled', false).val('ライセンスを認証');
                }
            });
        }

        /**
         * Validate license key format
         */
        validateLicenseKey(e) {
            const $input = $(e.target);
            const value = $input.val();
            const $validationMessage = $('.ktp-license-key-validation');
            
            // Remove existing validation message
            $validationMessage.remove();
            
            // Check if value matches expected format (KTPA-XXXXXX-XXXXXX-XXXX)
            const licensePattern = /^KTPA-[A-Z0-9]{6}-[A-Z0-9]{6}-[A-Z0-9]{4}$/;
            
            if (value && !licensePattern.test(value)) {
                $input.after('<div class="ktp-license-key-validation" style="color: #dc3232; font-size: 12px; margin-top: 5px;">ライセンスキーの形式が正しくありません。</div>');
            }
        }

        /**
         * Toggle license key visibility
         */
        toggleLicenseKey(e) {
            e.preventDefault();
            
            const $input = $('#ktp_license_key');
            const $toggleButton = $(e.target);
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $toggleButton.text('非表示');
            } else {
                $input.attr('type', 'password');
                $toggleButton.text('表示');
            }
        }

        /**
         * Refresh license status
         */
        refreshLicenseStatus() {
            const $refreshButton = $('.ktp-refresh-license');
            
            $refreshButton.prop('disabled', true).text('更新中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ktpwp_get_license_info',
                    nonce: ktp_license_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('ライセンス情報を更新しました。', 'success');
                        this.updateLicenseStatus();
                    } else {
                        this.showMessage(response.data || 'ライセンス情報の取得に失敗しました。', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('通信エラーが発生しました。', 'error');
                    console.error('License info refresh error:', error);
                },
                complete: () => {
                    $refreshButton.prop('disabled', false).text('更新');
                }
            });
        }

        /**
         * Update license status display
         */
        updateLicenseStatus() {
            const licenseKey = $('#ktp_license_key').val();
            const $statusDisplay = $('.ktp-license-status-display');
            
            if (!licenseKey) {
                $statusDisplay.find('h3 .dashicons').removeClass('dashicons-yes-alt dashicons-no-alt').addClass('dashicons-warning');
                $statusDisplay.find('h3 .dashicons').css('color', '#f56e28');
                $statusDisplay.find('p strong').text('ライセンスキーが設定されていません。');
            }
        }

        /**
         * Show message to user
         */
        showMessage(message, type = 'info') {
            const $messageContainer = $('.ktp-license-status-message');
            
            if (!$messageContainer.length) {
                $('.ktp-settings-container').prepend('<div class="ktp-license-status-message" style="margin-bottom: 20px;"></div>');
            }
            
            const $message = $('.ktp-license-status-message');
            const iconClass = this.getMessageIcon(type);
            const colorClass = this.getMessageColor(type);
            
            $message.html(`
                <div class="notice notice-${type} is-dismissible" style="margin: 0;">
                    <p>
                        <span class="dashicons ${iconClass}" style="color: ${colorClass};"></span>
                        ${message}
                    </p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">この通知を非表示にする。</span>
                    </button>
                </div>
            `);
            
            // Auto-dismiss after 5 seconds for success/info messages
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    $message.fadeOut();
                }, 5000);
            }
        }

        /**
         * Get message icon class
         */
        getMessageIcon(type) {
            const icons = {
                'success': 'dashicons-yes-alt',
                'error': 'dashicons-no-alt',
                'warning': 'dashicons-warning',
                'info': 'dashicons-info'
            };
            return icons[type] || icons.info;
        }

        /**
         * Get message color
         */
        getMessageColor(type) {
            const colors = {
                'success': '#46b450',
                'error': '#dc3232',
                'warning': '#f56e28',
                'info': '#0073aa'
            };
            return colors[type] || colors.info;
        }
    }

    // Initialize license manager when document is ready
    $(document).ready(function() {
        if (typeof ktp_license_ajax !== 'undefined') {
            new KTPLicenseManager();
        }
    });

})(jQuery); 