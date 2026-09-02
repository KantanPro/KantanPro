<?php
/**
 * KantanPro利用規約管理クラス
 *
 * @package KantanPro
 * @since 1.3.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Terms_Of_Service クラス
 */
class KTPWP_Terms_Of_Service {

    // 旧開発者パスワード関連は廃止しました

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * パスワードハッシュを生成（デバッグ用）
     */
    public static function generate_password_hash( $password ) {
        return wp_hash_password( $password );
    }

    /**
     * シングルトンインスタンス取得
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * テーブル名
     */
    private $table_name;

    /**
     * コンストラクタ（private）
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // セッション開始（安全な方法で）
        ktpwp_safe_session_start();

        // フックの設定
        add_action( 'admin_init', array( $this, 'handle_terms_actions' ) );
        add_action( 'wp_ajax_ktpwp_terms_agreement', array( $this, 'handle_terms_agreement' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_terms_agreement', array( $this, 'handle_terms_agreement' ) );
        add_action( 'init', array( $this, 'handle_public_terms_view' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_action( 'init', array( $this, 'maybe_schedule_terms_retry_cron' ) );
        add_action( 'ktpwp_flush_pending_terms_notifications', array( $this, 'flush_pending_notifications' ) );
    }

    /**
     * KLM 利用規約同意 API URL
     */
    private function get_klm_terms_api_url() {
        return 'https://www.kantanpro.com/wp-json/ktp-license/v1/terms-agreement';
    }

    /**
     * KLM 向けプラグイン slug
     */
    private function get_plugin_slug_for_klm() {
        if ( defined( 'KANTANPRO_PLUGIN_NAME' ) && KANTANPRO_PLUGIN_NAME === 'KantanProEX' ) {
            return 'kantanpro-ex';
        }
        return 'kantanpro';
    }

    /**
     * 保留通知オプション名
     */
    private function get_pending_option_name() {
        return 'ktpwp_pending_terms_notifications';
    }

    /**
     * クローンを禁止
     */
    private function __clone() {}

    /**
     * シリアライズを禁止
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }

    /**
     * テーブルを作成
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            terms_content longtext NOT NULL,
            version varchar(20) NOT NULL DEFAULT '1.0',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY version (version)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // デフォルトの利用規約を挿入
        self::insert_default_terms();
    }

    /**
     * バンドル既定の利用規約バージョン
     */
    public static function get_default_terms_version() {
        return '2.0';
    }

    /**
     * 規約文中のプラグイン表示名
     */
    public static function get_plugin_name_for_terms() {
        return defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanPro';
    }

    /**
     * デフォルトの利用規約を挿入
     */
    public static function insert_default_terms() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // 既に利用規約が存在するかチェック
        $existing_terms = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE is_active = 1" );
        
