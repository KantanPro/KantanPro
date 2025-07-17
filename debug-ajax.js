/**
 * KantanPro AJAX Debug Helper
 * 
 * WordPressのAJAXリクエストをデバッグするためのヘルパー関数
 * 大量のAJAX 400エラーを監視し、詳細な情報を出力します。
 */
(function($) {
    'use strict';

    // デバッグモードの設定
    const DEBUG_ENABLED = window.ktpDebugMode || false;
    const LOG_PREFIX = '[AJAX-DEBUG]';
    
    // エラーカウンター
    let errorCount = 0;
    let errorStats = {};
    
    /**
     * ログ出力ヘルパー関数
     */
    function debugLog(message, data = null, type = 'info') {
        if (!DEBUG_ENABLED) return;
        
        const timestamp = new Date().toLocaleTimeString();
        const logMessage = `${LOG_PREFIX} [${timestamp}] ${message}`;
        
        switch (type) {
            case 'error':
                console.error(logMessage, data);
                break;
            case 'warn':
                console.warn(logMessage, data);
                break;
            case 'group':
                console.group(logMessage);
                if (data) console.log(data);
                break;
            case 'groupEnd':
                console.groupEnd();
                break;
            default:
                console.log(logMessage, data);
                break;
        }
    }
    
    /**
     * AJAX設定の詳細情報を出力
     */
    function logAjaxConfig() {
        debugLog('=== AJAX Configuration Check ===', null, 'group');
        
        // WordPress AJAX設定の確認
        if (typeof ajaxurl !== 'undefined') {
            debugLog('WordPress ajaxurl:', ajaxurl);
        } else {
            debugLog('WordPress ajaxurl: NOT DEFINED', null, 'warn');
        }
        
        // KantanPro AJAX設定の確認
        const ajaxObjects = [
            'ktpwp_ajax',
            'ktp_ajax_object', 
            'ktp_ajax_nonce',
            'ktpwp_update_ajax',
            'ktpClientInvoice',
            'ktp_service_ajax_object'
        ];
        
        ajaxObjects.forEach(function(objName) {
            if (typeof window[objName] !== 'undefined') {
                debugLog(`${objName}:`, window[objName]);
            } else {
                debugLog(`${objName}: NOT DEFINED`, null, 'warn');
            }
        });
        
        debugLog('=== End Configuration Check ===', null, 'groupEnd');
    }
    
    /**
     * リクエストデータを解析してオブジェクトに変換
     */
    function parseRequestData(data) {
        if (!data) return {};
        
        // 既にオブジェクトの場合はそのまま返す
        if (typeof data === 'object' && data !== null) {
            return data;
        }
        
        // 文字列の場合はURLSearchParamsを使用して解析
        if (typeof data === 'string') {
            const params = new URLSearchParams(data);
            const result = {};
            for (const [key, value] of params) {
                result[key] = value;
            }
            return result;
        }
        
        return {};
    }
    
    /**
     * AJAX エラーの詳細を解析
     */
    function analyzeAjaxError(xhr, status, error, requestData) {
        errorCount++;
        
        // リクエストデータを解析
        const parsedData = parseRequestData(requestData);
        
        // エラー統計の更新
        const action = parsedData.action || 'unknown';
        if (!errorStats[action]) {
            errorStats[action] = 0;
        }
        errorStats[action]++;
        
        debugLog(`AJAX Error #${errorCount} - Action: ${action}`, null, 'group');
        
        // リクエスト詳細
        debugLog('Request Details:', {
            url: xhr.responseURL || 'unknown',
            method: 'POST',
            rawData: requestData,
            parsedData: parsedData,
            status: xhr.status,
            statusText: xhr.statusText,
            readyState: xhr.readyState
        });
        
        // レスポンス詳細
        debugLog('Response Details:', {
            responseText: xhr.responseText,
            responseType: xhr.responseType,
            response: xhr.response
        });
        
        // HTTP ヘッダー
        if (xhr.getAllResponseHeaders) {
            debugLog('Response Headers:', xhr.getAllResponseHeaders());
        }
        
        // エラー分析
        if (xhr.status === 400) {
            debugLog('400 Bad Request Analysis:', null, 'warn');
            
            // よくある原因
            const commonCauses = [
                'Invalid nonce',
                'Missing required parameters',
                'Invalid action name',
                'CSRF token mismatch',
                'Malformed request data'
            ];
            
            debugLog('Common causes for 400 errors:', commonCauses, 'warn');
            
            // Nonce の確認
            if (parsedData.nonce) {
                debugLog('Nonce in request:', parsedData.nonce);
            } else {
                debugLog('No nonce found in request data!', null, 'error');
            }
            
            // Action の確認
            if (parsedData.action) {
                debugLog('Action in request:', parsedData.action);
            } else {
                debugLog('No action found in request data!', null, 'error');
            }
            
            // レスポンステキストが "0" の場合の特別な処理
            if (xhr.responseText === '0') {
                debugLog('Response is "0" - This typically indicates:', [
                    'WordPress AJAX handler returned false',
                    'Action handler not found',
                    'Permission check failed',
                    'Invalid nonce verification'
                ], 'error');
            }
        }
        
        debugLog('=== End Error Analysis ===', null, 'groupEnd');
    }
    
    /**
     * エラー統計の表示
     */
    function showErrorStats() {
        if (errorCount === 0) {
            debugLog('No AJAX errors recorded.');
            return;
        }
        
        debugLog(`=== Error Statistics (Total: ${errorCount}) ===`, null, 'group');
        
        Object.keys(errorStats).forEach(function(action) {
            const count = errorStats[action];
            const percentage = ((count / errorCount) * 100).toFixed(1);
            debugLog(`${action}: ${count} errors (${percentage}%)`);
        });
        
        debugLog('=== End Error Statistics ===', null, 'groupEnd');
    }
    
    /**
     * jQuery AJAX のデフォルトエラーハンドラーを拡張
     */
    function setupAjaxErrorHandler() {
        // 既存のajaxError イベントリスナーを設定
        $(document).ajaxError(function(event, xhr, settings, error) {
            // KantanPro 関連のリクエストのみをキャッチ
            if (settings.url && settings.url.includes('admin-ajax.php')) {
                analyzeAjaxError(xhr, 'error', error, settings.data);
            }
        });
        
        // AJAX送信前のログ
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('admin-ajax.php')) {
                const parsedData = parseRequestData(settings.data);
                debugLog('AJAX Request Sent:', {
                    url: settings.url,
                    rawData: settings.data,
                    parsedData: parsedData,
                    type: settings.type || 'GET',
                    action: parsedData.action || 'unknown'
                });
            }
        });
        
        // AJAX完了時のログ
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('admin-ajax.php')) {
                const parsedData = parseRequestData(settings.data);
                if (xhr.status >= 200 && xhr.status < 300) {
                    debugLog('AJAX Request Completed Successfully:', {
                        url: settings.url,
                        status: xhr.status,
                        action: parsedData.action || 'unknown',
                        responseLength: xhr.responseText ? xhr.responseText.length : 0,
                        responsePreview: xhr.responseText ? xhr.responseText.substring(0, 200) + '...' : 'No response'
                    });
                }
            }
        });
    }
    
    /**
     * 初期化処理
     */
    function init() {
        debugLog('KantanPro AJAX Debug Helper initialized');
        
        // AJAX設定の確認
        logAjaxConfig();
        
        // エラーハンドラーの設定
        setupAjaxErrorHandler();
        
        // デバッグ情報の定期表示（開発モードのみ）
        if (DEBUG_ENABLED) {
            // 30秒ごとにエラー統計を表示
            setInterval(function() {
                if (errorCount > 0) {
                    showErrorStats();
                }
            }, 30000);
        }
        
        // ページ離脱時に最終統計を表示
        $(window).on('beforeunload', function() {
            if (errorCount > 0) {
                showErrorStats();
            }
        });
        
        // 手動でエラー統計を表示するキーボードショートカット (Ctrl+Shift+D)
        $(document).keydown(function(e) {
            if (e.ctrlKey && e.shiftKey && e.which === 68) { // Ctrl+Shift+D
                e.preventDefault();
                debugLog('=== Manual Debug Output ===', null, 'group');
                showErrorStats();
                logAjaxConfig();
                
                // サーバーログも表示
                if (window.KTPAjaxDebug && window.KTPAjaxDebug.showServerLog) {
                    window.KTPAjaxDebug.showServerLog(50);
                }
                
                debugLog('=== End Manual Debug Output ===', null, 'groupEnd');
            }
        });
    }
    
    // DOM読み込み完了後に初期化
    $(document).ready(function() {
        init();
    });
    
    // グローバル関数として公開
    window.KTPAjaxDebug = {
        showStats: showErrorStats,
        logConfig: logAjaxConfig,
        getErrorCount: function() { return errorCount; },
        getErrorStats: function() { return errorStats; },
        showServerLog: function(lines = 100) {
            const nonce = (typeof ktp_ajax_debug !== 'undefined' && ktp_ajax_debug.nonce) ? ktp_ajax_debug.nonce : '';
            const ajaxUrl = (typeof ktp_ajax_debug !== 'undefined' && ktp_ajax_debug.ajaxurl) ? ktp_ajax_debug.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            
            if (!ajaxUrl) {
                console.error('AJAX URL is not defined');
                return;
            }
            
            jQuery.post(ajaxUrl, {
                action: 'ktp_get_ajax_debug_log',
                lines: lines,
                _wpnonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    console.group('=== Server AJAX Debug Log ===');
                    console.log('Log file:', response.data.log_file);
                    console.log('Recent entries:');
                    console.log(response.data.log);
                    console.groupEnd();
                } else {
                    console.error('Failed to get server log:', response.data);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error fetching server log:', error);
            });
        },
        clearServerLog: function() {
            const nonce = (typeof ktp_ajax_debug !== 'undefined' && ktp_ajax_debug.nonce) ? ktp_ajax_debug.nonce : '';
            const ajaxUrl = (typeof ktp_ajax_debug !== 'undefined' && ktp_ajax_debug.ajaxurl) ? ktp_ajax_debug.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            
            if (!ajaxUrl) {
                console.error('AJAX URL is not defined');
                return;
            }
            
            jQuery.post(ajaxUrl, {
                action: 'ktp_clear_ajax_debug_log',
                _wpnonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    console.log('Server log cleared:', response.data);
                } else {
                    console.error('Failed to clear server log:', response.data);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error clearing server log:', error);
            });
        }
    };
    
})(jQuery);