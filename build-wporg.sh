#!/usr/bin/env bash
set -euo pipefail

# WordPress.org プラグインディレクトリ提出用のパッケージを作る。
#
# 自社配布版（GitHub リリース）との違い:
#   - ディレクトリ名を wp.org のスラッグ kantanpro に合わせる
#   - GitHub 自動更新のコードを「ファイルごと」除外する
#   - ライセンス管理のコードを「ファイルごと」除外する
#   - Update URI ヘッダを削除（これがあると wp.org からの更新が適用されない）
#   - 開発用ファイル・社内ドキュメントを除外
#
# **フラグで無効化するだけでは通らない。**
# wp.org のレビューは「その機能が動くか」ではなく「そのコードがZIPに入っているか」を見る。
# 2026-09-02 の自動プレレビューで、KTPWP_DISTRIBUTION='wporg' にしていたにもかかわらず
# 更新チェッカーとライセンス管理がそのまま指摘された。だから物理的に除外する。
#
# 除外しても fatal にならない根拠: ktpwp.php のオートローダは file_exists() で
# 存在確認してから require するため、クラスファイルが無くても黙って飛ばす。
# 呼び出し側もすべて class_exists() ガードの中にある（確認済み）。
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

# 除外の補足:
#   class-ktpwp-setting-ui.php … 設定タブは廃止済みで誰からも呼ばれない
#     （オートローダ登録も無い）。中身が HEREDOC のインライン script なので丸ごと外す。
#   migrations/20250730_...    … 未参照の使い捨てスクリプト。未エスケープ echo と
#     wp-config の直読みを持っていた。
# **この注釈を rsync の継続行の途中に入れないこと。** rsync がコメント行を
# 引数として受け取って syntax error になる（2026-09-03 に踏んだ）。
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
  --exclude '*.bak' \
  --exclude '*.bak-*' \
  --exclude '*.orig' \
  --exclude '*~' \
  --exclude 'create_dummy_data.php' \
  --exclude 'wp-cli*' \
  --exclude 'tools/' \
  --exclude '*.zip' \
  --exclude '*.log' \
  --exclude 'images/upload/*' \
  --exclude 'includes/class-ktpwp-update-checker.php' \
  --exclude 'includes/class-ktpwp-license-manager.php' \
  --exclude 'languages/' \
  --exclude 'includes/migrations/20250730_fix_dummy_order_creation_dates.php' \
  --exclude 'includes/class-ktpwp-setting-ui.php' \
  "$ROOT_DIR/" "$STAGE/"

# 1) GitHub 自動更新を無効化（wp.org 版は WordPress 本体が更新する）
perl -0pi -e "s/define\( 'KTPWP_DISTRIBUTION', 'github' \);/define( 'KTPWP_DISTRIBUTION', 'wporg' );/" "$STAGE/ktpwp.php"
perl -0pi -e "s/        return KTPWP_DISTRIBUTION !== 'wporg';/        \/\/ WordPress.org 配布版。更新は WordPress 本体が行う。\n        return false;/" "$STAGE/ktpwp.php"

# 2) Update URI ヘッダを削除（残っていると wp.org からの更新が適用されない）
perl -ni -e "print unless m{^ \* Update URI:}" "$STAGE/ktpwp.php"

# 3) 除外したクラスのオートローダ登録を消す
#    file_exists() ガードがあるので残っていても動作は壊れないが、
#    レビュアーが「存在しないファイルへの参照」を不審に思うので消しておく。
perl -ni -e "print unless m{'KTPWP_Update_Checker'\s*=>|'KTPWP_License_Manager'\s*=>}" "$STAGE/ktpwp.php"

# 4) load_plugin_textdomain() を除去
#    wp.org 配布版は WordPress が翻訳を自動で読み込むので不要（4.6以降）。
#    GitHub 配布版は wp.org に無く自動読み込みが効かないため、本体ソースには残す。
#    複数行にまたがる呼び出しがあるので -0777 でスラープして消す。
for f in includes/class-ktpwp-main.php includes/class-ktpwp-i18n.php includes/class-ktpwp-assets.php; do
  perl -0777 -pi -e "s{load_plugin_textdomain\s*\([^;]*\);}{// wp.org 版では不要（WordPress が翻訳を自動読み込みする）}gs" "$STAGE/$f"
done

# 5) ロックしていた機能のコードを取り除く（ガイドライン5 / Trialware）
#    どれを削除しどれを開放するかは tools/strip-wporg-features.py の冒頭に理由を書いてある。
"$ROOT_DIR/tools/strip-wporg-features.py" "$STAGE" || {
  echo "[ERROR] 機能の除去に失敗しました" >&2
  exit 1
}

# 検証
if grep -rq "load_plugin_textdomain" --include='*.php' "$STAGE"; then
  echo "[ERROR] load_plugin_textdomain が残っています" >&2
  grep -rn "load_plugin_textdomain" --include='*.php' "$STAGE" >&2
  exit 1
fi
if compgen -G "$STAGE/languages/*" > /dev/null; then
  echo "[ERROR] languages/ が残っています（翻訳は translate.wordpress.org に任せる）" >&2
  exit 1
fi
for f in includes/class-ktpwp-update-checker.php includes/class-ktpwp-license-manager.php; do
  if [[ -e "$STAGE/$f" ]]; then
    echo "[ERROR] 除外したはずのファイルが残っています: $f" >&2
    exit 1
  fi
done
# オートローダ登録（=> でファイルを指す行）だけを見る。
# class_exists( 'KTPWP_Update_Checker' ) のガードは残ってよい（false になるだけ）。
if grep -qE "'(KTPWP_Update_Checker|KTPWP_License_Manager)'[[:space:]]*=>" "$STAGE/ktpwp.php"; then
  echo "[ERROR] オートローダ登録が残っています" >&2
  exit 1
fi
STRAY=$(find "$STAGE" \( -name '*.bak' -o -name '*.bak-*' -o -name '*.orig' -o -name '*~' -o -name '*.sh' \) | head -5)
if [[ -n "$STRAY" ]]; then
  echo "[ERROR] 配布物に不要なファイルが混ざっています:" >&2
  echo "$STRAY" >&2
  exit 1
fi
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
