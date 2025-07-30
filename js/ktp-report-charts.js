/**
 * KTP Report Charts JavaScript
 * 
 * Handles chart rendering for the report tab using Chart.js
 * 
 * @package KTPWP
 * @since 1.0.0
 */

(function() {
    'use strict';

    // 色設定
    const chartColors = {
        primary: '#1976d2',
        secondary: '#4caf50',
        accent: '#ff9800',
        warning: '#f44336',
        info: '#2196f3',
        success: '#4caf50',
        light: '#f8f9fa',
        dark: '#333',
        gradients: [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
            'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'
        ]
    };

    // 共通のグラフオプション
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: chartColors.dark,
                    font: {
                        size: 12
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: '#eee'
                },
                ticks: {
                    color: chartColors.dark
                }
            },
            y: {
                grid: {
                    color: '#eee'
                },
                ticks: {
                    color: chartColors.dark,
                    callback: function(value) {
                        return '¥' + value.toLocaleString();
                    }
                }
            }
        }
    };

    // 棒グラフ用の高さ制限オプション
    const barChartOptions = {
        ...commonOptions,
        plugins: {
            ...commonOptions.plugins,
            legend: {
                ...commonOptions.plugins.legend,
                position: 'top'
            }
        }
    };

    // ページ読み込み完了時にグラフを初期化
    document.addEventListener('DOMContentLoaded', function() {
        // AJAX用のnonceを設定
        if (typeof ktp_ajax_object !== 'undefined') {
            console.log('ktp_ajax_object全体:', ktp_ajax_object);
            console.log('ktp_ajax_object.nonces:', ktp_ajax_object.nonces);
            console.log('ktp_ajax_object.nonce:', ktp_ajax_object.nonce);
            
            window.ktp_report_nonce = ktp_ajax_object.nonce || '';
            console.log('レポート用nonce設定:', {
                nonces: ktp_ajax_object.nonces,
                general: ktp_ajax_object.nonces?.general,
                nonce: ktp_ajax_object.nonce,
                final: window.ktp_report_nonce
            });
            
            // nonce設定後にグラフを初期化
            initializeCharts();
        } else {
            console.error('ktp_ajax_objectが利用できません');
        }
    });

    /**
     * 現在の期間を取得
     */
    function getCurrentPeriod() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('period') || 'all_time';
    }

    /**
     * グラフの初期化
     */
    function initializeCharts() {
        // 売上レポートのグラフ
        initializeSalesCharts();
        
        // 進捗レポートのグラフ
        initializeProgressCharts();
        
        // 顧客レポートのグラフ
        initializeClientCharts();
        
        // サービスレポートのグラフ
        initializeServiceCharts();
        
        // 協力会社レポートのグラフ
        initializeSupplierCharts();
    }

    /**
     * 売上レポートのグラフ初期化
     */
    function initializeSalesCharts() {
        const period = getCurrentPeriod();
        
        // AJAXでデータを取得してグラフを描画
        fetchReportData('sales', period).then(data => {
            console.log('売上レポートデータ:', data);
            
            // 月別売上推移グラフ
            const monthlySalesCanvas = document.getElementById('monthlySalesChart');
            if (monthlySalesCanvas && data.monthly) {
                const labels = data.monthly.map(item => item.label);
                const values = data.monthly.map(item => item.value);
                
                new Chart(monthlySalesCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '月別売上',
                            data: values,
                            borderColor: chartColors.primary,
                            backgroundColor: chartColors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: {
                            ...commonOptions.plugins,
                            title: {
                                display: true,
                                text: '月別売上推移',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }

            // 進捗別売上グラフ
            const progressSalesCanvas = document.getElementById('progressSalesChart');
            if (progressSalesCanvas && data.progress) {
                const labels = data.progress.map(item => item.label);
                const values = data.progress.map(item => item.value);
                
                new Chart(progressSalesCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: chartColors.gradients.map(gradient => {
                                return getGradientColor(gradient);
                            }),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: chartColors.dark,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: '進捗別売上',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * 進捗レポートのグラフ初期化
     */
    function initializeProgressCharts() {
        const period = getCurrentPeriod();
        
        // AJAXでデータを取得してグラフを描画
        fetchReportData('progress', period).then(data => {
            // 進捗状況分布グラフ
            const progressDistributionCanvas = document.getElementById('progressDistributionChart');
            if (progressDistributionCanvas && data.progress) {
                const labels = data.progress.map(item => item.label);
                const values = data.progress.map(item => item.value);
                
                new Chart(progressDistributionCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '案件数',
                            data: values,
                            backgroundColor: chartColors.gradients.map(gradient => {
                                return getGradientColor(gradient);
                            }),
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: {
                            ...commonOptions.plugins,
                            title: {
                                display: true,
                                text: '進捗状況分布',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + '件';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // 納期管理グラフ
            const deadlineCanvas = document.getElementById('deadlineChart');
            if (deadlineCanvas && data.deadline) {
                const labels = data.deadline.map(item => item.label);
                const overdue = data.deadline.map(item => item.overdue);
                const onTime = data.deadline.map(item => item.on_time);
                
                new Chart(deadlineCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '納期超過',
                            data: overdue,
                            backgroundColor: chartColors.warning,
                            borderColor: '#fff',
                            borderWidth: 1
                        }, {
                            label: '納期内',
                            data: onTime,
                            backgroundColor: chartColors.success,
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: {
                            ...commonOptions.plugins,
                            title: {
                                display: true,
                                text: '納期管理',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + '件';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * 顧客レポートのグラフ初期化
     */
    function initializeClientCharts() {
        const period = getCurrentPeriod();
        
        // AJAXでデータを取得してグラフを描画
        fetchReportData('client', period).then(data => {
            // 顧客別売上グラフ
            const clientSalesCanvas = document.getElementById('clientSalesChart');
            if (clientSalesCanvas && data.sales) {
                const labels = data.sales.map(item => item.label);
                const values = data.sales.map(item => item.value);
                
                new Chart(clientSalesCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '売上',
                            data: values,
                            backgroundColor: chartColors.primary,
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...barChartOptions,
                        plugins: {
                            ...barChartOptions.plugins,
                            title: {
                                display: true,
                                text: '顧客別売上',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }

            // 顧客別案件数グラフ
            const clientOrderCanvas = document.getElementById('clientOrderChart');
            if (clientOrderCanvas && data.orders) {
                const labels = data.orders.map(item => item.label);
                const values = data.orders.map(item => item.value);
                
                new Chart(clientOrderCanvas, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: chartColors.gradients.map(gradient => {
                                return getGradientColor(gradient);
                            }),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: chartColors.dark,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: '顧客別案件数',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * サービスレポートのグラフ初期化
     */
    function initializeServiceCharts() {
        const period = getCurrentPeriod();
        
        // AJAXでデータを取得してグラフを描画
        fetchReportData('service', period).then(data => {
            // サービス別売上グラフ
            const serviceSalesCanvas = document.getElementById('serviceSalesChart');
            if (serviceSalesCanvas && data.sales) {
                const labels = data.sales.map(item => item.label);
                const values = data.sales.map(item => item.value);
                
                new Chart(serviceSalesCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '売上',
                            data: values,
                            backgroundColor: chartColors.secondary,
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...barChartOptions,
                        plugins: {
                            ...barChartOptions.plugins,
                            title: {
                                display: true,
                                text: 'サービス別売上',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }

            // サービス利用率グラフ
            const serviceUsageCanvas = document.getElementById('serviceUsageChart');
            if (serviceUsageCanvas && data.usage) {
                const labels = data.usage.map(item => item.label);
                const values = data.usage.map(item => item.value);
                
                new Chart(serviceUsageCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: chartColors.gradients.map(gradient => {
                                return getGradientColor(gradient);
                            }),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: chartColors.dark,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'サービス利用率',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * 協力会社レポートのグラフ初期化
     */
    function initializeSupplierCharts() {
        const period = getCurrentPeriod();
        
        // AJAXでデータを取得してグラフを描画
        fetchReportData('supplier', period).then(data => {
            // 協力会社別貢献度グラフ
            const supplierContributionCanvas = document.getElementById('supplierContributionChart');
            if (supplierContributionCanvas && data.contribution) {
                const labels = data.contribution.map(item => item.label);
                const values = data.contribution.map(item => item.value);
                
                new Chart(supplierContributionCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '貢献度',
                            data: values,
                            backgroundColor: chartColors.accent,
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...barChartOptions,
                        plugins: {
                            ...barChartOptions.plugins,
                            title: {
                                display: true,
                                text: '協力会社別貢献度',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }

            // スキル別分布グラフ
            const skillDistributionCanvas = document.getElementById('skillDistributionChart');
            if (skillDistributionCanvas && data.skills) {
                const labels = data.skills.map(item => item.label);
                const values = data.skills.map(item => item.value);
                
                new Chart(skillDistributionCanvas, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: chartColors.gradients.map(gradient => {
                                return getGradientColor(gradient);
                            }),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: chartColors.dark,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'スキル別分布',
                                color: chartColors.dark,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * AJAXデータ取得関数群
     */
    
    /**
     * AJAXでレポートデータを取得
     */
    function fetchReportData(reportType, period = 'all_time') {
        return new Promise((resolve, reject) => {
            if (typeof ktp_ajax_object === 'undefined') {
                // AJAXが利用できない場合は空のデータを返す
                console.warn('AJAXオブジェクトが利用できません');
                resolve({});
                return;
            }

            const formData = new FormData();
            formData.append('action', 'ktp_get_report_data');
            formData.append('report_type', reportType);
            formData.append('period', period);
            // nonceが空の場合はktp_ajax_objectから直接取得
            const nonce = window.ktp_report_nonce || (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : '');
            formData.append('nonce', nonce);
            
            console.log('AJAXリクエスト送信:', {
                action: 'ktp_get_report_data',
                report_type: reportType,
                period: period,
                nonce: nonce
            });

            fetch(ktp_ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('AJAXレスポンス:', {
                    status: response.status,
                    statusText: response.statusText,
                    url: response.url
                });
                
                // レスポンスのテキストを取得してログ出力
                return response.text().then(text => {
                    console.log('AJAXレスポンス本文:', text.substring(0, 500) + (text.length > 500 ? '...' : ''));
                    
                    try {
                        return JSON.parse(text);
                    } catch (parseError) {
                        console.error('JSON解析エラー:', parseError);
                        console.error('レスポンス本文:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('AJAXレスポンス解析結果:', data);
                if (data.success) {
                    console.log('レポートデータ取得成功:', data.data);
                    resolve(data.data);
                } else {
                    console.warn('レポートデータの取得に失敗しました:', data);
                    resolve({});
                }
            })
            .catch(error => {
                console.error('AJAXエラー:', error);
                resolve({});
            });
        });
    }

    // ダミーデータ関数は削除（実際のデータのみを使用）

    /**
     * グラデーション文字列から色を取得（簡易版）
     */
    function getGradientColor(gradient) {
        const colorMap = {
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)': '#667eea',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)': '#f093fb',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)': '#4facfe',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)': '#43e97b',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)': '#fa709a',
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)': '#a8edea',
            'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)': '#ff9a9e'
        };
        return colorMap[gradient] || '#667eea';
    }

    // グローバルスコープに公開（必要に応じて）
    window.KTPReportCharts = {
        initializeCharts: initializeCharts,
        getMonthlySalesData: getMonthlySalesData,
        getProgressSalesData: getProgressSalesData
    };

})(); 