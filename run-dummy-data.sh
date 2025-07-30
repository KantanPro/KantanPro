#!/bin/bash

# 強化版ダミーデータ作成・クリアスクリプト
# KantanPro WordPressプラグイン用

# 色付き出力用の関数
print_success() {
    echo -e "\033[32m✓ $1\033[0m"
}

print_error() {
    echo -e "\033[31m✗ $1\033[0m"
}

print_info() {
    echo -e "\033[34mℹ $1\033[0m"
}

print_warning() {
    echo -e "\033[33m⚠ $1\033[0m"
}

# ヘルプ表示
show_help() {
    echo "強化版ダミーデータ作成・クリアスクリプト"
    echo ""
    echo "使用方法:"
    echo "  $0 [オプション]"
    echo ""
    echo "オプション:"
    echo "  create    ダミーデータを作成（デフォルト）"
    echo "  clear     ダミーデータをクリア"
    echo "  help      このヘルプを表示"
    echo ""
    echo "作成されるデータ:"
    echo "  - 顧客×6件（メールアドレス: info@kantanpro.com）"
    echo "  - 協力会社×6件（メールアドレス: info@kantanpro.com）"
    echo "  - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）"
    echo "  - 受注書×18件（顧客×6件 × 進捗3ステータス：受付中・受注・完成）"
    echo "  - 職能×18件（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）"
    echo "  - 各受注書に請求項目とコスト項目を自動追加"
    echo ""
    echo "例:"
    echo "  $0 create  # ダミーデータを作成"
    echo "  $0 clear   # ダミーデータをクリア"
}

# メイン処理
main() {
    local action=${1:-create}
    
    case $action in
        "create")
            print_info "強化版ダミーデータ作成を開始します..."
            
            # WordPressディレクトリに移動
            cd "$(dirname "$0")/wordpress" 2>/dev/null || {
                print_error "WordPressディレクトリが見つかりません"
                exit 1
            }
            
            # PHPスクリプトを実行
            if php ../create_dummy_data.php; then
                print_success "ダミーデータ作成が完了しました！"
            else
                print_error "ダミーデータ作成に失敗しました"
                exit 1
            fi
            ;;
            
        "clear")
            print_warning "ダミーデータのクリアを開始します..."
            print_warning "この操作は元に戻せません。続行しますか？ (y/N)"
            read -r response
            
            if [[ "$response" =~ ^[Yy]$ ]]; then
                # WordPressディレクトリに移動
                cd "$(dirname "$0")/wordpress" 2>/dev/null || {
                    print_error "WordPressディレクトリが見つかりません"
                    exit 1
                }
                
                # PHPスクリプトを実行
                if php ../create_dummy_data.php clear; then
                    print_success "ダミーデータクリアが完了しました！"
                else
                    print_error "ダミーデータクリアに失敗しました"
                    exit 1
                fi
            else
                print_info "クリア操作をキャンセルしました"
            fi
            ;;
            
        "help"|"-h"|"--help")
            show_help
            ;;
            
        *)
            print_error "不明なオプション: $action"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# スクリプト実行
main "$@" 