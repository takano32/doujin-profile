<?php

namespace DoujinProfile\Tests\Unit\Data;

use DateTimeZone;
use DoujinProfile\Data\ReleaseDate;
use DoujinProfile\Data\ReleaseLabel;
use DoujinProfile\Tests\Stubs;
use PHPUnit\Framework\TestCase;

final class ReleaseLabelTest extends TestCase {

	public function test_sanitize_strips_and_cuts_at_200(): void {
		$this->assertSame( '2026年5月下旬', ReleaseLabel::sanitize( ' <em>2026年5月下旬</em> ', Stubs::text() ) );
		$this->assertSame( 200, mb_strlen( ReleaseLabel::sanitize( str_repeat( 'あ', 300 ), Stubs::text() ) ) );
		$this->assertSame( '', ReleaseLabel::sanitize( array( 'x' ), Stubs::text() ) );
	}

	public function test_auto_label(): void {
		$this->assertSame( '2026年5月30日発売', ReleaseLabel::auto( '2026年5月30日', false ) );
		$this->assertSame( '発売予定日: 2026年5月30日', ReleaseLabel::auto( '2026年5月30日', true ) );
	}

	public function test_info_uses_explicit_label_when_present(): void {
		$tz   = new DateTimeZone( 'Asia/Tokyo' );
		$info = ReleaseLabel::info( '5月下旬', ReleaseDate::parse( '2026-05-30', $tz ), 1700000000, $this->formatter(), $tz );
		$this->assertSame( '5月下旬', $info['label'] );
		$this->assertTrue( $info['upcoming'] );
		$this->assertSame( '2026-05-30T00:00:00+09:00', $info['release_at'] );
	}

	public function test_info_builds_label_from_date(): void {
		$tz = new DateTimeZone( 'Asia/Tokyo' );
		$dt = ReleaseDate::parse( '2026-05-30T00:00:00+09:00', $tz );

		$past = ReleaseLabel::info( '', $dt, 1800000000, $this->formatter(), $tz );
		$this->assertSame( '2026年5月30日発売', $past['label'] );
		$this->assertFalse( $past['upcoming'] );

		$future = ReleaseLabel::info( '', $dt, 1700000000, $this->formatter(), $tz );
		$this->assertSame( '発売予定日: 2026年5月30日', $future['label'] );
		$this->assertTrue( $future['upcoming'] );
	}

	public function test_info_without_date(): void {
		$tz = new DateTimeZone( 'Asia/Tokyo' );
		$this->assertSame(
			array( 'label' => '', 'upcoming' => false, 'release_at' => '' ),
			ReleaseLabel::info( '', null, 1700000000, $this->formatter(), $tz )
		);
	}

	/** wp_date( 'Y年n月j日' ) の代わり (東京)。 */
	private function formatter(): callable {
		return static function ( $timestamp ) {
			return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) )->format( 'Y年n月j日' );
		};
	}
}
