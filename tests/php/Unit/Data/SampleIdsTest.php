<?php

namespace DoujinProfile\Tests\Unit\Data;

use DoujinProfile\Data\SampleIds;
use PHPUnit\Framework\TestCase;

final class SampleIdsTest extends TestCase {

	public function test_keeps_positive_unique_integers_in_order(): void {
		$this->assertSame( array( 3, 1, 2 ), SampleIds::sanitize( array( 3, '1', 2.0, 3, 0, -1, 'x', null, '1' ) ) );
	}

	public function test_non_array_is_empty(): void {
		$this->assertSame( array(), SampleIds::sanitize( '1,2,3' ) );
		$this->assertSame( array(), SampleIds::sanitize( null ) );
	}

	public function test_caps_at_max(): void {
		$this->assertCount( SampleIds::MAX, SampleIds::sanitize( range( 1, 100 ) ) );
		$this->assertSame( 80, SampleIds::MAX );
	}
}
