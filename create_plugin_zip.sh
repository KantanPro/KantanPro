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
  --exclude='.cursor/' \
  --exclude='.vscode/' \
  --exclude='.idea/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='*.zip' \
  --exclude='*.tmp' \
  --exclude='.DS_Store' \
  --exclude='wp-cli.phar' \
  --exclude='wp-cli.yml' \
  --exclude='wp-cli.sh' \
  --exclude='wp-cli-aliases.sh' \
  --exclude='setup-wp-cli.sh' \
  --exclude="${PLUGIN_NAME}_temp_*/" \
  "$SOURCE_DIR/" "$PLUGIN_DIR/"

rm -f "$PLUGIN_DIR/create_release_zip.sh" "$PLUGIN_DIR/create_plugin_zip.sh" "$PLUGIN_DIR/plugin_config.json"
rm -f "$PLUGIN_DIR/composer.lock"

find "$PLUGIN_DIR" -name '.DS_Store' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.phpcs.xml' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.editorconfig' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.cursorrules' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.gitignore' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.gitattributes' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name '.local-development' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -name 'development-config.php' -delete 2>/dev/null || true

find "$PLUGIN_DIR" -type f \( -name 'README.md' -o -name '*.md' -o -name '*.html' \) -delete 2>/dev/null || true
find "$PLUGIN_DIR/languages" -type f \( -name '*.po' -o -name '*.pot' \) -delete 2>/dev/null || true
rm -f "$PLUGIN_DIR/create_dummy_data.php.bak"

find "$PLUGIN_DIR" -type f \( -name 'test-*.php' -o -name 'test_*.php' -o -name 'debug-*.php' -o -name 'wp-cli-create-dummy-data.php' -o -name 'test-report-ajax.php' -o -name 'test-license-reset.php' \) -delete 2>/dev/null || true
find "$PLUGIN_DIR" -type f \( -name 'test-*.sh' -o -name 'run-dummy-data.sh' -o -name 'wp-cli.sh' -o -name 'wp-cli-aliases.sh' -o -name 'setup-wp-cli.sh' \) -delete 2>/dev/null || true
find "$PLUGIN_DIR" -type f \( -name '*-debug.js' -o -name '*-test.js' -o -name 'test-*.js' -o -name '*backup*.js' -o -name '*.bak' -o -name 'implementation-test.js' -o -name 'plugin-reference.js' -o -name 'service-fix.js' -o -name 'progress-select.js' \) -delete 2>/dev/null || true

if [[ -d "$PLUGIN_DIR/images/upload" ]]; then
  find "$PLUGIN_DIR/images/upload" -mindepth 1 -delete 2>/dev/null || true
fi

find "$PLUGIN_DIR" -type f -name '*.zip' -delete 2>/dev/null || true
find "$PLUGIN_DIR" -type f -name 'wp-cli.phar' -delete 2>/dev/null || true
rm -rf "$PLUGIN_DIR/.cursor" "$PLUGIN_DIR/.vscode" "$PLUGIN_DIR/.idea" 2>/dev/null || true
rm -f "$PLUGIN_DIR/wp-cli.yml" "$PLUGIN_DIR/wp-cli.sh" "$PLUGIN_DIR/wp-cli-aliases.sh" "$PLUGIN_DIR/setup-wp-cli.sh" "$PLUGIN_DIR/composer.lock" 2>/dev/null || true

if [[ ! -f "$PLUGIN_DIR/ktpwp.php" ]]; then
  print_error "ビルド内に ktpwp.php がありません"
  exit 1
fi

if ! grep -q "Plugin Name:[[:space:]]*KantanPro" "$PLUGIN_DIR/ktpwp.php"; then
  print_error "ktpwp.php に Plugin Name: KantanPro ヘッダーがありません"
  exit 1
fi

if [[ ! -f "$PLUGIN_DIR/create_dummy_data.php" ]]; then
  print_error "ビルド内に create_dummy_data.php がありません（ダミーデータ作成に必要）"
  exit 1
fi

