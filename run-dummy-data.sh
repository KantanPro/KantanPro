#!/bin/bash

# ダミーデータ作成実行スクリプト
# 
# 使用方法:
# ./run-dummy-data.sh [method]
# 
# method:
#   wp-cli    - WP-CLIコマンドを使用（推奨）
#   php       - 直接PHPスクリプトを実行
#   auto      - 自動判定（デフォルト）

set -e

# 色付きメッセージ関数
print_info() {
    echo -e "\033[34m[INFO]\033[0m $1"
}

print_success() {
    echo -e "\033[32m[SUCCESS]\033[0m $1"
}

print_warning() {
    echo -e "\033[33m[WARNING]\033[0m $1"
}

print_error() {
    echo -e "\033[31m[ERROR]\033[0m $1"
}

# 現在のディレクトリを確認
if [ ! -f "ktpwp.php" ]; then
    print_error "このスクリプトはKantanProプラグインディレクトリで実行してください。"
    exit 1
fi

# WordPressディレクトリの確認
if [ ! -f "../../../wp-config.php" ]; then
    print_error "WordPressのwp-config.phpが見つかりません。"
    print_error "このスクリプトはWordPressのwp-content/plugins/KantanPro/ディレクトリで実行してください。"
    exit 1
fi

# 実行方法の判定
METHOD=${1:-auto}

if [ "$METHOD" = "auto" ]; then
    if command -v wp &> /dev/null; then
        METHOD="wp-cli"
    else
        METHOD="php"
    fi
fi

print_info "実行方法: $METHOD"

# 実行前の確認
print_warning "このスクリプトは以下のダミーデータを作成します："
echo "  - 協力会社: 6件（メールアドレス: info@kantanpro.com）"
echo "  - 職能: 18件（協力会社×6件 × 税率3パターン）"
echo "  - サービス: 6件（一般：税率10%・食品：税率8%・不動産：非課税）各×2"
echo ""

read -p "続行しますか？ (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_info "実行をキャンセルしました。"
    exit 0
fi

# 実行方法に応じてダミーデータを作成
case $METHOD in
    "wp-cli")
        print_info "WP-CLIコマンドでダミーデータを作成します..."
        
        # WP-CLIコマンドファイルを読み込み
        if [ -f "wp-cli-create-dummy-data.php" ]; then
            # 一時的にコマンドを登録
            wp eval-file wp-cli-create-dummy-data.php -- ktp create-dummy-data --force
        else
            print_error "wp-cli-create-dummy-data.phpが見つかりません。"
            exit 1
        fi
        ;;
        
    "php")
        print_info "PHPスクリプトでダミーデータを作成します..."
        
        if [ -f "create_dummy_data.php" ]; then
            php create_dummy_data.php
        else
            print_error "create_dummy_data.phpが見つかりません。"
            exit 1
        fi
        ;;
        
    *)
        print_error "無効な実行方法です: $METHOD"
        print_info "使用可能な方法: wp-cli, php, auto"
        exit 1
        ;;
esac

print_success "ダミーデータ作成が完了しました！"
print_info "WordPress管理画面でデータを確認してください。" 