<?php
/**
 * 保存時の 1 ルール: 公開日時 = 発売日時と今の早いほう (spec §6.0)。計算は lib/Data/ReleaseDate.php。
 *
 * 未来の日付のままだと「予約」になって公開されないため。公開する作品 (publish。コアが未来の日付で future に変えたものも含む) で、
 * 発売日時があるときだけ効く。下書きの日付はコアの既定に任せる。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 発売日時は、同じ保存で渡された meta_input があればそれを、無ければ保存済みのメタを見る
 * (REST はメタを投稿のあとに保存するので、そちらは doujin_profile_release_at_changed() が受けて再保存する)。
 *
 * @param array $data    保存される投稿データ (サニタイズ済み)。
 * @param array $postarr 渡された投稿データ。
 * @return array
 */
function doujin_profile_apply_release_date( $data, $postarr ) {
	if ( 'post' !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
		return $data;
	}

	if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( 'doujin_release_at', $postarr['meta_input'] ) ) {
		$release_at = doujin_profile_sanitize_release_at( $postarr['meta_input']['doujin_release_at'] );
	} elseif ( ! empty( $postarr['ID'] ) ) {
		$release_at = get_post_meta( (int) $postarr['ID'], 'doujin_release_at', true );
	} else {
		return $data;
	}

	$date_gmt = doujin_profile_release_post_date_gmt( $release_at );
	if ( null === $date_gmt ) {
		return $data;
	}

	$data['post_date_gmt'] = $date_gmt;
	$data['post_date']     = get_date_from_gmt( $date_gmt );
	$data['post_status']   = 'publish'; // 今より先にはならないので、予約 (future) にはしない。

	return $data;
}
add_filter( 'wp_insert_post_data', 'doujin_profile_apply_release_date', 10, 2 );

/**
 * 発売日時のメタが (REST などで投稿の保存のあとに) 変わったら、公開日時をルールに合わせて再保存する。
 *
 * 再保存は wp_update_post() → doujin_profile_apply_release_date() の経路で、ここでは日付を計算しない。
 * 公開日時がすでに合っているときは何もしない (無駄な保存をしない)。
 *
 * @param int    $meta_id  メタ ID。
 * @param int    $post_id  投稿 ID。
 * @param string $meta_key メタのキー。
 */
function doujin_profile_release_at_changed( $meta_id, $post_id, $meta_key ) {
	if ( 'doujin_release_at' !== $meta_key ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}

	$date_gmt = doujin_profile_release_post_date_gmt( get_post_meta( $post_id, 'doujin_release_at', true ) );
	if ( null === $date_gmt || ( $date_gmt === $post->post_date_gmt && 'publish' === $post->post_status ) ) {
		return;
	}

	wp_update_post( array( 'ID' => $post_id ) );
}
add_action( 'added_post_meta', 'doujin_profile_release_at_changed', 10, 3 );
add_action( 'updated_post_meta', 'doujin_profile_release_at_changed', 10, 3 );
