#!/bin/bash

# WordPressのエラーログを確認するスクリプト

echo "=== WordPressエラーログ確認 ==="

# 一般的なログファイルの場所
log_files=(
    "/Users/kantanpro/ktplocal/wp/wp-content/debug.log"
    "/Users/kantanpro/ktplocal/wp/debug.log"
    "/var/log/apache2/error.log"
    "/var/log/nginx/error.log"
    "/usr/local/var/log/apache2/error.log"
    "/usr/local/var/log/nginx/error.log"
)

echo "1. ログファイルの場所を確認中..."
for log_file in "${log_files[@]}"; do
    if [ -f "$log_file" ]; then
        echo "  存在: $log_file"
        echo "    サイズ: $(ls -lh "$log_file" | awk '{print $5}')"
        echo "    最終更新: $(ls -l "$log_file" | awk '{print $6, $7, $8}')"
    else
        echo "  存在しない: $log_file"
    fi
done

echo ""
echo "2. wp-config.phpのデバッグ設定確認..."
cd /Users/kantanpro/ktplocal/wp
grep -n "WP_DEBUG\|WP_DEBUG_LOG" wp-config.php 2>/dev/null || echo "  デバッグ設定が見つかりません"

echo ""
echo "3. 最新のKTPデバッグログ（存在する場合）..."
for log_file in "${log_files[@]}"; do
    if [ -f "$log_file" ]; then
        echo "  --- $log_file から最新のKTデバッグログ ---"
        tail -50 "$log_file" 2>/dev/null | grep -i "ktp" | tail -10 || echo "  KTPログなし"
        echo ""
    fi
done

echo "4. リアルタイムログ監視用コマンド:"
echo "  次のコマンドでリアルタイムにログを監視できます："
for log_file in "${log_files[@]}"; do
    if [ -f "$log_file" ]; then
        echo "    tail -f $log_file | grep KTPWP"
    fi
done
