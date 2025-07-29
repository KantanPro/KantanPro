#!/bin/zsh

# --- 設定 ---
# プラグインのソースコードが格納されているディレクトリ
SOURCE_DIR="/Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/plugins/KantanPro"
# 生成したZIPファイルの保存先
DEST_PARENT_DIR="/Users/kantanpro/Desktop"
# 保存先フォルダ名
DEST_DIR_NAME="ktpa_TEST_UP"
# --- 設定ここまで ---

# ビルド用の変数を設定
DEST_DIR="${DEST_PARENT_DIR}/${DEST_DIR_NAME}"
BUILD_DIR_NAME="KantanPro"
BUILD_DIR="${DEST_DIR}/${BUILD_DIR_NAME}"

# エラーが発生した場合はスクリプトを終了する
set -e

echo "--------------------------------------------------"
echo "KantanPro プラグイン配布用ZIPファイル生成スクリプト"
echo "--------------------------------------------------"

# 1. バージョンと日付の取得
echo "[1/6] バージョン情報を取得中..."
# ktpwp.phpからバージョンを抽出 (例: "1.0.6(preview)" -> "1.0.6")
VERSION=$(grep -i "Version:" "$SOURCE_DIR/ktpwp.php" | head -n 1 | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')
DATE=$(date +%Y%m%d)
ZIP_FILE_NAME="KantanPro_${VERSION}_${DATE}.zip"
FINAL_ZIP_PATH="${DEST_DIR}/${ZIP_FILE_NAME}"

echo "  - バージョン: ${VERSION}"
echo "  - 日付: ${DATE}"
echo "  - ZIPファイル名: ${ZIP_FILE_NAME}"

# 2. ビルド環境の準備
echo "\n[2/6] ビルド環境をクリーンアップ中..."
mkdir -p "${DEST_DIR}"
rm -rf "${BUILD_DIR}"
rm -f "${FINAL_ZIP_PATH}"
echo "  - 完了"

# 3. ソースファイルをビルドディレクトリにコピー
echo "\n[3/6] ソースファイルをコピー中..."
# コピー除外リスト（vendorディレクトリは不要なため除外）
EXCLUDE_LIST=(".git" ".vscode" ".idea" "KantanPro_build_temp" "KantanPro_temp" "wp" "node_modules" "vendor")
EXCLUDE_OPTS=""
for item in "${EXCLUDE_LIST[@]}"; do
    EXCLUDE_OPTS+="--exclude=${item} "
done
# rsync を実行
eval rsync -a ${EXCLUDE_OPTS} "\"${SOURCE_DIR}/\"" "\"${BUILD_DIR}/\""
echo "  - 完了"

# 4. Composer依存関係の処理（現在は不要な依存関係なし）
echo "\n[4/6] Composer依存関係を確認中..."
if [ -f "${BUILD_DIR}/composer.json" ]; then
    # 現在は外部依存関係がないため、composer.lockのみコピー
    if [ -f "${BUILD_DIR}/composer.lock" ]; then
        echo "  - composer.lock を保持しました"
    else
        echo "  - composer.lock が見つかりません"
    fi
    echo "  - 完了"
else
    echo "  - composer.json が見つからないためスキップしました。"
fi

# 5. 不要なファイルを削除
echo "\n[5/6] 不要な開発用ファイルを削除中..."
# 削除前のファイル数を記録
BEFORE_COUNT=$(find "${BUILD_DIR}" -type f | wc -l)

# 設定ファイルと開発ツール
find "${BUILD_DIR}" -type f -name ".DS_Store" -delete
find "${BUILD_DIR}" -type f -name ".phpcs.xml" -delete
find "${BUILD_DIR}" -type f -name ".editorconfig" -delete
find "${BUILD_DIR}" -type f -name ".cursorrules" -delete
find "${BUILD_DIR}" -type f -name ".gitignore" -delete
find "${BUILD_DIR}" -type f -name "*.log" -delete

# WP-CLI関連ファイル
find "${BUILD_DIR}" -type f -name "wp-cli.phar" -delete
find "${BUILD_DIR}" -type f -name "wp-cli.yml" -delete
find "${BUILD_DIR}" -type f -name "wp-cli.sh" -delete
find "${BUILD_DIR}" -type f -name "wp-cli-aliases.sh" -delete
find "${BUILD_DIR}" -type f -name "setup-wp-cli.sh" -delete
find "${BUILD_DIR}" -type f -name "WP-CLI-README.md" -delete

# 開発用PHPファイル
find "${BUILD_DIR}" -type f \( -name "test-*.php" -o -name "test_*.php" -o -name "debug-*.php" -o -name "debug_*.php" -o -name "check-*.php" -o -name "check_*.php" -o -name "fix-*.php" -o -name "fix_*.php" -o -name "migrate-*.php" -o -name "migrate_*.php" -o -name "auto-*.php" -o -name "auto_*.php" -o -name "manual-*.php" -o -name "manual_*.php" -o -name "direct-*.php" -o -name "direct_*.php" -o -name "clear-*.php" -o -name "clear_*.php" -o -name "run-*.php" -o -name "run_*.php" -o -name "admin-migrate.php" -o -name "ajax_test.php" -o -name "analyze_debug_log.php" \) -delete

# 開発用shellスクリプト
find "${BUILD_DIR}" -type f \( -name "test-*.sh" -o -name "test_*.sh" -o -name "*_test.sh" -o -name "*-test.sh" -o -name "create_release_zip.sh" \) -delete

# ドキュメントファイル
find "${BUILD_DIR}" -type f \( -name "README.md" -o -name "*.md" -o -name "*.html" \) -delete

# 開発用JS/CSSファイル
find "${BUILD_DIR}" -type f \( -name "*-test.js" -o -name "*-debug.js" -o -name "*-fixed.js" -o -name "*-test.css" -o -name "*-debug.css" -o -name "*-fixed.css" -o -name "test-*.js" -o -name "debug-*.js" -o -name "fix-*.js" -o -name "test-*.css" -o -name "debug-*.css" -o -name "fix-*.css" -o -name "service-fix.*" -o -name "*debug-helper.js" \) -delete

# 不要なディレクトリ
find "${BUILD_DIR}" -type d -name "KantanPro_temp" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -type d -name "wp" -exec rm -rf {} + 2>/dev/null || true
if [ -d "${BUILD_DIR}/images/upload" ]; then
    find "${BUILD_DIR}/images/upload" -mindepth 1 -delete 2>/dev/null || true
fi

# 削除後のファイル数を記録
AFTER_COUNT=$(find "${BUILD_DIR}" -type f | wc -l)
DELETED_COUNT=$((BEFORE_COUNT - AFTER_COUNT))
echo "  - 削除されたファイル数: ${DELETED_COUNT}"
echo "  - 配布版ファイル数: ${AFTER_COUNT}"
echo "  - 完了"

# 6. ZIP圧縮
echo "\n[6/7] ZIPファイルを作成中..."
(cd "${BUILD_DIR}/.." && zip -r -q "${FINAL_ZIP_PATH}" "${BUILD_DIR_NAME}")

if [ $? -eq 0 ]; then
    # 7. 最終検証
    echo "\n[7/7] 最終検証を実行中..."
    
    # ZIPファイルの整合性チェック
    if unzip -t "${FINAL_ZIP_PATH}" > /dev/null 2>&1; then
        echo "  ✅ ZIPファイルの整合性: 正常"
    else
        echo "  ❌ ZIPファイルの整合性: エラー"
        exit 1
    fi
    
    # ファイルサイズチェック
    ZIP_SIZE=$(ls -lh "${FINAL_ZIP_PATH}" | awk '{print $5}')
    echo "  ✅ ZIPファイルサイズ: ${ZIP_SIZE}"
    
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
    
    # クリーンアップ
    rm -rf "${BUILD_DIR}"
    echo "  ✅ 一時ファイル: クリーンアップ完了"
    
    echo "\n--------------------------------------------------"
    echo "✅ ビルドプロセスが正常に完了しました！"
    echo "ZIPファイル: ${FINAL_ZIP_PATH}"
    echo "ファイルサイズ: ${ZIP_SIZE}"
    echo "--------------------------------------------------"
else
    echo "\n❌ ZIPファイルの作成に失敗しました。"
    exit 1
fi 