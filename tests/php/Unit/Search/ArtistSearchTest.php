<?php

namespace DoujinProfile\Tests\Unit\Search;

use DoujinProfile\Search\ArtistSearch;
use PHPUnit\Framework\TestCase;

final class ArtistSearchTest extends TestCase {

	public function test_unique_ids_are_integers_without_duplicates(): void {
		$this->assertSame( array( 3, 5 ), ArtistSearch::uniqueIds( array( '3', 5, 3, '5' ) ) );
	}

	public function test_wrap_keeps_core_sql_and_ors_the_artist_condition(): void {
		$search = " AND (((wp_posts.post_title LIKE '%x%')))";
		$sql    = ArtistSearch::wrap( $search, array( 3, 5 ), 'wp_posts', 'wp_term_relationships', false );
		$this->assertSame(
			" AND ( ( wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (3,5) ) ) OR ( 1=1 {$search} ) ) ",
			$sql
		);
	}

	public function test_wrap_hides_password_protected_posts_for_visitors(): void {
		$sql = ArtistSearch::wrap( ' AND (x)', array( 3 ), 'wp_posts', 'wp_term_relationships', true );
		$this->assertStringContainsString( "AND wp_posts.post_password = '' )", $sql );
	}
}
