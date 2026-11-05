<?php
/**
 * wp doujin init-site: 新しいサイトの初期設定を、作成済みのサイトに施す (処理は includes/init-site.php)。
 */

namespace DoujinProfile\Commands;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InitSite {

	/**
	 * 新しいサイトの初期設定を、いまのサイトに施す。
	 *
	 * home・siteurl を https に、初期の「Hello world!」とサンプルページを消し、固定ページ「ホーム」をトップにし、
	 * パーマリンクと書き換え規則を整える (spec §6.1)。何度呼んでも壊れない (あるものは作らない)。メインサイトには効かない。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin init-site --url=https://ftsf.dp.no32.tk
	 */
	public function __invoke( $args, $assoc_args ) {
		doujin_profile_cli_require_profile_site();

		$result = doujin_profile_init_site( get_current_user_id() );
		foreach ( $result['done'] as $line ) {
			WP_CLI::log( '- ' . $line );
		}
		foreach ( $result['errors'] as $line ) {
			WP_CLI::warning( $line );
		}
		WP_CLI::success( sprintf( '%s の初期設定を整えました。', home_url( '/' ) ) );
	}
}
