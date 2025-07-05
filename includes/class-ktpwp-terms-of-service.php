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

    /**
     * 開発者パスワード（暗号化済み）
     */
    const DEVELOPER_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // 8bee1222

    /**
     * パスワードハッシュを生成（デバッグ用）
     */
    public static function generate_password_hash( $password ) {
        return wp_hash_password( $password );
    }

    /**
     * テーブル名
     */
    private $table_name;

    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // セッション開始
        if ( ! session_id() ) {
            session_start();
        }

        // フックの設定
        add_action( 'admin_init', array( $this, 'handle_terms_actions' ) );
        add_action( 'wp_footer', array( $this, 'add_terms_footer_link' ) );
        add_action( 'admin_footer', array( $this, 'add_terms_footer_link' ) );
        add_action( 'wp_ajax_ktpwp_agree_terms', array( $this, 'handle_terms_agreement' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_agree_terms', array( $this, 'handle_terms_agreement' ) );
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
     * デフォルトの利用規約を挿入
     */
    private static function insert_default_terms() {
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
                    'version' => '1.0',
                    'is_active' => 1
                ),
                array( '%s', '%s', '%d' )
            );
        }
    }

    /**
     * デフォルトの利用規約内容を取得
     */
    private static function get_default_terms_content() {
        return '## KantanPro利用規約

### 第1条（適用）
本規約は、KantanProプラグイン（以下「本プラグイン」）の利用に関して適用されます。

### 第2条（利用条件）
1. 本プラグインは、WordPress環境での利用を前提としています。
2. 利用者は、本プラグインの利用にあたり、適切な権限を有する必要があります。

### 第3条（禁止事項）
利用者は、本プラグインの利用にあたり、以下の行為を行ってはなりません：
1. 法令または公序良俗に違反する行為
2. 犯罪行為に関連する行為
3. 本プラグインの運営を妨害する行為
4. 他の利用者に迷惑をかける行為
5. その他、当社が不適切と判断する行為

### 第4条（本プラグインの提供の停止等）
当社は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本プラグインの全部または一部の提供を停止または中断することができるものとします。
1. 本プラグインにかかるコンピュータシステムの保守点検または更新を行う場合
2. 地震、落雷、火災、停電または天災などの不可抗力により、本プラグインの提供が困難となった場合
3. その他、当社が本プラグインの提供が困難と判断した場合

### 第5条（免責事項）
1. 当社は、本プラグインに関して、利用者と他の利用者または第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。
2. 当社は、本プラグインの利用により生じる損害について一切の責任を負いません。
3. 当社は、本プラグインの利用により生じるデータの損失について一切の責任を負いません。

### 第6条（サービス内容の変更等）
当社は、利用者に通知することなく、本プラグインの内容を変更しまたは本プラグインの提供を中止することができるものとし、これによって利用者に生じた損害について一切の責任を負いません。

### 第7条（利用規約の変更）
当社は、必要と判断した場合には、利用者に通知することなくいつでも本規約を変更することができるものとします。

### 第8条（準拠法・裁判管轄）
1. 本規約の解釈にあたっては、日本法を準拠法とします。
2. 本プラグインに関して紛争が生じた場合には、当社の本店所在地を管轄する裁判所を専属的合意管轄とします。

### 第9条（お問い合わせ）
本規約に関するお問い合わせは、以下のメールアドレスまでお願いいたします。
kantanpro22@gmail.com

以上';
    }

    /**
     * 利用規約メニューを追加
     */
    public function add_terms_menu() {
        // メニューは既にKTP_Settingsクラスで追加されているため、ここでは何もしない
    }

    /**
     * 利用規約ページを作成
     */
    public function create_terms_page() {
        // 開発者パスワード認証
        if ( ! $this->verify_developer_password() ) {
            $this->display_password_form();
            return;
        }

        $this->display_terms_management_page();
    }

    /**
     * 開発者パスワード認証
     */
    private function verify_developer_password() {
        // パスワード認証処理
        if ( isset( $_POST['developer_password'] ) ) {
            $password = sanitize_text_field( $_POST['developer_password'] );
            // 直接比較（簡易認証）
            if ( $password === '8bee1222' ) {
                $_SESSION['ktp_developer_authenticated'] = true;
                // 認証成功後、同じページにリダイレクト
                wp_redirect( admin_url( 'admin.php?page=ktp-terms&authenticated=1' ) );
                exit;
            } else {
                // 認証失敗
                $_SESSION['ktp_developer_authenticated'] = false;
            }
        }

        // セッションから認証状態を確認
        return isset( $_SESSION['ktp_developer_authenticated'] ) && $_SESSION['ktp_developer_authenticated'] === true;
    }

    /**
     * パスワード入力フォームを表示
     */
    private function display_password_form() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( '利用規約管理', 'ktpwp' ); ?></h1>
            <div class="notice notice-warning">
                <p><?php echo esc_html__( '開発者パスワードが必要です。', 'ktpwp' ); ?></p>
            </div>
            <?php
            // 認証失敗メッセージ
            if ( isset( $_POST['developer_password'] ) && sanitize_text_field( $_POST['developer_password'] ) !== '8bee1222' ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'パスワードが正しくありません。', 'ktpwp' ) . '</p></div>';
            }
            ?>
            <form method="post" style="max-width: 400px;">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="developer_password"><?php echo esc_html__( '開発者パスワード', 'ktpwp' ); ?></label>
                        </th>
                        <td>
                            <input type="password" name="developer_password" id="developer_password" class="regular-text" required />
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( '認証', 'ktpwp' ) ); ?>
            </form>
            
            <?php
            // デバッグ用：パスワード情報を表示
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                echo '<div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc;">';
                echo '<h3>デバッグ情報</h3>';
                echo '<p><strong>開発者パスワード:</strong> 8bee1222</p>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * 利用規約管理ページを表示
     */
    private function display_terms_management_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // 認証成功メッセージ
        if ( isset( $_GET['authenticated'] ) && $_GET['authenticated'] === '1' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '認証に成功しました。利用規約の編集が可能です。', 'ktpwp' ) . '</p></div>';
        }

        // ログアウト処理
        if ( isset( $_GET['logout'] ) && $_GET['logout'] === '1' ) {
            $_SESSION['ktp_developer_authenticated'] = false;
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '認証を解除しました。', 'ktpwp' ) . '</p></div>';
        }

        // 利用規約の取得
        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );

        ?>
        <div class="ktp-settings-container">
            <div class="ktp-settings-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?php echo esc_html__( '利用規約編集', 'ktpwp' ); ?></h2>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ktp-terms&logout=1' ) ); ?>" class="button button-secondary">
                        <?php echo esc_html__( '認証解除', 'ktpwp' ); ?>
                    </a>
                </div>
                
                <div class="notice notice-info">
                    <p><?php echo esc_html__( '利用規約の内容を編集できます。変更後は保存ボタンをクリックしてください。', 'ktpwp' ); ?></p>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field( 'ktp_terms_update', 'ktp_terms_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="terms_content"><?php echo esc_html__( '利用規約内容', 'ktpwp' ); ?></label>
                            </th>
                            <td>
                                <textarea name="terms_content" id="terms_content" rows="30" cols="80" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $terms ? $terms->terms_content : '' ); ?></textarea>
                                <p class="description"><?php echo esc_html__( 'Markdown形式で記述できます。', 'ktpwp' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="terms_version"><?php echo esc_html__( 'バージョン', 'ktpwp' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="terms_version" id="terms_version" value="<?php echo esc_attr( $terms ? $terms->version : '1.0' ); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( '保存', 'ktpwp' ) ); ?>
                </form>

                <hr style="margin: 30px 0;">

                <h3><?php echo esc_html__( '利用規約の表示', 'ktpwp' ); ?></h3>
                <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php echo $this->format_terms_content( $terms ? $terms->terms_content : '' ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 利用規約内容をフォーマット
     */
    private function format_terms_content( $content ) {
        // Markdown風の変換
        $content = preg_replace( '/^## (.*$)/m', '<h2>$1</h2>', $content );
        $content = preg_replace( '/^### (.*$)/m', '<h3>$1</h3>', $content );
        $content = preg_replace( '/^1\. (.*$)/m', '<ol><li>$1</li></ol>', $content );
        $content = preg_replace( '/^(\d+)\. (.*$)/m', '<li>$2</li>', $content );
        $content = preg_replace( '/^\- (.*$)/m', '<ul><li>$1</li></ul>', $content );
        $content = preg_replace( '/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content );
        $content = preg_replace( '/\*(.*?)\*/s', '<em>$1</em>', $content );
        
        // 段落の処理
        $content = '<p>' . str_replace( "\n\n", '</p><p>', $content ) . '</p>';
        $content = str_replace( '<p></p>', '', $content );
        
        return $content;
    }

    /**
     * 利用規約アクションを処理
     */
    public function handle_terms_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['ktp_terms_nonce'] ) && wp_verify_nonce( $_POST['ktp_terms_nonce'], 'ktp_terms_update' ) ) {
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
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '利用規約を更新しました。', 'ktpwp' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '利用規約の更新に失敗しました。', 'ktpwp' ) . '</p></div>';
        }
    }

    /**
     * 利用規約同意を処理
     */
    public function handle_terms_agreement() {
        check_ajax_referer( 'ktpwp_terms_agreement', 'nonce' );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            update_user_meta( $user_id, 'ktpwp_terms_agreed', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'ktpwp_terms_version', $this->get_current_terms_version() );
            
            // 開発者にメール通知
            $this->send_developer_notification( $user_id );
            
            wp_send_json_success( array( 'message' => __( '利用規約に同意しました。', 'ktpwp' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'ユーザー認証に失敗しました。', 'ktpwp' ) ) );
        }
    }

    /**
     * 開発者にメール通知を送信
     */
    private function send_developer_notification( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
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
            "利用規約バージョン: %s",
            $user->display_name,
            $user->user_email,
            $user_id,
            current_time( 'mysql' ),
            $this->get_current_terms_version()
        );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

        return wp_mail( $to, $subject, $message, $headers );
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
     * 利用規約に同意済みかチェック
     */
    public function has_user_agreed_to_terms( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $agreed_time = get_user_meta( $user_id, 'ktpwp_terms_agreed', true );
        $agreed_version = get_user_meta( $user_id, 'ktpwp_terms_version', true );
        $current_version = $this->get_current_terms_version();

        return ! empty( $agreed_time ) && $agreed_version === $current_version;
    }

    /**
     * 利用規約を取得
     */
    public function get_terms_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';
        
        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
        return $terms ? $terms->terms_content : '';
    }

    /**
     * フッターに利用規約リンクを追加
     */
    public function add_terms_footer_link() {
        // 管理画面でのみ表示
        if ( ! is_admin() ) {
            return;
        }

        // KantanProの管理画面でのみ表示
        $current_screen = get_current_screen();
        if ( ! $current_screen || strpos( $current_screen->id, 'ktp' ) === false ) {
            return;
        }

        $terms_url = admin_url( 'admin.php?page=ktp-terms' );
        ?>
        <div style="text-align: center; margin: 20px 0; font-size: 12px; color: #666;">
            <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank"><?php echo esc_html__( '利用規約', 'ktpwp' ); ?></a>
        </div>
        <?php
    }

    /**
     * 利用規約同意ダイアログを表示
     */
    public function display_terms_dialog() {
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
                    <h2><?php echo esc_html__( '利用規約', 'ktpwp' ); ?></h2>
                </div>
                <div class="ktpwp-terms-content">
                    <?php echo $this->format_terms_content( $terms_content ); ?>
                </div>
                <div class="ktpwp-terms-footer">
                    <button type="button" id="ktpwp-agree-terms" class="button button-primary">
                        <?php echo esc_html__( '同意する', 'ktpwp' ); ?>
                    </button>
                    <button type="button" id="ktpwp-decline-terms" class="button">
                        <?php echo esc_html__( '同意しない', 'ktpwp' ); ?>
                    </button>
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
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 10000;
        }
        .ktpwp-terms-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .ktpwp-terms-content {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .ktpwp-terms-footer {
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .ktpwp-terms-footer button {
            margin: 0 10px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-terms-dialog').show();
            
            $('#ktpwp-agree-terms').click(function() {
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: {
                        action: 'ktpwp_agree_terms',
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
                        alert('<?php echo esc_js( __( 'エラーが発生しました。', 'ktpwp' ) ); ?>');
                    }
                });
            });
            
            $('#ktpwp-decline-terms').click(function() {
                if (confirm('<?php echo esc_js( __( '利用規約に同意しない場合、プラグインを利用できません。本当に同意しませんか？', 'ktpwp' ) ); ?>')) {
                    window.location.href = '<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>';
                }
            });
        });
        </script>
        <?php
    }
}

// クラスのインスタンス化
new KTPWP_Terms_Of_Service(); 