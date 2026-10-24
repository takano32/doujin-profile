<?php
/**
 * パターン (spec §6.3)。
 *
 * プロフィールの見出し (spec §6.2 / owner-editor-design Q13) は独自ブロックにせず、コアのブロックの組
 * (サイトロゴ・サイトのタイトル・キャッチフレーズ) をパターンで出す。サイトロゴのブロックは設定 site_logo を
 * 表示するので、マイページはアイコン (site_icon) を保存するときに site_logo も同じ画像にする。
 *
 * トップページ (フロントページ) のスターターパターンは、見出し → 販売ページのリンク → 作品 (クエリーループ +
 * さらに作品を見る) → お知らせ・活動情報のリンク。固定ページに入れて「ホームページの表示」に設定する (§6.1)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プロフィールの見出しのブロックマークアップ。
 *
 * @return string
 */
function doujin_profile_pattern_profile_header() {
	return <<<'HTML'
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":120,"shouldSyncIcon":true,"align":"center"} /-->

<!-- wp:site-title {"level":1,"textAlign":"center","isLink":false} /-->

<!-- wp:site-tagline {"textAlign":"center"} /--></div>
<!-- /wp:group -->
HTML;
}

/**
 * トップページのブロックマークアップ (見出しを含む)。
 *
 * @return string
 */
function doujin_profile_pattern_front_page() {
	return doujin_profile_pattern_profile_header() . "\n\n" . <<<'HTML'
<!-- wp:doujin-profile/links {"list":"shops"} /-->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">作品</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":1,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"enhancedPagination":false} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"20rem"}} -->
<!-- wp:doujin-profile/work-card /-->
<!-- /wp:post-template -->

<!-- wp:doujin-profile/more-works /-->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">作品はまだありません。</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">お知らせ・活動情報</h2>
<!-- /wp:heading -->

<!-- wp:doujin-profile/links {"list":"accounts"} /-->
HTML;
}

/**
 * パターンのカテゴリ「サークル紹介」と 2 つのパターンを登録する。
 */
function doujin_profile_register_patterns() {
	register_block_pattern_category( 'doujin-profile', array( 'label' => 'サークル紹介' ) );

	register_block_pattern(
		'doujin-profile/profile-header',
		array(
			'title'       => 'プロフィールの見出し',
			'description' => 'アイコン・サークル名・リード文 (サイトのアイコン・タイトル・キャッチフレーズ)。',
			'categories'  => array( 'doujin-profile' ),
			'content'     => doujin_profile_pattern_profile_header(),
		)
	);

	register_block_pattern(
		'doujin-profile/front-page',
		array(
			'title'       => 'サークル紹介のトップページ',
			'description' => 'プロフィールの見出し、販売ページ、作品 6 件と「さらに作品を見る」、お知らせ・活動情報。固定ページに入れて「ホームページの表示」にする。',
			'categories'  => array( 'doujin-profile' ),
			'blockTypes'  => array( 'core/post-content' ),
			'postTypes'   => array( 'page' ),
			'content'     => doujin_profile_pattern_front_page(),
		)
	);
}
add_action( 'init', 'doujin_profile_register_patterns' );
