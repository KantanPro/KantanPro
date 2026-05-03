#!/usr/bin/env bash
# KantanPro 手動配布用 ZIP（ルートフォルダ名は常に KantanPro）
set -euo pipefail

PLUGIN_NAME="KantanPro"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/plugin_config.json"
DATE=$(date +%Y%m%d)

print_info() { echo -e "\033[34m[INFO]\033[0m $1"; }
print_success() { echo -e "\033[32m[SUCCESS]\033[0m $1"; }
print_error() { echo -e "\033[31m[ERROR]\033[0m $1"; }

load_config() {
  if [[ ! -f "$CONFIG_FILE" ]]; then
    print_error "plugin_config.json が見つかりません: $CONFIG_FILE"
    exit 1
  fi
  php -r '
    $c = json_decode(file_get_contents("'"$CONFIG_FILE"'"), true);
    echo ($c["default_version"] ?? "1.0.0") . "\t" . ($c["output_directory"] ?? "") . PHP_EOL;
  '
}

IFS=$'\t' read -r VERSION OUTPUT_DIR < <(load_config)
SOURCE_DIR="$SCRIPT_DIR"

show_help() {
  echo "KantanPro プラグイン ZIP 作成"
  echo "  $0 [-v VERSION] [-o OUTPUT_DIR] [-s SOURCE_DIR]"
}

while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--version) VERSION="$2"; shift 2 ;;
    -o|--output) OUTPUT_DIR="$2"; shift 2 ;;
    -s|--source) SOURCE_DIR="$2"; shift 2 ;;
    -h|--help) show_help; exit 0 ;;
    *) print_error "不明なオプション: $1"; show_help; exit 1 ;;
  esac
done

if [[ ! -d "$SOURCE_DIR" ]]; then
  print_error "ソースディレクトリが見つかりません: $SOURCE_DIR"
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

WORK_DIR="$OUTPUT_DIR/${PLUGIN_NAME}_temp_$$"
PLUGIN_DIR="$WORK_DIR/$PLUGIN_NAME"

print_info "バージョン: $VERSION / 出力: $OUTPUT_DIR / ソース: $SOURCE_DIR"

rm -rf "$WORK_DIR"
mkdir -p "$PLUGIN_DIR"

print_info "ファイルをコピー中（rsync）..."
rsync -a \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='*.zip' \
  --exclude='*.tmp' \
  --exclude='.DS_Store' \
  --exclude="${PLUGIN_NAME}_temp_*/" \
  "$SOURCE_DIR/" "$PLUGIN_DIR/"

rm -f "$PLUGIN_DIR/create_release_zip.sh" "$PLUGIN_DIR/create_plugin_zip.sh" "$PLUGIN_DIR/plugin_config.json"

find "$PLUGIN_DIR" -name '.DS_Store' -delete 2>/dev/null || true

print_info "PHP 構文チェック（メインファイル）..."
if ! php -l "$PLUGIN_DIR/ktpwp.php" >/dev/null; then
  print_error "ktpwp.php に構文エラーがあります"
  exit 1
fi
print_success "ktpwp.php: OK"

ZIP_NAME="KantanPro_${VERSION}_${DATE}.zip"
ZIP_PATH="$OUTPUT_DIR/$ZIP_NAME"

print_info "ZIP 作成: $ZIP_NAME"
(
  cd "$WORK_DIR"
  zip -rq "$ZIP_PATH" "$PLUGIN_NAME"
)

print_success "完了: $ZIP_PATH ($(du -h "$ZIP_PATH" | awk '{print $1}'))"

rm -rf "$WORK_DIR"
ls -la "$ZIP_PATH"
