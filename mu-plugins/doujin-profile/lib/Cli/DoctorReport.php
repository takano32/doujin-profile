<?php
/**
 * 点検 (wp dp doctor) の記録。1 件ずつ [OK] / [NG] / [FIXED] / [--] の行を出し、NG の数を持つ。
 *
 * 行の出し方 (WP_CLI::log) は注入する。--fix のときは、直す処理 (安全なものだけ) があれば実行して [FIXED] にする。
 */

namespace DoujinProfile\Cli;

final class DoctorReport {

	/** @var bool --fix か。 */
	private $fix;

	/** @var callable 1 行出す (fn( string ): void)。 */
	private $log;

	/** @var int NG の数。 */
	private $ng = 0;

	/**
	 * @param bool     $fix --fix か。
	 * @param callable $log 1 行出す。
	 */
	public function __construct( bool $fix, callable $log ) {
		$this->fix = $fix;
		$this->log = $log;
	}

	/**
	 * 点検 1 件。NG なら直し方を添え、--fix と直す処理があれば直す。
	 *
	 * @param mixed         $ok    合格か。
	 * @param string        $what  見たこと。
	 * @param string        $fix   直し方 (人向け)。
	 * @param callable|null $fixer --fix で行う直し (安全なものだけ)。
	 */
	public function check( $ok, string $what, string $fix = '', ?callable $fixer = null ): void {
		if ( $ok ) {
			( $this->log )( "[OK] {$what}" );
			return;
		}
		if ( $fixer && $this->fix ) {
			$fixer();
			( $this->log )( "[FIXED] {$what} → 直した" );
			return;
		}
		++$this->ng;
		( $this->log )( "[NG] {$what}" . ( '' !== $fix ? " → {$fix}" : '' ) . ( $fixer ? ' [FIX]' : '' ) );
	}

	/**
	 * 判定のない情報 1 行。
	 *
	 * @param string $what 文。
	 */
	public function info( string $what ): void {
		( $this->log )( "[--] {$what}" );
	}

	/**
	 * NG の数。
	 *
	 * @return int
	 */
	public function ng(): int {
		return $this->ng;
	}

	/**
	 * 終わりの文 (NG があるとき)。
	 *
	 * @return string
	 */
	public function summary(): string {
		return sprintf( 'NG %d 件。直し方は各行の → のあと%s。', $this->ng, $this->fix ? '' : ' (--fix で直せるものは [FIX] と付けた)' );
	}
}
