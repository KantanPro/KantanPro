#!/usr/bin/env bash
set -euo pipefail

# WordPress.org プラグインディレクトリ提出用のパッケージを作る。
#
# 自社配布版（GitHub リリース）との違い:
#   - ディレクトリ名を wp.org のスラッグ kantanpro に合わせる
#   - GitHub 自動更新を無効化（更新は WordPress 本体に任せる）
#   - Update URI ヘッダを削除（これがあると wp.org からの更新が適用されない）
#   - 開発用ファイル・社内ドキュメントを除外
#
# 使い方: ./build-wporg.sh [出力先ディレクトリ]

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SLUG="kantanpro"
OUT_DIR="${1:-$HOME/Desktop/KantanPro_wporg}"

VERSION=$(sed -n 's/^ \* Version: *\(.*\)$/\1/p' "$ROOT_DIR/ktpwp.php" | head -1)
if [[ -z "$VERSION" ]]; then
  echo "[ERROR] ktpwp.php からバージョンを取得できませんでした" >&2
  exit 1
fi

STAGE="$OUT_DIR/$SLUG"
rm -rf "$STAGE"
mkdir -p "$STAGE"

echo "[INFO] KantanPro v$VERSION を WordPress.org 用にビルドします"

rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.cursor/' \
  --exclude '.cursorrules' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude '.phpcs.xml' \
  --exclude '.DS_Store' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'docs/' \
  --exclude 'CLAUDE.md' \
  --exclude '*.md' \
  --exclude 'plugin_config.json' \
  --exclude 'create_plugin_zip.sh' \
  --exclude 'create_release_zip.sh' \
  --exclude 'build-wporg.sh' \
  --exclude 'create_dummy_data.php' \
  --exclude 'wp-cli*' \
  --exclude '*.zip' \
  --exclude '*.log' \
  --exclude 'images/upload/*' \
  "$ROOT_DIR/" "$STAGE/"

# 1) GitHub 自動更新を無効化（wp.org 版は WordPress 本体が更新する）
perl -0pi -e "s/define\( 'KTPWP_DISTRIBUTION', 'github' \);/define( 'KTPWP_DISTRIBUTION', 'wporg' );/" "$STAGE/ktpwp.php"
perl -0pi -e "s/        return KTPWP_DISTRIBUTION !== 'wporg';/        \/\/ WordPress.org 配布版。更新は WordPress 本体が行う。\n        return false;/" "$STAGE/ktpwp.php"

# 2) Update URI ヘッダを削除（残っていると wp.org からの更新が適用されない）
perl -ni -e "print unless m{^ \* Update URI:}" "$STAGE/ktpwp.php"

# 検証
if grep -q "Update URI:" "$STAGE/ktpwp.php"; then
  echo "[ERROR] Update URI ヘッダが残っています" >&2
  exit 1
fi
if ! grep -q "'KTPWP_DISTRIBUTION', 'wporg'" "$STAGE/ktpwp.php"; then
  echo "[ERROR] KTPWP_DISTRIBUTION の書き換えに失敗しました" >&2
  exit 1
fi
CDN_HITS=$(grep -rl "cdnjs.cloudflare\|cdn.jsdelivr\|ajax.googleapis\|fonts.googleapis\|fonts.gstatic" \
  --include='*.php' --include='*.js' --include='*.css' "$STAGE" 2>/dev/null \
  | grep -v "/js/lib/" || true)
if [[ -n "$CDN_HITS" ]]; then
  echo "[ERROR] 外部 CDN への参照が残っています:" >&2
  echo "$CDN_HITS" >&2
  exit 1
fi
php -l "$STAGE/ktpwp.php" > /dev/null

ZIP="$OUT_DIR/${SLUG}-${VERSION}.zip"
rm -f "$ZIP"
( cd "$OUT_DIR" && zip -qr "$(basename "$ZIP")" "$SLUG" -x '*.DS_Store' )

echo "[INFO] 完了"
echo "  ディレクトリ: $STAGE"
echo "  ZIP:          $ZIP  ($(du -h "$ZIP" | cut -f1))"
