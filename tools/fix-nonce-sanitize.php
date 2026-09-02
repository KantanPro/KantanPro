<?php
/**
 * wp_verify_nonce() に渡すスーパーグローバルをサニタイズする一括修正スクリプト。
 *
 * wp_verify_nonce() は pluggable なので、他プラグインが差し替えている可能性がある。
 * そのため WordPress.org のガイドラインでは、渡す前に wp_unslash() + sanitize_text_field()
 * を通すことが求められる（2026-09-02 のレビューで51件指摘）。
 *
 *   before: wp_verify_nonce( $_POST['nonce'], 'action' )
 *   after : wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' )
 *
 * 使い方:
 *   php tools/fix-nonce-sanitize.php --dry-run   # 変更内容だけ表示
 *   php tools/fix-nonce-sanitize.php             # 実際に書き換える
 *
 * 対象は $_POST / $_GET / $_REQUEST の直接参照のみ。
 * 変数経由（$nonce など）は代入元を人が確認する必要があるので触らない。
 */

$root = dirname( __DIR__ );
$dry  = in_array( '--dry-run', $argv, true );

// wp_verify_nonce( $_POST['x'] , ...  /  wp_verify_nonce( $_GET['x'] ?? '' , ...
$pattern = '/wp_verify_nonce\(\s*(\$_(?:POST|GET|REQUEST)\[\s*[\'"][^\'"]+[\'"]\s\]?\]?(?:\s*\?\?\s*[\'"][\'"])?)\s*,/';
// 上の書き方だと崩れやすいので、実際にはこちらを使う
$pattern = '/wp_verify_nonce\(\s*(\$_(?:POST|GET|REQUEST)\[[^\]]+\](?:\s*\?\?\s*\'\')?)\s*,/';

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$total = 0;
$touched = array();

foreach ( $files as $file ) {
	if ( $file->getExtension() !== 'php' ) {
		continue;
	}
	$path = $file->getPathname();
	if ( strpos( $path, '/vendor/' ) !== false || strpos( $path, '/node_modules/' ) !== false
		|| strpos( $path, '/tools/' ) !== false ) {
		continue;
	}

	$src = file_get_contents( $path );
	$out = preg_replace_callback(
		$pattern,
		function ( $m ) {
			// 既にサニタイズ済みなら触らない（多重適用の防止）
			if ( strpos( $m[0], 'sanitize_text_field' ) !== false ) {
				return $m[0];
			}
			return 'wp_verify_nonce( sanitize_text_field( wp_unslash( ' . trim( $m[1] ) . ' ) ),';
		},
		$src,
		-1,
		$count
	);

	if ( $count > 0 ) {
		$total += $count;
		$touched[ str_replace( $root . '/', '', $path ) ] = $count;
		if ( ! $dry ) {
			file_put_contents( $path, $out );
		}
	}
}

foreach ( $touched as $f => $n ) {
	printf( "%-58s %d件\n", $f, $n );
}
printf( "\n%s: 合計 %d件 / %d ファイル\n", $dry ? '[dry-run] 対象' : '修正しました', $total, count( $touched ) );
