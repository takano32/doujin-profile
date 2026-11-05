<?php
/**
 * 公開側の独自ブロックの登録 (spec §6.2。T.32.3)。
 *
 * ブロックは属性にデータを持たず、投稿の項目・メタ・分類・サイト設定を読んで描くだけ (動的ブロック)。
 * 定義は src/blocks/<名前>/block.json で、@wordpress/scripts が build/blocks/<名前>/ に
 * block.json・render.php・index.js (エディタ)・view.js (表示側) を出す。render.php が共用する描き方は includes/blocks/render.php。
 *
 *   doujin-profile/work-card   作品カード (サンプルビューアを含む)
 *   doujin-profile/links       リンクボタン (サイト設定 doujin_profile_links の shops / accounts)
 *   doujin-profile/more-works  さらに作品を見る (クエリーループの次のページを下へ継ぎ足す。T.31.4)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * build/blocks/ 以下の block.json をすべて登録する (ビルドが無ければ何もしない)。
 */
function doujin_profile_register_blocks() {
	$metadata_files = glob( DOUJIN_PROFILE_DIR . '/build/blocks/*/block.json' );
	foreach ( $metadata_files ? $metadata_files : array() as $metadata_file ) {
		register_block_type( dirname( $metadata_file ) );
	}
}
add_action( 'init', 'doujin_profile_register_blocks' );

/**
 * インサーターに「サークル紹介」のカテゴリを足す (独自ブロックとパターンの置き場)。
 *
 * @param array $categories ブロックのカテゴリ。
 * @return array
 */
function doujin_profile_block_category( $categories ) {
	$categories[] = array(
		'slug'  => 'doujin-profile',
		'title' => 'サークル紹介',
	);

	return $categories;
}
add_filter( 'block_categories_all', 'doujin_profile_block_category' );
