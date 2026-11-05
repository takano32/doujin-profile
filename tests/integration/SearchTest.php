<?php

namespace DoujinProfile\Tests\Integration;

use WP_Query;

/**
 * サイト内検索で作家名でも当たること (spec §6.0。T.31.2)。
 */
final class SearchTest extends IntegrationTestCase {

	public function test_search_matches_artist_names_and_still_matches_titles(): void {
		$this->useSite();
		wp_set_current_user( 0 );
		$one = self::workBySlug( 'work-one' );
		$two = self::workBySlug( 'work-two' );

		$by_artist = new WP_Query( array( 's' => '作家A', 'post_type' => 'post' ) );
		$this->assertContains( $one->ID, wp_list_pluck( $by_artist->posts, 'ID' ), 'タイトルに無い作家名でも当たる' );
		$this->assertNotContains( $two->ID, wp_list_pluck( $by_artist->posts, 'ID' ) );

		$by_title = new WP_Query( array( 's' => '作品その二', 'post_type' => 'post' ) );
		$this->assertSame( array( $two->ID ), wp_list_pluck( $by_title->posts, 'ID' ), 'コアの検索はそのまま' );

		$none = new WP_Query( array( 's' => 'zzz-no-such-artist', 'post_type' => 'post' ) );
		$this->assertSame( array(), $none->posts );
	}
}
