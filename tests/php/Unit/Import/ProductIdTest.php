<?php

namespace DoujinProfile\Tests\Unit\Import;

use DoujinProfile\Import\ProductId;
use PHPUnit\Framework\TestCase;

final class ProductIdTest extends TestCase {

	public function test_fanza_cid_is_lowercased(): void {
		$this->assertSame( array( 'service' => 'fanza', 'id' => 'd_737824' ), ProductId::parse( ' D_737824 ' ) );
		$this->assertSame( array( 'service' => 'fanza', 'id' => 'd_abc_01' ), ProductId::parse( 'd_abc_01' ) );
	}

	public function test_dlsite_workno_is_uppercased(): void {
		$this->assertSame( array( 'service' => 'dlsite', 'id' => 'RJ01634826' ), ProductId::parse( 'rj01634826' ) );
		$this->assertSame( array( 'service' => 'dlsite', 'id' => 'BJ123456' ), ProductId::parse( 'BJ123456' ) );
	}

	/**
	 * @dataProvider badIds
	 */
	public function test_rejects_other_shapes( $value ): void {
		$this->assertNull( ProductId::parse( $value ) );
	}

	public function badIds(): array {
		return array(
			array( '' ),
			array( 'RJ12345' ),            // 桁が少ない
			array( 'RJ123456789' ),        // 桁が多い
			array( 'R01634826' ),          // 英字 1 つ
			array( 'd_' ),                 // 本体が無い
			array( 'd_' . str_repeat( 'a', 41 ) ),
			array( 'https://www.dlsite.com/maniax/work/=/product_id/RJ01634826.html' ),
			array( '737824' ),
			array( 'd-737824' ),
		);
	}

	public function test_patterns_cover_both_services(): void {
		$this->assertSame( array( 'fanza', 'dlsite' ), array_keys( ProductId::patterns() ) );
	}
}