print_info "PHP 構文チェック（メインファイル）..."
if ! php -l "$PLUGIN_DIR/ktpwp.php" >/dev/null; then
  print_error "ktpwp.php に構文エラーがあります"
  exit 1
fi
print_success "ktpwp.php: OK"

ZIP_NAME="KantanPro_${VERSION}_${DATE}.zip"
ZIP_PATH="$OUTPUT_DIR/$ZIP_NAME"

print_info "ZIP 作成: $ZIP_NAME"
rm -f "$ZIP_PATH"
(
  cd "$WORK_DIR"
  zip -rq "$ZIP_PATH" "$PLUGIN_NAME"
)

if ! unzip -t "$ZIP_PATH" >/dev/null 2>&1; then
  print_error "ZIP 整合性チェックに失敗しました: $ZIP_PATH"
  rm -rf "$WORK_DIR"
  exit 1
fi

ZIP_LIST=$(unzip -Z1 "$ZIP_PATH")

if ! echo "$ZIP_LIST" | grep -Fxq "${PLUGIN_NAME}/ktpwp.php"; then
  print_error "ZIP 内に ${PLUGIN_NAME}/ktpwp.php が見つかりません"
  rm -rf "$WORK_DIR"
  exit 1
fi

TMP_HEADER=$(mktemp)
unzip -p "$ZIP_PATH" "${PLUGIN_NAME}/ktpwp.php" > "$TMP_HEADER"
if ! grep -q "Plugin Name:[[:space:]]*KantanPro" "$TMP_HEADER"; then
  print_error "ZIP 内の ktpwp.php に有効な Plugin Name ヘッダーがありません"
  rm -f "$TMP_HEADER"
  rm -rf "$WORK_DIR"
  exit 1
fi
rm -f "$TMP_HEADER"

if ! echo "$ZIP_LIST" | grep -Fxq "${PLUGIN_NAME}/readme.txt"; then
  print_error "ZIP 内に readme.txt がありません"
  rm -rf "$WORK_DIR"
  exit 1
fi

FORBIDDEN_IN_ZIP=$(echo "$ZIP_LIST" | grep -E "^${PLUGIN_NAME}/(\.cursor/|\.vscode/|wp-cli\.yml|composer\.lock)" || true)
if [[ -n "$FORBIDDEN_IN_ZIP" ]]; then
  print_error "ZIP に配布不要ファイルが含まれています:"
  echo "$FORBIDDEN_IN_ZIP"
  rm -rf "$WORK_DIR"
  exit 1
fi

if ! echo "$ZIP_LIST" | grep -Fxq "${PLUGIN_NAME}/create_dummy_data.php"; then
  print_error "ZIP 内に create_dummy_data.php がありません"
  rm -rf "$WORK_DIR"
  exit 1
fi

# ルートが単一フォルダ（KantanPro/）であることを確認
ROOT_ENTRIES=$(echo "$ZIP_LIST" | awk -F/ 'NF>=1 && $1!="" {print $1}' | sort -u)
ROOT_COUNT=$(echo "$ROOT_ENTRIES" | grep -c . || true)
if [[ "$ROOT_COUNT" -ne 1 ]] || [[ "$ROOT_ENTRIES" != "$PLUGIN_NAME" ]]; then
  print_error "ZIP のルート構造が不正です（期待: ${PLUGIN_NAME}/ のみ）"
  echo "$ROOT_ENTRIES"
  rm -rf "$WORK_DIR"
  exit 1
fi

ZIP_SIZE_BYTES=$(stat -f%z "$ZIP_PATH" 2>/dev/null || stat -c%s "$ZIP_PATH")
if [[ "$ZIP_SIZE_BYTES" -ge 3145728 ]]; then
  print_error "ZIP が 3MB 以上です（${ZIP_SIZE_BYTES} bytes）。一般 WordPress ではインストール不可の可能性があります。"
  rm -rf "$WORK_DIR"
  exit 1
fi

print_success "完了: $ZIP_PATH ($(du -h "$ZIP_PATH" | awk '{print $1}')) — 3MB 未満"

rm -rf "$WORK_DIR"
ls -la "$ZIP_PATH"
