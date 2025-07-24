<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Processor {

    /**
     * 画像のアップロードと削除を行う
     *
     * @param string $tab_name テーブル名
     * @param int    $data_id データID
     * @param string $default_image_url デフォルト画像のURL
     * @return string $image_url 画像のURL
     */
    public function handle_image( $tab_name, $data_id, $default_image_url ) {
        global $wpdb;

        // デバッグログを追加
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( isset( $_FILES['image'] ) ) {
            }
        }

        // 保存先ディレクトリを images/upload/ に変更
        $upload_dir = __DIR__ . '/../images/upload/';
        // ディレクトリがなければ作成
        if ( ! file_exists( $upload_dir ) ) {
            mkdir( $upload_dir, 0777, true );
        }
        // 保存ファイル名
        // $file_path = $upload_dir . $data_id . '.jpeg'; // 修正前

        if ( isset( $_FILES['image'] ) && is_uploaded_file( $_FILES['image']['tmp_name'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            }

            $file = $_FILES['image'];
            $mime = mime_content_type( $file['tmp_name'] );
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            $size = filesize( $file['tmp_name'] );
            $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif' );
            $allowed_exts = array( 'jpg', 'jpeg', 'png', 'gif' );
            $max_size = 10 * 1024 * 1024; // 10MBに拡張

            // 新しいファイル名を生成 (商品ID-アップロード日.拡張子)
            $date_suffix = current_time( 'Ymd' );
            $new_file_name = $data_id . '-' . $date_suffix . '.' . $ext;
            $file_path = $upload_dir . $new_file_name;

            // MIME,拡張子,サイズチェック
            if ( ! in_array( $mime, $allowed_mimes, true ) || ! in_array( $ext, $allowed_exts, true ) || $size > $max_size ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: 不正なファイルアップロード検出' );
                }
                return $default_image_url;
            }

            // 画像を読み込み、圧縮・リサイズ処理を行う
            $image = null;
            if ( $mime === 'image/jpeg' ) {
                $image = imagecreatefromjpeg( $file['tmp_name'] );
            } elseif ( $mime === 'image/png' ) {
                $image = imagecreatefrompng( $file['tmp_name'] );
            } elseif ( $mime === 'image/gif' ) {
                $image = imagecreatefromgif( $file['tmp_name'] );
            }

            if ( $image ) {
                // 元の画像サイズを取得
                $original_width = imagesx( $image );
                $original_height = imagesy( $image );

                // 最大サイズを設定（幅または高さの最大値）
                $max_dimension = 1200;

                // リサイズが必要かチェック
                if ( $original_width > $max_dimension || $original_height > $max_dimension ) {
                    // アスペクト比を保持してリサイズ
                    if ( $original_width > $original_height ) {
                        $new_width = $max_dimension;
                        $new_height = ( $original_height * $max_dimension ) / $original_width;
                    } else {
                        $new_height = $max_dimension;
                        $new_width = ( $original_width * $max_dimension ) / $original_height;
                    }

                    // リサイズされた画像を作成
                    $resized_image = imagecreatetruecolor( $new_width, $new_height );

                    // 透明度の保持（PNG用）
                    imagealphablending( $resized_image, false );
                    imagesavealpha( $resized_image, true );

                    // リサイズ実行
                    imagecopyresampled(
                        $resized_image,
                        $image,
                        0,
                        0,
                        0,
                        0,
                        $new_width,
                        $new_height,
                        $original_width,
                        $original_height
                    );

                    // 元の画像リソースを解放
                    imagedestroy( $image );
                    $image = $resized_image;

                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    }
                }

                // JPEGで保存（品質85%で圧縮） // 拡張子に応じて保存形式を変更
                if ( $ext === 'png' ) {
                    imagepng( $image, $file_path, 9 ); // PNGは圧縮レベル (0-9)
                } elseif ( $ext === 'gif' ) {
                    imagegif( $image, $file_path );
                } else { // デフォルトはJPEG
                    imagejpeg( $image, $file_path, 85 );
                }
                imagedestroy( $image );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    $final_size = filesize( $file_path );
                }
            } else {
                return $default_image_url;
            }

            $plugin_url = plugin_dir_url( __DIR__ );
            $image_url = $plugin_url . 'images/upload/' . $new_file_name; // 修正後

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            }

            return $image_url;
        }

        // カスタム画像ファイルが存在するか確認
        // 既存のファイル名形式も考慮する必要があるか、または新しい形式のみをチェックするか検討
        // ここでは、新しいファイル名形式で最も最近のものを探すロジックが必要になる可能性があります。
        // 簡単のため、ここでは完全な日付一致ではなく、data_idで始まるファイルを探します。
        // より堅牢な実装のためには、日付部分も考慮した検索が必要です。
        $files = glob( $upload_dir . $data_id . '-*.{jpeg,jpg,png,gif}', GLOB_BRACE );
        if ( ! empty( $files ) ) {
            // 日付でソートして最新のものを取得するなどのロジックを追加可能
            $latest_file = $files[ count( $files ) - 1 ]; // 最も新しいファイル（名前順）
            $latest_file_name = basename( $latest_file );
            $plugin_url = plugin_dir_url( __DIR__ );
            return $plugin_url . 'images/upload/' . $latest_file_name;
        }

        // それ以外はデフォルト画像を返す
        return $default_image_url;
    }
}
