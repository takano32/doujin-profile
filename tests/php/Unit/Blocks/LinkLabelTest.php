<?php

namespace DoujinProfile\Tests\Unit\Blocks;

use DoujinProfile\Blocks\LinkLabel;
use PHPUnit\Framework\TestCase;

final class LinkLabelTest extends TestCase {

	private function link( string $service, string $label = '' ): array {
		return array( 'service' => $service, 'url' => 'https://example.com/', 'label' => $label, 'sponsored' => false );
	}

	public function test_explicit_label_wins(): void {
		$this->assertSame( '通販', LinkLabel::of( $this->link( 'fanza', '通販' ) ) );
		$this->assertSame( '通販', LinkLabel::of( $this->link( 'fanza', '通販' ), true ) );
	}

	public function test_service_name_with_or_without_verb(): void {
		$this->assertSame( 'FANZA', LinkLabel::of( $this->link( 'fanza' ) ) );
		$this->assertSame( 'FANZAで見る', LinkLabel::of( $this->link( 'fanza' ), true ) );
		$this->assertSame( 'とらのあなで見る', LinkLabel::of( $this->link( 'toranoana' ), true ) );
	}

	public function test_other_service(): void {
		$this->assertSame( 'リンク', LinkLabel::of( $this->link( 'other' ) ) );
		$this->assertSame( '販売ページを見る', LinkLabel::of( $this->link( 'other' ), true ) );
	}

	public function test_rel(): void {
		$this->assertSame( 'noopener', LinkLabel::rel( false ) );
		$this->assertSame( 'noopener sponsored', LinkLabel::rel( true ) );
	}
}
