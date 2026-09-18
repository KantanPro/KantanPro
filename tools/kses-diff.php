<?php
/**
 * KTPWP_Kses の許可リストが、業務画面の出力を削っていないかを確認する開発用ツール。
 *
 * 使い方（テストサイトのコンテナ内で）:
 *   wp eval-file wp-content/plugins/KantanPro/tools/kses-diff.php
 *
 * 各タブ（と、実データを持つ詳細表示）で kantanAllTab を描画し、
 * kses に通す直前の HTML と、通したあとの HTML を DOM で比べる。
 * 「通す前にあって、通したあとに無いもの」（タグ・属性・style の宣言・script/style ブロック）を
 * 全て列挙する。何も出なければ許可リストは十分。差分があれば非ゼロで終了する。
 *
 * tools/ は配布物（wp.org ZIP）に含まれない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;

/**
 * DOM を走査して、タグ・属性・style 宣言・script/style の出現数を数える。
 *
 * @param string $html HTML 断片。
 * @return array{tags: array<string,int>, attrs: array<string,int>, styles: array<string,int>, scripts: int, stylesheets: int}
 */
function ktpwp_kses_diff_stats( $html ) {
	$counts = array(
		'tags'        => array(),
		'attrs'       => array(),
		'styles'      => array(),
		'scripts'     => 0,
		'stylesheets' => 0,
	);
	$dom    = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();

	$walk = function ( DOMNode $node ) use ( &$walk, &$counts ) {
		if ( $node instanceof DOMElement ) {
			$tag = strtolower( $node->tagName );
			if ( $tag === 'script' ) {
				++$counts['scripts'];
			} elseif ( $tag === 'style' ) {
				++$counts['stylesheets'];
			}
			$counts['tags'][ $tag ] = ( $counts['tags'][ $tag ] ?? 0 ) + 1;
			foreach ( $node->attributes as $attr ) {
				$name = strtolower( $attr->name );
				$key  = $tag . '[' . $name . ']';
				if ( strpos( $name, 'data-' ) === 0 ) {
					$key = $tag . '[data-*]';
				}
				$counts['attrs'][ $key ] = ( $counts['attrs'][ $key ] ?? 0 ) + 1;
				if ( $name === 'style' ) {
					foreach ( explode( ';', $attr->value ) as $decl ) {
						if ( strpos( $decl, ':' ) === false ) {
							continue;
						}
						$prop = strtolower( trim( substr( $decl, 0, strpos( $decl, ':' ) ) ) );
						if ( $prop !== '' ) {
							$counts['styles'][ $prop ] = ( $counts['styles'][ $prop ] ?? 0 ) + 1;
						}
					}
				}
			}
		}
		foreach ( $node->childNodes as $child ) {
			$walk( $child );
		}
	};
	$walk( $dom );
	return $counts;
}

/**
 * 1 シナリオを描画し、kses 前後の差分を返す。
 *
 * @param string               $label シナリオ名。
 * @param array<string,string> $get   $_GET に入れる値。
 * @return array{label: string, missing: string[], bytes: array{0:int,1:int}}|null
 */
function ktpwp_kses_diff_run( $label, array $get ) {
	$_GET                      = $get;
	$_POST                     = array();
	$_REQUEST                  = $get;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI']    = '/?' . http_build_query( $get );

	$raw = null;
	$cb  = function ( $html ) use ( &$raw ) {
		$raw = $html;
	};
	add_action( 'ktpwp_business_screen_before_kses', $cb );
	$out = do_shortcode( '[kantanAllTab]' );
	remove_action( 'ktpwp_business_screen_before_kses', $cb );

	if ( $raw === null ) {
		return null;
	}

	$before  = ktpwp_kses_diff_stats( $raw );
	$after   = ktpwp_kses_diff_stats( $out );
	$missing = array();

	foreach ( array( 'tags', 'attrs', 'styles' ) as $kind ) {
		foreach ( $before[ $kind ] as $k => $n ) {
			$m = $after[ $kind ][ $k ] ?? 0;
			if ( $m < $n ) {
				$missing[] = sprintf( '%s %s: %d → %d', rtrim( $kind, 's' ), $k, $n, $m );
			}
		}
	}
	if ( $after['scripts'] < $before['scripts'] ) {
		$missing[] = sprintf( '<script> ブロック: %d → %d', $before['scripts'], $after['scripts'] );
		// どの script が消えたか分かるように、先頭の一部を出す。
		if ( preg_match_all( '#<script\b[^>]*>(.{0,90})#su', $raw, $mm ) ) {
			foreach ( $mm[0] as $i => $whole ) {
				$missing[] = '  ↳ ' . preg_replace( '/\s+/', ' ', mb_substr( $whole, 0, 130 ) );
			}
		}
	}
	if ( $after['stylesheets'] < $before['stylesheets'] ) {
		$missing[] = sprintf( '<style> ブロック: %d → %d', $before['stylesheets'], $after['stylesheets'] );
	}

	return array(
		'label'   => $label,
		'missing' => $missing,
		'bytes'   => array( strlen( $raw ), strlen( $out ) ),
	);
}

wp_set_current_user( 1 );

$first_id = function ( $table ) use ( $wpdb ) {
	$t = $wpdb->prefix . 'ktp_' . $table;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
		return 0;
	}
	return (int) $wpdb->get_var( "SELECT id FROM `{$t}` ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
};

$scenarios = array(
	'list'                => array( 'tab_name' => 'list' ),
	'order'               => array( 'tab_name' => 'order' ),
	'client'              => array( 'tab_name' => 'client' ),
	'service'             => array( 'tab_name' => 'service' ),
	'supplier'            => array( 'tab_name' => 'supplier' ),
	'info'                => array( 'tab_name' => 'info' ),
	'list (受注書の進捗)' => array(
		'tab_name' => 'list',
		'progress' => '3',
	),
);
foreach ( array(
	'order'    => 'order_id',
	'client'   => 'data_id',
	'service'  => 'data_id',
	'supplier' => 'data_id',
) as $tab => $param ) {
	$id = $first_id( $tab === 'order' ? 'order' : $tab );
	if ( $id > 0 ) {
		$scenarios[ $tab . ' 詳細 #' . $id ] = array(
			'tab_name' => $tab,
			$param     => (string) $id,
		);
	}
}
// 検索（複数ヒットのポップアップ、印刷用の全件表示）
$scenarios['client 検索']   = array(
	'tab_name'     => 'client',
	'search_query' => '株式会社',
);
$scenarios['service 検索']  = array(
	'tab_name'            => 'service',
	'search_service_name' => 'a',
);
$scenarios['client 全件']   = array(
	'tab_name'  => 'client',
	'print_all' => '1',
);
$scenarios['supplier 検索'] = array(
	'tab_name'     => 'supplier',
	'search_query' => '株式会社',
);

$exit = 0;
foreach ( $scenarios as $label => $get ) {
	$r = ktpwp_kses_diff_run( $label, $get );
	if ( $r === null ) {
		echo "[SKIP] {$label}: kantanAllTab が kses まで到達しませんでした\n";
		continue;
	}
	if ( empty( $r['missing'] ) ) {
		printf( "[OK]   %s (%d → %d bytes)\n", $r['label'], $r['bytes'][0], $r['bytes'][1] );
		continue;
	}
	$exit = 1;
	printf( "[DIFF] %s (%d → %d bytes)\n", $r['label'], $r['bytes'][0], $r['bytes'][1] );
	foreach ( $r['missing'] as $m ) {
		echo "         - {$m}\n";
	}
}

exit( $exit );
