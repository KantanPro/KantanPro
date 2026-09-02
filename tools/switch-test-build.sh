#!/usr/bin/env bash
set -euo pipefail

# テストサイト（http://localhost:8090）で動かす版を切り替える。
#
#   ./tools/switch-test-build.sh wporg    … wp.org 提出版（ビルドし直して入れ替え）
#   ./tools/switch-test-build.sh github   … 開発ソースそのまま（GitHub 配布版）
#   ./tools/switch-test-build.sh status   … いまどちらが動いているか
#
# 仕組み:
#   コンテナには2つのプラグインが入っていて、有効なほうが表示される。
#     KantanPro  … ホストの開発ソースをバインドマウント（= GitHub 版）
#     kantanpro  … build-wporg.sh の成果物をコンテナへコピーしたもの（= wp.org 版）
#   macOS は大文字小文字を区別しないため、ホスト側に両方を置くことはできない。
#   だから wp.org 版はコンテナ内にだけ置いている。
#
# 注意: 開発ソースを編集したあと wp.org 版に反映するには、必ず wporg を指定し直すこと
#       （このスクリプトが build-wporg.sh から作り直す）。

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTAINER="KantanProFree_wordpress"
BUILD_DIR="${KTPWP_TEST_BUILD_DIR:-/tmp/wporg-test}"
SITE_URL="http://localhost:8090"

die() { echo "[ERROR] $*" >&2; exit 1; }

docker inspect "$CONTAINER" >/dev/null 2>&1 \
  || die "コンテナ $CONTAINER が見つかりません。docker compose を起動してください。"

wp_cli() {
  # プラグインの error_log が混ざるので取り除く
  docker exec "$CONTAINER" wp "$@" --allow-root 2>/dev/null | grep -v '^\[' || true
}

show_status() {
  local active
  active=$(wp_cli plugin list --status=active --field=name | tr -d '\r')
  case "$active" in
    *kantanpro*) echo "いま動いているのは: wp.org 版（kantanpro）" ;;
    *KantanPro*) echo "いま動いているのは: GitHub 版（KantanPro / 開発ソース）" ;;
    *)           echo "いま動いているのは: 不明（有効なプラグインなし）" ;;
  esac
  echo "  $SITE_URL/?tab_name=info の帯にも同じことが出ます"
}

case "${1:-status}" in
  wporg)
    echo "[1/3] wp.org 版をビルドします…"
    "$ROOT_DIR/build-wporg.sh" "$BUILD_DIR" >/dev/null \
      || die "ビルドに失敗しました。./build-wporg.sh を単体で実行して原因を見てください。"

    echo "[2/3] コンテナへ配置します…"
    docker exec "$CONTAINER" rm -rf /var/www/html/wp-content/plugins/kantanpro
    docker cp "$BUILD_DIR/kantanpro" "$CONTAINER:/var/www/html/wp-content/plugins/kantanpro"

    echo "[3/3] 有効化を切り替えます…"
    wp_cli plugin deactivate KantanPro >/dev/null
    wp_cli plugin activate kantanpro >/dev/null
    echo
    echo "wp.org 版に切り替えました（審査に出す ZIP と同じ中身）。"
    echo "  バナー: 出ません / 有料版の案内: 出ません"
    ;;

  github)
    wp_cli plugin deactivate kantanpro >/dev/null
    wp_cli plugin activate KantanPro >/dev/null
    echo "GitHub 版に切り替えました（ホストの開発ソースがそのまま動きます）。"
    echo "  バナー: 出ます / 有料版の案内: 出ます"
    echo "  ソースを編集すると即座に反映されます（ビルド不要）。"
    ;;

  status)
    show_status
    ;;

  *)
    echo "使い方: $0 [wporg|github|status]" >&2
    exit 2
    ;;
esac
