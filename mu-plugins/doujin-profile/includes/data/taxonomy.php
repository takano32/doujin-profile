<?php
/**
 * 分類 artist: 作家 (作画担当。タグ型。1 作品に複数可、役割の区別はしない。T.31.1)。一覧ページは /artist/<slug>/ (T.31.3)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 分類の登録 (サイト単位。init で毎回)。
 */
function doujin_profile_register_taxonomy() {
	register_taxonomy(
		'artist',
		'post',
		array(
			'labels'            => array(
				'name'          => '作家',
				'singular_name' => '作家',
				'search_items'  => '作家を検索',
				'all_items'     => 'すべての作家',
				'edit_item'     => '作家を編集',
				'update_item'   => '作家を更新',
				'add_new_item'  => '作家を追加',
				'new_item_name' => '新しい作家の名前',
				'not_found'     => '作家が見つかりません',
				'menu_name'     => '作家',
			),
			'description'       => '作品の作画担当。1 作品に複数可、役割の区別はしない (T.31.1)',
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'artist' ), // /artist/<slug>/ (T.31.3)。配備後に既存のサイトで一度 wp rewrite flush
		)
	);
}
add_action( 'init', 'doujin_profile_register_taxonomy' );
