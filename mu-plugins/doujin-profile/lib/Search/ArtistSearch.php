<?php
/**
 * サイト内検索で作家名でも当たる手当て (spec §6.0。T31.2) の SQL の組み立て。用語の検索は糊。
 */

namespace DoujinProfile\Search;

final class ArtistSearch {

	/**
	 * term_taxonomy_id を整数にそろえ、重複を除く。
	 *
	 * @param array $tt_ids 見つかった用語の term_taxonomy_id。
	 * @return int[]
	 */
	public static function uniqueIds( array $tt_ids ): array {
		return array_values( array_unique( array_map( 'intval', $tt_ids ) ) );
	}

	/**
	 * コアの検索の SQL を " AND ( 作家の条件 OR ( 1=1 $search ) ) " で包む。
	 *
	 * コアが作る $search は " AND ((…) AND (…)) " の形 (ログインしていなければ末尾に
	 * " AND (post_password = '') " が付く)。中身は書き換えない。
	 *
	 * @param string $search              コアの検索の SQL (WHERE 句の一部)。
	 * @param int[]  $tt_ids              作家の term_taxonomy_id (1 つ以上)。
	 * @param string $posts_table         投稿テーブル名。
	 * @param string $relationships_table term_relationships テーブル名。
	 * @param bool   $hide_protected      パスワード付きの投稿を出さないか (ログインしていないとき)。
	 * @return string
	 */
	public static function wrap( string $search, array $tt_ids, string $posts_table, string $relationships_table, bool $hide_protected ): string {
		$by_artist = "{$posts_table}.ID IN ( SELECT object_id FROM {$relationships_table} WHERE term_taxonomy_id IN (" . implode( ',', $tt_ids ) . ') )';
		if ( $hide_protected ) {
			$by_artist .= " AND {$posts_table}.post_password = ''"; // コアの検索と同じく、パスワード付きの投稿は出さない。
		}

		return " AND ( ( {$by_artist} ) OR ( 1=1 {$search} ) ) ";
	}
}
