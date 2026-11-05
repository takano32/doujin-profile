<?php

namespace DoujinProfile\Tests\Unit\Cli;

use DoujinProfile\Cli\DoctorReport;
use PHPUnit\Framework\TestCase;

final class DoctorReportTest extends TestCase {

	/** @var string[] */
	private $lines = array();

	private function report( bool $fix ): DoctorReport {
		$this->lines = array();

		return new DoctorReport(
			$fix,
			function ( $line ) {
				$this->lines[] = $line;
			}
		);
	}

	public function test_ok_lines_do_not_count(): void {
		$report = $this->report( false );
		$report->check( true, '合格' );
		$report->info( '情報' );
		$this->assertSame( array( '[OK] 合格', '[--] 情報' ), $this->lines );
		$this->assertSame( 0, $report->ng() );
	}

	public function test_ng_with_hint_and_fix_marker(): void {
		$called = false;
		$report = $this->report( false );
		$report->check( false, '不合格', '直し方' );
		$report->check( 0, '直せる不合格', '直し方 2', static function () use ( &$called ) {
			$called = true;
		} );
		$report->check( false, 'ヒント無し' );
		$this->assertSame(
			array( '[NG] 不合格 → 直し方', '[NG] 直せる不合格 → 直し方 2 [FIX]', '[NG] ヒント無し' ),
			$this->lines
		);
		$this->assertFalse( $called, '--fix でないときは直さない' );
		$this->assertSame( 3, $report->ng() );
		$this->assertSame( 'NG 3 件。直し方は各行の → のあと (--fix で直せるものは [FIX] と付けた)。', $report->summary() );
	}

	public function test_fix_mode_runs_fixer_and_does_not_count(): void {
		$called = 0;
		$report = $this->report( true );
		$report->check( false, '直せる', 'ヒント', static function () use ( &$called ) {
			++$called;
		} );
		$report->check( false, '直せない', 'ヒント' );
		$this->assertSame( array( '[FIXED] 直せる → 直した', '[NG] 直せない → ヒント' ), $this->lines );
		$this->assertSame( 1, $called );
		$this->assertSame( 1, $report->ng() );
		$this->assertSame( 'NG 1 件。直し方は各行の → のあと。', $report->summary() );
	}
}
