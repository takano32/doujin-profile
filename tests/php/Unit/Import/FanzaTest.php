<?php

namespace DoujinProfile\Tests\Unit\Import;

use DoujinProfile\Import\Fanza;
use PHPUnit\Framework\TestCase;

final class FanzaTest extends TestCase {

	public function test_source_builds_fixed_urls_without_title(): void {
		$source = Fanza::source( 'd_737824' );
		$this->assertSame( 'https://doujin-assets.dmm.co.jp/digital/comic/d_737824/d_737824pl.jpg', $source['image_url'] );
		$this->assertSame( 'https://www.dmm.co.jp/dc/doujin/-/detail/=/cid=d_737824/', $source['shop_url'] );
		$this->assertSame( '', $source['title'] );
		$this->assertSame( '', $source['release_at'] );
		$this->assertNull( $source['samples'] );
	}

	public function test_sample_url_is_zero_padded(): void {
		$this->assertSame( 'https://doujin-assets.dmm.co.jp/digital/comic/d_737824/d_737824jp-001.jpg', Fanza::sampleUrl( 'd_737824', 1 ) );
		$this->assertSame( 'https://doujin-assets.dmm.co.jp/digital/comic/d_737824/d_737824jp-012.jpg', Fanza::sampleUrl( 'd_737824', 12 ) );
	}

	public function test_connection_failure(): void {
		$failure = Fanza::connectionFailure();
		$this->assertSame( 'doujin_profile_fetch_failed', $failure->code );
		$this->assertSame( 502, $failure->status );
		$this->assertStringContainsString( 'FANZA', $failure->message );
	}
}
