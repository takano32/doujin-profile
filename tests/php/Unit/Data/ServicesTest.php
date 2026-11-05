<?php

namespace DoujinProfile\Tests\Unit\Data;

use DoujinProfile\Data\Services;
use PHPUnit\Framework\TestCase;

final class ServicesTest extends TestCase {

	public function test_all_has_sixteen_services_ending_with_other(): void {
		$all = Services::all();
		$this->assertCount( 16, $all );
		$this->assertSame( 'other', array_key_last( $all ) );
		$this->assertSame( 'FANZA', $all['fanza'] );
		$this->assertSame( 'その他', $all['other'] );
	}

	public function test_has(): void {
		$this->assertTrue( Services::has( 'dlsite' ) );
		$this->assertFalse( Services::has( 'DLsite' ) );
		$this->assertFalse( Services::has( '' ) );
	}

	public function test_name_falls_back_to_other(): void {
		$this->assertSame( 'メロンブックス', Services::name( 'melonbooks' ) );
		$this->assertSame( 'その他', Services::name( 'unknown' ) );
	}
}