        if ( $existing_terms == 0 ) {
            $default_terms = self::get_default_terms_content();
            
            $wpdb->insert(
                $table_name,
                array(
                    'terms_content' => $default_terms,
                    'version' => self::get_default_terms_version(),
                    'is_active' => 1
                ),
                array( '%s', '%s', '%d' )
            );
        }
    }

    /**
     * 旧既定規約（v1.0）から現行既定規約へ自動移行
     */
    public static function maybe_upgrade_default_terms() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
        if ( ! $terms ) {
            return;
        }

        if ( version_compare( (string) $terms->version, self::get_default_terms_version(), '>=' ) ) {
            return;
        }

        if ( ! self::is_legacy_v1_default_terms_content( $terms->terms_content, (string) $terms->version ) ) {
            return;
        }

        $wpdb->update(
            $table_name,
            array( 'is_active' => 0 ),
            array( 'is_active' => 1 ),
            array( '%d' ),
            array( '%d' )
        );

        $wpdb->insert(
            $table_name,
            array(
                'terms_content' => self::get_default_terms_content(),
                'version' => self::get_default_terms_version(),
                'is_active' => 1,
            ),
            array( '%s', '%s', '%d' )
        );
    }

    /**
     * デフォルトの利用規約内容を取得
     */
    public static function get_default_terms_content() {
        $plugin = self::get_plugin_name_for_terms();

        return sprintf(
            '### 第1条（適用）
本規約は、%1$sプラグイン（以下「本プラグイン」）の利用に関して適用されます。
本規約における「当社」とは、本プラグインの開発・運営者をいいます。

### 第2条（利用条件）
1. 本プラグインは、WordPress環境での利用を前提としています。
2. 利用者は、本プラグインの利用にあたり、適切な権限を有する必要があります。
3. 本プラグインの設定・管理を行うWordPress管理者（以下「管理者」）は、初回利用時および本規約の改定時に、画面上で提示される利用規約への同意が必要です。管理者以外のユーザーには、同意確認を求めません。

### 第3条（利用規約への同意）
1. 管理者は、利用規約確認画面で内容を確認し、「確認しました」にチェックを入れたうえで「利用開始する」を選択することにより、本規約に同意したものとみなされます。
2. 同意の記録（同意日時および同意した利用規約のバージョン）は、当該管理者のWordPressユーザー情報としてサイト内に保存されます。
3. 本規約の内容またはバージョンが改定された場合、管理者は改定後の規約への再度の同意が必要となることがあります。

### 第4条（同意時の通知および情報の取扱い）
1. 管理者が本規約に同意した際、当社は同意の事実を把握・記録するため、以下の情報を当社が運営するライセンス管理サーバー（kantanpro.com）へ送信することがあります。
   - サイトURL
   - WordPressの管理者メールアドレス
   - 同意した管理者の表示名およびメールアドレス
   - 本プラグインの名称およびバージョン
   - 同意した利用規約のバージョンおよび同意日時
2. 前項の送信に失敗した場合、当社は当該サイトのメール送信機能（SMTP設定等）を用いて、当社指定の連絡先へ通知を試行することがあります。また、送信に失敗した通知はサイト内に一時的に保留され、後日自動的に再送信が試行されます。
3. 前各項の通知処理の失敗または遅延により、管理者による本プラグインの利用が制限されることはありません。

### 第5条（禁止事項）
利用者は、本プラグインの利用にあたり、以下の行為を行ってはなりません：
1. 法令または公序良俗に違反する行為
2. 犯罪行為に関連する行為
3. 本プラグインの運営を妨害する行為
4. 他の利用者に迷惑をかける行為
5. その他、当社が不適切と判断する行為

### 第6条（本プラグインの提供の停止等）
当社は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本プラグインの全部または一部の提供を停止または中断することができるものとします。
1. 本プラグインにかかるコンピュータシステムの保守点検または更新を行う場合
2. 地震、落雷、火災、停電または天災などの不可抗力により、本プラグインの提供が困難となった場合
3. その他、当社が本プラグインの提供が困難と判断した場合

### 第7条（免責事項）
1. 当社は、本プラグインに関して、利用者と他の利用者または第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。
2. 当社は、本プラグインの利用により生じる損害について一切の責任を負いません。
3. 当社は、本プラグインの利用により生じるデータの損失について一切の責任を負いません。

### 第8条（サービス内容の変更等）
当社は、利用者に通知することなく、本プラグインの内容を変更しまたは本プラグインの提供を中止することができるものとし、これによって利用者に生じた損害について一切の責任を負いません。

### 第9条（利用規約の変更）
当社は、必要と判断した場合には、利用者に通知することなくいつでも本規約を変更することができるものとします。規約の改定後は、管理者に対して再度の同意を求める場合があります。

### 第10条（準拠法・裁判管轄）
1. 本規約の解釈にあたっては、日本法を準拠法とします。
2. 本プラグインに関して紛争が生じた場合には、当社の本店所在地を管轄する裁判所を専属的合意管轄とします。

### 第11条（お問い合わせ）
本規約に関するお問い合わせは、以下のメールアドレスまでお願いいたします。
kantanpro22@gmail.com

以上',
            $plugin
        );
    }

    /**
     * デフォルトの利用規約内容（英語）を取得
     */
    public static function get_default_terms_content_en() {
        $plugin = self::get_plugin_name_for_terms();

        return sprintf(
            '### Article 1 (Application)
These Terms apply to the use of the %1$s plugin (the "Plugin").
"We," "us," or "our" refers to the developer and operator of the Plugin.

### Article 2 (Conditions of Use)
1. The Plugin is intended for use in a WordPress environment.
2. Users must have the appropriate authority to use the Plugin.
3. WordPress administrators who configure or manage the Plugin (each, an "Administrator") must agree to these Terms on first use and whenever these Terms are revised. Users who are not Administrators are not required to complete the agreement dialog.

### Article 3 (Agreement to These Terms)
1. An Administrator is deemed to have agreed to these Terms by reviewing the agreement screen, checking "I have reviewed," and selecting "Start using."
2. A record of agreement (date/time and the version of these Terms agreed to) is stored in that Administrator\'s WordPress user information on the site.
3. If the content or version of these Terms is revised, Administrators may be required to agree again to the revised Terms.

### Article 4 (Notifications and Information Handling upon Agreement)
1. When an Administrator agrees to these Terms, we may send the following information to our license management server operated at kantanpro.com in order to acknowledge and record the agreement:
   - Site URL
   - WordPress administrator email address
   - Display name and email address of the agreeing Administrator
   - Plugin name and version
   - Version of these Terms agreed to and the date/time of agreement
2. If transmission under the preceding paragraph fails, we may attempt to notify a contact address designated by us using the site\'s mail delivery function (such as SMTP settings). Failed notifications may be temporarily retained on the site and automatically retried later.
3. Failure or delay of the notifications described above does not restrict the Administrator\'s use of the Plugin.

### Article 5 (Prohibited Acts)
Users must not engage in any of the following acts when using the Plugin:
1. Acts that violate laws, regulations, or public order and morals
2. Acts related to criminal activity
3. Acts that interfere with the operation of the Plugin
4. Acts that cause inconvenience to other users
5. Any other acts deemed inappropriate by us

### Article 6 (Suspension of the Plugin)
If we determine that any of the following circumstances apply, we may suspend or interrupt all or part of the Plugin without prior notice to users.
1. Maintenance, inspection, or updates of computer systems related to the Plugin
2. Difficulty providing the Plugin due to force majeure such as earthquakes, lightning, fire, power outages, or natural disasters
3. Any other circumstance in which we determine that providing the Plugin is difficult

### Article 7 (Disclaimer)
1. We are not responsible for any transactions, communications, disputes, or other matters arising between users and other users or third parties in connection with the Plugin.
2. We are not responsible for any damages arising from the use of the Plugin.
3. We are not responsible for any loss of data arising from the use of the Plugin.

### Article 8 (Changes to Services)
We may change the contents of the Plugin or discontinue providing the Plugin without notice to users, and we are not responsible for any damages incurred by users as a result.

### Article 9 (Changes to These Terms)
We may change these Terms at any time without notice to users if we determine that such changes are necessary. After a revision, we may require Administrators to agree again.

### Article 10 (Governing Law and Jurisdiction)
1. These Terms are governed by the laws of Japan.
2. If a dispute arises in connection with the Plugin, the court having jurisdiction over our head office location shall have exclusive agreed jurisdiction.

### Article 11 (Contact)
For inquiries regarding these Terms, please contact us at the following email address.
kantanpro22@gmail.com

End',
            $plugin
        );
    }

    /**
     * 利用規約を英語で表示するか判定
     */
    private function should_translate_terms_to_english() {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        return strpos( strtolower( (string) $locale ), 'en' ) === 0;
    }

    /**
     * DB保存済みの初期日本語規約か判定
     */
    private function is_default_japanese_terms_content( $content ) {
        $content = (string) $content;
        return strpos( $content, '### 第1条（適用）' ) !== false
            && strpos( $content, 'kantanpro22@gmail.com' ) !== false
            && (
                strpos( $content, '### 第3条（利用規約への同意）' ) !== false
                || strpos( $content, '### 第3条（禁止事項）' ) !== false
            );
    }

    /**
     * 旧バンドル既定規約（v1.0）か判定
     */
    public static function is_legacy_v1_default_terms_content( $content, $version ) {
        if ( (string) $version !== '1.0' ) {
            return false;
        }

        $content = (string) $content;

        return strpos( $content, '### 第1条（適用）' ) !== false
            && strpos( $content, '### 第3条（禁止事項）' ) !== false
            && strpos( $content, '### 第3条（利用規約への同意）' ) === false
            && strpos( $content, 'kantanpro22@gmail.com' ) !== false;
    }

    /**
     * 表示ロケールに応じた初期規約を取得
     */
    private function get_default_terms_content_for_locale() {
        return $this->should_translate_terms_to_english() ? self::get_default_terms_content_en() : self::get_default_terms_content();
    }

    /**
     * 利用規約タイトルを取得
     */
    private function get_terms_title() {
        if ( $this->should_translate_terms_to_english() ) {
            /* translators: 1: サイト名, 2: プラグイン名 */
            return sprintf( __( '%1$s %2$s Terms of Service', 'kantanpro' ), KANTANPRO_PLUGIN_NAME, KANTANPRO_PLUGIN_VERSION );
        }

        return KANTANPRO_PLUGIN_NAME . KANTANPRO_PLUGIN_VERSION . '利用規約';
    }
    /**
     * 利用規約メニューを追加
     */
    public function add_terms_menu() {
        // メニューは既にKTPWP_Settingsクラスで追加されているため、ここでは何もしない
    }

    /**
     * 管理者通知を表示
     */
    public function display_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 利用規約同意通知をチェック
        $user_id = get_current_user_id();
        $notice = get_transient( 'ktpwp_terms_agreement_notice_' . $user_id );
        
        if ( $notice ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>KantanPro:</strong> ' . esc_html( $notice ) . '</p>';
            echo '</div>';
            
            // 通知を削除
            delete_transient( 'ktpwp_terms_agreement_notice_' . $user_id );
        }
    }

    /**
     * 利用規約ページを作成
     */
    public function create_terms_page() {
        // 開発モード時のみUIを有効化（配布先では非表示/不可）
        if ( ! ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'このページは現在無効化されています。', 'kantanpro' ) . '</p></div>';
            return;
        }

        $this->display_terms_management_page();
    }

    /**
     * 開発者パスワード認証
     */
    private function verify_developer_password() {
        // 廃止：開発モードかつ管理者のみ許可
        return ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE && current_user_can( 'manage_options' ) );
    }

    /**
     * パスワード入力フォームを表示
     */
    private function display_password_form() {
        // 廃止: パスワードフォームの代わりに無効化メッセージのみ表示
        echo '<div class="wrap"><div class="notice notice-info is-dismissible"><p>' . esc_html__( 'このページは現在無効化されています。', 'kantanpro' ) . '</p></div></div>';
    }

    /**
     * 利用規約管理ページを表示
     */
    private function display_terms_management_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // --- 追加: 同意状態リセット処理 ---
        if ( isset($_POST['ktp_terms_reset_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktp_terms_reset_nonce'] ) ), 'ktp_terms_reset') ) {
            $this->reset_user_terms_agreement();
            echo '<div class="notice notice-success is-dismissible"><p>利用規約同意状態をリセットしました（このユーザーのみ）。</p></div>';
        }

        // 認証成功メッセージ
        if ( isset( $_GET['authenticated'] ) && $_GET['authenticated'] === '1' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '認証に成功しました。利用規約の編集が可能です。', 'kantanpro' ) . '</p></div>';
        }

        // ログアウト処理
        if ( isset( $_GET['logout'] ) && $_GET['logout'] === '1' ) {
            $_SESSION['ktp_developer_authenticated'] = false;
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '認証を解除しました。', 'kantanpro' ) . '</p></div>';
        }

        // 利用規約の取得
        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );

        ?>
        <div class="ktp-settings-container">
            <div class="ktp-settings-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?php echo esc_html__( '利用規約編集', 'kantanpro' ); ?></h2>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ktp-terms&logout=1' ) ); ?>" class="button button-secondary">
                        <?php echo esc_html__( '認証解除', 'kantanpro' ); ?>
                    </a>
                </div>
                
                <div class="notice notice-info">
                    <p><?php echo esc_html__( '利用規約の内容を編集できます。変更後は保存ボタンをクリックしてください。', 'kantanpro' ); ?></p>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field( 'ktp_terms_update', 'ktp_terms_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="terms_content"><?php echo esc_html__( '利用規約内容', 'kantanpro' ); ?></label>
                            </th>
                            <td>
                                <textarea name="terms_content" id="terms_content" rows="30" cols="80" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $terms ? $terms->terms_content : '' ); ?></textarea>
                                <p class="description"><?php echo esc_html__( 'Markdown形式で記述できます。', 'kantanpro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="terms_version"><?php echo esc_html__( 'バージョン', 'kantanpro' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="terms_version" id="terms_version" value="<?php echo esc_attr( $terms ? $terms->version : '1.0' ); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( '保存', 'kantanpro' ) ); ?>
                </form>

                <hr style="margin: 30px 0;">

                <h3><?php echo esc_html__( '利用規約の表示', 'kantanpro' ); ?></h3>
                <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px;"><?php echo esc_html( $this->get_terms_title() ); ?></h2>
                    <?php echo $this->format_terms_content( $terms ? $terms->terms_content : '' ); ?>
                </div>
            </div>
        </div>
        <?php
        ?>
        <form method="post" style="margin-top:20px;">
            <?php wp_nonce_field('ktp_terms_reset', 'ktp_terms_reset_nonce'); ?>
            <button type="submit" class="button button-secondary" onclick="return confirm('本当にこのユーザーの同意状態をリセットしますか？')">同意状態リセット（このユーザーのみ）</button>
        </form>
        <?php
    }

    /**
     * 現在のユーザーの利用規約同意状態をリセット（デバッグ用）
     */
    public function reset_user_terms_agreement( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( $user_id ) {
            delete_user_meta( $user_id, 'ktpwp_terms_agreed' );
            delete_user_meta( $user_id, 'ktpwp_terms_version' );
            delete_user_meta( $user_id, 'ktpwp_terms_agreed_plugin_version' );
        }
    }

    /**
     * 利用規約内容をフォーマット
     */
    private function format_terms_content( $content ) {
        // 行ごとに処理
        $lines = explode( "\n", $content );
        $formatted_lines = array();
        $in_list = false;
        $list_type = '';
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            
            if ( empty( $line ) ) {
                if ( $in_list ) {
                    $formatted_lines[] = '</' . $list_type . '>';
                    $in_list = false;
                }
                // 段落の区切りを追加
                $formatted_lines[] = '</p><p>';
                continue;
            }
            
            // 見出しの処理
            if ( preg_match( '/^## (.+)$/', $line, $matches ) ) {
                if ( $in_list ) {
                    $formatted_lines[] = '</' . $list_type . '>';
                    $in_list = false;
                }
                $formatted_lines[] = '<h2>' . esc_html( $matches[1] ) . '</h2>';
                continue;
            }
            
            if ( preg_match( '/^### (.+)$/', $line, $matches ) ) {
                if ( $in_list ) {
                    $formatted_lines[] = '</' . $list_type . '>';
                    $in_list = false;
                }
                $formatted_lines[] = '<h3>' . esc_html( $matches[1] ) . '</h3>';
                continue;
            }
            
            // 番号付きリストの処理
            if ( preg_match( '/^(\d+)\. (.+)$/', $line, $matches ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) {
                        $formatted_lines[] = '</' . $list_type . '>';
                    }
                    $formatted_lines[] = '<ol>';
                    $in_list = true;
                    $list_type = 'ol';
                }
                $formatted_lines[] = '<li>' . esc_html( $matches[2] ) . '</li>';
                continue;
            }
            
            // 箇条書きリストの処理
            if ( preg_match( '/^\- (.+)$/', $line, $matches ) ) {
                if ( ! $in_list || $list_type !== 'ul' ) {
                    if ( $in_list ) {
                        $formatted_lines[] = '</' . $list_type . '>';
                    }
                    $formatted_lines[] = '<ul>';
                    $in_list = true;
                    $list_type = 'ul';
                }
                $formatted_lines[] = '<li>' . esc_html( $matches[1] ) . '</li>';
                continue;
            }
            
            // 通常のテキスト処理
            if ( $in_list ) {
                $formatted_lines[] = '</' . $list_type . '>';
                $in_list = false;
            }
            
            // 太字と斜体の処理
            $line = preg_replace( '/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $line );
            $line = preg_replace( '/\*(.*?)\*/s', '<em>$1</em>', $line );
            
            $formatted_lines[] = esc_html( $line );
        }
        
        // 最後のリストを閉じる
        if ( $in_list ) {
            $formatted_lines[] = '</' . $list_type . '>';
        }
        
        // 結果を結合
        $result = implode( ' ', $formatted_lines );
        
        // 段落タグを適切に処理
        $result = preg_replace( '/<\/p><p>\s*<\/p><p>/', '</p><p>', $result );
        $result = str_replace( '<p></p>', '', $result );
        $result = str_replace( '<p> </p>', '', $result );
        
        // 最初と最後の段落タグを追加
        if ( ! empty( $result ) ) {
            $result = '<p>' . $result . '</p>';
        }
        
        return $result;
    }

    /**
     * 利用規約アクションを処理
     */
    public function handle_terms_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['ktp_terms_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktp_terms_nonce'] ) ), 'ktp_terms_update' ) ) {
            if ( $this->verify_developer_password() ) {
                $this->update_terms();
            }
        }
    }

    /**
     * 利用規約を更新
     */
    private function update_terms() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        $terms_content = sanitize_textarea_field( $_POST['terms_content'] );
        $version = sanitize_text_field( $_POST['terms_version'] );

        // 既存の利用規約を非アクティブにする
        $wpdb->update(
            $table_name,
            array( 'is_active' => 0 ),
            array( 'is_active' => 1 ),
            array( '%d' ),
            array( '%d' )
        );

        // 新しい利用規約を挿入
        $result = $wpdb->insert(
            $table_name,
            array(
                'terms_content' => $terms_content,
                'version' => $version,
                'is_active' => 1
            ),
            array( '%s', '%s', '%d' )
        );

        if ( $result ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '利用規約を更新しました。', 'kantanpro' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '利用規約の更新に失敗しました。', 'kantanpro' ) . '</p></div>';
        }
    }

    /**
     * 利用規約同意を処理
     */
    public function handle_terms_agreement() {
        check_ajax_referer( 'ktpwp_terms_agreement', 'nonce' );

        // 管理者権限チェック（管理者のみ処理）
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '管理者権限が必要です。', 'kantanpro' ) ) );
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id ) {
            // 既に同意済みかチェック（重複防止）
            if ( $this->has_user_agreed_to_terms( $user_id ) ) {
                wp_send_json_success( array( 'message' => __( '既に利用規約に同意済みです。', 'kantanpro' ) ) );
                return;
            }

            // 同意状態を保存
            update_user_meta( $user_id, 'ktpwp_terms_agreed', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'ktpwp_terms_version', $this->get_current_terms_version() );
            update_user_meta( $user_id, 'ktpwp_terms_agreed_plugin_version', $this->get_plugin_version_for_terms_consent() );
            
            // メール送信の重複防止用トランジェントをチェック
            $mail_transient_key = 'ktpwp_terms_mail_sent_' . $user_id;
            if ( ! get_transient( $mail_transient_key ) ) {
                $notify_result = $this->notify_developer( $user_id );
                
                // メール送信完了をマーク（5分間有効）
                set_transient( $mail_transient_key, true, 300 );
                
                $response_message = __( '利用規約に同意しました。', 'kantanpro' );
                
                // デバッグモードでは通知状況を表示
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    if ( ! empty( $notify_result['klm_sent'] ) ) {
                        $response_message .= ' 開発者サーバーへの通知を送信しました。';
                    } elseif ( ! empty( $notify_result['mail_sent'] ) ) {
                        $response_message .= ' 開発者への通知メールを送信しました。';
                    } elseif ( ! empty( $notify_result['queued'] ) ) {
                        $response_message .= ' （開発者への通知は後で再試行されます）';
                    } else {
                        $response_message .= ' （開発者への通知送信に失敗しました）';
                    }
                }
                
                wp_send_json_success( array( 
                    'message' => $response_message,
                    'klm_sent' => ! empty( $notify_result['klm_sent'] ),
                    'mail_sent' => ! empty( $notify_result['mail_sent'] ),
                    'queued' => ! empty( $notify_result['queued'] ),
                ) );
            } else {
                // メール送信済みの場合
                wp_send_json_success( array( 
                    'message' => __( '利用規約に同意しました。', 'kantanpro' ),
                    'mail_sent' => true,
                    'klm_sent' => true,
                ) );
            }
        } else {
            wp_send_json_error( array( 'message' => __( 'ユーザー認証に失敗しました。', 'kantanpro' ) ) );
        }
    }

    /**
     * 開発者へ通知（KLM API → wp_mail → 保留キュー）
     *
     * @param int $user_id User ID.
     * @return array{klm_sent:bool,mail_sent:bool,queued:bool}
     */
    private function notify_developer( $user_id ) {
        $result = array(
            'klm_sent'  => false,
            'mail_sent' => false,
            'queued'    => false,
        );

        // WordPress.org 配布版では、利用者のメールアドレス・表示名・サイト URL を
        // 開発元へ送信しない（WordPress.org プラグインガイドライン第 7 項）。
        if ( function_exists( 'ktpwp_uses_self_hosted_updates' ) && ! ktpwp_uses_self_hosted_updates() ) {
            return $result;
        }

        if ( $this->notify_via_klm_api( $user_id ) ) {
            $result['klm_sent'] = true;
            return $result;
        }

        $mail_sent = $this->send_developer_notification( $user_id );
        if ( $mail_sent ) {
            $result['mail_sent'] = true;
            return $result;
        }

        $this->queue_pending_notification( $user_id );
        $result['queued'] = true;
        return $result;
    }

    /**
     * KLM API へ利用規約同意を送信
     *
     * @param int $user_id User ID.
     * @return bool
     */
    private function notify_via_klm_api( $user_id ) {
        $payload = $this->build_terms_notification_payload( $user_id );
        if ( ! $payload ) {
            return false;
        }

        $body_string = http_build_query( $payload, '', '&', PHP_QUERY_RFC3986 );
        $plugin_version = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : 'unknown';

        $response = wp_remote_post(
            $this->get_klm_terms_api_url(),
            array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'User-Agent'   => 'KantanPro/' . $plugin_version,
                ),
                'body'      => $body_string,
                'timeout'   => 20,
                'sslverify' => true,
            )
        );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Terms Agreement: KLM API error - ' . $response->get_error_message() );
            }
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 200 && $status_code < 300 && is_array( $data ) && ! empty( $data['success'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Terms Agreement: KLM API success (User ID: ' . $user_id . ')' );
            }
            return true;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Terms Agreement: KLM API failed (status=' . $status_code . ', body=' . $body . ')' );
        }

        return false;
    }

    /**
     * 通知ペイロードを生成
     *
     * @param int $user_id User ID.
     * @return array<string, string>|false
     */
    private function build_terms_notification_payload( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return array(
            'site_url'       => home_url(),
            'admin_email'    => (string) get_option( 'admin_email' ),
            'user_email'     => (string) $user->user_email,
            'display_name'   => (string) $user->display_name,
            'plugin_slug'    => $this->get_plugin_slug_for_klm(),
            'plugin_version' => defined( 'KANTANPRO_PLUGIN_VERSION' ) ? (string) KANTANPRO_PLUGIN_VERSION : '',
            'terms_version'  => (string) $this->get_current_terms_version(),
            'agreed_at'      => (string) get_user_meta( $user_id, 'ktpwp_terms_agreed', true ),
        );
    }

    /**
     * 保留通知をキューに追加
     *
     * @param int $user_id User ID.
     */
    private function queue_pending_notification( $user_id ) {
        $payload = $this->build_terms_notification_payload( $user_id );
        if ( ! $payload ) {
            return;
        }

        $queue = get_option( $this->get_pending_option_name(), array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }

        $key = md5( $payload['site_url'] . '|' . $payload['user_email'] . '|' . $payload['terms_version'] );
        $queue[ $key ] = array(
            'user_id'    => (int) $user_id,
            'payload'    => $payload,
            'created_at' => current_time( 'mysql' ),
            'attempts'   => isset( $queue[ $key ]['attempts'] ) ? (int) $queue[ $key ]['attempts'] + 1 : 1,
        );

        update_option( $this->get_pending_option_name(), $queue, false );
        $this->maybe_schedule_terms_retry_cron();
    }

    /**
     * 保留通知の再送
     */
    public function flush_pending_notifications() {
        // WordPress.org 配布版では開発元への通知自体を行わない。
        if ( function_exists( 'ktpwp_uses_self_hosted_updates' ) && ! ktpwp_uses_self_hosted_updates() ) {
            return;
        }

        $queue = get_option( $this->get_pending_option_name(), array() );
        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return;
        }

        $remaining = array();
        foreach ( $queue as $key => $item ) {
            $user_id = isset( $item['user_id'] ) ? (int) $item['user_id'] : 0;
            if ( $user_id <= 0 ) {
                continue;
            }

            if ( $this->notify_via_klm_api( $user_id ) ) {
                continue;
            }

            if ( $this->send_developer_notification( $user_id ) ) {
                continue;
            }

            $item['attempts'] = isset( $item['attempts'] ) ? (int) $item['attempts'] + 1 : 1;
            if ( $item['attempts'] <= 30 ) {
                $remaining[ $key ] = $item;
            }
        }

        if ( empty( $remaining ) ) {
            delete_option( $this->get_pending_option_name() );
            wp_clear_scheduled_hook( 'ktpwp_flush_pending_terms_notifications' );
        } else {
            update_option( $this->get_pending_option_name(), $remaining, false );
        }
    }

    /**
     * 保留通知の Cron をスケジュール
     */
    public function maybe_schedule_terms_retry_cron() {
        $queue = get_option( $this->get_pending_option_name(), array() );
        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return;
        }

        if ( ! wp_next_scheduled( 'ktpwp_flush_pending_terms_notifications' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ktpwp_flush_pending_terms_notifications' );
        }
    }

    /**
     * 開発者にメール通知を送信（フォールバック）
     */
    private function send_developer_notification( $user_id ) {
        // 重複送信防止のためのログ出力
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Terms Agreement: メール送信処理開始 (User ID: ' . $user_id . ')' );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Terms Agreement: ユーザー情報の取得に失敗しました (User ID: ' . $user_id . ')' );
            }
            return false;
        }

        $to = 'kantanpro22@gmail.com';
        $subject = 'KantanPro利用規約同意通知';
        $message = sprintf(
            "KantanProプラグインの利用規約に新しいユーザーが同意しました。\n\n" .
            "ユーザー情報:\n" .
            "表示名: %s\n" .
            "メールアドレス: %s\n" .
            "ユーザーID: %d\n" .
            "同意日時: %s\n" .
            "利用規約バージョン: %s\n" .
            "サイトURL: %s",
            $user->display_name,
            $user->user_email,
            $user_id,
            current_time( 'mysql' ),
            $this->get_current_terms_version(),
            home_url()
        );

        // SMTP設定を取得
        $smtp_settings = get_option( 'ktp_smtp_settings', array() );
        $from_email = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : get_option( 'admin_email' );
        $from_name = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : get_bloginfo( 'name' );

        // ヘッダーを設定
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( ! empty( $from_email ) ) {
            if ( ! empty( $from_name ) ) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            } else {
                $headers[] = 'From: ' . $from_email;
            }
        }

        // メール送信を実行
        $sent = wp_mail( $to, $subject, $message, $headers );

        // 詳細なログ出力
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( $sent ) {
                error_log( 'KTPWP Terms Agreement: 開発者通知メールを送信しました (User: ' . $user->display_name . ', Email: ' . $user->user_email . ', To: ' . $to . ')' );
            } else {
                // PHPMailerのエラー情報を取得
                global $phpmailer;
                $error_message = '';
                if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
                    $error_message = $phpmailer->ErrorInfo;
                }
                error_log( 'KTPWP Terms Agreement: 開発者通知メールの送信に失敗しました (User: ' . $user->display_name . ', Email: ' . $user->user_email . ', To: ' . $to . ', Error: ' . $error_message . ')' );
            }
        }

        // メール送信が失敗した場合のフォールバック処理
        if ( ! $sent ) {
            // 管理者に通知（WordPressの管理画面通知として）
            $admin_notice = sprintf(
                'KantanPro利用規約同意通知: %s（%s）が利用規約に同意しましたが、開発者への通知メール送信に失敗しました。',
                $user->display_name,
                $user->user_email
            );
            
            // 管理者通知を一時的に保存
            set_transient( 'ktpwp_terms_agreement_notice_' . $user_id, $admin_notice, DAY_IN_SECONDS );
            
            // 管理者にメール通知を試行（別の宛先として）
            $admin_email = get_option( 'admin_email' );
            if ( $admin_email && $admin_email !== $to ) {
                $admin_subject = '[' . get_bloginfo( 'name' ) . '] KantanPro利用規約同意通知（メール送信失敗）';
                $admin_message = sprintf(
                    "KantanProプラグインの利用規約に新しいユーザーが同意しました。\n\n" .
                    "※ 開発者への通知メール送信に失敗したため、管理者宛に送信しています。\n\n" .
                    "ユーザー情報:\n" .
                    "表示名: %s\n" .
                    "メールアドレス: %s\n" .
                    "ユーザーID: %d\n" .
                    "同意日時: %s\n" .
                    "利用規約バージョン: %s\n" .
                    "サイトURL: %s",
                    $user->display_name,
                    $user->user_email,
                    $user_id,
                    current_time( 'mysql' ),
                    $this->get_current_terms_version(),
                    home_url()
                );
                
                wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
            }
        }

        return $sent;
    }

    /**
     * 現在の利用規約バージョンを取得
     */
    private function get_current_terms_version() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';
        
        $terms = $wpdb->get_row( "SELECT version FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
        return $terms ? $terms->version : '1.0';
    }

    /**
     * 利用規約の再同意が必要なプラグインバージョン（この版のみ一度限り）
     */
    public static function get_required_terms_reconsent_plugin_version() {
        return '1.2.91';
    }

    /**
     * 同意時に記録するプラグインバージョン
     */
    private function get_plugin_version_for_terms_consent() {
        return defined( 'KANTANPRO_PLUGIN_VERSION' ) ? (string) KANTANPRO_PLUGIN_VERSION : self::get_required_terms_reconsent_plugin_version();
    }

    /**
     * v1.2.91 以降の再同意要件を満たしているか
     */
    private function has_completed_required_plugin_reconsent( $user_id ) {
        $required = self::get_required_terms_reconsent_plugin_version();
        $agreed_plugin_version = (string) get_user_meta( $user_id, 'ktpwp_terms_agreed_plugin_version', true );

        if ( $agreed_plugin_version === '' ) {
            return false;
        }

        return version_compare( $agreed_plugin_version, $required, '>=' );
    }

    /**
     * 利用規約に同意済みかチェック
     */
    public function has_user_agreed_to_terms( $user_id = null ) {
        // 管理者権限チェック（管理者のみチェック）
        if ( ! current_user_can( 'manage_options' ) ) {
            return true; // 管理者以外は常に同意済みとみなす
        }

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $agreed_time = get_user_meta( $user_id, 'ktpwp_terms_agreed', true );
        $agreed_version = get_user_meta( $user_id, 'ktpwp_terms_version', true );
        $current_version = $this->get_current_terms_version();

        if ( empty( $agreed_time ) || $agreed_version !== $current_version ) {
            return false;
        }

        return $this->has_completed_required_plugin_reconsent( $user_id );
    }

    /**
     * 利用規約を取得
     */
    public function get_terms_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';
        
        // テーブルの存在確認
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
        
        if ( ! $table_exists ) {
            // テーブルが存在しない場合は自動修復を試行
            if ( function_exists( 'ktpwp_ensure_terms_table' ) ) {
                ktpwp_ensure_terms_table();
            }
            
            // 修復後に再取得を試行
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
            if ( ! $table_exists ) {
                // それでも失敗した場合はデフォルトの利用規約を返す
                return $this->get_default_terms_content_for_locale();
            }
        }
        
        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
        
        if ( ! $terms ) {
            // データが存在しない場合はデフォルトの利用規約を返す
            return $this->get_default_terms_content_for_locale();
        }
        
        if ( empty( trim( $terms->terms_content ) ) ) {
            // 内容が空の場合はデフォルトの利用規約を返す
            return $this->get_default_terms_content_for_locale();
        }

        if ( $this->should_translate_terms_to_english() && $this->is_default_japanese_terms_content( $terms->terms_content ) ) {
            return self::get_default_terms_content_en();
        }
        
        return $terms->terms_content;
    }

    /**
     * 公開利用規約ページの処理
     */
    public function handle_public_terms_view() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ktp-terms' && isset( $_GET['view'] ) && $_GET['view'] === 'public' ) {
            $this->display_public_terms_page();
            exit;
        }
    }

    /**
     * 公開利用規約ページを表示
     */
    private function display_public_terms_page() {
        $terms_content = $this->get_terms_content();
        $terms_lang    = $this->should_translate_terms_to_english() ? 'en' : 'ja';
        $terms_title   = $this->get_terms_title();
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr( $terms_lang ); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( $terms_title ); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f9f9f9;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #2c3e50;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 10px;
                }
                h2 {
                    color: #34495e;
                    margin-top: 30px;
                }
                h3 {
                    color: #2c3e50;
                    margin-top: 25px;
                }
                p {
                    margin-bottom: 15px;
                }
                ol, ul {
                    margin-bottom: 15px;
                    padding-left: 20px;
                }
                li {
                    margin-bottom: 5px;
                }
                strong {
                    color: #2c3e50;
                }
                .back-link {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    text-align: center;
                }
                .back-link a {
                    color: #3498db;
                    text-decoration: none;
                }
                .back-link a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php echo esc_html( $terms_title ); ?></h1>
                <?php echo $this->format_terms_content( $terms_content ); ?>
                
                <div class="back-link">
                    <a href="javascript:window.close();"><?php echo esc_html__( 'このウィンドウを閉じる', 'kantanpro' ); ?></a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * フッターに利用規約リンクを追加
     */
    public function add_terms_footer_link() {
        // フロントエンドでのみ表示
        if ( is_admin() ) {
            return;
        }

        // KantanProのショートコードが使用されているページでのみ表示
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ) {
            return;
        }

        $terms_url = admin_url( 'admin.php?page=ktp-terms&view=public' );
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // フッターのバージョン表示要素をクラスで特定
            var footerText = document.querySelector('.ktp-footer-text');
            if (!footerText) {
                return;
            }

            // 既にリンクがある場合は何もしない
            if (footerText.querySelector('a[href*="ktp-terms"]')) {
                return;
            }

            // 利用規約リンクを作成
            var termsLink = document.createElement('a');
            termsLink.href = '<?php echo esc_url( $terms_url ); ?>';
            termsLink.target = '_blank';
            termsLink.textContent = '<?php echo esc_js( __( '利用規約', 'kantanpro' ) ); ?>';
            termsLink.style.marginLeft = '10px';
            termsLink.style.color = '#666';
            termsLink.style.textDecoration = 'none';
            termsLink.style.fontSize = '12px';

            // スペースを挟んで追加
            footerText.appendChild(document.createTextNode(' '));
            footerText.appendChild(termsLink);
        });
        </script>
        <?php
    }

    /**
     * 利用規約同意ダイアログを表示
     */
    public function display_terms_dialog() {
        // 管理者権限チェック（管理者のみに表示）
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( $this->has_user_agreed_to_terms() ) {
            return;
        }

        $terms_content = $this->get_terms_content();
        if ( empty( $terms_content ) ) {
            return;
        }

        ?>
        <div id="ktpwp-terms-dialog" style="display: none;">
            <div class="ktpwp-terms-overlay"></div>
            <div class="ktpwp-terms-modal">
                <div class="ktpwp-terms-header">
                    <h2><?php echo esc_html( /* translators: 1: サイト名, 2: プラグイン名 */ sprintf( __( '%1$s %2$s Terms of Service', 'kantanpro' ), KANTANPRO_PLUGIN_NAME, KANTANPRO_PLUGIN_VERSION ) ); ?></h2>
                </div>
                <div class="ktpwp-terms-content">
                    <?php echo $this->format_terms_content( $terms_content ); ?>
                </div>
                <div class="ktpwp-terms-footer">
                    <div class="ktpwp-terms-checkbox-container">
                        <input type="checkbox" id="ktpwp-terms-checkbox" />
                        <label for="ktpwp-terms-checkbox"><?php echo esc_html__( '確認しました', 'kantanpro' ); ?></label>
                    </div>
                    <button type="button" id="ktpwp-start-usage" class="ktpwp-start-btn" disabled>
                        <?php echo esc_html__( '利用開始する', 'kantanpro' ); ?>
                    </button>
                    <div class="ktpwp-home-link">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'ホームへ', 'kantanpro' ); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .ktpwp-terms-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }
        .ktpwp-terms-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0 50px;
            border-radius: 8px;
            width: 600px;
            max-height: 80vh;
            z-index: 10000;
            display: flex;
            flex-direction: column;
        }
        .ktpwp-terms-header {
            border-bottom: 1px solid #ddd;
            padding: 20px 20px 10px 20px;
            margin-bottom: 0;
            position: relative;
            text-align: center;
        }
        .ktpwp-terms-header h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .ktpwp-terms-content {
            padding: 20px;
            height: 500px;
            overflow-y: auto;
            line-height: 1.6;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
            background-color: #f8f8f8;
        }
        .ktpwp-terms-footer {
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .ktpwp-terms-checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ktpwp-terms-checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .ktpwp-terms-checkbox-container label {
            font-size: 14px;
            color: #333;
            cursor: pointer;
        }
        .ktpwp-start-btn {
            background-color: #0073aa;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .ktpwp-start-btn:hover:not(:disabled) {
            background-color: #005a87;
        }
        .ktpwp-start-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .ktpwp-home-link {
            margin-top: 10px;
        }
        .ktpwp-home-link a {
            color: #0073aa;
            text-decoration: none;
            font-size: 14px;
        }
        .ktpwp-home-link a:hover {
            text-decoration: underline;
        }

        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-terms-dialog').show();
            
            // チェックボックスの状態に応じてボタンの有効/無効を切り替え
            $('#ktpwp-terms-checkbox').change(function() {
                $('#ktpwp-start-usage').prop('disabled', !this.checked);
            });
            
            // 利用開始するボタンクリック
            $('#ktpwp-start-usage').click(function() {
                if (!$('#ktpwp-terms-checkbox').is(':checked')) {
                    return;
                }
                
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: {
                        action: 'ktpwp_terms_agreement',
                        nonce: '<?php echo wp_create_nonce( 'ktpwp_terms_agreement' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ktpwp-terms-dialog').hide();
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js( __( 'エラーが発生しました。', 'kantanpro' ) ); ?>');
                    }
                });
            });
            

        });
        </script>
        <?php
    }
}

// シングルトンインスタンスの初期化
KTPWP_Terms_Of_Service::get_instance(); 