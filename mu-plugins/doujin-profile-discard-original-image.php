<?php
/**
 * Plugin Name: doujin-profile: 元の巨大画像を破棄する
 * Description: WordPress 本体が長辺 2560px の -scaled 版(または -rotated 版)を作ったあと、表示に使われない元の画像ファイルを消し、メタデータの original_image を外す(T.51.5)。
 * Version:     1.0.0
 * Requires PHP: 8.0
 *
 * リポジトリの mu-plugins/ が正本で、本番へは scripts/deploy.sh が rsync する。
 * サーバ上で直接編集しないこと (次の配備で上書きされる)。
 * 外すときは、サーバ上のこのファイルを消すだけでよい (設定は持たない)。有効中に消えた元の画像は戻らない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 本体が縮小版を作ったあとに残る元の画像ファイルを消す。
 *
 * メタデータを作る全経路で呼ばれる: 従来のアップロード ('create')、
 * WP 7.1 のブラウザ側処理の finalize ('update')、再生成 ('update')。
 * ブラウザ側処理では 'create' の時点で -scaled がまだ無いので、context は見ない (冪等)。
 *
 * @param array  $metadata      添付のメタデータ。
 * @param int    $attachment_id 添付 ID。
 * @param string $context       'create' か 'update'。
 * @return array
 */
function doujin_profile_discard_original_image( $metadata, $attachment_id, $context = '' ) {
	if ( ! is_array( $metadata ) || empty( $metadata['original_image'] ) || empty( $metadata['file'] ) ) {
		return $metadata;
	}

	$uploads  = wp_get_upload_dir();
	$full     = path_join( $uploads['basedir'], $metadata['file'] ); // 表示に使うファイル (-scaled / -rotated)
	$dir      = dirname( $full );
	$original = path_join( $dir, wp_basename( $metadata['original_image'] ) );

	// 表示用のファイルが実在し、元と別のファイルであるときだけ消す。
	if ( $original === $full || ! is_file( $full ) ) {
		return $metadata;
	}

	if ( is_file( $original ) && ! wp_delete_file_from_directory( $original, $dir ) ) {
		return $metadata; // 消せなかったときは本体の既定のまま (元を残す)。
	}

	unset( $metadata['original_image'] );

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'doujin_profile_discard_original_image', 99, 3 );
