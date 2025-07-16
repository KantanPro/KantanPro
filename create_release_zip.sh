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
# ktpwp.phpからバージョンを抽出 (例: "1.1.1(preview)" -> "1.1.1")
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

# 3. ソースファイルをビルドディレクトリにコピー (vendor を除く)
echo "\n[3/6] ソースファイルをコピー中 (vendorディレクトリを除く)..."
# コピー除外リスト
EXCLUDE_LIST=(".git" ".vscode" ".idea" "KantanPro_build_temp" "KantanPro_temp" "wp" "node_modules" "vendor" "wp-content")
EXCLUDE_OPTS=""
for item in "${EXCLUDE_LIST[@]}"; do
    EXCLUDE_OPTS+="--exclude=${item} "
done
# rsync を実行
eval rsync -a ${EXCLUDE_OPTS} "\"${SOURCE_DIR}/\"" "\"${BUILD_DIR}/\""
echo "  - 完了"

# 4. 本番用の依存関係をインストール
echo "\n[4/6] Composerを使用して本番用の依存関係をインストール中..."
if [ -f "${BUILD_DIR}/composer.json" ]; then
    # composer.lock が composer.json と乖離している場合があるため update を実行する
    if [ -f "${BUILD_DIR}/composer.lock" ]; then
        (cd "${BUILD_DIR}" && composer update --no-dev --optimize-autoloader)
    else
        (cd "${BUILD_DIR}" && composer install --no-dev --optimize-autoloader)
    fi
    echo "  - 完了"
else
    echo "  - composer.json が見つからないためスキップしました。"
fi

# 5. 不要なファイルを削除
echo "\n[5/6] 不要な開発用ファイルを削除中..."
# システムファイル
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

# テスト・デバッグ・マイグレーション関連ファイル
find "${BUILD_DIR}" -type f \( -name "test-*.php" -o -name "debug-*.php" -o -name "check-*.php" -o -name "fix-*.php" -o -name "migrate-*.php" -o -name "auto-*.php" -o -name "manual-*.php" -o -name "direct-*.php" -o -name "clear-*.php" -o -name "admin-migrate.php" -o -name "run_*.php" \) -delete

# ドキュメントファイル（readme.txtは除く）
find "${BUILD_DIR}" -type f \( -name "README.md" -o -name "*.md" -o -name "*.html" \) -delete

# 一時ディレクトリ
find "${BUILD_DIR}" -type d -name "KantanPro_temp" -exec rm -rf {} +
find "${BUILD_DIR}" -type d -name "wp" -exec rm -rf {} +
find "${BUILD_DIR}" -type d -name "wp-content" -exec rm -rf {} +

# アップロード画像をクリア（ディレクトリ構造は保持）
find "${BUILD_DIR}/images/upload" -mindepth 1 -delete

# 開発用のコピーファイル
find "${BUILD_DIR}" -type f -name "*のコピー*" -delete

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