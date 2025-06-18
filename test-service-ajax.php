<?php
/**
 * サービス選択Ajax機能のテストスクリプト
 * 
 * 使用方法：WordPressの管理画面で、以下のURLにアクセスしてください
 * http://localhost:8010/wp-content/plugins/KantanPro/test-service-ajax.php
 */

// WordPressの読み込み
require_once('../../../wp-load.php');

// ログインチェック
if (!is_user_logged_in()) {
    die('<h1>エラー</h1><p>この機能を使用するにはログインが必要です。</p><p><a href="' . wp_login_url() . '">ログインページ</a></p>');
}

// 管理者権限チェック
if (!current_user_can('edit_posts')) {
    die('<h1>エラー</h1><p>この機能を使用するには投稿編集権限が必要です。</p>');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>サービス選択Ajax テスト</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        button { padding: 10px 15px; margin: 5px; }
        .result { background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; }
        .error { border-left-color: #dc3232; }
        .success { border-left-color: #46b450; }
    </style>
</head>
<body>
    <h1>サービス選択Ajax テスト</h1>
    
    <div class="test-section">
        <h2>テスト 1: 基本的なサービス一覧取得</h2>
        <button onclick="testServiceList()">サービス一覧取得テスト</button>
        <div id="test1-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>テスト 2: ページネーション付きサービス一覧取得</h2>
        <button onclick="testServiceListPagination()">ページネーション テスト</button>
        <div style="margin: 10px 0;">
            ページ: <input type="number" id="test-page" value="1" min="1" style="width: 60px;">
            リミット: <input type="number" id="test-limit" value="5" min="1" max="50" style="width: 60px;">
            <button onclick="testServiceListWithParams()">指定ページテスト</button>
        </div>
        <div id="test2-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>テスト 3: サービス選択ポップアップ（ページネーション対応）</h2>
        <div style="margin: 10px 0;">
            <input type="hidden" name="order_id" value="1" />
            <table>
                <tr id="test-row">
                    <td><button type="button" onclick="openServiceSelector()">サービス選択 ></button></td>
                    <td><input type="text" class="product-name" value="" /></td>
                    <td><input type="number" class="price" value="0" /></td>
                    <td><input type="number" class="quantity" value="1" /></td>
                    <td><input type="text" class="unit" value="" /></td>
                    <td><input type="number" class="amount" value="0" readonly /></td>
                </tr>
            </table>
        </div>
        <div id="test3-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>テスト 4: 検索機能付きサービス一覧取得</h2>
        <div style="margin: 10px 0;">
            検索キーワード: <input type="text" id="search-term" placeholder="サービス名で検索" style="width: 200px;">
            <button onclick="testServiceListSearch()">検索テスト</button>
        </div>
        <div id="test4-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>テスト 3: 検索機能付きサービス一覧取得</h2>
        <input type="text" id="search-term" placeholder="検索キーワード" value="テスト">
        <button onclick="testServiceListSearch()">検索テスト</button>
        <div id="test3-result" class="result"></div>
    </div>

    <script>
        // Ajax URL
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        const nonce = '<?php echo wp_create_nonce('ktp_ajax_nonce'); ?>';

        console.log('Ajax URL:', ajaxUrl);
        console.log('Nonce:', nonce);

        // 基本的なサービス一覧取得テスト
        function testServiceList() {
            const resultDiv = document.getElementById('test1-result');
            resultDiv.innerHTML = 'テスト実行中...';
            
            const formData = new FormData();
            formData.append('action', 'ktp_get_service_list');
            formData.append('nonce', nonce);
            formData.append('page', '1');
            formData.append('limit', '10');

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test 1 Response:', data);
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>成功!</h3>
                        <p>取得件数: ${data.data.services.length}</p>
                        <p>総ページ数: ${data.data.pagination.total_pages}</p>
                        <p>サービス例:</p>
                        <pre>${JSON.stringify(data.data.services[0] || {}, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h3>エラー</h3><p>${data.data}</p>`;
                }
            })
            .catch(error => {
                console.error('Test 1 Error:', error);
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<h3>通信エラー</h3><p>${error.message}</p>`;
            });
        }

        // ページネーション付きテスト
        function testServiceListPagination() {
            const resultDiv = document.getElementById('test2-result');
            resultDiv.innerHTML = 'テスト実行中...';
            
            const formData = new FormData();
            formData.append('action', 'ktp_get_service_list');
            formData.append('nonce', nonce);
            formData.append('page', '1');
            formData.append('limit', '5');

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test 2 Response:', data);
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>成功!</h3>
                        <p>現在のページ: ${data.data.pagination.current_page}</p>
                        <p>総ページ数: ${data.data.pagination.total_pages}</p>
                        <p>総アイテム数: ${data.data.pagination.total_items}</p>
                        <p>ページあたりアイテム数: ${data.data.pagination.items_per_page}</p>
                        <p>取得したサービス:</p>
                        <ul>
                            ${data.data.services.map(service => 
                                `<li>ID:${service.id} - ${service.service_name} - ${service.price}円</li>`
                            ).join('')}
                        </ul>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h3>エラー</h3><p>${data.data}</p>`;
                }
            })
            .catch(error => {
                console.error('Test 2 Error:', error);
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<h3>通信エラー</h3><p>${error.message}</p>`;
            });
        }

        // 指定ページ・リミットでのテスト
        function testServiceListWithParams() {
            const resultDiv = document.getElementById('test2-result');
            const page = document.getElementById('test-page').value;
            const limit = document.getElementById('test-limit').value;
            resultDiv.innerHTML = 'テスト実行中...';
            
            const formData = new FormData();
            formData.append('action', 'ktp_get_service_list');
            formData.append('nonce', nonce);
            formData.append('page', page);
            formData.append('limit', limit);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test 2b Response:', data);
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>成功! (ページ:${page}, リミット:${limit})</h3>
                        <p>現在のページ: ${data.data.pagination.current_page}</p>
                        <p>総ページ数: ${data.data.pagination.total_pages}</p>
                        <p>総アイテム数: ${data.data.pagination.total_items}</p>
                        <p>取得したサービス:</p>
                        <ul>
                            ${data.data.services.map(service => 
                                `<li>ID:${service.id} - ${service.service_name} - ${service.price}円</li>`
                            ).join('')}
                        </ul>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h3>エラー</h3><p>${data.data}</p>`;
                }
            })
            .catch(error => {
                console.error('Test 2b Error:', error);
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<h3>通信エラー</h3><p>${error.message}</p>`;
            });
        }

        // サービス選択ポップアップのテスト
        function openServiceSelector() {
            const testRow = document.getElementById('test-row');
            const resultDiv = document.getElementById('test3-result');
            resultDiv.innerHTML = 'サービス選択ポップアップを開いています...';
            
            if (typeof window.ktpShowServiceSelector === 'function') {
                window.ktpShowServiceSelector($(testRow), 'add');
                resultDiv.className = 'result success';
                resultDiv.innerHTML = '<h3>ポップアップが開かれました</h3><p>ページネーション機能をテストしてください。</p>';
            } else {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = '<h3>エラー</h3><p>ktpShowServiceSelector関数が見つかりません。</p>';
            }
        }

        // 検索機能付きテスト
        function testServiceListSearch() {
            const resultDiv = document.getElementById('test4-result');
            const searchTerm = document.getElementById('search-term').value;
            resultDiv.innerHTML = 'テスト実行中...';
            
            const formData = new FormData();
            formData.append('action', 'ktp_get_service_list');
            formData.append('nonce', nonce);
            formData.append('page', '1');
            formData.append('limit', '10');
            formData.append('search', searchTerm);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test 4 Response:', data);
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>成功!</h3>
                        <p>検索キーワード: ${searchTerm}</p>
                        <p>取得件数: ${data.data.services.length}</p>
                        <p>サービス一覧:</p>
                        <ul>
                            ${data.data.services.map(service => 
                                `<li>${service.service_name} - ${service.price}円 (${service.unit})</li>`
                            ).join('')}
                        </ul>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h3>エラー</h3><p>${data.data}</p>`;
                }
            })
            .catch(error => {
                console.error('Test 4 Error:', error);
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<h3>通信エラー</h3><p>${error.message}</p>`;
            });
        }
    </script>
</body>
</html>
