<?php
/**
 * マイページのスクリプトの読み込み (spec §8)。build/my-page.js (@wordpress/scripts が手元で作る) と、画面が要るサイトの情報。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * マイページを開いたときだけ、スクリプトとメディアの部品を読み込む。
 *
 * @param string $hook_suffix 開いている管理画面。
 */
function doujin_profile_enqueue_my_page( $hook_suffix ) {
	if ( 'toplevel_page_' . DOUJIN_PROFILE_MY_PAGE_SLUG !== $hook_suffix ) {
		return;
	}

	wp_enqueue_media(); // 書影・サンプルを選ぶメディアのモーダル (wp.media)。
	$handle = doujin_profile_enqueue_build( 'my-page' );
	if ( ! $handle ) {
		return;
	}

	wp_add_inline_script( $handle, 'var doujinProfileData = ' . wp_json_encode( doujin_profile_my_page_data() ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'doujin_profile_enqueue_my_page' );

/**
 * 画面が要る、サイトの情報。保存はしない (保存は REST。書影・サンプルの取り込みだけ独自の REST。includes/import/rest.php)。
 *
 * @return array
 */
function doujin_profile_my_page_data() {
	return array(
		'homeUrl'          => home_url( '/' ),
		'adminUrl'         => admin_url(),
		'themesUrl'        => admin_url( 'themes.php' ),
		'siteEditorUrl'    => admin_url( 'site-editor.php' ),
		'services'         => doujin_profile_services(),
		'maxLinks'         => DOUJIN_PROFILE_MAX_LINKS,
		'maxSamples'       => DOUJIN_PROFILE_MAX_SAMPLES,
		'coverImportPath'  => '/' . DOUJIN_PROFILE_REST_NAMESPACE . '/works/%d/cover',   // %d に作品の投稿 ID を入れる。
		'sampleImportPath' => '/' . DOUJIN_PROFILE_REST_NAMESPACE . '/works/%d/samples',
	);
}

/**
 * build/<名前>.asset.php (@wordpress/scripts が作る依存とバージョン) を読む。
 *
 * @param string $name エントリの名前 (例 my-page)。
 * @return array|null 無ければ null。
 */
function doujin_profile_build_asset( $name ) {
	$asset_file = DOUJIN_PROFILE_DIR . "/build/{$name}.asset.php";
	if ( ! is_readable( $asset_file ) ) {
		return null;
	}
	$asset = include $asset_file;

	return is_array( $asset ) ? $asset : null;
}

/**
 * ビルド済みのスクリプト (と、あれば同名の CSS) を読み込む。
 *
 * @param string $name エントリの名前 (例 my-page)。
 * @return string|null スクリプトのハンドル。ビルドが無ければ null。
 */
function doujin_profile_enqueue_build( $name ) {
	$asset = doujin_profile_build_asset( $name );
	if ( ! $asset ) {
		return null;
	}

	$handle  = "doujin-profile-{$name}";
	$version = isset( $asset['version'] ) ? (string) $asset['version'] : DOUJIN_PROFILE_VERSION;
	wp_enqueue_script(
		$handle,
		plugins_url( "build/{$name}.js", DOUJIN_PROFILE_FILE ),
		isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
		$version,
		true
	);

	if ( is_readable( DOUJIN_PROFILE_DIR . "/build/{$name}.css" ) ) {
		// 同梱の部品 (wp.components) の見た目はスクリプトの依存では読まれないので、ここで一緒に読む。
		wp_enqueue_style( $handle, plugins_url( "build/{$name}.css", DOUJIN_PROFILE_FILE ), array( 'wp-components' ), $version );
	}

	return $handle;
}
