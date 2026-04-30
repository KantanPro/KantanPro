<?php
/**
 * Internationalization helpers for fixed labels and legacy inline strings.
 *
 * @package KTPWP
 * @since 1.2.69
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides gettext loading, fixed-value translation, and a JS bridge.
 */
class KTPWP_I18n {

    /**
     * Singleton instance.
     *
     * @var KTPWP_I18n|null
     */
    private static $instance = null;

    /**
     * Cached dictionary.
     *
     * @var array<string,string>|null
     */
    private $dictionary = null;

    /**
     * Get instance.
     *
     * @return KTPWP_I18n
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'init', array( $this, 'load_textdomain' ), 0 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script_bridge' ), 0 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script_bridge' ), 0 );
        add_action( 'template_redirect', array( $this, 'start_frontend_buffer' ), 0 );
        add_action( 'admin_init', array( $this, 'start_admin_buffer' ), 0 );
    }

    /**
     * Load plugin translations from the root languages directory.
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'ktpwp', false, dirname( plugin_basename( KTPWP_PLUGIN_FILE ) ) . '/languages/' );
    }

    /**
     * Translate fixed labels that may live outside gettext wrappers.
     *
     * @param string $text Original text.
     * @return string
     */
    public function translate_fixed_text( $text ) {
        if ( ! is_string( $text ) || $text === '' || ! $this->should_translate_to_english() ) {
            return $text;
        }
        $dictionary = $this->get_dictionary();
        return isset( $dictionary[ $text ] ) ? $dictionary[ $text ] : $text;
    }

