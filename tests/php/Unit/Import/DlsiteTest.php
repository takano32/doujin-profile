<?php

namespace DoujinProfile\Tests\Unit\Import;

use DateTimeZone;
use DoujinProfile\Failure;
use DoujinProfile\Import\Dlsite;
use DoujinProfile\Tests\Stubs;
use PHPUnit\Framework\TestCase;

final class DlsiteTest extends TestCase {

	private function parse( $json, int $max = 80 ) {
		return Dlsite::parse( $json, 'RJ01634826', Stubs::text(), new DateTimeZone( 'Asia/Tokyo' ), $max );
	}

	private function info( array $overrides = array() ): array {
		return array(
			array_merge(
				array(
					'work_name'     => ' <b>作品名</b> ',
					'regist_date'   => '2026-05-29 00:00:00',
					'site_id'       => 'maniax',
					'image_main'    => array( 'url' => '//img.dlsite.jp/modpub/images2/work/doujin/RJ01635000/RJ01634826_img_main.jpg' ),
					'image_samples' => array(
						array( 'url' => '//img.dlsite.jp/x/smp1.jpg' ),
						array( 'url' => '//evil.example/smp2.jpg' ),
						array( 'nope' => 1 ),
						array( 'url' => 'https://img.dlsite.jp/x/smp3.jpg' ),
					),
				),
				$overrides
			),
		);
	}

	public function test_api_url_encodes_workno(): void {
		$this->assertSame( 'https://www.dlsite.com/maniax/api/=/product.json?workno=RJ01634826', Dlsite::apiUrl( 'RJ01634826' ) );
	}

	public function test_image_url_accepts_only_https_img_dlsite_jp(): void {
		$this->assertSame( 'https://img.dlsite.jp/a/b.jpg', Dlsite::imageUrl( '//img.dlsite.jp/a/b.jpg' ) );
		$this->assertSame( 'https://img.dlsite.jp/a/b.jpg', Dlsite::imageUrl( 'https://img.dlsite.jp/a/b.jpg' ) );
		$this->assertSame( '', Dlsite::imageUrl( 'http://img.dlsite.jp/a/b.jpg' ) );
		$this->assertSame( '', Dlsite::imageUrl( '//img.dlsite.jp.evil.example/a.jpg' ) );
		$this->assertSame( '', Dlsite::imageUrl( '//evil.example/img.dlsite.jp/a.jpg' ) );
		$this->assertSame( '', Dlsite::imageUrl( '' ) );
		$this->assertSame( '', Dlsite::imageUrl( array( '//img.dlsite.jp/a.jpg' ) ) );
	}

	public function test_parse_reads_title_release_shop_and_samples(): void {
		$source = $this->parse( $this->info() );
		$this->assertIsArray( $source );
		$this->assertSame( 'https://img.dlsite.jp/modpub/images2/work/doujin/RJ01635000/RJ01634826_img_main.jpg', $source['image_url'] );
		$this->assertSame( 'https://www.dlsite.com/maniax/work/=/product_id/RJ01634826.html', $source['shop_url'] );
		$this->assertSame( '作品名', $source['title'] );
		$this->assertSame( '2026-05-29T00:00:00+09:00', $source['release_at'] );
		$this->assertSame( array( 'https://img.dlsite.jp/x/smp1.jpg', 'https://img.dlsite.jp/x/smp3.jpg' ), $source['samples'] ); // 他のホストと壊れた件は捨てる
	}

	public function test_parse_caps_samples(): void {
		$source = $this->parse( $this->info(), 1 );
		$this->assertSame( array( 'https://img.dlsite.jp/x/smp1.jpg' ), $source['samples'] );
	}

	public function test_parse_uses_site_id_when_well_formed(): void {
		$this->assertStringContainsString( '/girls/work/', $this->parse( $this->info( array( 'site_id' => 'girls' ) ) )['shop_url'] );
		$this->assertStringContainsString( '/maniax/work/', $this->parse( $this->info( array( 'site_id' => '../evil' ) ) )['shop_url'] );
		$this->assertStringContainsString( '/maniax/work/', $this->parse( $this->info( array( 'site_id' => null ) ) )['shop_url'] );
	}

	public function test_parse_tolerates_missing_optional_fields(): void {
		$source = $this->parse( $this->info( array( 'work_name' => null, 'regist_date' => 'bad', 'image_samples' => 'nope' ) ) );
		$this->assertSame( '', $source['title'] );
		$this->assertSame( '', $source['release_at'] );
		$this->assertSame( array(), $source['samples'] );
	}

	public function test_parse_empty_list_is_not_found(): void {
		$failure = $this->parse( array() );
		$this->assertInstanceOf( Failure::class, $failure );
		$this->assertSame( 'doujin_profile_not_found', $failure->code );
		$this->assertSame( 404, $failure->status );
		$this->assertStringContainsString( 'RJ01634826', $failure->message );

		$this->assertSame( 'doujin_profile_not_found', $this->parse( null )->code );
		$this->assertSame( 'doujin_profile_not_found', $this->parse( array( 'not a list' ) )->code );
	}

	public function test_parse_rejects_unexpected_image_host(): void {
		$failure = $this->parse( $this->info( array( 'image_main' => array( 'url' => '//cdn.example/main.jpg' ) ) ) );
		$this->assertInstanceOf( Failure::class, $failure );
		$this->assertSame( 'doujin_profile_bad_image_url', $failure->code );
		$this->assertSame( 502, $failure->status );

		$this->assertSame( 'doujin_profile_bad_image_url', $this->parse( $this->info( array( 'image_main' => 'string' ) ) )->code );
	}

	public function test_http_failures(): void {
		$this->assertSame( 502, Dlsite::connectionFailure()->status );
		$this->assertStringContainsString( 'HTTP 503', Dlsite::httpFailure( 503 )->message );
	}
}
