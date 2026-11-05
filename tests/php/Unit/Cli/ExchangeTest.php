<?php

namespace DoujinProfile\Tests\Unit\Cli;

use DoujinProfile\Cli\Exchange;
use DoujinProfile\Tests\Stubs;
use PHPUnit\Framework\TestCase;

final class ExchangeTest extends TestCase {

	public function test_ja_reads_strings_and_ja_keys(): void {
		$this->assertSame( '題名', Exchange::ja( ' 題名 ' ) );
		$this->assertSame( '題名', Exchange::ja( array( 'ja' => '題名', 'en' => 'Title' ) ) );
		$this->assertSame( '', Exchange::ja( array( 'en' => 'Title' ) ) );
		$this->assertSame( '', Exchange::ja( null ) );
		$this->assertSame( '', Exchange::ja( 12 ) );
	}

	public function test_work_key_prefers_key_then_cover_id_then_title(): void {
		$slug = Stubs::slug();
		$this->assertSame( 'my-key', Exchange::workKey( array( 'key' => 'My Key', 'cover' => array( 'id' => 'd_1' ) ), '題名', $slug ) );
		$this->assertSame( 'd_1', Exchange::workKey( array( 'cover' => array( 'id' => 'd_1' ) ), '題名', $slug ) );
		$this->assertSame( '題名', Exchange::workKey( array( 'cover' => 'https://x/a.jpg' ), '題名', $slug ) );
		$this->assertSame( '', Exchange::workKey( array( 'key' => '' ), '', $slug ) );
	}

	public function test_statuses(): void {
		$this->assertSame( 'publish', Exchange::importStatus( array( 'status' => 'publish' ) ) );
		$this->assertSame( 'draft', Exchange::importStatus( array( 'status' => 'future' ) ) );
		$this->assertSame( 'draft', Exchange::importStatus( array() ) );
		$this->assertSame( 'publish', Exchange::exportStatus( 'future' ) );
		$this->assertSame( 'publish', Exchange::exportStatus( 'publish' ) );
		$this->assertSame( 'draft', Exchange::exportStatus( 'private' ) );
	}

	public function test_links_from_json_flattens_labels_and_skips_non_arrays(): void {
		$links = Exchange::linksFromJson(
			array(
				array( 'service' => 'fanza', 'url' => 'https://a/', 'label' => array( 'ja' => '日本語', 'en' => 'English' ) ),
				array( 'service' => 'x', 'url' => 'https://b/' ),
				'broken',
			)
		);
		$this->assertCount( 2, $links );
		$this->assertSame( '日本語', $links[0]['label'] );
		$this->assertSame( '', $links[1]['label'] );
		$this->assertSame( array(), Exchange::linksFromJson( 'nope' ) );
	}

	public function test_links_to_json_round_trip(): void {
		$json = Exchange::linksToJson(
			array(
				array( 'service' => 'fanza', 'url' => 'https://a/', 'label' => '日本語', 'sponsored' => true ),
				array( 'service' => 'x', 'url' => 'https://b/', 'label' => '', 'sponsored' => false ),
			)
		);
		$this->assertSame(
			array(
				array( 'service' => 'fanza', 'url' => 'https://a/', 'label' => array( 'ja' => '日本語' ), 'sponsored' => true ),
				array( 'service' => 'x', 'url' => 'https://b/' ),
			),
			$json
		);
	}

	public function test_image_kind(): void {
		$this->assertSame( Exchange::IMAGE_PRODUCT, Exchange::imageKind( array( 'service' => 'fanza', 'id' => 'd_1' ) ) );
		$this->assertSame( Exchange::IMAGE_URL, Exchange::imageKind( ' https://x/a.jpg ' ) );
		$this->assertSame( Exchange::IMAGE_PATH, Exchange::imageKind( 'samples/01.jpg' ) );
		$this->assertSame( Exchange::IMAGE_PATH, Exchange::imageKind( 'http://insecure/a.jpg' ) ); // http は URL とみなさず道筋として扱われ、読めないファイルになる
		$this->assertSame( Exchange::IMAGE_BAD, Exchange::imageKind( '  ' ) );
		$this->assertSame( Exchange::IMAGE_BAD, Exchange::imageKind( array( 'service' => 'fanza' ) ) );
		$this->assertSame( Exchange::IMAGE_BAD, Exchange::imageKind( null ) );
	}

	public function test_resolve_path(): void {
		$this->assertSame( '/abs/a.jpg', Exchange::resolvePath( '/abs/a.jpg', '/images' ) );
		$this->assertSame( '/images/samples/a.jpg', Exchange::resolvePath( 'samples/a.jpg', '/images' ) );
	}

	public function test_describe(): void {
		$this->assertSame( '作品番号 d_1', Exchange::describe( array( 'id' => 'd_1' ) ) );
		$this->assertSame( '(形が違う)', Exchange::describe( array( 'x' => 1 ) ) );
		$this->assertSame( 'a.jpg', Exchange::describe( 'a.jpg' ) );
		$this->assertSame( '(形が違う)', Exchange::describe( 5 ) );
	}

	public function test_describe_samples(): void {
		$this->assertSame( '  サンプル: 0 件', Exchange::describeSamples( array() ) );
		$this->assertSame( '  サンプル: 1 件 (a.jpg)', Exchange::describeSamples( array( 'a.jpg' ) ) );
		$this->assertSame( '  サンプル: 3 件 (a.jpg … c.jpg)', Exchange::describeSamples( array( 'a.jpg', 'b.jpg', 'c.jpg' ) ) );
	}
}