    /**
     * Register a JS translation bridge for legacy scripts.
     *
     * @return void
     */
    public function enqueue_script_bridge() {
        $currency = class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::get_currency_config() : array(
            'code' => 'JPY',
            'symbol' => '円',
            'position' => 'after',
            'decimals' => 0,
        );

        $data = array(
            'locale' => determine_locale(),
            'strings' => $this->should_translate_to_english() ? $this->get_dictionary() : array(),
            'currency' => $currency,
        );

        $script = 'window.ktpwpI18n=' . wp_json_encode( $data ) . ';'
            . 'window.ktpwpTranslate=function(text){var s=(window.ktpwpI18n&&window.ktpwpI18n.strings)||{};return s[text]||text;};'
            . 'window.ktpwpFormatMoney=function(amount){var c=(window.ktpwpI18n&&window.ktpwpI18n.currency)||{code:\'JPY\',symbol:\'¥\',position:\'after\',decimals:0};var n=Number(amount)||0;var d=Number(c.decimals)||0;var f=n.toLocaleString(undefined,{minimumFractionDigits:d,maximumFractionDigits:d});return c.position===\'before\'?String(c.symbol||\'\')+f:f+String(c.symbol||\'\');};'
            . '(function(){var nativeAlert=window.alert,nativeConfirm=window.confirm;'
            . 'window.alert=function(message){return nativeAlert.call(window,window.ktpwpTranslate(String(message)));};'
            . 'window.confirm=function(message){return nativeConfirm.call(window,window.ktpwpTranslate(String(message)));};'
            . '})();';

        wp_register_script( 'ktpwp-i18n-bridge', '', array(), defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0.0', true );
        wp_enqueue_script( 'ktpwp-i18n-bridge' );
        wp_add_inline_script( 'ktpwp-i18n-bridge', $script, 'before' );
    }

    /**
     * Start output translation on KantanPro shortcode pages.
     *
     * @return void
     */
    public function start_frontend_buffer() {
        if ( is_admin() || wp_doing_ajax() || ! $this->should_translate_to_english() ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post = get_post();
        if ( ! $post || ! has_shortcode( (string) $post->post_content, 'ktpwp_all_tab' ) ) {
            return;
        }

        ob_start( array( $this, 'translate_output' ) );
    }

    /**
     * Start output translation on KantanPro admin pages.
     *
     * @return void
     */
    public function start_admin_buffer() {
        if ( ! is_admin() || wp_doing_ajax() || ! $this->should_translate_to_english() ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( strpos( $page, 'ktp' ) !== 0 && strpos( $page, 'kantanpro' ) !== 0 ) {
            return;
        }

        ob_start( array( $this, 'translate_output' ) );
    }

    /**
     * Translate fixed strings in legacy HTML output.
     *
     * @param string $buffer HTML buffer.
     * @return string
     */
    public function translate_output( $buffer ) {
        if ( ! is_string( $buffer ) || $buffer === '' || ! $this->should_translate_to_english() ) {
            return $buffer;
        }
        return strtr( $buffer, $this->get_dictionary() );
    }

    /**
     * Determine whether current locale should use English labels.
     *
     * @return bool
     */
    private function should_translate_to_english() {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        return is_string( $locale ) && strpos( strtolower( $locale ), 'en' ) === 0;
    }

    /**
     * English dictionary for fixed strings and legacy JS/PHP output.
     *
     * @return array<string,string>
     */
    private function get_dictionary() {
        if ( null !== $this->dictionary ) {
            return $this->dictionary;
        }

        $this->dictionary = array(
            '表示' => 'Show',
            '%1$d / %2$d ページ（全 %3$d 件）' => '%1$d / %2$d page (%3$d total)',
            '%s の詳細を表示' => 'View details for %s',
            '%d 件の注文を KantanPro に同期しました。' => 'Synced %d orders to KantanPro.',
            '%s は無料版では利用できません。' => '%s is not available in the free version.',
            '%s へ移行してご利用ください。' => 'Please migrate to %s to use this feature.',
            '非表示' => 'Hide',
            '閉じる' => 'Close',
            '開く' => 'Open',
            '保存' => 'Save',
            '保存しました' => 'Saved.',
            '保存に失敗しました' => 'Failed to save.',
            '更新' => 'Update',
            '更新しました' => 'Updated.',
            '更新に失敗しました。' => 'Update failed.',
            '削除' => 'Delete',
            '削除しました' => 'Deleted.',
            '削除に失敗しました。' => 'Delete failed.',
            '追加' => 'Add',
            '追加しました' => 'Added.',
            '追加に失敗しました。' => 'Add failed.',
            '編集' => 'Edit',
            '複製' => 'Duplicate',
            '検索' => 'Search',
            '検索結果' => 'Search results',
            '検索結果が複数あります！' => 'Multiple search results were found.',
            '該当するデータがありません。' => 'No matching data found.',
            '該当するサービスが見つかりませんでした。条件を変更して再検索してください。' => 'No matching services were found. Change the conditions and search again.',
            '検索モードです。条件を入力して検索してください。' => 'Search mode. Enter conditions and search.',
            'キャンセル' => 'Cancel',
            '確認' => 'Confirm',
            '戻る' => 'Back',
            '次へ' => 'Next',
            '前へ' => 'Previous',
            '適用' => 'Apply',
            '印刷' => 'Print',
            '印刷する' => 'Print',
            'メール' => 'Email',
            'メール送信' => 'Send email',
            'メール内容を読み込み中...' => 'Loading email content...',
            'メール内容の取得に失敗しました' => 'Failed to get email content.',
            'メール内容の読み込みに失敗しました' => 'Failed to load email content.',
            '再度お試しください' => 'Please try again.',
            '権限がありません。ログインを確認してください。' => 'You do not have permission. Please check your login.',
            '受注書が見つかりませんでした。' => 'Order not found.',
            'サーバーエラーが発生しました。' => 'A server error occurred.',
            'レスポンスの解析に失敗しました。' => 'Failed to parse the response.',
            'HTMLエラーメッセージが混在している可能性があります。' => 'The response may include an HTML error message.',
            'ステータス' => 'Status',
            '宛先' => 'To',
            'CC（任意・カンマ区切り）' => 'CC (optional, comma-separated)',
            '顧客代表・各部署に登録されたメールのうち、宛先（To）以外を自動で入れます（KantanBizの「CC（任意）」と同様）。編集できます。' => 'Automatically fills addresses registered for the customer representative and departments except the To address. You can edit this field.',
            '件名' => 'Subject',
            '本文' => 'Body',
            'ファイル添付' => 'File attachments',
            'ファイルをドラッグ&ドロップまたはクリックして選択' => 'Drag and drop files or click to select',
            '対応形式：PDF, 画像(JPG,PNG,GIF), Word, Excel, 圧縮ファイル等' => 'Supported formats: PDF, images (JPG, PNG, GIF), Word, Excel, archives, etc.',
            '最大ファイルサイズ：10MB/ファイル, 合計50MB' => 'Maximum file size: 10 MB per file, 50 MB total',
            'ファイル "%s" は10MBを超えています。\n最大ファイルサイズ：10MB' => 'File "%s" exceeds 10 MB.\nMaximum file size: 10 MB',
            '合計ファイルサイズが50MBを超えます。\nファイルを減らしてください。' => 'Total file size exceeds 50 MB.\nPlease reduce the number of files.',
            'ファイル "%s" は対応していない形式です。\n対応形式：PDF, 画像, Word, Excel, 圧縮ファイル等' => 'File "%s" is not a supported format.\nSupported formats: PDF, images, Word, Excel, archives, etc.',
            '選択されたファイル' => 'Selected files',
            'メール送信中...' => 'Sending email...',
            '%d件のファイルを添付中...' => 'Attaching %d files...',
            'メール送信完了' => 'Email sent successfully',
            '進捗を「%s」に更新しました。' => 'Updated progress to "%s".',
            '添付ファイル' => 'Attachments',
            'メール送信に失敗しました' => 'Failed to send email.',
            'メール送信失敗' => 'Email sending failed',
            'メール送信エラー' => 'Email sending error',
            '再試行' => 'Retry',
            '受注書IDが見つかりません。' => 'Order ID not found.',
            '件名と本文を入力してください。' => 'Enter a subject and body.',
            'メール送信不可（顧客データなし）' => 'Cannot send email (no customer data)',
            '顧客データが見つかりません' => 'Customer data not found',
            'この受注書に関連する顧客データが見つかりません。顧客管理画面で顧客を登録してください。' => 'Customer data related to this order was not found. Register the customer on the customer management screen.',
            'メール送信不可（対象外顧客）' => 'Cannot send email (excluded customer)',
            'この顧客は削除済み（対象外）のため、メール送信はできません。' => 'This customer has been deleted or excluded, so email cannot be sent.',
            'メール送信不可（メールアドレス未設定または無効）' => 'Cannot send email (email address missing or invalid)',
            'メールアドレス未設定' => 'Email address not set',
            'この顧客のメールアドレスが未設定または無効です。顧客管理画面で代表メールまたは部署メールを登録してください。' => 'This customer email address is missing or invalid. Register a representative or department email on the customer management screen.',
            'メール送信不可' => 'Email cannot be sent',
            '送信' => 'Send',
            '送信しました。' => 'Sent.',
            '送信に失敗しました。' => 'Failed to send.',
            '登録' => 'Register',
            'ログイン' => 'Log in',
            'ログアウト' => 'Log out',
            'ホームへ' => 'Home',
            '権限がありません。' => 'You do not have permission.',
            'セキュリティチェックに失敗しました。' => 'Security check failed.',
            'セキュリティトークンが取得できません。ページを再読み込みしてください。' => 'Could not get the security token. Please reload the page.',
            '不正なリクエストです。' => 'Invalid request.',
            '仕事リスト' => 'Work List',
            '寄付する' => 'Donate',
            'ヘルプ' => 'Help',
            'フリーワード' => 'Keyword',
            '📅 ソート順:' => '📅 Sort order:',
            'ソート順:' => 'Sort order:',
            '納期が迫っている順 → 受注日時順（新しい順）で表示されています。' => 'Displayed by nearest delivery date, then by order date (newest first).',
            '納期' => 'Delivery date',
            'このウィンドウを閉じる' => 'Close this window',
            '利用規約' => 'Terms of Service',
            '納期が過ぎています' => 'The delivery date has passed.',
            '納期が迫っています' => 'The delivery date is approaching.',
            '納期が迫っている、または過ぎている案件が%d件あります' => 'There are %d orders with approaching or overdue delivery dates.',
            '請求日を過ぎている案件が%d件あります' => 'There are %d orders past the billing date.',
            '入金予定日を過ぎている案件が%d件あります' => 'There are %d orders past the expected payment date.',
            '受注書' => 'Order',
            '受注書データがありません。' => 'No order data found.',
            '顧客タブで顧客情報を入力し受注書を作成してください' => 'Enter customer information on the Customer tab and create an order.',
            '受注書を作成しました。' => 'Order created.',
            '受注書作成' => 'Create order',
            '案件名' => 'Project name',
            '顧客' => 'Customer',
            '顧客情報' => 'Customer information',
            '顧客情報印刷' => 'Customer information print',
            '印刷日時:' => 'Print date:',
            '顧客リスト' => 'Customer list',
            '顧客データが見つかりません。' => 'No customer data found.',
            '会社名' => 'Company name',
            '担当者名' => 'Contact person',
            'メールアドレス' => 'Email address',
            '電話番号' => 'Phone number',
            '住所' => 'Address',
            'カテゴリー' => 'Category',
            '対象｜対象外' => 'Active / inactive',
            '頻度' => 'Frequency',
            '登録日' => 'Registration date',
            '注文ID' => 'Order ID',
            '注文履歴' => 'Order history',
            '日付' => 'Date',
            '受付' => 'Received',
            '受付中' => 'Received',
            '見積中' => 'Estimating',
            '受注' => 'Ordered',
            '完了' => 'Completed',
            '請求済' => 'Invoiced',
            '入金済' => 'Paid',
            'ボツ' => 'Rejected',
            '進捗' => 'Progress',
            '支払' => 'Payment',
            '後払い' => 'Postpay',
            '前入金済' => 'Prepaid',
            'WC受注' => 'WooCommerce order',
            '約束納期' => 'Promised due date',
            '希望納期' => 'Requested due date',
            '納品予定日' => 'Scheduled delivery date',
            '完了日' => 'Completion date',
            'メモ' => 'Memo',
            '備考' => 'Remarks',
            'コスト項目' => 'Cost items',
            '請求項目' => 'Invoice items',
            '商品名' => 'Item name',
            '単価' => 'Unit price',
            '数量' => 'Quantity',
            '単位' => 'Unit',
            '税率' => 'Tax rate',
            '金額' => 'Amount',
            '小計' => 'Subtotal',
            '消費税' => 'Tax',
            '円' => class_exists( 'KTPWP_Settings' ) ? ' ' . KTPWP_Settings::get_currency_code() : ' JPY',
            '内税' => 'tax included',
            '外税' => 'tax excluded',
            '非課税' => 'Tax exempt',
            '合計金額' => 'Total amount',
            '金額合計' => 'Amount total',
            '税込合計' => 'Total incl. tax',
            '利益' => 'Profit',
            '適格請求書コスト' => 'Qualified invoice cost',
            '非適格請求書コスト' => 'Non-qualified invoice cost',
            'メール送信履歴' => 'Email history',
            '案件ファイル' => 'Project files',
            'スタッフチャット' => 'Staff chat',
            '合計' => 'Total',
            '協力会社' => 'Supplier',
            '協力会社リスト' => 'Supplier list',
            '協力会社情報' => 'Supplier information',
            '協力会社を削除しました。' => 'Supplier deleted.',
            '協力会社情報を更新しました。' => 'Supplier information updated.',
            'サービス' => 'Service',
            'サービス情報' => 'Service information',
            'サービスリスト' => 'Service list',
            '■ サービスリスト' => '■ Service list',
            '■ サービスの詳細' => '■ Service details',
            'サービスの詳細（検索モード）' => 'Service details (search mode)',
            'サービス追加中' => 'Adding service',
            'サービス名' => 'Service name',
            '価格' => 'Price',
            '新しいサービス' => 'New service',
            '新規追加に失敗しました。' => 'Failed to add a new item.',
            'ファイルを追加しました。' => 'File added.',
            'ファイルを追加できませんでした。' => 'Could not add the file.',
            'ファイルサイズが大きすぎます（最大 20MB）。' => 'The file is too large (maximum 20MB).',
            'PDF または画像（JPEG・PNG・GIF・WebP）のみアップロードできます。' => 'Only PDF or image files (JPEG, PNG, GIF, WebP) can be uploaded.',
            'ファイルを削除しました。' => 'File deleted.',
            'ファイルを削除できませんでした。' => 'Could not delete the file.',
            '受注書が見つかりません。' => 'Order not found.',
            'レポート' => 'Report',
            '売上レポート' => 'Sales report',
            '売上台帳' => 'Sales ledger',
            '設定' => 'Settings',
            'ライセンス' => 'License',
            'ライセンスキーを入力してください。' => 'Please enter a license key.',
            'ライセンスが正常に認証されました。' => 'The license was activated successfully.',
            'ライセンスの認証に失敗しました。' => 'License activation failed.',
            'ライセンスが有効です。' => 'The license is valid.',
            'ライセンスが無効です。' => 'The license is invalid.',
            'ライセンスキーが設定されていません。' => 'No license key is set.',
            '更新をチェック中...' => 'Checking for updates...',
            '更新をチェック' => 'Check for updates',
            'エラーが発生しました' => 'An error occurred',
            '新しいバージョンが利用可能です！' => 'A new version is available!',
            '最新バージョンです。' => 'You are using the latest version.',
            '詳細を表示' => 'View details',
            'KantanProプラグインが正常にインストールされました。' => 'KantanPro plugin was installed successfully.',
            'KantanProプラグインが正常に有効化されました。' => 'KantanPro plugin was activated successfully.',
            'KantanProプラグインが正常に有効化されました。すべての機能が利用可能です。' => 'KantanPro plugin was activated successfully. All features are available.',
            'KantanProプラグインが正常に更新されました。適格請求書ナンバー機能も含まれています。' => 'KantanPro plugin was updated successfully. The qualified invoice number feature is also included.',
            '削除時の動作' => 'Deletion behavior',
            'データを残す' => 'Keep data',
            '変更' => 'Change',
            '完全削除' => 'Full delete',
            'スモールビジネスのための販売支援ツール' => 'Sales support tool for small businesses',
            '自動更新を無効化' => 'Disable auto-updates',
            '%s の自動更新を無効化' => 'Disable auto-updates for %s',
            '自動更新を有効化' => 'Enable auto-updates',
            '%s の自動更新を有効化' => 'Enable auto-updates for %s',
            'スモールビジネスのための販売支援ツール。ショートコード[ktpwp_all_tab]を固定ページに設置してください。' => 'Sales support tool for small businesses. Place the shortcode [ktpwp_all_tab] on a fixed page.',
            '詳細な変更履歴については、GitHubリポジトリをご確認ください。' => 'See the GitHub repository for the detailed changelog.',
            'プラグインをアップロードして有効化してください。ショートコード[ktpwp_all_tab]を固定ページに設置することで、システムが利用可能になります。' => 'Upload and activate the plugin. The system becomes available when you place the shortcode [ktpwp_all_tab] on a fixed page.',
            'よくある質問については、プラグインのドキュメントをご確認ください。' => 'See the plugin documentation for frequently asked questions.',
            '詳細な変更履歴は公式サイトまたはリポジトリをご確認ください。' => 'See the official site or repository for detailed changelog information.',
            'この操作を実行する権限がありません。' => 'You do not have permission to perform this action.',
            'この機能は開発環境でのみ利用可能です。' => 'This feature is only available in the development environment.',
            'お問い合わせの件' => 'Inquiry',
            '件名:' => 'Subject:',
            'メッセージ本文:' => 'Message:',
            '一般' => 'General',
            '通常の協力会社' => 'Regular Supplier',
            '税込' => 'Tax Included',
            '税別' => 'Tax Excluded',
            '昇順' => 'Ascending',
            '降順' => 'Descending',
            '未設定' => 'Not set',
            '請求書発行' => 'Issue invoice',
            '請求書' => 'Invoice',
            '請求書プレビュー' => 'Invoice preview',
            '読み込み中...' => 'Loading...',
            '※ 期間集計は受付日ではなく、完了日を基準にしています。' => 'Period totals are based on completion date, not received date.',
            'レポート機能の利用にはライセンスが必要です' => 'A license is required to use reports',
            '詳細な分析とレポート機能を利用するには、ライセンスキーを購入して設定してください。' => 'Purchase and set a license key to use detailed analytics and reports.',
            'ライセンスを購入' => 'Purchase a license',
            'ライセンス購入後は%sでライセンスキーを入力してください' => 'After purchasing a license, enter your license key in %s.',
            'ライセンス設定' => 'License settings',
            '該当期間に売上データがありません。期間を変更するか、請求済以降の案件・請求項目を登録してください。' => 'No sales data found for this period. Change the period or add invoice items to orders at Invoice Sent or later.',
            '該当期間に貢献度データがありません。期間を変更するか、請求済以降の案件に協力会社・原価項目を登録してください。' => 'No contribution data found for this period. Change the period or add suppliers and cost items to orders at Invoice Sent or later.',
            '売上台帳_%s年_%s' => 'sales-ledger-%s-%s',
            'Y年m月d日' => 'Y-m-d',
            '（自社名未設定）' => '(Company name not set)',
            '%s年度' => 'Fiscal year %s',
            '作成日：%s' => 'Created: %s',
            '年間売上サマリー' => 'Annual sales summary',
            '月別売上サマリー' => 'Monthly sales summary',
            '月' => 'Month',
            '%d月' => '%d',
            '売上明細' => 'Sales details',
            '商品・サービス' => 'Products/services',
            '無効な年度が指定されました。' => 'Invalid year specified.',
            'PDF生成中にエラーが発生しました: ' => 'An error occurred while generating the PDF: ',
            '顧客別レポート' => 'Customer report',
            'サービス別レポート' => 'Service report',
            '協力会社レポート' => 'Supplier report',
            '確定申告用' => 'Tax return',
            '📊 売上計算について' => '📊 About sales calculation',
            '売上は「請求済」以降の進捗状況の案件のみを対象としています。' => 'Sales include only orders whose progress is Invoice Sent or later.',
            '※ 請求項目があっても進捗が「完了」以前の場合は売上に含まれません。' => 'Orders before Completed are not included in sales even if they have invoice items.',
            '※ 「ボツ」案件は売上計算から除外されています。' => 'Rejected orders are excluded from sales calculations.',
            '月別売上推移' => 'Monthly sales trend',
            '月別利益コスト比較' => 'Monthly profit and cost comparison',
            '顧客別売上' => 'Sales by customer',
            '顧客別案件数' => 'Orders by customer',
            'サービス別売上' => 'Sales by service',
            'サービス別比率（受注ベース）' => 'Service ratio (order based)',
            '協力会社別貢献度' => 'Contribution by supplier',
            'スキル別協力会社数' => 'Suppliers by skill',
            '売上は「請求済」以降の進捗状況の案件のみを対象としています。「ボツ」案件は売上計算から除外されています。対象期間：%s' => 'Sales include only orders whose progress is Invoice Sent or later. Rejected orders are excluded. Period: %s',
            '売上は「請求済」以降の進捗状況の案件のみを対象としています。サービス別比率は「受注」以降の進捗状況の案件を対象としています。「ボツ」案件は計算から除外されています。対象期間：%s' => 'Sales include only orders whose progress is Invoice Sent or later. Service ratios include orders from Ordered or later. Rejected orders are excluded. Period: %s',
            '貢献度は「請求済」以降の進捗状況の案件のみを対象としています。「ボツ」案件は計算から除外されています。対象期間：%s' => 'Contribution includes only orders whose progress is Invoice Sent or later. Rejected orders are excluded. Period: %s',
            '全期間' => 'All time',
            '今年' => 'This year',
            '去年' => 'Last year',
            '先月' => 'Last month',
            '過去3ヶ月' => 'Last 3 months',
            '過去6ヶ月' => 'Last 6 months',
            '期間選択' => 'Select period',
            '総売上' => 'Total sales',
            '案件数' => 'Orders',
            '平均単価' => 'Average amount',
            '%s件' => '%s items',
            '売上TOP5顧客' => 'Top 5 customers by sales',
            '売上TOP5サービス' => 'Top 5 services by sales',
            '貢献度TOP5協力会社' => 'Top 5 suppliers by contribution',
            '（顧客未設定）' => '(Customer not set)',
            '（協力会社未設定）' => '(Supplier not set)',
            '（未設定）' => '(Not set)',
            '無題' => 'Untitled',
            '対象年度選択' => 'Select tax year',
            '%s年' => '%s',
            '売上台帳（%s年）' => 'Sales ledger (%s)',
            '年間売上合計' => 'Annual sales total',
            '売上件数' => 'Sales count',
            '📋 売上台帳プレビュー' => '📋 Sales ledger preview',
            '（最新10件）' => '(latest 10)',
            '顧客名' => 'Customer name',
            '売上金額' => 'Sales amount',
            '※ 全%1$d件中、最新10件を表示。全件は印刷プレビューでご確認ください。' => 'Showing the latest 10 of %1$d items. View all items in print preview.',
            '対象年度の売上データがありません。' => 'No sales data found for the selected year.',
            '📊 確定申告サポート機能' => '📊 Tax return support features',
            '売上台帳印刷' => 'Sales ledger printing',
            '年度別の売上データを帳簿形式で印刷' => 'Print yearly sales data in ledger format',
            '税務署提出対応' => 'Tax office submission support',
            '確定申告に必要な売上情報を整理' => 'Organize sales information needed for tax returns',
            '月別集計' => 'Monthly totals',
            '月ごとの売上推移を確認可能' => 'Check month-by-month sales trends',
            '主要取引先の売上内訳を把握' => 'Understand sales breakdowns by major customers',
            '進行中' => 'In progress',
            '支払済' => 'Paid',
            'コスト' => 'Cost',
            '貢献度' => 'Contribution',
            '印刷する内容が見つかりません。' => 'No printable content found.',
            '印刷データの作成に失敗しました。' => 'Failed to create print data.',
            '年度が指定されていません。' => 'Year is not specified.',
            '🖨️ 生成中...' => '🖨️ Generating...',
            '🖨️ 印刷' => '🖨️ Print',
            'PDFデータの取得に失敗しました: ' => 'Failed to get PDF data: ',
            'エラー詳細不明' => 'Unknown error details',
            'PDFデータの解析に失敗しました。' => 'Failed to parse PDF data.',
            'PDFデータの取得中にエラーが発生しました: ' => 'An error occurred while getting PDF data: ',
            '■ 協力会社の詳細' => '■ Supplier details',
            '（ ID: %s ）' => '( ID: %s )',
            '■ %1$s（ID: %2$s）の商品' => '■ %1$s products (ID: %2$s)',
            '■ %sの商品' => '■ %s products',
            '削除する' => 'Delete',
            '削除（無効化）する' => 'Delete (deactivate)',
            '追加する' => 'Add',
            '追加実行' => 'Add',
            '検索する' => 'Search',
            '更新する' => 'Update',
            '複製する' => 'Duplicate',
            '画像をアップロード' => 'Upload image',
            '受注書がありません' => 'No order available',
            '非表示にする' => 'Hide',
            '本当に削除しますか？' => 'Are you sure you want to delete this?',
            'このメッセージを削除' => 'Delete this message',
            'ドラッグして並び替え' => 'Drag to reorder',
            '行を追加' => 'Add row',
            '行を削除' => 'Delete row',
            '行を移動' => 'Move row',
            'サービス選択' => 'Select service',
            '売上レポートの期間判定に使われる登録日です' => 'Registration date used for sales report period calculation',
            '請求日を過ぎています' => 'Invoice date has passed',
            '印刷する' => 'Print',
            '印刷' => 'Print',
            '作成' => 'Create',
            '会社名：' => 'Company name:',
            '名前：' => 'Name:',
            'カテゴリー：' => 'Category:',
            'の注文履歴' => ' order history',
            '不明' => 'Unknown',
            '完了日：' => 'Completion date:',
            'まだ注文がありません。' => 'No orders yet.',
            '[削除済み]' => '[Deleted]',
            '[＋]ボタンを押してデーターを作成してください' => 'Press the [+] button to create data.',
            'データがまだ登録されていません' => 'No data has been registered yet.',
            '顧客データがありません。' => 'No customer data found.',
            '顧客削除の選択' => 'Select Customer Deletion Method',
            'の削除方法を選択してください' => ', select a deletion method.',
            '1. 対象外（推奨）' => '1. Exclude (recommended)',
            '受注書は残り、顧客データは復元可能です' => 'Orders remain, and customer data can be restored.',
            '2. 通常削除' => '2. Delete',
            '顧客データと部署データを削除（受注書は残す）' => 'Delete customer and department data (orders remain).',
            '3. 完全削除' => '3. Permanent Delete',
            '顧客データと関連する受注書を完全に削除します' => 'Permanently delete customer data and related orders.',
            '削除実行' => 'Delete',
            '削除方法を選択してください。' => 'Please select a deletion method.',
            '対象外に変更しますか？\n受注書は残り、顧客データは復元可能です。' => 'Change this customer to inactive?\nOrders remain, and customer data can be restored.',
            '顧客データと部署データを削除しますか？\n\n注意：\n• 顧客データと部署データが完全に削除されます\n• 受注書は残りますが、顧客情報は失われます\n• この操作は元に戻せません' => 'Delete customer and department data?\n\nCaution:\n• Customer and department data will be permanently deleted\n• Orders will remain, but customer information will be lost\n• This action cannot be undone',
            '顧客データと関連する受注書を完全に削除しますか？\n\n警告：\n• 顧客データが完全に削除されます\n• 関連するすべての受注書が削除されます\n• この操作は元に戻せません\n• データの復元は不可能です' => 'Permanently delete customer data and related orders?\n\nWarning:\n• Customer data will be permanently deleted\n• All related orders will be deleted\n• This action cannot be undone\n• The data cannot be restored',
            '受注書作成（対象外顧客のため無効）' => 'Create order (disabled for inactive customer)',
            '顧客ID: ' => 'Customer ID: ',
            '対象外顧客のため、受注書を作成できません。' => 'Cannot create an order for an inactive customer.',
            'フリーワード検索' => 'Keyword search',
            '検索実行' => 'Search',
            '■ 顧客の詳細' => '■ Customer details',
            '■ 顧客の詳細（検索モード）' => '■ Customer details (search mode)',
            '部署設定（複数の部署や担当者がある場合は設定してください）' => 'Department settings (set this when there are multiple departments or contacts)',
            '部署名' => 'Department name',
            '操作' => 'Actions',
            '選択された部署' => 'Selected department',
            '部署名:' => 'Department name:',
            '担当者名:' => 'Contact person:',
            'メールアドレス:' => 'Email address:',
            '部署を選択してください' => 'Please select a department.',
            '部署が登録されていません。' => 'No departments registered.',
            '顧客を保存後に部署を追加できます。' => 'You can add departments after saving the customer.',
            '担当者名とメールアドレスを入力してください。' => 'Please enter a contact person and email address.',
            '部署の追加に失敗しました: ' => 'Failed to add department: ',
            '部署の追加に失敗しました。' => 'Failed to add department.',
            'この部署を削除しますか？' => 'Delete this department?',
            '部署の削除に失敗しました: ' => 'Failed to delete department: ',
            '部署の削除に失敗しました。' => 'Failed to delete department.',
            '部署選択状態の更新に失敗しました: ' => 'Failed to update department selection: ',
            '部署選択状態の更新に失敗しました。' => 'Failed to update department selection.',
            '初めてのお客様' => 'First Customer',
            '名前' => 'Name',
            'メール' => 'Email',
            '代表者名' => 'Representative name',
            '郵便番号' => 'Postal code',
            '都道府県' => 'Prefecture',
            '市区町村' => 'City',
            '番地' => 'Street address',
            '建物名' => 'Building',
            '締め日' => 'Closing day',
            '支払月' => 'Payment month',
            '支払日' => 'Payment day',
            '支払方法' => 'Payment method',
            '税区分' => 'Tax category',
            '支払タイミング' => 'Payment timing',
            '必須 法人名または屋号' => 'Required: company or trade name',
            '半角数字 ハイフン不要' => 'Half-width digits, no hyphens',
            '5日' => '5th',
            '10日' => '10th',
            '15日' => '15th',
            '20日' => '20th',
            '25日' => '25th',
            '末日' => 'End of month',
            '今月' => 'This month',
            '翌月' => 'Next month',
            '翌々月' => 'Month after next',
            'その他' => 'Other',
            '即日' => 'Same day',
            '銀行振込' => 'Bank transfer',
            'クレジット' => 'Credit card',
            '現金集金' => 'Cash collection',
            '対象' => 'Active',
            '対象外' => 'Inactive',
            ' 様' => '',
            '様' => '',
            '通信エラーが発生しました。ページを再読み込みして再度お試しください。' => 'A communication error occurred. Please reload the page and try again.',
            '適格請求書番号：' => 'Qualified invoice number:',
            '平素より大変お世話になっております。下記の通りご請求申し上げます。' => 'Thank you for your continued business. Please find the invoice details below.',
            '合計金額：' => 'Total amount:',
            '繰越金額：' => 'Carryover amount:',
            '消費税：' => 'Tax:',
            '税込合計：' => 'Total incl. tax:',
            '（内消費税：' => ' (incl. tax:',
            '請求金額：' => 'Invoice amount:',
            'お支払い期日：' => 'Payment due date:',
            '締日：' => 'Closing date:',
            '　案件数：' => ' Orders:',
            '案件数：' => 'Orders:',
            '（完了日：' => ' (Completion date:',
            '金額（税抜）' => 'Amount (excl. tax)',
            '金額（税込）' => 'Amount (incl. tax)',
            '単価（税抜）' => 'Unit price (excl. tax)',
            '単価（税込）' => 'Unit price (incl. tax)',
            '税額（外税）' => 'Tax (exclusive)',
            '税額（内税）' => 'Tax (included)',
            '数量/単位' => 'Qty/Unit',
            '式' => 'set',
            '案件合計：' => 'Order total:',
            '請求項目なし' => 'No invoice items',
            ' 月別合計：' => ' monthly total:',
            '対象受注書の進捗を「請求済」に変更する' => 'Change target orders to Invoiced',
            '印刷 PDF保存' => 'Print / Save PDF',
            '該当する案件はありません。' => 'No matching orders found.',
            'データ取得エラー: ' => 'Data retrieval error: ',
            'レスポンス: ' => 'Response: ',
            '通信エラー (HTTP ' => 'Communication error (HTTP ',
            '顧客IDが見つかりません。' => 'Customer ID not found.',
            'セキュリティエラー: nonceが見つかりません。' => 'Security error: nonce not found.',
            'あり' => 'Yes',
            'なし' => 'No',
            'はい' => 'Yes',
            'いいえ' => 'No',
        );

        return $this->dictionary;
    }
}

if ( ! function_exists( 'ktpwp_translate_fixed_text' ) ) {
    /**
     * Translate a fixed KantanPro label when the site locale is English.
     *
     * @param string $text Original text.
     * @return string
     */
    function ktpwp_translate_fixed_text( $text ) {
        if ( ! class_exists( 'KTPWP_I18n' ) ) {
            return $text;
        }
        return KTPWP_I18n::get_instance()->translate_fixed_text( $text );
    }
}
