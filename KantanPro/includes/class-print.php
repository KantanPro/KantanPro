<?php

class Print_Class {
    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    // Generate HTML
    public function generateHTML() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_setting'; // テーブル名を適切に設定してください

        // データベースからテンプレートを取得
        $template_row = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = 1" );

        // nullチェックを追加
        if ( $template_row === null ) {
            // デフォルトテンプレートを使用
            $template = $this->getDefaultTemplate();
        } else {
            $template = $template_row->template_content;
        }

        // $this->data から値を取得
        $service_name = $this->data['service_name'] ?? '';
        $category = $this->data['category'] ?? '';
        $image_url = $this->data['image_url'] ?? '';
        $postal_code = $this->data['postal_code'] ?? '';
        $prefecture = $this->data['prefecture'] ?? '';
        $city = $this->data['city'] ?? '';
        $address = $this->data['address'] ?? '';
        $building = $this->data['building'] ?? '';
        $customer = $this->data['customer'] ?? '';
        $user_name = $this->data['user_name'] ?? '';

        // 部署情報を取得
        $department_info = '';
        if ( class_exists( 'KTPWP_Department_Manager' ) && ! empty( $this->data['client_id'] ) ) {
            $departments = KTPWP_Department_Manager::get_departments_by_client( $this->data['client_id'] );
            if ( ! empty( $departments ) ) {
                $department_lines = array();
                foreach ( $departments as $dept ) {
                    $department_lines[] = $dept->department_name . ' ' . $dept->contact_person . ' 様';
                }
                $department_info = implode( "\n", $department_lines );
            }
        }

        $replacements = array(
            '_%service_name%_' => $service_name,
            '_%category%_' => $category,
            '_%image_url%_' => $image_url,
            '_%postal_code%_' => $postal_code,
            '_%prefecture%_' => $prefecture,
            '_%city%_' => $city,
            '_%address%_' => $address,
            '_%building%_' => $building,
            '_%customer%_' => $customer,
            '_%user_name%_' => $user_name,
            '_%department_info%_' => $department_info,
        );

        $html = strtr( $template, $replacements );

        return $html;
    }

    /**
     * デフォルトテンプレートを取得
     *
     * @return string デフォルトのHTMLテンプレート
     */
    private function getDefaultTemplate() {
        return '
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <h2>サービス情報</h2>
            <table style="border-collapse: collapse; width: 100%;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">サービス名:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%service_name%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">カテゴリ:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%category%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">画像URL:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%image_url%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">郵便番号:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%postal_code%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">都道府県:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%prefecture%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">市区町村:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%city%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">住所:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%address%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">建物名:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%building%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">顧客名:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%customer%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">ユーザー名:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%user_name%_</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">部署情報:</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">_%department_info%_</td>
                </tr>
            </table>
        </div>';
    }
}
