<?php
/**
 * ログイン後の直行 (spec §7.3)。
 *
 * メインサイト以外でログインしたら、誰でもそのサイトのマイページへ送る。
 * コアの既定はダッシュボード。特権管理者かどうかでは分けない (分けると ftsf に効かないため。T.36.2)。
 * 「ログインしてから、さっき開こうとした画面へ」(redirect_to が付いている) は邪魔しない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ログイン後の送り先をマイページにする。
 *
 * @param string           $redirect_to           送り先 (既定は admin_url())。
 * @param string           $requested_redirect_to redirect_to で求められた送り先 (無ければ空)。
 * @param WP_User|WP_Error $user                  ログインしたユーザ (失敗なら WP_Error)。
 * @return string
 */
function doujin_profile_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) || ! is_multisite() || is_main_site() ) {
		return $redirect_to;
	}

	// ダッシュボード以外の画面を求めている (例: 編集画面のリンクを踏んでログインした) なら、そちらへ。
	if ( '' !== $requested_redirect_to ) {
		$path      = (string) wp_parse_url( $requested_redirect_to, PHP_URL_PATH );
		$dashboard = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
		if ( ! in_array( untrailingslashit( $path ), array( untrailingslashit( $dashboard ), $dashboard . 'index.php' ), true ) ) {
			return $redirect_to;
		}
	}

	if ( ! user_can( $user, 'edit_posts' ) ) {
		return $redirect_to; // マイページを開けない人 (購読者) はコアの既定へ。
	}

	return doujin_profile_my_page_url();
}
add_filter( 'login_redirect', 'doujin_profile_login_redirect', 10, 3 );
