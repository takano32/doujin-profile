<?php

namespace DoujinProfile\Tests\Unit\Import;

use DoujinProfile\Import\ImportTitle;
use PHPUnit\Framework\TestCase;

final class ImportTitleTest extends TestCase {

	public function test_resolve_prefers_source_then_request_then_product_id(): void {
		$this->assertSame( 'DLsite の題名', ImportTitle::resolve( 'DLsite の題名', '入力中', 'RJ1' ) );
		$this->assertSame( '入力中', ImportTitle::resolve( '', '  入力中 ', 'd_1' ) );
		$this->assertSame( 'd_1', ImportTitle::resolve( '', '   ', 'd_1' ) );
	}

	public function test_sample_title_is_zero_padded(): void {
		$this->assertSame( '作品 サンプル 01', ImportTitle::sample( '作品', 1 ) );
		$this->assertSame( '作品 サンプル 12', ImportTitle::sample( '作品', 12 ) );
	}
}
