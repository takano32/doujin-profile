<?php
/**
 * WP-CLI の子コマンドが共用する小物。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * メインサイトでは止める (プロフィールのサイトで使うコマンド)。
 */
function doujin_profile_cli_require_profile_site() {
	if ( is_multisite() && is_main_site() ) {
		WP_CLI::error( 'このコマンドはプロフィールのサイトで使います。--url=https://<名前>.dp.no32.tk を付けてください (メインサイトには効かせません)。' );
	}
}

/**
 * サイトの名前 (サブドメインの最初のラベル。例 ftsf)。
 *
 * @return string
 */
function doujin_profile_cli_site_name() {
	$domain = is_multisite() ? get_site()->domain : (string) wp_parse_url( home_url(), PHP_URL_HOST );

	return (string) strtok( $domain, '.' );
}

/**
 * 新しく作る投稿の作者: いまのユーザ (--user)、無ければサイトの管理者 (最初の 1 人)。
 *
 * @return int
 */
function doujin_profile_cli_author_id() {
	if ( get_current_user_id() ) {
		return get_current_user_id();
	}

	return doujin_profile_first_admin_id();
}

/**
 * コアの検証 (WP_Error) の文を 1 行にする (HTML は外す)。
 *
 * @param WP_Error $errors 検証の結果。
 * @return string
 */
function doujin_profile_cli_error_text( $errors ) {
	return wp_strip_all_tags( implode( ' / ', $errors->get_error_messages() ) );
}
