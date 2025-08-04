#!/bin/zsh

# --- 設定 ---
# プラグインのソースコードが格納されているディレクトリ（現在のディレクトリを使用）
SOURCE_DIR="$(pwd)"
# 生成したZIPファイルの保存先
DEST_PARENT_DIR="/Users/kantanpro/Desktop"
# 保存先フォルダ名
DEST_DIR_NAME="KantanPro_TEST_UP"
# --- 設定ここまで ---

# ビルド用の変数を設定
DEST_DIR="${DEST_PARENT_DIR}/${DEST_DIR_NAME}"
BUILD_DIR_NAME="KantanPro"
BUILD_DIR="${DEST_DIR}/${BUILD_DIR_NAME}"

# エラーが発生した場合はスクリプトを終了する
set -e

echo "--------------------------------------------------"
echo "KantanPro プラグイン配布サイト用ZIPファイル生成スクリプト"
echo "--------------------------------------------------"

# 1. バージョンと日付の取得
echo "[1/7] バージョン情報を取得中..."
# ktpwp.phpからバージョンを抽出 (例: "1.0.6(preview)" -> "1.0.6", "1.0.0(a)" -> "1.0.0a")
VERSION_RAW=$(grep -i "Version:" "$SOURCE_DIR/ktpwp.php" | head -n 1)
echo "  - 生のバージョン情報: ${VERSION_RAW}"
VERSION=$(echo "$VERSION_RAW" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+)\(?([a-zA-Z0-9]*)\)?.*/\1\2/')
DATE=$(date +%Y%m%d)
ZIP_FILE_NAME="KantanPro_${VERSION}_${DATE}.zip"
FINAL_ZIP_PATH="${DEST_DIR}/${ZIP_FILE_NAME}"

echo "  - バージョン: ${VERSION}"
echo "  - 日付: ${DATE}"
echo "  - ZIPファイル名: ${ZIP_FILE_NAME}"

# 2. ビルド環境の準備
echo "\n[2/7] ビルド環境をクリーンアップ中..."
mkdir -p "${DEST_DIR}"
rm -rf "${BUILD_DIR}"
rm -f "${FINAL_ZIP_PATH}"
echo "  - 完了"

