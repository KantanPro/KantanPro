<?php

// 古いメニューは新しい設定ページに統合したため無効化
// add_action( 'admin_menu', 'add_general_custom_fields' );

function add_general_custom_fields() {
    /*
    add_menu_page(
        'KTPWPの設定', // page_title
        'KTPWP', // menu_title
        'edit_posts', // capability - allow editors and above
        'ktp-admin', // menu_slug
        'display_plugin_admin_page', // function
        'dashicons-admin-generic', // icon_url（WordPress標準アイコン）
        3 // position（必要に応じて調整）
    );
    */
    register_setting(
        'ktp-group', // option_group
        'ktp_activation_key' // option_name
    );
}

function active_ktp_validation( $input ) {
    $input = (int) $input;
    $activation_key = get_site_option( 'ktp_activation_key' );

    // 有効化キーが正しい場合のみ、入力を受け付ける
    if ( $activation_key === 'your_activation_key' && ( $input === 0 || $input === 1 ) ) {
        return $input;
    } else {
        add_settings_error(
            'active_ktp',
            'active-ktp-validation_error',
            __( 'illegal data', 'Hello_World' ),
            'error'
        );
    }
}

function display_plugin_admin_page() {
	do_settings_sections( 'default' );
	echo '<div class="wrap">';
	echo '<form method="post" action="">';

    $activation_key = get_site_option( 'ktp_activation_key' );

    echo '<div class="wrap">';
    echo '<h2>KTPWP設定</h2>';
    // WordPress標準の通知表示
    settings_errors();

    echo '<form method="post" action="options.php">';
    settings_fields( 'ktp-group' );
    do_settings_sections( 'default' );
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="ktp_activation_key">有効化キー</label></th>';
    echo '<td>';
    echo '<input type="text" id="ktp_activation_key" name="ktp_activation_key" value="' . esc_attr( $activation_key ) . '" />';
    echo '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    submit_button( '設定を保存' );
    echo '</form>';
    echo '</div><!-- .wrap -->';
}
