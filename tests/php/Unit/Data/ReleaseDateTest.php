<?php

namespace DoujinProfile\Tests\Unit\Data;

use DateTimeZone;
use DoujinProfile\Data\ReleaseDate;
use PHPUnit\Framework\TestCase;

final class ReleaseDateTest extends TestCase {

	private function tokyo(): DateTimeZone {
		return new DateTimeZone( 'Asia/Tokyo' );
	}

	public function test_parse_reads_offsetless_values_in_site_timezone(): void {
		$datetime = ReleaseDate::parse( ' 2026-05-29T00:00 ', $this->tokyo() );
		$this->assertNotNull( $datetime );
		$this->assertSame( '2026-05-28T15:00:00+00:00', $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_ATOM ) );
	}

	public function test_parse_keeps_an_explicit_offset(): void {
		$datetime = ReleaseDate::parse( '2026-05-29T00:00:00Z', $this->tokyo() );
		$this->assertSame( 1780012800, $datetime->getTimestamp() ); // 2026-05-29T00:00:00Z
	}

	public function test_parse_returns_null_for_empty_or_unreadable(): void {
		$tz = $this->tokyo();
		$this->assertNull( ReleaseDate::parse( '', $tz ) );
		$this->assertNull( ReleaseDate::parse( '   ', $tz ) );
		$this->assertNull( ReleaseDate::parse( null, $tz ) );
		$this->assertNull( ReleaseDate::parse( 123, $tz ) );
		$this->assertNull( ReleaseDate::parse( '2026-13-45', $tz ) );
		$this->assertNull( ReleaseDate::parse( 'nonsense', $tz ) );
	}

	public function test_normalize_formats_in_site_timezone(): void {
		$tz = $this->tokyo();
		$this->assertSame( '2026-05-29T09:00:00+09:00', ReleaseDate::normalize( '2026-05-29T00:00:00Z', $tz ) );
		$this->assertSame( '2026-05-29T00:00:00+09:00', ReleaseDate::normalize( '2026-05-29', $tz ) );
		$this->assertSame( '', ReleaseDate::normalize( 'nonsense', $tz ) );
		$this->assertSame( '', ReleaseDate::normalize( array(), $tz ) );
	}

	public function test_post_date_gmt_is_the_earlier_of_release_and_now(): void {
		$tz  = $this->tokyo();
		$now = 1780000000; // 2026-05-28T20:26:40Z
		$this->assertSame( '2026-05-28 15:00:00', ReleaseDate::postDateGmt( '2026-05-29T00:00:00+09:00', $tz, $now ) ); // 過去 → 発売日時
		$this->assertSame( '2026-05-28 20:26:40', ReleaseDate::postDateGmt( '2030-01-01T00:00:00+09:00', $tz, $now ) ); // 未来 → 今
		$this->assertNull( ReleaseDate::postDateGmt( '', $tz, $now ) );
		$this->assertNull( ReleaseDate::postDateGmt( 'nonsense', $tz, $now ) );
	}

	public function test_is_upcoming(): void {
		$tz = $this->tokyo();
		$this->assertTrue( ReleaseDate::isUpcoming( ReleaseDate::parse( '2030-01-01', $tz ), 1780000000 ) );
		$this->assertFalse( ReleaseDate::isUpcoming( ReleaseDate::parse( '2020-01-01', $tz ), 1780000000 ) );
		$this->assertFalse( ReleaseDate::isUpcoming( null, 1780000000 ) );
	}

	public function test_rest_schema_constants(): void {
		$this->assertSame( 1, preg_match( '/' . ReleaseDate::REST_PATTERN . '/', '2026-05-29T00:00' ) );
		$this->assertSame( 1, preg_match( '/' . ReleaseDate::REST_PATTERN . '/', '' ) );
		$this->assertSame( 0, preg_match( '/' . ReleaseDate::REST_PATTERN . '/', '2026-05-29' ) );
		$this->assertSame( 32, ReleaseDate::REST_MAX_LENGTH );
	}
}
