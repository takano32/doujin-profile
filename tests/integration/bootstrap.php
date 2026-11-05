<?php
/**
 * 結合試験の土台: 使い捨ての WordPress (SQLite) を、メインサイト (dp.test) の訪問者として読み込む。
 *
 * tests/remote/integration.sh が WordPress を用意し、環境変数で場所を渡す:
 *   DOUJIN_PROFILE_WP        WordPress の設置先 (…/doujin-profile-test/wp)
 *   DOUJIN_PROFILE_FIXTURES  交換形式の見本と画像 (…/doujin-profile-test/fixtures)
 *   DOUJIN_PROFILE_DEBUG_LOG WP_DEBUG_LOG の道筋 (本体の PHP の警告が無いことを最後に確かめる)
 * WP-CLI の子コマンドは wp を子プロセスで呼ぶ (tests/integration/IntegrationTestCase.php)。
 */

$doujin_profile_wp = getenv( 'DOUJIN_PROFILE_WP' );
if ( ! $doujin_profile_wp || ! is_file( $doujin_profile_wp . '/wp-load.php' ) ) {
	fwrite( STDERR, "DOUJIN_PROFILE_WP が使い捨ての WordPress を指していません (scripts/test.sh integration から実行してください)。\n" );
	exit( 1 );
}

// メインサイトへの GET として読み込む (Multisite はホスト名でサイトを選ぶ)。
$_SERVER['HTTP_HOST']       = 'dp.test';
$_SERVER['SERVER_NAME']     = 'dp.test';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

define( 'WP_USE_THEMES', false );
define( 'DOUJIN_PROFILE_INTEGRATION_TEST', true );

require $doujin_profile_wp . '/wp-load.php';

require __DIR__ . '/IntegrationTestCase.php';