# 3. ソースファイルをビルドディレクトリにコピー
echo "\n[3/7] ソースファイルをコピー中..."
# コピー除外リスト（配布サイト用により厳格に設定）
EXCLUDE_LIST=(".git" ".vscode" ".idea" "KantanPro_build_temp" "KantanPro_temp" "wp" "node_modules" "vendor" "wp-content" "wp-cli.phar" "wp-cli.yml" "wp-cli.sh" "wp-cli-aliases.sh" "setup-wp-cli.sh" "WP-CLI-README.md" "create_release_zip.sh" "create_dummy_data.php" "create_dummy_data.php.bak" "run-dummy-data.sh" "test-report-ajax.php" "debug-progress-chart.html" "wp-cli-create-dummy-data.php" "wp-cli-aliases.sh" "wp-cli.yml" "wp-cli.sh" "wp-cli.phar" "setup-wp-cli.sh" "WP-CLI-README.md" "QUICK-START.md" "SECURITY.md" "DUMMY-DATA-README.md" "WP-CLI-README.md" "DEVELOPMENT-ENVIRONMENT-SETUP.md" "DEVELOPMENT-ENVIRONMENT-DETECTION-IMPLEMENTATION.md" "DEBUG-SETUP.md" "DEBUG-AJAX-IMPLEMENTATION.md" "AUTO-MIGRATION-ENHANCEMENT-COMPLETE.md" "AUTO-MIGRATION-IMPLEMENTATION-COMPLETE.md" "CACHE-OPTIMIZATION-FOR-DISTRIBUTION-COMPLETE.md" "COMPLETION-DATE-AUTO-SET-IMPLEMENTATION.md" "COMPREHENSIVE-TAX-TEST-RESULTS.md" "DISTRIBUTION-MIGRATION-COMPLETE.md" "DISTRIBUTION-MIGRATION-ENHANCEMENT-COMPLETE.md" "DISTRIBUTION-MIGRATION-ERROR-FIX-COMPLETE.md" "DISTRIBUTION-README.md" "DISTRIBUTION-UPDATE-CHECK-FIX-COMPLETE.md" "DUMMY-DATA-ENHANCEMENT-PROPOSAL.md" "DUMMY-ORDER-CREATION-DATE-FIX-COMPLETE.md" "FIX-DELETED-SKILLS-CACHE-ISSUE.md" "FOOD-SKILL-TAX-RATE-FIX-COMPLETE.md" "IMPLEMENTATION-SUMMARY.md" "INTERNAL-TAX-CALCULATION-FIX-COMPLETE.md" "INVOICE-PREVIEW-TAX-RATE-COLUMN-COMPLETE.md" "INVOICE-TAX-IMPLEMENTATION-COMPLETE.md" "INVOICE-TAX-TEST-CHECKLIST.md" "LICENSE-MANAGEMENT-IMPLEMENTATION-COMPLETE.md" "MULTIPLE-TAX-RATES-IMPLEMENTATION-COMPLETE.md" "ORDER-INVOICE-TAX-CATEGORY-UPDATE-COMPLETE.md" "ORDER-MEMORY-IMPLEMENTATION-COMPLETE.md" "OUTPUT-BUFFERING-FIX-COMPLETE.md" "PAGINATION-IMPLEMENTATION-COMPLETE.md" "PRODUCT-MANAGEMENT-UPDATE.md" "PROFIT-CALCULATION-FIX-COMPLETE.md" "PURCHASE-ORDER-EMAIL-OPTIMIZATION-COMPLETE.md" "QUALIFIED-INVOICE-PROFIT-CALCULATION-IMPLEMENTATION-COMPLETE.md" "REPORT-TAB-IMPLEMENTATION-COMPLETE.md" "SERVICE-TAX-RATE-NULL-IMPLEMENTATION-COMPLETE.md" "SKILLS-PAGINATION-COMPLETE.md" "STAFF-AVATAR-DISPLAY.md" "STAFF-CHAT-AUTO-SCROLL.md" "SUPPLIER-SKILLS-COMPLETE.md" "SUPPLIER-TAX-CALCULATION-IMPLEMENTATION-COMPLETE.md" "SUPPLIER-TAX-RATE-UPDATE-FIX-COMPLETE.md" "TAX-CATEGORY-LABELS-UPDATE-COMPLETE.md" "TAX-INCLUSIVE-SETTING-REMOVAL-COMPLETE.md" "TAX-RATE-NULL-ALLOWED-COMPLETE.md" "TAX-RATE-NULL-FIX-COMPLETE.md" "TAX-RATE-UPDATE-FIX-COMPLETE.md" "TAX-RATE-ZERO-FIX-COMPLETE.md" "UPDATE-NOTIFICATION-VERSION-FIX-COMPLETE.md" "URL-PERMALINK-DYNAMIC-IMPLEMENTATION-COMPLETE.md")
EXCLUDE_OPTS=""
for item in "${EXCLUDE_LIST[@]}"; do
    EXCLUDE_OPTS+="--exclude=${item} "
done
# rsync を実行
eval rsync -a ${EXCLUDE_OPTS} "\"${SOURCE_DIR}/\"" "\"${BUILD_DIR}/\""
echo "  - 完了"

# 4. Composer依存関係の処理（配布サイト用には不要）
echo "\n[4/7] Composer依存関係を処理中..."
if [ -f "${BUILD_DIR}/composer.json" ]; then
    # 配布サイト用にはcomposer.lockも削除
    rm -f "${BUILD_DIR}/composer.lock"
    echo "  - composer.lock を削除しました（配布サイト用）"
    echo "  - 完了"
else
    echo "  - composer.json が見つからないためスキップしました。"
fi

# 5. 不要なファイルを削除（配布サイト用により厳格に）
echo "\n[5/7] 不要な開発用ファイルを削除中..."
# 削除前のファイル数を記録
BEFORE_COUNT=$(find "${BUILD_DIR}" -type f | wc -l)

# 設定ファイルと開発ツール
find "${BUILD_DIR}" -type f -name ".DS_Store" -delete
find "${BUILD_DIR}" -type f -name ".phpcs.xml" -delete
find "${BUILD_DIR}" -type f -name ".editorconfig" -delete
find "${BUILD_DIR}" -type f -name ".cursorrules" -delete
find "${BUILD_DIR}" -type f -name ".gitignore" -delete
find "${BUILD_DIR}" -type f -name "*.log" -delete

