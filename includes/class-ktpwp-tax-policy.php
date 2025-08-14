<?php
declare(strict_types=1);

/**
 * 税制ポリシーヘルパー
 * 設定に基づき税率の正規化、表示制御、JSへの設定受け渡しを行う。
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KTPWP_Tax_Policy {
    /**
     * 取得: 一般設定
     */
    private static function get_general_settings(): array {
        $settings = get_option( 'ktp_general_settings', array() );
        return is_array( $settings ) ? $settings : array();
    }

    /**
     * 税制モード multiple|unified|abolished
     */
    public static function get_mode(): string {
        $settings = self::get_general_settings();
        $mode = isset( $settings['tax_mode'] ) ? sanitize_text_field( (string) $settings['tax_mode'] ) : 'multiple';
        return in_array( $mode, array( 'multiple', 'unified', 'abolished' ), true ) ? $mode : 'multiple';
    }

    /**
     * 一律税率（%）
     */
    public static function get_unified_tax_rate(): float {
        $settings = self::get_general_settings();
        if ( self::is_abolished() ) {
            return 0.0;
        }
        $rate = isset( $settings['unified_tax_rate'] ) ? (float) $settings['unified_tax_rate'] : 5.0;
        return max( 0.0, $rate );
    }

    /**
     * 税率/税額列の非表示
     */
    public static function hide_tax_columns(): bool {
        // 仕様変更: 消費税なし（abolished）のときは必ず非表示、それ以外は必ず表示
        if ( self::is_abolished() ) {
            return true;
        }
        return false;
    }

    /**
     * 明細税率の編集ロック
     */
    public static function lock_line_tax_rate(): bool {
        $settings = self::get_general_settings();
        // モードがunified/abolishedのときは常にロック
        if ( self::is_unified() || self::is_abolished() ) {
            return true;
        }
        return ! empty( $settings['lock_line_tax_rate'] );
    }

    public static function is_unified(): bool {
        return self::get_mode() === 'unified';
    }

    public static function is_abolished(): bool {
        return self::get_mode() === 'abolished';
    }

    /**
     * 適用税率を決定
     * - abolished: 0%
     * - unified: 一律税率
     * - multiple: 数値ならそのまま、それ以外はnull
     */
    public static function get_effective_rate( $raw_rate ): ?float {
        if ( self::is_abolished() ) {
            return 0.0;
        }
        if ( self::is_unified() ) {
            return self::get_unified_tax_rate();
        }
        if ( $raw_rate === null || $raw_rate === '' || ! is_numeric( $raw_rate ) ) {
            return null;
        }
        return (float) $raw_rate;
    }

    /**
     * JS向け設定
     */
    public static function get_js_config(): array {
        $config = array(
            'mode' => self::get_mode(),
            'unified_tax_rate' => self::get_unified_tax_rate(),
            'hide_tax_columns' => self::hide_tax_columns(),
            'lock_line_tax_rate' => self::lock_line_tax_rate(),
        );
        // 後方互換: JS側で 'hide_columns' を参照している箇所に対応
        $config['hide_columns'] = $config['hide_tax_columns'];
        return $config;
    }
}


