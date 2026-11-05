<?php
/**
 * wp dp publish: 準備中のサイトを公開する (--unpublish で準備中に戻す)。印は設定 doujin_profile_preparing (includes/preparing.php)。
 */

namespace DoujinProfile\Commands;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Publish {

	/**
	 * 準備中のサイトを公開する (--unpublish で準備中に戻す)。
	 *
	 * 持ち主はマイページの「公開する」で自分でも公開できる。準備中に戻せるのは運営者 (このコマンド) だけ。
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : サイトの名前 (<名前>.dp.no32.tk)。
	 *
	 * [--unpublish]
	 * : 準備中に戻す。
	 *
	 * ## EXAMPLES
	 *
	 *     wp dp publish example
	 *     wp dp publish example --unpublish
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'Multisite でだけ使えます。' );
		}
		$name  = strtolower( trim( (string) $args[0] ) );
		$sites = get_sites(
			array(
				'domain' => $name . '.' . get_network()->domain,
				'number' => 1,
			)
		);
		if ( ! $sites ) {
			WP_CLI::error( "サイト「{$name}」はありません (wp site list で確かめてください)。" );
		}
		$site = $sites[0];
		if ( is_main_site( $site->blog_id ) ) {
			WP_CLI::error( 'メインサイトには効かせません。' );
		}
		$preparing = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'unpublish', false );

		switch_to_blog( $site->blog_id );
		$current = (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
		if ( $current === $preparing ) {
			WP_CLI::log( "https://{$site->domain}/ はすでに" . ( $preparing ? '準備中' : '公開中' ) . 'です。' );
		} else {
			update_option( DOUJIN_PROFILE_PREPARING_OPTION, $preparing );
		}
		restore_current_blog();
		WP_CLI::success( "https://{$site->domain}/ を" . ( $preparing ? '準備中に戻しました' : '公開しました' ) . '。' );
	}
}
