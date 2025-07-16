#!/bin/zsh

# --- 設定 ---
# プラグインのソースコードが格納されているディレクトリ
SOURCE_DIR="/Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/plugins/KantanPro"
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
# 設定ファイルと開発ツール
find "${BUILD_DIR}" -type f -name ".DS_Store" -delete
find "${BUILD_DIR}" -type f -name ".phpcs.xml" -delete
find "${BUILD_DIR}" -type f -name ".editorconfig" -delete
find "${BUILD_DIR}" -type f -name ".cursorrules" -delete
find "${BUILD_DIR}" -type f -name ".gitignore" -delete
# WP-CLI関連ファイル
find "${BUILD_DIR}" -type f -name "wp-cli.phar" -delete
find "${BUILD_DIR}" -type f -name "wp-cli.yml" -delete
find "${BUILD_DIR}" -type f -name "wp-cli.sh" -delete
find "${BUILD_DIR}" -type f -name "wp-cli-aliases.sh" -delete
find "${BUILD_DIR}" -type f -name "setup-wp-cli.sh" -delete
find "${BUILD_DIR}" -type f -name "WP-CLI-README.md" -delete
# 開発用PHPファイル
find "${BUILD_DIR}" -type f \( -name "test-*.php" -o -name "test_*.php" -o -name "debug-*.php" -o -name "debug_*.php" -o -name "check-*.php" -o -name "check_*.php" -o -name "fix-*.php" -o -name "fix_*.php" -o -name "migrate-*.php" -o -name "migrate_*.php" -o -name "auto-*.php" -o -name "auto_*.php" -o -name "manual-*.php" -o -name "manual_*.php" -o -name "direct-*.php" -o -name "direct_*.php" -o -name "clear-*.php" -o -name "clear_*.php" -o -name "run-*.php" -o -name "run_*.php" -o -name "admin-migrate.php" \) -delete
# ドキュメントファイル
find "${BUILD_DIR}" -type f \( -name "README.md" -o -name "*.md" -o -name "*.html" \) -delete
# 開発用JS/CSSファイル
find "${BUILD_DIR}" -type f \( -name "*-test.js" -o -name "*-debug.js" -o -name "*-fixed.js" -o -name "*-test.css" -o -name "*-debug.css" -o -name "*-fixed.css" -o -name "test-*.js" -o -name "debug-*.js" -o -name "fix-*.js" -o -name "test-*.css" -o -name "debug-*.css" -o -name "fix-*.css" -o -name "service-fix.*" \) -delete
# 不要なディレクトリ
find "${BUILD_DIR}" -type d -name "KantanPro_temp" -exec rm -rf {} +
find "${BUILD_DIR}" -type d -name "wp" -exec rm -rf {} +
find "${BUILD_DIR}/images/upload" -mindepth 1 -delete
echo "  - 完了"

# 6. ZIP圧縮
echo "\n[6/6] ZIPファイルを作成中..."
(cd "${BUILD_DIR}/.." && zip -r -q "${FINAL_ZIP_PATH}" "${BUILD_DIR_NAME}")

if [ $? -eq 0 ]; then
    # クリーンアップ
    rm -rf "${BUILD_DIR}"
    echo "\n--------------------------------------------------"
    echo "✅ ビルドプロセスが正常に完了しました！"
    echo "ZIPファイル: ${FINAL_ZIP_PATH}"
    echo "--------------------------------------------------"
else
    echo "\n❌ ZIPファイルの作成に失敗しました。"
    exit 1
fi 