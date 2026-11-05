<?php
/**
 * lib/ のクラスを名前で読み込む小さなオートローダ (Composer は使わない。サーバに無い)。
 *
 * 名前空間 DoujinProfile\ をこのディレクトリに対応づける (DoujinProfile\Data\Links → lib/Data/Links.php)。
 * lib/ のクラスは WordPress に依存しない純粋なロジックで、tests/php の PHPUnit がそのまま読む。
 * WordPress の糊 (フック・REST・WP-CLI の入口) は includes/ にあり、本体 (doujin-profile.php) が require で読む。
 */

namespace DoujinProfile;

final class Autoloader {

	/**
	 * オートローダを登録する。
	 *
	 * @param string $base_dir lib/ の絶対パス。
	 */
	public static function register( string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $base_dir ): void {
				$prefix = __NAMESPACE__ . '\\';
				if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
					return;
				}
				$file = $base_dir . '/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
			}
		);
	}
}
