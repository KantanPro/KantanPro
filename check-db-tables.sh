#!/bin/bash

# データベースのテーブル構造とデータを確認するスクリプト

echo "=== KantanPro DBテーブル確認 ==="

cd /Users/kantanpro/ktplocal/wp

echo "1. 請求項目関連テーブルの確認"
wp db query "SHOW TABLES LIKE '%invoice%'" --skip-plugins --skip-themes

echo "2. 受注書関連テーブルの確認"  
wp db query "SHOW TABLES LIKE '%order%'" --skip-plugins --skip-themes

echo "3. ktp_invoice_itemテーブルの構造"
wp db query "DESCRIBE wp_ktp_invoice_item" --skip-plugins --skip-themes 2>/dev/null || echo "wp_ktp_invoice_itemテーブルが存在しません"

echo "4. ktp_order_invoice_itemsテーブルの構造"
wp db query "DESCRIBE wp_ktp_order_invoice_items" --skip-plugins --skip-themes 2>/dev/null || echo "wp_ktp_order_invoice_itemsテーブルが存在しません"

echo "5. 各テーブルのレコード数"
echo "wp_ktp_invoice_item:"
wp db query "SELECT COUNT(*) as count FROM wp_ktp_invoice_item" --skip-plugins --skip-themes 2>/dev/null || echo "テーブルが存在しません"

echo "wp_ktp_order_invoice_items:"
wp db query "SELECT COUNT(*) as count FROM wp_ktp_order_invoice_items" --skip-plugins --skip-themes 2>/dev/null || echo "テーブルが存在しません"

echo "6. 最近の受注書のサンプルデータ（wp_ktp_invoice_item）"
wp db query "SELECT id, order_id, product_name, price, quantity, amount FROM wp_ktp_invoice_item ORDER BY id DESC LIMIT 5" --skip-plugins --skip-themes 2>/dev/null || echo "データが取得できません"

echo "7. 最近の受注書のサンプルデータ（wp_ktp_order_invoice_items）"
wp db query "SELECT id, order_id, product_name, price, quantity, amount FROM wp_ktp_order_invoice_items ORDER BY id DESC LIMIT 5" --skip-plugins --skip-themes 2>/dev/null || echo "データが取得できません"
