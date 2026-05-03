#!/usr/bin/env bash
set -euo pipefail

# 使い方: ./create_release_zip.sh [version]
# version 未指定時は plugin_config.json の default_version を使用

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$ROOT_DIR/plugin_config.json"

if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "plugin_config.json が見つかりません" >&2
  exit 1
fi

if [[ -n "${1:-}" ]]; then
  VERSION="$1"
else
  VERSION=$(php -r 'echo json_decode(file_get_contents("'$CONFIG_FILE'"), true)["default_version"];')
fi

OUTPUT_DIR=$(php -r 'echo json_decode(file_get_contents("'$CONFIG_FILE'"), true)["output_directory"];')

mkdir -p "$OUTPUT_DIR"

echo "[INFO] create_plugin_zip.sh を呼び出します... (v$VERSION)"

"$ROOT_DIR/create_plugin_zip.sh" -v "$VERSION" -o "$OUTPUT_DIR" -s "$ROOT_DIR"

echo "[INFO] 完了"
