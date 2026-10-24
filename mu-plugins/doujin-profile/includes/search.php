<?php
/**
 * サイト内検索で作家名でも当たる手当て (spec §6.0。T.31.2)。
 *
 * 標準の検索はタイトル・本文・抜粋を見る。検索語に名前が部分一致する作家 (分類 artist) があれば、
 * その作家の作品も結果に含める。検索の SQL (posts_search) の前に OR で足すだけで、
 * 並び順・ページ送り・検索ブロックは標準のまま。高度な絞り込みは作らない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 検索語に当たる作家の作品を、検索の WHERE 句に OR で足す。
 *
 * コアが作る $search は " AND ((…) AND (…)) " の形 (ログインしていなければ末尾に
 * " AND (post_password = '') " が付く)。中身を書き換えずに、全体を
 * " AND ( 作家の条件 OR ( 1=1 $search ) ) " で包む。
 *
 * @param string   $search 検索の SQL (WHERE 句の一部)。
 * @param WP_Query $query  クエリー。
 * @return string
 */
function doujin_profile_search_by_artist( $search, $query ) {
	global $wpdb;

	if ( '' === $search || empty( $query->query_vars['search_terms'] ) || ! is_array( $query->query_vars['search_terms'] ) ) {
		return $search;
	}

	$tt_ids = array();
	foreach ( $query->query_vars['search_terms'] as $term ) {
		$found = get_terms(
			array(
				'taxonomy'   => 'artist',
				'name__like' => $term,
				'fields'     => 'tt_ids',
				'hide_empty' => true,
			)
		);
		if ( is_array( $found ) ) {
			$tt_ids = array_merge( $tt_ids, $found );
		}
	}

	$tt_ids = array_values( array_unique( array_map( 'intval', $tt_ids ) ) );
	if ( ! $tt_ids ) {
		return $search;
	}

	$by_artist = "{$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $tt_ids ) . ') )';
	if ( ! is_user_logged_in() ) {
		$by_artist .= " AND {$wpdb->posts}.post_password = ''"; // コアの検索と同じく、パスワード付きの投稿は出さない。
	}

	return " AND ( ( {$by_artist} ) OR ( 1=1 {$search} ) ) ";
}
add_filter( 'posts_search', 'doujin_profile_search_by_artist', 10, 2 );
