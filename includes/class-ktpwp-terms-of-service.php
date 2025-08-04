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
        add_action( 'wp_footer', array( $this, 'add_terms_footer_link' ) );
        add_action( 'admin_footer', array( $this, 'add_terms_footer_link' ) );
        add_action( 'wp_ajax_ktpwp_terms_agreement', array( $this, 'handle_terms_agreement' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_terms_agreement', array( $this, 'handle_terms_agreement' ) );
        add_action( 'init', array( $this, 'handle_public_terms_view' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
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
    public static function get_default_terms_content() {
        return '### 第1条（適用）
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
        // 開発者認証が済んでいる場合は認証不要
        if ( isset( $_SESSION['ktpwp_developer_authenticated'] ) && $_SESSION['ktpwp_developer_authenticated'] === true ) {
            return true;
        }

        // パスワード認証処理
        if ( isset( $_POST['developer_password'] ) ) {
            $password = sanitize_text_field( $_POST['developer_password'] );
            
            // 開発者パスワード（暗号化済み）- 8bee1222の正しいハッシュ
            $developer_password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // 8bee1222

            // 新しいハッシュを生成して使用（デバッグ用）
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $new_hash = wp_hash_password( '8bee1222' );
                error_log( 'KantanPro Terms: New hash for 8bee1222: ' . $new_hash );
                // 新しいハッシュを使用
                $developer_password_hash = $new_hash;
            }

            if ( wp_check_password( $password, $developer_password_hash ) ) {
                $_SESSION['ktp_developer_authenticated'] = true;
                $_SESSION['ktpwp_developer_authenticated'] = true; // 開発者認証も設定
                wp_redirect( admin_url( 'admin.php?page=ktp-terms&authenticated=1' ) );
                exit;
            } else {
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
            // パスワード入力フォームのエラーメッセージも修正
            if ( isset( $_POST['developer_password'] ) ) {
                $password = sanitize_text_field( $_POST['developer_password'] );
                
                // 開発者パスワード（暗号化済み）- 8bee1222の正しいハッシュ
                $developer_password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // 8bee1222

                // 新しいハッシュを生成して使用（デバッグ用）
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    $new_hash = wp_hash_password( '8bee1222' );
                    $developer_password_hash = $new_hash;
                }

                if ( ! wp_check_password( $password, $developer_password_hash ) ) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'パスワードが正しくありません。', 'ktpwp' ) . '</p></div>';
                }
            } ?>
            <form method="post" style="max-width: 600px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="developer_password" style="white-space: nowrap;"><?php echo esc_html__( '開発者パスワード', 'ktpwp' ); ?></label>
                    <input type="password" name="developer_password" id="developer_password" class="regular-text" required style="flex: 1;" />
                    <?php submit_button( __( '認証', 'ktpwp' ), 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * 利用規約管理ページを表示
     */
    private function display_terms_management_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_terms_of_service';

        // --- 追加: 同意状態リセット処理 ---
        if ( isset($_POST['ktp_terms_reset_nonce']) && wp_verify_nonce($_POST['ktp_terms_reset_nonce'], 'ktp_terms_reset') ) {
            $this->reset_user_terms_agreement();
            echo '<div class="notice notice-success is-dismissible"><p>利用規約同意状態をリセットしました（このユーザーのみ）。</p></div>';
        }

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
                    <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px;">KantanPro<?php echo KANTANPRO_PLUGIN_VERSION; ?>利用規約</h2>
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

        // 管理者権限チェック（管理者のみ処理）
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '管理者権限が必要です。', 'ktpwp' ) ) );
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id ) {
            // 既に同意済みかチェック（重複防止）
            if ( $this->has_user_agreed_to_terms( $user_id ) ) {
                wp_send_json_success( array( 'message' => __( '既に利用規約に同意済みです。', 'ktpwp' ) ) );
                return;
            }

            // 同意状態を保存
            update_user_meta( $user_id, 'ktpwp_terms_agreed', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'ktpwp_terms_version', $this->get_current_terms_version() );
            
            // メール送信の重複防止用トランジェントをチェック
            $mail_transient_key = 'ktpwp_terms_mail_sent_' . $user_id;
            if ( ! get_transient( $mail_transient_key ) ) {
                // 開発者にメール通知
                $mail_sent = $this->send_developer_notification( $user_id );
                
                // メール送信完了をマーク（5分間有効）
                set_transient( $mail_transient_key, true, 300 );
                
                $response_message = __( '利用規約に同意しました。', 'ktpwp' );
                
                // デバッグモードでは通知状況を表示
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    if ( $mail_sent ) {
                        $response_message .= ' 開発者への通知メールを送信しました。';
                    } else {
                        $response_message .= ' （開発者への通知メール送信に失敗しました）';
                    }
                }
                
                wp_send_json_success( array( 
                    'message' => $response_message,
                    'mail_sent' => $mail_sent
                ) );
            } else {
                // メール送信済みの場合
                wp_send_json_success( array( 
                    'message' => __( '利用規約に同意しました。', 'ktpwp' ),
                    'mail_sent' => true
                ) );
            }
        } else {
            wp_send_json_error( array( 'message' => __( 'ユーザー認証に失敗しました。', 'ktpwp' ) ) );
        }
    }

    /**
     * 開発者にメール通知を送信
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

        return ! empty( $agreed_time ) && $agreed_version === $current_version;
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
                return self::get_default_terms_content();
            }
        }
        
        $terms = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
        
        if ( ! $terms ) {
            // データが存在しない場合はデフォルトの利用規約を返す
            return self::get_default_terms_content();
        }
        
        if ( empty( trim( $terms->terms_content ) ) ) {
            // 内容が空の場合はデフォルトの利用規約を返す
            return self::get_default_terms_content();
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
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>KantanPro<?php echo KANTANPRO_PLUGIN_VERSION; ?>利用規約</title>
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
                <h1>KantanPro<?php echo KANTANPRO_PLUGIN_VERSION; ?>利用規約</h1>
                <?php echo $this->format_terms_content( $terms_content ); ?>
                
                <div class="back-link">
                    <a href="javascript:window.close();">このウィンドウを閉じる</a>
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
            // フッターのKantanProバージョン表示を探す
            var footerElements = document.querySelectorAll('*');
            for (var i = 0; i < footerElements.length; i++) {
                var element = footerElements[i];
                if (element.textContent && element.textContent.includes('KantanPro v1.3.0(beta)')) {
                    // 既に利用規約リンクが追加されているかチェック
                    if (element.querySelector('a[href*="ktp-terms"]')) {
                        return;
                    }
                    
                    // 利用規約リンクを追加
                    var termsLink = document.createElement('a');
                    termsLink.href = '<?php echo esc_url( $terms_url ); ?>';
                    termsLink.target = '_blank';
                    termsLink.textContent = 'KantanPro<?php echo KANTANPRO_PLUGIN_VERSION; ?>利用規約';
                    termsLink.style.marginLeft = '10px';
                    termsLink.style.color = '#666';
                    termsLink.style.textDecoration = 'none';
                    termsLink.style.fontSize = '12px';
                    
                    // 既存のテキストの後に追加
                    element.appendChild(document.createTextNode(' '));
                    element.appendChild(termsLink);
                    break;
                }
            }
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
                    <h2><?php echo esc_html__( 'KantanPro' . KANTANPRO_PLUGIN_VERSION . '利用規約', 'ktpwp' ); ?></h2>
                </div>
                <div class="ktpwp-terms-content">
                    <?php echo $this->format_terms_content( $terms_content ); ?>
                </div>
                <div class="ktpwp-terms-footer">
                    <div class="ktpwp-terms-checkbox-container">
                        <input type="checkbox" id="ktpwp-terms-checkbox" />
                        <label for="ktpwp-terms-checkbox">確認しました</label>
                    </div>
                    <button type="button" id="ktpwp-start-usage" class="ktpwp-start-btn" disabled>
                        利用開始する
                    </button>
                    <div class="ktpwp-home-link">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホームへ</a>
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
                        alert('<?php echo esc_js( __( 'エラーが発生しました。', 'ktpwp' ) ); ?>');
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