# 開発用PHPファイル（より厳格に）
find "${BUILD_DIR}" -type f \( -name "test-*.php" -o -name "test_*.php" -o -name "debug-*.php" -o -name "debug_*.php" -o -name "check-*.php" -o -name "check_*.php" -o -name "fix-*.php" -o -name "fix_*.php" -o -name "migrate-*.php" -o -name "migrate_*.php" -o -name "auto-*.php" -o -name "auto_*.php" -o -name "manual-*.php" -o -name "manual_*.php" -o -name "direct-*.php" -o -name "direct_*.php" -o -name "clear-*.php" -o -name "clear_*.php" -o -name "run-*.php" -o -name "run_*.php" -o -name "admin-migrate.php" -o -name "ajax_test.php" -o -name "analyze_debug_log.php" -o -name "create_dummy_data.php" -o -name "create_dummy_data.php.bak" -o -name "test-report-ajax.php" -o -name "wp-cli-create-dummy-data.php" \) -delete

# 開発用shellスクリプト
find "${BUILD_DIR}" -type f \( -name "test-*.sh" -o -name "test_*.sh" -o -name "*_test.sh" -o -name "*-test.sh" -o -name "create_release_zip.sh" -o -name "run-dummy-data.sh" -o -name "wp-cli.sh" -o -name "wp-cli-aliases.sh" -o -name "setup-wp-cli.sh" \) -delete

# ドキュメントファイル（配布サイト用には不要）
find "${BUILD_DIR}" -type f \( -name "README.md" -o -name "*.md" -o -name "*.html" -o -name "debug-progress-chart.html" \) -delete

# 開発環境関連ファイル（重要：本番環境への配布を防ぐ）
find "${BUILD_DIR}" -type f -name ".local-development" -delete
find "${BUILD_DIR}" -type f -name "DEVELOPMENT-ENVIRONMENT-SETUP.md" -delete
find "${BUILD_DIR}" -type f -name "development-config.php" -delete

# 開発用JS/CSSファイル（より厳格に）
find "${BUILD_DIR}" -type f \( -name "*-test.js" -o -name "*-debug.js" -o -name "*-fixed.js" -o -name "*-test.css" -o -name "*-debug.css" -o -name "*-fixed.css" -o -name "test-*.js" -o -name "debug-*.js" -o -name "fix-*.js" -o -name "test-*.css" -o -name "debug-*.css" -o -name "fix-*.css" -o -name "service-fix.*" -o -name "*debug-helper.js" -o -name "cost-toggle-debug-helper.js" -o -name "cost-toggle-debug.js" -o -name "implementation-test.js" -o -name "ktp-calculation-debug.js" -o -name "ktp-calculation-monitor.js" -o -name "ktp-calculation-test.js" -o -name "ktp-cost-toggle-test.js" -o -name "ktp-js-backup-*.js" -o -name "ktp-js-fixed.js" -o -name "ktp-js-working.js" -o -name "ktp-js.js.bak" -o -name "plugin-reference.js" -o -name "progress-select.js" -o -name "service-fix.js" -o -name "test-both-toggles.js" -o -name "test-staff-chat-scroll.js" -o -name "ktp-invoice-items.js.bak" \) -delete

# 不要なディレクトリ
find "${BUILD_DIR}" -type d -name "KantanPro_temp" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -type d -name "wp" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -type d -name "wp-content" -exec rm -rf {} + 2>/dev/null || true
if [ -d "${BUILD_DIR}/images/upload" ]; then
    find "${BUILD_DIR}/images/upload" -mindepth 1 -delete 2>/dev/null || true
fi

# 削除後のファイル数を記録
AFTER_COUNT=$(find "${BUILD_DIR}" -type f | wc -l)
DELETED_COUNT=$((BEFORE_COUNT - AFTER_COUNT))
echo "  - 削除されたファイル数: ${DELETED_COUNT}"
echo "  - 配布版ファイル数: ${AFTER_COUNT}"
echo "  - 完了"

