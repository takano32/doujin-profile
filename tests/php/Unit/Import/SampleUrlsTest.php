<?php

namespace DoujinProfile\Tests\Unit\Import;

use DoujinProfile\Failure;
use DoujinProfile\Import\Fanza;
use DoujinProfile\Import\SampleUrls;
use PHPUnit\Framework\TestCase;

final class SampleUrlsTest extends TestCase {

	public function test_fanza_count_stops_at_first_non_image(): void {
		$seen  = array();
		$probe = static function ( $url ) use ( &$seen ) {
			$seen[] = $url;
			$n      = (int) substr( $url, -7, 3 );

			return $n <= 3 ? array( 'code' => 200, 'type' => 'image/jpeg' ) : array( 'code' => 302, 'type' => 'text/html' );
		};
		$this->assertSame( 3, SampleUrls::fanzaCount( 'd_737824', 80, $probe ) );
		$this->assertCount( 4, $seen ); // 4 枚目で止まる
		$this->assertSame( Fanza::sampleUrl( 'd_737824', 4 ), end( $seen ) );
	}

	public function test_fanza_count_treats_non_image_200_as_end(): void {
		$probe = static fn( $url ) => array( 'code' => 200, 'type' => 'text/html; charset=utf-8' );
		$this->assertSame( 0, SampleUrls::fanzaCount( 'd_1', 80, $probe ) );
	}

	public function test_fanza_count_respects_max(): void {
		$probe = static fn( $url ) => array( 'code' => 200, 'type' => 'image/jpeg' );
		$this->assertSame( 5, SampleUrls::fanzaCount( 'd_1', 5, $probe ) );
	}

	public function test_fanza_count_fails_when_connection_fails(): void {
		$result = SampleUrls::fanzaCount( 'd_1', 80, static fn( $url ) => null );
		$this->assertInstanceOf( Failure::class, $result );
		$this->assertSame( 'doujin_profile_fetch_failed', $result->code );
	}

	public function test_fanza_list(): void {
		$this->assertSame( array(), SampleUrls::fanzaList( 'd_1', 0 ) );
		$this->assertSame( array( Fanza::sampleUrl( 'd_1', 1 ), Fanza::sampleUrl( 'd_1', 2 ) ), SampleUrls::fanzaList( 'd_1', 2 ) );
	}

	public function test_one_uses_source_list_for_dlsite_and_numbering_for_fanza(): void {
		$dlsite = array( 'service' => 'dlsite', 'id' => 'RJ1' );
		$source = array( 'samples' => array( 'https://img.dlsite.jp/a.jpg', 'https://img.dlsite.jp/b.jpg' ) );
		$this->assertSame( 'https://img.dlsite.jp/b.jpg', SampleUrls::one( $dlsite, $source, 2 ) );
		$this->assertSame( '', SampleUrls::one( $dlsite, $source, 3 ) );

		$fanza = array( 'service' => 'fanza', 'id' => 'd_737824' );
		$this->assertSame( Fanza::sampleUrl( 'd_737824', 7 ), SampleUrls::one( $fanza, array( 'samples' => null ), 7 ) );
	}
}
