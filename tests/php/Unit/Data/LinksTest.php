<?php

namespace DoujinProfile\Tests\Unit\Data;

use DoujinProfile\Data\Links;
use DoujinProfile\Tests\Stubs;
use PHPUnit\Framework\TestCase;

final class LinksTest extends TestCase {

	private function links(): Links {
		return new Links( Stubs::httpsOnly(), Stubs::text() );
	}

	public function test_one_normalizes_a_link(): void {
		$link = $this->links()->one(
			array(
				'service'   => 'fanza',
				'url'       => '  https://www.dmm.co.jp/x/ ',
				'label'     => ' <b>FANZA</b> で買う ',
				'sponsored' => 'true',
			)
		);
		$this->assertSame(
			array(
				'service'   => 'fanza',
				'url'       => 'https://www.dmm.co.jp/x/',
				'label'     => 'FANZA で買う',
				'sponsored' => true,
			),
			$link
		);
	}

	public function test_one_rejects_non_https_and_non_array(): void {
		$links = $this->links();
		$this->assertNull( $links->one( array( 'url' => 'http://example.com/' ) ) );
		$this->assertNull( $links->one( array( 'url' => '' ) ) );
		$this->assertNull( $links->one( array( 'service' => 'x' ) ) );
		$this->assertNull( $links->one( 'https://example.com/' ) );
		$this->assertNull( $links->one( null ) );
	}

	public function test_one_defaults_unknown_service_to_other_and_sponsored_false(): void {
		$link = $this->links()->one( array( 'url' => 'https://example.com/', 'service' => 'nope' ) );
		$this->assertSame( 'other', $link['service'] );
		$this->assertSame( '', $link['label'] );
		$this->assertFalse( $link['sponsored'] );

		$link = $this->links()->one( array( 'url' => 'https://example.com/', 'service' => array( 'fanza' ) ) );
		$this->assertSame( 'other', $link['service'] );
	}

	public function test_sponsored_string_false_is_false(): void {
		$link = $this->links()->one( array( 'url' => 'https://example.com/', 'sponsored' => 'false' ) );
		$this->assertFalse( $link['sponsored'] );
		$link = $this->links()->one( array( 'url' => 'https://example.com/', 'sponsored' => 1 ) );
		$this->assertTrue( $link['sponsored'] );
	}

	public function test_label_is_cut_at_100_characters(): void {
		$link = $this->links()->one( array( 'url' => 'https://example.com/', 'label' => str_repeat( 'あ', 150 ) ) );
		$this->assertSame( 100, mb_strlen( $link['label'] ) );
	}

	public function test_many_drops_broken_items_and_caps_at_max(): void {
		$input = array( 'not an array', array( 'url' => 'http://insecure/' ) );
		for ( $i = 0; $i < 35; $i++ ) {
			$input[] = array( 'url' => "https://example.com/{$i}" );
		}
		$clean = $this->links()->many( $input );
		$this->assertCount( Links::MAX, $clean );
		$this->assertSame( 'https://example.com/0', $clean[0]['url'] );
		$this->assertSame( array(), $this->links()->many( 'nope' ) );
	}

	public function test_profile_keeps_only_shops_and_accounts(): void {
		$profile = $this->links()->profile(
			array(
				'shops'    => array( array( 'url' => 'https://shop/' ) ),
				'accounts' => 'broken',
				'extra'    => array(),
			)
		);
		$this->assertSame( array( 'shops', 'accounts' ), array_keys( $profile ) );
		$this->assertCount( 1, $profile['shops'] );
		$this->assertSame( array(), $profile['accounts'] );
		$this->assertSame( array( 'shops' => array(), 'accounts' => array() ), $this->links()->profile( null ) );
	}

	public function test_schema_lists_services_and_requires_service_and_url(): void {
		$schema = Links::schema();
		$this->assertSame( array( 'service', 'url' ), $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertContains( 'other', $schema['properties']['service']['enum'] );
		$this->assertSame( '^https://', $schema['properties']['url']['pattern'] );
		$this->assertSame( 100, $schema['properties']['label']['maxLength'] );
	}
}
