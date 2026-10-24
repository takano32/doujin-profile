<?php
/**
 * 準備中 (spec §7.6。T.11。ユーザ裁定 2026-10-25「持ち主がマイページのボタンで自分で公開する」)。
 *
 * 新しく招待したサイトは「準備中」で始まり (wp doujin invite)、持ち主がマイページの「公開する」で公開する。
 * 印はサイトの設定 doujin_profile_preparing (真偽)。register_setting() で標準の REST (wp/v2/settings) に載せるので、
 * マイページは標準の保存口だけで書く (権限はコアの manage_options)。独自の保存口は作らない。
 *
 * 準備中のサイトは、そのサイトを編集できない訪問者には「準備中です」だけの小さなページを出す。
 * HTTP は 200 + noindex にした (spec §7.6 の 503 ではない): 前段 Caddy の on-demand TLS の ask は
 * トップページが 2xx かで「ネットワークにあるサイト」を判定する (docs/caddy-front-trial.md) ので、503 だと
 * 準備中のサイトは証明書が取れず、持ち主がログインすらできない。検索エンジンには meta robots と X-Robots-Tag で載せないよう頼む。
 * template_redirect (優先度 1。サイトマップより先) で止めるので、REST・ログイン・管理画面・robots.txt は妨げない。
 * 公開 → 準備中へ戻せるのは運営者だけ (特権管理者か WP-CLI の wp doujin publish --unpublish)。持ち主の REST からは戻せない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_PREPARING_OPTION = 'doujin_profile_preparing';

/**
 * 設定の登録 (REST の wp/v2/settings に載る)。
 */
function doujin_profile_register_preparing() {
	register_setting(
		'doujin_profile',
		DOUJIN_PROFILE_PREPARING_OPTION,
		array(
			'type'              => 'boolean',
			'label'             => '準備中',
			'description'       => '真なら、サイトを編集できない訪問者には「準備中です」だけを出す',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'doujin_profile_register_preparing' );

/**
 * いまのサイトが準備中か。メインサイトは常に公開。
 *
 * @return bool
 */
function doujin_profile_is_preparing() {
	return is_multisite() && ! is_main_site() && (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
}

/**
 * 公開 → 準備中へ戻すのは運営者だけ。持ち主 (manage_options) が REST から真に戻そうとしても前の値のまま。
 *
 * @param mixed $value     新しい値。
 * @param mixed $old_value いまの値。
 * @return mixed
 */
function doujin_profile_guard_preparing( $value, $old_value ) {
	if ( $value && ! $old_value && ! is_super_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return $old_value;
	}

	return $value;
}
add_filter( 'pre_update_option_' . DOUJIN_PROFILE_PREPARING_OPTION, 'doujin_profile_guard_preparing', 10, 2 );

/**
 * 準備中のサイトでは、編集できない訪問者に「準備中です」だけを出して終える。
 */
function doujin_profile_preparing_gate() {
	if ( ! doujin_profile_is_preparing() || is_robots() || current_user_can( 'edit_posts' ) ) {
		return;
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	$name = (string) get_bloginfo( 'name', 'display' );
	?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $name ); ?> — 準備中</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;color:#333;background:#fff;text-align:center}h1{font-size:1.5rem}</style>
</head>
<body><main><h1><?php echo esc_html( $name ); ?></h1><p>準備中です。</p></main></body>
</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'doujin_profile_preparing_gate', 1 );
