<?php

namespace DoujinProfile\Tests\Unit\Cli;

use DoujinProfile\Cli\EmailMask;
use PHPUnit\Framework\TestCase;

final class EmailMaskTest extends TestCase {

	public function test_mask_keeps_first_character_and_domain(): void {
		$this->assertSame( 'c***@example.com', EmailMask::mask( 'circle@example.com' ) );
		$this->assertSame( 'さ***@example.com', EmailMask::mask( 'さーくる@example.com' ) );
	}

	public function test_without_at_sign(): void {
		$this->assertSame( '***', EmailMask::mask( 'not-an-address' ) );
	}
}