# 6. 配布サイト用の最終クリーンアップ
echo "\n[6/7] 配布サイト用の最終クリーンアップ中..."
# 残っている可能性のある開発用ファイルを最終チェック
find "${BUILD_DIR}" -type f -name "*.bak" -delete
find "${BUILD_DIR}" -type f -name "*.tmp" -delete
find "${BUILD_DIR}" -type f -name "*.temp" -delete
find "${BUILD_DIR}" -type f -name "*.old" -delete
find "${BUILD_DIR}" -type f -name "*.orig" -delete
echo "  - 完了"

# 7. ZIP圧縮
echo "\n[7/8] ZIPファイルを作成中..."
(cd "${BUILD_DIR}/.." && zip -r -q "${FINAL_ZIP_PATH}" "${BUILD_DIR_NAME}")

if [ $? -eq 0 ]; then
    # 8. 最終検証
    echo "\n[8/8] 最終検証を実行中..."
    
    # ZIPファイルの整合性チェック
    if unzip -t "${FINAL_ZIP_PATH}" > /dev/null 2>&1; then
        echo "  ✅ ZIPファイルの整合性: 正常"
    else
        echo "  ❌ ZIPファイルの整合性: エラー"
        exit 1
    fi
    
    # ファイルサイズチェック
    ZIP_SIZE=$(ls -lh "${FINAL_ZIP_PATH}" | awk '{print $5}')
    ZIP_SIZE_BYTES=$(ls -l "${FINAL_ZIP_PATH}" | awk '{print $5}')
    echo "  ✅ ZIPファイルサイズ: ${ZIP_SIZE}"
    
    # ファイルサイズが1-2MBの範囲内かチェック
    if [ "$ZIP_SIZE_BYTES" -ge 1048576 ] && [ "$ZIP_SIZE_BYTES" -le 2097152 ]; then
        echo "  ✅ ファイルサイズ: 1-2MBの範囲内"
    else
        echo "  ⚠️  ファイルサイズ: 1-2MBの範囲外（${ZIP_SIZE}）"
    fi
    
    # 重要ファイルの存在チェック
    if unzip -l "${FINAL_ZIP_PATH}" | grep -q "ktpwp.php"; then
        echo "  ✅ メインプラグインファイル: 存在"
    else
        echo "  ❌ メインプラグインファイル: 見つかりません"
        exit 1
    fi
    
    if unzip -l "${FINAL_ZIP_PATH}" | grep -q "readme.txt"; then
        echo "  ✅ readme.txt: 存在"
    else
        echo "  ❌ readme.txt: 見つかりません"
        exit 1
    fi
    
    # 開発ファイルが除外されているかチェック
    if ! unzip -l "${FINAL_ZIP_PATH}" | grep -q "debug-"; then
        echo "  ✅ デバッグファイル: 適切に除外"
    else
        echo "  ⚠️  デバッグファイル: 一部が残っています"
    fi
    
    # 開発環境ファイルが除外されているかチェック
    if ! unzip -l "${FINAL_ZIP_PATH}" | grep -q ".local-development"; then
        echo "  ✅ 開発環境マーカー: 適切に除外"
    else
        echo "  ❌ 開発環境マーカー: 含まれています（セキュリティリスク）"
        exit 1
    fi
    
    # ドキュメントファイルが除外されているかチェック
    if ! unzip -l "${FINAL_ZIP_PATH}" | grep -q "\.md$"; then
        echo "  ✅ ドキュメントファイル: 適切に除外"
    else
        echo "  ⚠️  ドキュメントファイル: 一部が残っています"
    fi
    
    # クリーンアップ
    rm -rf "${BUILD_DIR}"
    echo "  ✅ 一時ファイル: クリーンアップ完了"
    
    echo "\n--------------------------------------------------"
    echo "✅ 配布サイト用ビルドプロセスが正常に完了しました！"
    echo "ZIPファイル: ${FINAL_ZIP_PATH}"
    echo "ファイルサイズ: ${ZIP_SIZE}"
    echo "解凍後フォルダ: ${BUILD_DIR_NAME}"
    echo "--------------------------------------------------"
else
    echo "\n❌ ZIPファイルの作成に失敗しました。"
    exit 1
fi 