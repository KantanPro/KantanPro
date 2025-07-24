<?php
/**
 * KantanPro Image Optimizer
 * 
 * 画像の最適化とWebP変換を管理するクラス
 * 
 * @package KantanPro
 * @since 1.1.4
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Image_Optimizer クラス
 * 
 * 画像の最適化、WebP変換、レスポンシブ画像の提供を行います。
 */
class KTPWP_Image_Optimizer {

    /**
     * シングルトンインスタンス
     * 
     * @var KTPWP_Image_Optimizer
     */
    private static $instance = null;
    
    /**
     * WebP対応ブラウザのUser-Agent文字列
     * 
     * @var array
     */
    private $webp_supported_browsers = array(
        'Chrome',
        'Firefox',
        'Opera',
        'Edge',
        'Android',
    );
    
    /**
     * 最適化統計
     * 
     * @var array
     */
    private $optimization_stats = array(
        'webp_conversions' => 0,
        'size_reductions' => 0,
        'cache_hits' => 0,
    );

    /**
     * シングルトンインスタンスを取得
     * 
     * @return KTPWP_Image_Optimizer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * フックを初期化
     */
    private function init_hooks() {
        // 画像アップロード時の自動WebP変換
        add_filter( 'wp_handle_upload', array( $this, 'handle_image_upload' ), 10, 2 );
        
        // 画像出力時のWebP提供
        add_filter( 'wp_get_attachment_image_src', array( $this, 'maybe_serve_webp' ), 10, 4 );
        add_filter( 'wp_get_attachment_image', array( $this, 'add_webp_srcset' ), 10, 5 );
        
        // 画像サイズ生成時の最適化
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_image_sizes' ), 10, 2 );
        
        // コンテンツ内の画像を最適化
        add_filter( 'the_content', array( $this, 'optimize_content_images' ), 20 );
        
        // 管理画面にWebP変換ボタンを追加
        if ( is_admin() ) {
            add_filter( 'attachment_fields_to_edit', array( $this, 'add_webp_conversion_field' ), 10, 2 );
            add_filter( 'attachment_fields_to_save', array( $this, 'handle_webp_conversion_save' ), 10, 2 );
            add_action( 'wp_ajax_ktpwp_convert_to_webp', array( $this, 'ajax_convert_to_webp' ) );
        }
        
        // .htaccessファイルにWebP配信ルールを追加
        add_action( 'init', array( $this, 'maybe_update_htaccess' ) );
        
        // デバッグ時の統計表示
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_admin() ) {
            add_action( 'admin_footer', array( $this, 'display_optimization_stats' ) );
        }
    }

    /**
     * 画像アップロード処理
     * 
     * @param array $upload アップロード情報
     * @param string $context アップロードコンテキスト
     * @return array
     */
    public function handle_image_upload( $upload, $context ) {
        if ( ! isset( $upload['file'] ) || ! $this->is_image_file( $upload['file'] ) ) {
            return $upload;
        }
        
        // WebP変換を実行
        $webp_file = $this->convert_to_webp( $upload['file'] );
        
        if ( $webp_file ) {
            $this->optimization_stats['webp_conversions']++;
            
            // メタデータにWebPファイル情報を追加
            $upload['webp_file'] = $webp_file;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Image Optimizer: WebP converted - {$upload['file']} -> {$webp_file}" );
            }
        }
        
        return $upload;
    }

    /**
     * WebP変換を実行
     * 
     * @param string $image_path 元画像のパス
     * @return string|false WebPファイルのパス、失敗時はfalse
     */
    public function convert_to_webp( $image_path ) {
        if ( ! $this->webp_supported() ) {
            return false;
        }
        
        $path_info = pathinfo( $image_path );
        $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
        
        // 既にWebPファイルが存在する場合はスキップ
        if ( file_exists( $webp_path ) ) {
            return $webp_path;
        }
        
        // キャッシュから変換結果を確認
        $cache_key = 'webp_conversion_' . md5( $image_path );
        $cached_result = ktpwp_cache_get( $cache_key );
        
        if ( false !== $cached_result ) {
            $this->optimization_stats['cache_hits']++;
            return $cached_result;
        }
        
        $success = false;
        $image_type = wp_check_filetype( $image_path )['type'];
        
        switch ( $image_type ) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg( $image_path );
                break;
            case 'image/png':
                $image = imagecreatefrompng( $image_path );
                // PNG透明度を保持
                imagepalettetotruecolor( $image );
                imagealphablending( $image, true );
                imagesavealpha( $image, true );
                break;
            case 'image/gif':
                $image = imagecreatefromgif( $image_path );
                break;
            default:
                return false;
        }
        
        if ( $image ) {
            // WebP品質設定（管理画面で設定可能にする予定）
            $quality = apply_filters( 'ktpwp_webp_quality', 85 );
            $success = imagewebp( $image, $webp_path, $quality );
            imagedestroy( $image );
            
            if ( $success ) {
                // ファイルサイズ比較
                $original_size = filesize( $image_path );
                $webp_size = filesize( $webp_path );
                $size_reduction = $original_size - $webp_size;
                
                if ( $size_reduction > 0 ) {
                    $this->optimization_stats['size_reductions'] += $size_reduction;
                }
                
                // 結果をキャッシュに保存
                ktpwp_cache_set( $cache_key, $webp_path, 86400 ); // 24時間
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    $reduction_percent = round( ( $size_reduction / $original_size ) * 100, 2 );
                    error_log( "KTPWP Image Optimizer: WebP conversion successful - Size reduction: {$reduction_percent}%" );
                }
                
                return $webp_path;
            }
        }
        
        // 失敗した場合もキャッシュに記録（再試行を避けるため）
        ktpwp_cache_set( $cache_key, false, 3600 ); // 1時間
        
        return false;
    }

    /**
     * WebP画像を提供するかどうかを判断
     * 
     * @param array $image 画像情報
     * @param int $attachment_id 添付ファイルID
     * @param string $size 画像サイズ
     * @param bool $icon アイコンかどうか
     * @return array
     */
    public function maybe_serve_webp( $image, $attachment_id, $size, $icon ) {
        if ( ! $this->client_supports_webp() || ! is_array( $image ) ) {
            return $image;
        }
        
        $webp_url = $this->get_webp_url( $image[0] );
        
        if ( $webp_url ) {
            $image[0] = $webp_url;
        }
        
        return $image;
    }

    /**
     * 画像タグにWebP srcsetを追加
     * 
     * @param string $html 画像HTML
     * @param int $attachment_id 添付ファイルID
     * @param string $size 画像サイズ
     * @param bool $icon アイコンかどうか
     * @param array $attr 属性配列
     * @return string
     */
    public function add_webp_srcset( $html, $attachment_id, $size, $icon, $attr ) {
        if ( ! $this->client_supports_webp() ) {
            return $html;
        }
        
        // srcset属性を取得
        if ( preg_match( '/srcset="([^"]+)"/', $html, $matches ) ) {
            $srcset = $matches[1];
            $webp_srcset = $this->convert_srcset_to_webp( $srcset );
            
            if ( $webp_srcset !== $srcset ) {
                $html = str_replace( $srcset, $webp_srcset, $html );
            }
        }
        
        return $html;
    }

    /**
     * srcset文字列をWebP版に変換
     * 
     * @param string $srcset 元のsrcset
     * @return string WebP版srcset
     */
    private function convert_srcset_to_webp( $srcset ) {
        $sources = explode( ',', $srcset );
        $webp_sources = array();
        
        foreach ( $sources as $source ) {
            $source = trim( $source );
            if ( preg_match( '/^(\S+)\s+(.+)$/', $source, $matches ) ) {
                $url = $matches[1];
                $descriptor = $matches[2];
                
                $webp_url = $this->get_webp_url( $url );
                if ( $webp_url ) {
                    $webp_sources[] = $webp_url . ' ' . $descriptor;
                } else {
                    $webp_sources[] = $source;
                }
            }
        }
        
        return implode( ', ', $webp_sources );
    }

    /**
     * 画像URLに対応するWebP URLを取得
     * 
     * @param string $image_url 元画像URL
     * @return string|false WebP URL、存在しない場合はfalse
     */
    private function get_webp_url( $image_url ) {
        $path_info = pathinfo( $image_url );
        $webp_url = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
        
        // WebPファイルの存在確認
        $webp_path = $this->url_to_path( $webp_url );
        
        if ( $webp_path && file_exists( $webp_path ) ) {
            return $webp_url;
        }
        
        return false;
    }

    /**
     * URLをファイルパスに変換
     * 
     * @param string $url URL
     * @return string|false ファイルパス
     */
    private function url_to_path( $url ) {
        $upload_dir = wp_upload_dir();
        
        if ( strpos( $url, $upload_dir['baseurl'] ) === 0 ) {
            return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
        }
        
        return false;
    }

    /**
     * 画像サイズ生成時の最適化
     * 
     * @param array $metadata 画像メタデータ
     * @param int $attachment_id 添付ファイルID
     * @return array
     */
    public function optimize_image_sizes( $metadata, $attachment_id ) {
        if ( ! isset( $metadata['file'] ) ) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        // メイン画像のWebP変換
        $webp_file = $this->convert_to_webp( $image_path );
        if ( $webp_file ) {
            $metadata['webp_file'] = basename( $webp_file );
        }
        
        // 各サイズのWebP変換
        if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size => $size_data ) {
                $size_path = dirname( $image_path ) . '/' . $size_data['file'];
                $webp_file = $this->convert_to_webp( $size_path );
                
                if ( $webp_file ) {
                    $metadata['sizes'][ $size ]['webp_file'] = basename( $webp_file );
                }
            }
        }
        
        return $metadata;
    }

    /**
     * コンテンツ内の画像を最適化
     * 
     * @param string $content コンテンツ
     * @return string 最適化されたコンテンツ
     */
    public function optimize_content_images( $content ) {
        if ( ! $this->client_supports_webp() ) {
            return $content;
        }
        
        // img タグのパターンマッチング
        $pattern = '/<img([^>]+)>/i';
        
        return preg_replace_callback( $pattern, array( $this, 'optimize_img_tag' ), $content );
    }

    /**
     * 個別のimgタグを最適化
     * 
     * @param array $matches 正規表現マッチ結果
     * @return string 最適化されたimgタグ
     */
    private function optimize_img_tag( $matches ) {
        $img_tag = $matches[0];
        
        // src属性を取得
        if ( preg_match( '/src="([^"]+)"/', $img_tag, $src_matches ) ) {
            $original_src = $src_matches[1];
            $webp_src = $this->get_webp_url( $original_src );
            
            if ( $webp_src ) {
                // picture要素を使用してWebPとフォールバックを提供
                $picture_tag = '<picture>';
                $picture_tag .= '<source srcset="' . esc_url( $webp_src ) . '" type="image/webp">';
                $picture_tag .= $img_tag;
                $picture_tag .= '</picture>';
                
                return $picture_tag;
            }
        }
        
        return $img_tag;
    }

    /**
     * 管理画面にWebP変換フィールドを追加
     * 
     * @param array $form_fields フォームフィールド
     * @param object $post 投稿オブジェクト
     * @return array
     */
    public function add_webp_conversion_field( $form_fields, $post ) {
        if ( ! $this->is_image_attachment( $post->ID ) ) {
            return $form_fields;
        }
        
        $webp_exists = $this->webp_version_exists( $post->ID );
        
        $form_fields['ktpwp_webp_conversion'] = array(
            'label' => 'WebP変換',
            'input' => 'html',
            'html' => $this->get_webp_conversion_html( $post->ID, $webp_exists ),
        );
        
        return $form_fields;
    }

    /**
     * WebP変換HTMLを取得
     * 
     * @param int $attachment_id 添付ファイルID
     * @param bool $webp_exists WebPが存在するか
     * @return string HTML
     */
    private function get_webp_conversion_html( $attachment_id, $webp_exists ) {
        $nonce = wp_create_nonce( 'ktpwp_webp_conversion_' . $attachment_id );
        
        if ( $webp_exists ) {
            return '<span style="color: green;">✓ WebPバージョンが利用可能です</span>';
        } else {
            return sprintf(
                '<button type="button" class="button" onclick="ktpwpConvertToWebP(%d, \'%s\')">WebPに変換</button>
                <div id="ktpwp-webp-status-%d"></div>',
                $attachment_id,
                $nonce,
                $attachment_id
            );
        }
    }

    /**
     * WebP変換のAJAX処理
     */
    public function ajax_convert_to_webp() {
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        
        if ( ! wp_verify_nonce( $nonce, 'ktpwp_webp_conversion_' . $attachment_id ) ) {
            wp_send_json_error( 'セキュリティチェックに失敗しました' );
        }
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( '権限がありません' );
        }
        
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path || ! $this->is_image_file( $image_path ) ) {
            wp_send_json_error( '有効な画像ファイルではありません' );
        }
        
        $webp_file = $this->convert_to_webp( $image_path );
        
        if ( $webp_file ) {
            wp_send_json_success( 'WebP変換が完了しました' );
        } else {
            wp_send_json_error( 'WebP変換に失敗しました' );
        }
    }

    /**
     * クライアントがWebPをサポートしているかチェック
     * 
     * @return bool
     */
    private function client_supports_webp() {
        // Accept ヘッダーをチェック
        if ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
            return true;
        }
        
        // User-Agent をチェック
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            foreach ( $this->webp_supported_browsers as $browser ) {
                if ( strpos( $user_agent, $browser ) !== false ) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * サーバーがWebPをサポートしているかチェック
     * 
     * @return bool
     */
    private function webp_supported() {
        return function_exists( 'imagewebp' );
    }

    /**
     * ファイルが画像かどうかをチェック
     * 
     * @param string $file_path ファイルパス
     * @return bool
     */
    private function is_image_file( $file_path ) {
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
        $file_type = wp_check_filetype( $file_path )['type'];
        
        return in_array( $file_type, $allowed_types, true );
    }

    /**
     * 添付ファイルが画像かどうかをチェック
     * 
     * @param int $attachment_id 添付ファイルID
     * @return bool
     */
    private function is_image_attachment( $attachment_id ) {
        return strpos( get_post_mime_type( $attachment_id ), 'image/' ) === 0;
    }

    /**
     * WebPバージョンが存在するかチェック
     * 
     * @param int $attachment_id 添付ファイルID
     * @return bool
     */
    private function webp_version_exists( $attachment_id ) {
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path ) {
            return false;
        }
        
        $path_info = pathinfo( $image_path );
        $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
        
        return file_exists( $webp_path );
    }

    /**
     * .htaccessファイルを更新（WebP配信ルール追加）
     */
    public function maybe_update_htaccess() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // 既に処理済みかチェック
        if ( get_option( 'ktpwp_htaccess_webp_updated' ) ) {
            return;
        }
        
        $htaccess_file = ABSPATH . '.htaccess';
        
        if ( ! is_writable( $htaccess_file ) ) {
            return;
        }
        
        $webp_rules = $this->get_webp_htaccess_rules();
        $current_content = file_get_contents( $htaccess_file );
        
        if ( strpos( $current_content, '# KantanPro WebP Rules' ) === false ) {
            $new_content = $webp_rules . "\n" . $current_content;
            
            if ( file_put_contents( $htaccess_file, $new_content ) ) {
                update_option( 'ktpwp_htaccess_webp_updated', true );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Image Optimizer: .htaccess updated with WebP rules' );
                }
            }
        }
    }

    /**
     * WebP配信用の.htaccessルールを取得
     * 
     * @return string
     */
    private function get_webp_htaccess_rules() {
        return '# KantanPro WebP Rules
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # WebP対応ブラウザに対してWebPを配信
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png)$
    RewriteCond %{REQUEST_FILENAME}.webp -f
    RewriteRule ^(.+)\.(jpe?g|png)$ $1.$2.webp [T=image/webp,E=accept:1]
