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

    // 背景色が明るいか（rgb/rgba/#hex から簡易判定）
    function isLightBackgroundColor(cssColor) {
        if (!cssColor || cssColor === 'transparent' || cssColor === 'rgba(0, 0, 0, 0)') {
            return null;
        }
        var m = cssColor.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (m) {
            var r = parseInt(m[1], 10), g = parseInt(m[2], 10), b = parseInt(m[3], 10);
            return (r + g + b) / 3 > 128;
        }
        m = cssColor.match(/#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})/);
        if (m) {
            var r2 = parseInt(m[1], 16), g2 = parseInt(m[2], 16), b2 = parseInt(m[3], 16);
            return (r2 + g2 + b2) / 3 > 128;
        }
        if (/white|#fff|#ffffff/i.test(cssColor)) { return true; }
        if (/black|#000|#000000/i.test(cssColor)) { return false; }
        return null;
    }

    // 要素から親を遡り、最初に得られた非透明な背景色を返す
    function getEffectiveBackgroundColor(el) {
        var node = el;
        while (node && node !== document.body) {
            var bg = window.getComputedStyle(node).backgroundColor;
            if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
                return bg;
            }
            node = node.parentElement || node.parentNode;
        }
        if (document.body) {
            return window.getComputedStyle(document.body).backgroundColor;
        }
        if (document.documentElement) {
            return window.getComputedStyle(document.documentElement).backgroundColor;
        }
        return '';
    }

    // ダークモード検出（実際の背景色を最優先し、ライト/ダーク両方でグラフが読めるように）
    function isDarkMode() {
        if (document.body && document.body.getAttribute('data-ktp-force-light') === '1') {
            return false;
        }
        var reportEl = document.getElementById('report_content');
        if (reportEl) {
            var bg = getEffectiveBackgroundColor(reportEl);
            var lightBg = isLightBackgroundColor(bg);
            if (lightBg === true) { return false; }
            if (lightBg === false) { return true; }
            if (lightBg === null) { return false; }
        }
        var body = document.body;
        if (body) {
            var bg = window.getComputedStyle(body).backgroundColor;
            var lightBg = isLightBackgroundColor(bg);
            if (lightBg === null && document.documentElement) {
                bg = window.getComputedStyle(document.documentElement).backgroundColor;
                lightBg = isLightBackgroundColor(bg);
            }
            if (lightBg === true) { return false; }
            if (lightBg === false) { return true; }
        }
        var bodyClass = body && body.className ? body.className : '';
        if (/admin-color-(light|fresh|blue|coffee|ectoplasm|ocean|sunrise)/i.test(bodyClass)) {
            return false;
        }
        if (/admin-color-(modern|midnight)/i.test(bodyClass) || /dark|nightly/i.test(bodyClass)) {
            return true;
        }
        var wrap = document.getElementById('wpwrap');
        if (wrap && wrap.className && /dark|nightly/i.test(wrap.className)) {
            return true;
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return true;
        }
        return false;
    }

    // テーマに応じたグラフ用の文字色・グリッド色を返す（印刷時は黒で可読性を確保）
    // レポートでも isDarkMode() で判定（#report_content はインラインで白指定されているため
    // 背景色取得だとダークモード時も黒文字になり、印刷ダイアログ閉じ後に読めなくなる）
    function getChartTheme() {
        var dark = isDarkMode();
        return {
            textColor: dark ? '#e8e8e8' : '#333333',
            gridColor: dark ? 'rgba(255, 255, 255, 0.2)' : '#ddd'
        };
    }

    // 共通のグラフオプション（ダークモード対応）
    function getCommonOptions() {
        var theme = getChartTheme();
        var isDark = theme.textColor === '#e8e8e8';
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: theme.textColor,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: isDark ? {
                    titleColor: '#e8e8e8',
                    bodyColor: '#e8e8e8',
                    backgroundColor: 'rgba(50, 50, 50, 0.95)',
                    borderColor: 'rgba(255, 255, 255, 0.2)'
                } : {}
            },
            scales: {
                x: {
                    grid: {
                        color: theme.gridColor
                    },
                    ticks: {
                        color: theme.textColor
                    }
                },
                y: {
                    grid: {
                        color: theme.gridColor
                    },
                    ticks: {
                        color: theme.textColor,
                        callback: function(value) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            }
        };
    }

    // 棒グラフ用の高さ制限オプション
    function getBarChartOptions() {
        var common = getCommonOptions();
        return {
            ...common,
            plugins: {
                ...common.plugins,
                legend: {
                    ...common.plugins.legend,
                    position: 'top'
                }
            }
        };
    }

    var chartsInitialized = false;

    // レポートタブかどうか（URL の tab_name=report）
    function isReportTabActive() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('tab_name') === 'report';
    }

    // canvas に描画用のサイズを確実に持たせる（親が非表示で 0x0 の場合の対策）
    function ensureCanvasSize(canvas) {
        if (!canvas) return;
        var w = canvas.getAttribute('width') || 400;
        var h = canvas.getAttribute('height') || 300;
        if (canvas.offsetWidth === 0 || canvas.offsetHeight === 0) {
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
        }
    }

    // ページ完全読み込み後・レポートタブのときだけグラフを初期化（確実に描画するため）
    function tryInitializeChartsWhenVisible() {
        if (chartsInitialized || typeof ktp_ajax_object === 'undefined') {
            return;
        }
        if (typeof Chart === 'undefined') {
            setTimeout(tryInitializeChartsWhenVisible, 100);
            return;
        }
        window.ktp_report_nonce = ktp_ajax_object.nonce || '';
        if (!isReportTabActive()) {
            return;
        }
        chartsInitialized = true;
        initializeCharts();
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof ktp_ajax_object !== 'undefined') {
            window.ktp_report_nonce = ktp_ajax_object.nonce || '';
            setTimeout(tryInitializeChartsWhenVisible, 100);
        }
    });
    window.addEventListener('load', function() {
        if (chartsInitialized) return;
        if (typeof ktp_ajax_object === 'undefined') return;
        if (isReportTabActive()) {
            chartsInitialized = true;
            initializeCharts();
            return;
        }
        // URL に tab_name が無い場合のフォールバック: レポート用 canvas が存在すれば遅延で初期化
        setTimeout(function() {
            if (chartsInitialized) return;
            if (document.getElementById('monthlySalesChart') || document.getElementById('clientSalesChart')) {
                chartsInitialized = true;
                initializeCharts();
            }
        }, 500);
    });

    // 現在の期間を取得
    function getCurrentPeriod() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('period') || 'all_time';
    }

    // グラフの初期化
    function initializeCharts() {
        const currentReport = getCurrentReportType();
        const currentPeriod = getCurrentPeriod();

        // ここで Chart.js 全体のデフォルト文字色も黒に固定しておく（テーマに依存させない）
        if (typeof Chart !== 'undefined' && Chart.defaults && Chart.defaults.color !== '#000000') {
            Chart.defaults.color = '#000000';
            if (Chart.defaults.font) {
                Chart.defaults.font.family = Chart.defaults.font.family || '"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif';
                Chart.defaults.font.size   = Chart.defaults.font.size || 12;
            }
        }
        
        console.log('グラフ初期化:', { report: currentReport, period: currentPeriod });
        
        // レポートタイプに応じてグラフを初期化
        switch (currentReport) {
            case 'sales':
                initializeSalesCharts(currentPeriod);
                break;
            case 'client':
                initializeClientCharts(currentPeriod);
                break;
            case 'service':
                initializeServiceCharts(currentPeriod);
                break;
            case 'supplier':
                initializeSupplierCharts(currentPeriod);
                break;
            default:
                initializeSalesCharts(currentPeriod);
                break;
        }
    }

    // 売上レポートのグラフ初期化
    function initializeSalesCharts(period = 'all_time') {
        console.log('売上レポートグラフ初期化開始:', period);
        
        fetchReportData('sales', period).then(function(data) {
            console.log('売上データ取得成功:', data);
            
            // 月別売上推移グラフ
            const monthlySalesCtx = document.getElementById('monthlySalesChart');
            if (monthlySalesCtx && data.monthly_sales) {
                ensureCanvasSize(monthlySalesCtx);
                var monthlyTheme = getChartTheme();
                var monthlyCommon = getCommonOptions();
                var monthlyOptions = {
                    ...monthlyCommon,
                    responsive: false,
                    plugins: {
                        ...monthlyCommon.plugins,
                        title: {
                            display: true,
                            text: '月別売上推移',
                            color: monthlyTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    },
                    scales: {
                        ...monthlyCommon.scales,
                        y: {
                            ...monthlyCommon.scales.y,
                            beginAtZero: true,
                            ticks: {
                                ...monthlyCommon.scales.y.ticks,
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    }
                };
                new Chart(monthlySalesCtx, {
                    type: 'line',
                    data: {
                        labels: data.monthly_sales.labels,
                        datasets: [{
                            label: '売上金額',
                            data: data.monthly_sales.data,
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(25, 118, 210, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: chartColors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: monthlyOptions
                });
            }

            // 利益推移グラフ
            const profitTrendCtx = document.getElementById('profitTrendChart');
            if (profitTrendCtx && data.profit_trend) {
                ensureCanvasSize(profitTrendCtx);
                var profitTheme = getChartTheme();
                var profitOptions = {
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: profitTheme.textColor,
                                font: { size: 12 }
                            }
                        },
                        title: {
                            display: true,
                            text: '月別利益コスト比較',
                            color: profitTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { color: profitTheme.gridColor },
                            ticks: { color: profitTheme.textColor }
                        },
                        y: {
                            stacked: true,
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: profitTheme.gridColor },
                            ticks: {
                                color: profitTheme.textColor,
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            },
                            beginAtZero: true
                        }
                    }
                };
                new Chart(profitTrendCtx, {
                    type: 'bar',
                    data: {
                        labels: data.profit_trend.labels,
                        datasets: [
                            {
                                label: 'コスト',
                                data: data.profit_trend.cost,
                                backgroundColor: chartColors.warning,
                                borderColor: chartColors.warning,
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                label: '利益',
                                data: data.profit_trend.profit,
                                backgroundColor: chartColors.success,
                                borderColor: chartColors.success,
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y'
                            }
                        ]
                    },
                    options: profitOptions
                });
            }
        }).catch(function(error) {
            console.error('売上データ取得エラー:', error);
        });
    }



    // 顧客レポートのグラフ初期化
    function initializeClientCharts(period = 'all_time') {
        console.log('顧客レポートグラフ初期化開始:', period);
        
        fetchReportData('client', period).then(function(data) {
            console.log('顧客データ取得成功:', data);
            
            // 顧客別売上グラフ
            const clientSalesCtx = document.getElementById('clientSalesChart');
            if (clientSalesCtx && data.client_sales) {
                ensureCanvasSize(clientSalesCtx);
                var clientSalesTheme = getChartTheme();
                var clientSalesBarOpts = getBarChartOptions();
                var clientSalesOptions = {
                    ...clientSalesBarOpts,
                    responsive: false,
                    plugins: {
                        ...clientSalesBarOpts.plugins,
                        title: {
                            display: true,
                            text: '顧客別売上',
                            color: clientSalesTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    },
                    scales: {
                        ...clientSalesBarOpts.scales,
                        y: {
                            ...clientSalesBarOpts.scales.y,
                            beginAtZero: true,
                            ticks: {
                                ...clientSalesBarOpts.scales.y.ticks,
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    }
                };
                new Chart(clientSalesCtx, {
                    type: 'bar',
                    data: {
                        labels: data.client_sales.labels,
                        datasets: [{
                            label: '売上金額',
                            data: data.client_sales.data,
                            backgroundColor: data.client_sales.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0,
                            borderRadius: 8
                        }]
                    },
                    options: clientSalesOptions
                });
            }

            // 顧客別案件数グラフ
            const clientOrderCtx = document.getElementById('clientOrderChart');
            if (clientOrderCtx && data.client_orders) {
                ensureCanvasSize(clientOrderCtx);
                var clientOrderTheme = getChartTheme();
                var clientOrderOptions = {
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: clientOrderTheme.textColor,
                                font: { size: 12 },
                                padding: 20
                            }
                        },
                        title: {
                            display: true,
                            text: '顧客別案件数',
                            color: clientOrderTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    }
                };
                new Chart(clientOrderCtx, {
                    type: 'pie',
                    data: {
                        labels: data.client_orders.labels,
                        datasets: [{
                            data: data.client_orders.data,
                            backgroundColor: data.client_orders.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0
                        }]
                    },
                    options: clientOrderOptions
                });
            }
        }).catch(function(error) {
            console.error('顧客データ取得エラー:', error);
        });
    }

    // サービスレポートのグラフ初期化
    function initializeServiceCharts(period = 'all_time') {
        console.log('サービスレポートグラフ初期化開始:', period);
        
        fetchReportData('service', period).then(function(data) {
            console.log('サービスデータ取得成功:', data);
            
            // サービス別売上グラフ
            const serviceSalesCtx = document.getElementById('serviceSalesChart');
            if (serviceSalesCtx && data.service_sales) {
                ensureCanvasSize(serviceSalesCtx);
                var serviceSalesTheme = getChartTheme();
                var serviceSalesBarOpts = getBarChartOptions();
                var serviceSalesOptions = {
                    ...serviceSalesBarOpts,
                    responsive: false,
                    plugins: {
                        ...serviceSalesBarOpts.plugins,
                        title: {
                            display: true,
                            text: 'サービス別売上',
                            color: serviceSalesTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    },
                    scales: {
                        ...serviceSalesBarOpts.scales,
                        y: {
                            ...serviceSalesBarOpts.scales.y,
                            beginAtZero: true,
                            ticks: {
                                ...serviceSalesBarOpts.scales.y.ticks,
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    }
                };
                new Chart(serviceSalesCtx, {
                    type: 'bar',
                    data: {
                        labels: data.service_sales.labels,
                        datasets: [{
                            label: '売上金額',
                            data: data.service_sales.data,
                            backgroundColor: data.service_sales.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0,
                            borderRadius: 8
                        }]
                    },
                    options: serviceSalesOptions
                });
            }

            // サービス別比率（受注ベース）グラフ
            const serviceQuantityCtx = document.getElementById('serviceQuantityChart');
            if (serviceQuantityCtx && data.service_quantity) {
                ensureCanvasSize(serviceQuantityCtx);
                var serviceQtyTheme = getChartTheme();
                var serviceQtyOptions = {
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: serviceQtyTheme.textColor,
                                font: { size: 12 },
                                padding: 20
                            }
                        },
                        title: {
                            display: true,
                            text: 'サービス別比率（受注ベース）',
                            color: serviceQtyTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + '件 (' + percentage + '%)';
                                }
                            }
                        }
                    }
                };
                new Chart(serviceQuantityCtx, {
                    type: 'pie',
                    data: {
                        labels: data.service_quantity.labels,
                        datasets: [{
                            data: data.service_quantity.data,
                            backgroundColor: data.service_quantity.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0
                        }]
                    },
                    options: serviceQtyOptions
                });
            }
        }).catch(function(error) {
            console.error('サービスデータ取得エラー:', error);
        });
    }

    // 協力会社レポートのグラフ初期化
    function initializeSupplierCharts(period = 'all_time') {
        console.log('協力会社レポートグラフ初期化開始:', period);
        
        fetchReportData('supplier', period).then(function(data) {
            console.log('協力会社データ取得成功:', data);
            
            // 協力会社別スキル数グラフ
            const supplierSkillsCtx = document.getElementById('supplierSkillsChart');
            if (supplierSkillsCtx && data.supplier_skills) {
                ensureCanvasSize(supplierSkillsCtx);
                var supplierSkillsTheme = getChartTheme();
                var supplierSkillsBarOpts = getBarChartOptions();
                var supplierSkillsOptions = {
                    ...supplierSkillsBarOpts,
                    responsive: false,
                    plugins: {
                        ...supplierSkillsBarOpts.plugins,
                        title: {
                            display: true,
                            text: '協力会社別貢献度',
                            color: supplierSkillsTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    },
                    scales: {
                        ...supplierSkillsBarOpts.scales,
                        y: {
                            ...supplierSkillsBarOpts.scales.y,
                            beginAtZero: true,
                            ticks: {
                                ...supplierSkillsBarOpts.scales.y.ticks,
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    }
                };
                new Chart(supplierSkillsCtx, {
                    type: 'bar',
                    data: {
                        labels: data.supplier_skills.labels,
                        datasets: [{
                            label: '貢献度',
                            data: data.supplier_skills.data,
                            backgroundColor: data.supplier_skills.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0,
                            borderRadius: 8
                        }]
                    },
                    options: supplierSkillsOptions
                });
            }

            // スキル別協力会社数グラフ
            const skillSuppliersCtx = document.getElementById('skillSuppliersChart');
            if (skillSuppliersCtx && data.skill_suppliers) {
                ensureCanvasSize(skillSuppliersCtx);
                var skillSuppliersTheme = getChartTheme();
                var skillSuppliersOptions = {
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: skillSuppliersTheme.textColor,
                                font: { size: 12 },
                                padding: 20
                            }
                        },
                        title: {
                            display: true,
                            text: 'スキル別協力会社数',
                            color: skillSuppliersTheme.textColor,
                            font: { size: 16, weight: 'bold' }
                        }
                    }
                };
                new Chart(skillSuppliersCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.skill_suppliers.labels,
                        datasets: [{
                            data: data.skill_suppliers.data,
                            backgroundColor: data.skill_suppliers.labels.map(function(_, index) {
                                return getGradientColor(chartColors.gradients[index % chartColors.gradients.length]);
                            }),
                            borderWidth: 0
                        }]
                    },
                    options: skillSuppliersOptions
                });
            }
        }).catch(function(error) {
            console.error('協力会社データ取得エラー:', error);
        });
    }

    // レポートデータを取得
    function fetchReportData(reportType, period = 'all_time') {
        console.log('レポートデータ取得開始:', { reportType, period });
        
        return new Promise(function(resolve, reject) {
            if (typeof ktp_ajax_object === 'undefined') {
                reject(new Error('AJAX設定が見つかりません'));
                return;
            }

            const formData = new FormData();
            formData.append('action', 'ktpwp_get_report_data');
            formData.append('report_type', reportType);
            formData.append('period', period);
            formData.append('nonce', window.ktp_report_nonce || ktp_ajax_object.nonce);

            fetch(ktp_ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                console.log('AJAXレスポンス:', data);
                if (data.success) {
                    resolve(data.data);
                } else {
                    reject(new Error(data.data || 'データ取得に失敗しました'));
                }
            })
            .catch(function(error) {
                console.error('AJAXエラー:', error);
                reject(error);
            });
        });
    }

    // 現在のレポートタイプを取得
    function getCurrentReportType() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('report_type') || 'sales';
    }

    // グラデーション色を取得
    function getGradientColor(gradient) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const gradientObj = ctx.createLinearGradient(0, 0, 0, 400);
        
        if (gradient.includes('linear-gradient')) {
            // グラデーション文字列から色を抽出
            const colors = gradient.match(/#[a-fA-F0-9]{6}/g);
            if (colors && colors.length >= 2) {
                gradientObj.addColorStop(0, colors[0]);
                gradientObj.addColorStop(1, colors[1]);
            }
        }
        
        return gradientObj;
    }

    // グローバルスコープに公開（必要に応じて）
    window.KTPReportCharts = {
        initializeCharts: initializeCharts,
        getMonthlySalesData: getMonthlySalesData,
        getProfitTrendData: getProfitTrendData
    };

})(); 