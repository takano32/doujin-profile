<?php

namespace DoujinProfile\Tests\Unit\Head;

use DoujinProfile\Head\MetaTags;
use DoujinProfile\Tests\Stubs;
use PHPUnit\Framework\TestCase;

final class MetaTagsTest extends TestCase {

	public function test_description_strips_tags_and_collapses_whitespace(): void {
		$this->assertSame( 'ひとこと あらすじ', MetaTags::description( "<p>ひとこと</p>\n\n  あらすじ ", 'リード', Stubs::text() ) );
		$this->assertSame( 'リード', MetaTags::description( "<p></p>  \n", 'リード', Stubs::text() ) );
	}

	public function test_build_without_image(): void {
		$meta = MetaTags::build(
			array( 'title' => 'サークル', 'description' => '', 'url' => 'https://ftsf.dp.no32.tk/', 'type' => 'website', 'image' => null ),
			'サークル'
		);
		$names = array_map( static fn( $m ) => $m[1], $meta );
		$this->assertSame( array( 'og:site_name', 'og:locale', 'og:type', 'og:url', 'og:title', 'twitter:card' ), $names ); // description は空なので出ない
		$this->assertSame( array( 'name', 'twitter:card', 'summary' ), end( $meta ) );
		$this->assertSame( array( 'property', 'og:locale', 'ja_JP' ), $meta[1] );
	}

	public function test_build_with_image_adds_size_and_large_card(): void {
		$meta = MetaTags::build(
			array( 'title' => '作品', 'description' => 'あらすじ', 'url' => 'https://x/', 'type' => 'article', 'image' => array( 'url' => 'https://x/i.jpg', 'width' => 1024, 'height' => 768 ) ),
			'サークル'
		);
		$by_name = array();
		foreach ( $meta as $m ) {
			$by_name[ $m[1] ] = $m[2];
		}
		$this->assertSame( 'https://x/i.jpg', $by_name['og:image'] );
		$this->assertSame( '1024', $by_name['og:image:width'] );
		$this->assertSame( '768', $by_name['og:image:height'] );
		$this->assertSame( 'summary_large_image', $by_name['twitter:card'] );
		$this->assertSame( 'あらすじ', $by_name['description'] );
		$this->assertSame( 'article', $by_name['og:type'] );
	}

	public function test_build_omits_size_when_unknown(): void {
		$meta  = MetaTags::build(
			array( 'title' => 't', 'description' => 'd', 'url' => 'u', 'type' => 'article', 'image' => array( 'url' => 'https://x/i.jpg', 'width' => 0, 'height' => 0 ) ),
			's'
		);
		$names = array_map( static fn( $m ) => $m[1], $meta );
		$this->assertContains( 'og:image', $names );
		$this->assertNotContains( 'og:image:width', $names );
	}
}
