<?php
/**
 * 取り込んだ画像のメディアライブラリへの保存 (spec §8.3・§6.4)。
 *
 * 取り込んだ添付には出どころの URL (WP-CLI の import では JSON に書かれた道筋) をメタ _doujin_profile_source_url に持つ。
 * 同じ番号を 2 度取り込んでも、同じ URL の添付がその作品の下にあればそれを返し、二重にはしない。書影を取り込み直したときは、
 * 前のアイキャッチがこの作品の取り込みでできたもので、ほかで使われていなければ消す (手で上げた画像は消さない)。
 * 応答の判定 (200 + image/*) は lib/Import/ImageResponse.php。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Import\ImageResponse;

const DOUJIN_PROFILE_SOURCE_URL_META = '_doujin_profile_source_url';

/**
 * この作品の下にある、取り込みでできた添付。
 *
 * @param int $post_id 作品。
 * @return array<string, int> 出どころの URL → 添付 ID。
 */
function doujin_profile_imported_attachments( $post_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => DOUJIN_PROFILE_SOURCE_URL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'no_found_rows'  => true,
		)
	);

	$map = array();
	foreach ( $ids as $id ) {
		$url = (string) get_post_meta( $id, DOUJIN_PROFILE_SOURCE_URL_META, true );
		if ( '' !== $url && ! isset( $map[ $url ] ) ) {
			$map[ $url ] = (int) $id;
		}
	}

	return $map;
}

/**
 * 画像を取ってメディアライブラリに保存する。同じ URL から取り込んだ添付がこの作品の下にあれば、取らずにそれを返す。
 *
 * @param string $image_url 画像の URL (決まったホストだけ)。
 * @param int    $post_id   添付の親にする作品。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_import_image( $image_url, $post_id, $title ) {
	$imported = doujin_profile_imported_attachments( $post_id );
	if ( isset( $imported[ $image_url ] ) ) {
		return $imported[ $image_url ];
	}

	$attachment_id = doujin_profile_sideload_image( $image_url, $post_id, $title );
	if ( ! is_wp_error( $attachment_id ) ) {
		update_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, esc_url_raw( $image_url ) );
	}

	return $attachment_id;
}

/**
 * 画像を取ってメディアライブラリに保存する (コア標準の wp_safe_remote_get() + media_handle_sideload())。
 *
 * download_url() は転送を追ってしまうので使わない (FANZA の無い画像は「準備中」の画像へ 302 で転送される)。
 * 200 で image/* が返ったときだけ保存する。
 *
 * @param string $image_url 画像の URL (決まったホストだけ)。
 * @param int    $post_id   添付の親にする作品。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_sideload_image( $image_url, $post_id, $title ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$tmp = wp_tempnam( $image_url );
	if ( ! $tmp ) {
		return new WP_Error( 'doujin_profile_download_failed', '一時ファイルを作れませんでした。', array( 'status' => 500 ) );
	}

	$response = wp_safe_remote_get(
		$image_url,
		array(
			'timeout'     => 30,
			'redirection' => 0,
			'stream'      => true,
			'filename'    => $tmp,
		)
	);
	$code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$type    = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'content-type' );
	$failure = ImageResponse::failure( $code, $type );
	if ( $failure ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return doujin_profile_wp_error( $failure );
	}

	$file_name = wp_basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) );

	return doujin_profile_attach_file( $tmp, $file_name, $post_id, $title );
}

/**
 * 手元のファイル (WP-CLI の import。spec §6.4) をメディアライブラリに保存する。
 * 同じ道筋から取り込んだ添付がこの作品の下にあれば、それを返す (二重にしない)。
 *
 * @param string $path       ファイルの道筋 (読める絶対パス)。
 * @param int    $post_id    添付の親にする作品。
 * @param string $title      添付のタイトル・代替テキスト。
 * @param string $source_key 出どころの印 (JSON に書かれた道筋。_doujin_profile_source_url に入れて照合に使う)。
 * @param string $file_name  メディアでのファイル名 (空なら元の名前)。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_import_file( $path, $post_id, $title, $source_key, $file_name = '' ) {
	$imported = doujin_profile_imported_attachments( $post_id );
	if ( isset( $imported[ $source_key ] ) ) {
		return $imported[ $source_key ];
	}
	if ( ! is_file( $path ) || ! is_readable( $path ) ) {
		return new WP_Error( 'doujin_profile_file_missing', "ファイルが読めません: {$path}" );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = wp_tempnam( $path );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return new WP_Error( 'doujin_profile_download_failed', '一時ファイルを作れませんでした。' );
	}

	$attachment_id = doujin_profile_attach_file( $tmp, '' !== $file_name ? $file_name : wp_basename( $path ), $post_id, $title );
	if ( ! is_wp_error( $attachment_id ) ) {
		update_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, $source_key );
	}

	return $attachment_id;
}

/**
 * 一時ファイルを添付にする (コア標準の media_handle_sideload())。失敗したら一時ファイルを消す。
 *
 * @param string $tmp       一時ファイル (成功すると uploads へ移される)。
 * @param string $file_name メディアでのファイル名。
 * @param int    $post_id   添付の親。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_attach_file( $tmp, $file_name, $post_id, $title ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$file_name = sanitize_file_name( $file_name );
	$file      = array(
		'name'     => '' !== $file_name ? $file_name : 'image.jpg',
		'tmp_name' => $tmp,
	);
	// 画像以外 (エラーページなど) は media_handle_sideload() の中の wp_check_filetype_and_ext() で弾かれる。
	$attachment_id = media_handle_sideload( $file, $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'doujin_profile_sideload_failed', '画像をメディアに保存できませんでした: ' . $attachment_id->get_error_message(), array( 'status' => 500 ) );
	}

	if ( '' !== $title ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
	}

	return (int) $attachment_id;
}

/**
 * 取り込み直して外れた前の書影を、安全なときだけメディアから消す。
 *
 * 消す条件: この作品の下にあり、取り込みでできたもので (手で上げた画像は消さない)、この作品のサンプルに入っておらず、
 * ほかの投稿のアイキャッチでもなく、この人が消せる。迷うものは残す (メディアから手で消せる)。
 *
 * @param int $attachment_id 前のアイキャッチ。
 * @param int $post_id       作品。
 * @return bool 消したか。
 */
function doujin_profile_delete_imported_cover( $attachment_id, $post_id ) {
	$attachment = get_post( $attachment_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type || (int) $attachment->post_parent !== $post_id ) {
		return false;
	}
	if ( '' === (string) get_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, true ) ) {
		return false;
	}
	if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
		return false;
	}
	$samples = get_post_meta( $post_id, 'doujin_sample_ids', true );
	if ( is_array( $samples ) && in_array( $attachment_id, array_map( 'intval', $samples ), true ) ) {
		return false;
	}
	$used_elsewhere = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => array( $post_id ),
			'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $attachment_id,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'  => true,
		)
	);
	if ( $used_elsewhere ) {
		return false;
	}

	return (bool) wp_delete_attachment( $attachment_id, true );
}
