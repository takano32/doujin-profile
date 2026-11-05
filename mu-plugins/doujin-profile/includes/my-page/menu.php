<?php
/**
 * マイページの入れ物: メニューと土台 (spec §8)。
 *
 * 管理画面の最上位メニュー「マイページ」(admin.php?page=doujin-profile)。画面の中身は src/my-page/ (React。
 * WordPress 同梱の部品 + 同梱の SortableJS) で、スクリプトの読み込みは includes/my-page/assets.php。
 * 保存はすべて標準の REST (例外は書影・サンプルの取り込み。includes/import/)。ここは PHP 側の入れ物だけ。
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
	echo '<h1>マイページ</h1>'; // 見出しはここだけ (JS 側は出さない)。
	if ( ! doujin_profile_build_asset( 'my-page' ) ) {
		echo '<div class="notice notice-error"><p>マイページのスクリプトがビルドされていません (mu-plugins/doujin-profile/ で <code>npm run build</code> してから配備してください)。</p></div>';
	}
	echo '<div id="doujin-profile-my-page"></div>';
	echo '</div>';
}