</IfModule>

<IfModule mod_headers.c>
    Header append Vary Accept env=REDIRECT_accept
</IfModule>

# WebPファイルの適切なMIMEタイプ設定
<IfModule mod_mime.c>
    AddType image/webp .webp
</IfModule>
# End KantanPro WebP Rules';
    }

    /**
     * 最適化統計を取得
     * 
     * @return array
     */
    public function get_optimization_stats() {
        return $this->optimization_stats;
    }

    /**
     * 最適化統計を表示（デバッグ用）
     */
    public function display_optimization_stats() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $stats = $this->get_optimization_stats();
        $size_reduction_mb = round( $stats['size_reductions'] / 1024 / 1024, 2 );
        
        echo '<div style="position: fixed; bottom: 130px; right: 10px; background: #fff; border: 1px solid #ccc; padding: 10px; font-size: 12px; z-index: 9999;">';
        echo '<strong>KTPWP Image Optimization:</strong><br>';
        echo "WebP Conversions: {$stats['webp_conversions']}<br>";
        echo "Size Reduction: {$size_reduction_mb} MB<br>";
        echo "Cache Hits: {$stats['cache_hits']}<br>";
        echo "WebP Support: " . ( $this->webp_supported() ? 'Yes' : 'No' );
        echo '</div>';
    }
}
