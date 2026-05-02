#!/usr/bin/env bash
# KantanPro 手動配布用 ZIP 生成（WordPress の plugins 直下に展開できる構成）
# 使い方: ./create_release_zip.sh [出力ディレクトリ]
# 未指定時は /Users/kantanpro/Desktop/KantanPro_TEST_UP

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_DIR="${1:-/Users/kantanpro/Desktop/KantanPro_TEST_UP}"
PLUGIN_FOLDER_NAME="KantanPro"

VERSION="$(grep '^\s*\* Version:' "${ROOT_DIR}/ktpwp.php" | head -1 | sed 's/.*Version:[[:space:]]*//;s/[[:space:]]*$//')"
TODAY="$(date +%Y%m%d)"
ZIP_NAME="${PLUGIN_FOLDER_NAME}_${VERSION}_${TODAY}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_NAME}"

echo "=========================================="
echo "KantanPro リリース ZIP"
echo "=========================================="
echo "ソース: ${ROOT_DIR}"
echo "バージョン: ${VERSION}"
echo "出力: ${ZIP_PATH}"
echo "=========================================="

mkdir -p "${OUTPUT_DIR}"

TEMP_DIR="$(mktemp -d)"
cleanup() { rm -rf "${TEMP_DIR}"; }
trap cleanup EXIT

DEST="${TEMP_DIR}/${PLUGIN_FOLDER_NAME}"
mkdir -p "${DEST}"

rsync -a \
  --exclude='.git/' \
  --exclude='.cursor/' \
  --exclude='.DS_Store' \
  --exclude='*.log' \
  --exclude='*.tmp' \
  "${ROOT_DIR}/" "${DEST}/"

(
  cd "${TEMP_DIR}"
  zip -rq "${ZIP_PATH}" "${PLUGIN_FOLDER_NAME}" -x "*.DS_Store" -x "*.log" -x "*.tmp"
)

SIZE="$(ls -lh "${ZIP_PATH}" | awk '{print $5}')"
BYTES="$(stat -f%z "${ZIP_PATH}" 2>/dev/null || stat -c%s "${ZIP_PATH}" 2>/dev/null)"
echo "完了: ${ZIP_NAME} (${SIZE} / ${BYTES} bytes)"
