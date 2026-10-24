<?php
/**
 * マイページの入れ物 (spec §8)。
 *
 * 管理画面の最上位メニュー「マイページ」(admin.php?page=doujin-profile) に、ビルド済みの
 * スクリプト build/my-page.js を読み込む。画面の中身は src/my-page/ (React。WordPress 同梱の部品) で、
 * 保存はすべて標準の REST。ここは PHP 側の入れ物だけ。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_MY_PAGE_SLUG = 'doujin-profile';

/**
 * マイページの URL。
 *
 * @return string
 */
function doujin_profile_my_page_url() {
	return admin_url( 'admin.php?page=' . DOUJIN_PROFILE_MY_PAGE_SLUG );
}

/**
 * メニューに「マイページ」を足す (ダッシュボードの直下)。
 *
 * 開ける条件は edit_posts (持ち主 = サイト管理者と、合同サークルで足した編集者)。
 * 画面の中の保存はそれぞれ標準の REST の権限 (edit_posts / upload_files / manage_options) で判定される。
 */
function doujin_profile_add_my_page_menu() {
	add_menu_page(
		'マイページ',
		'マイページ',
		'edit_posts',
		DOUJIN_PROFILE_MY_PAGE_SLUG,
		'doujin_profile_render_my_page',
		'dashicons-id-alt',
		2
	);
}
add_action( 'admin_menu', 'doujin_profile_add_my_page_menu' );

/**
 * マイページの土台を出す。中身は JS が描く。
 */
function doujin_profile_render_my_page() {
	echo '<div class="wrap doujin-profile-my-page-wrap">';
	echo '<h1 class="screen-reader-text">マイページ</h1>';
	if ( ! doujin_profile_build_asset( 'my-page' ) ) {
		echo '<div class="notice notice-error"><p>マイページのスクリプトがビルドされていません (mu-plugins/doujin-profile/ で <code>npm run build</code> してから配備してください)。</p></div>';
	}
	echo '<div id="doujin-profile-my-page"></div>';
	echo '</div>';
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

	// 画面が要る、サイトの情報。保存はしない (保存は REST)。
	$data = array(
		'homeUrl'  => home_url( '/' ),
		'adminUrl' => admin_url(),
		'services' => doujin_profile_services(),
	);
	wp_add_inline_script( $handle, 'var doujinProfileData = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'doujin_profile_enqueue_my_page' );

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
