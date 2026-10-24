<?php
/**
 * head の説明文・OGP・Twitter カード (spec §9.4。T.32.7)。テーマに依存しない。
 *
 * 出すのはトップページと単体ページだけ (アーカイブ・検索・404 には出さない)。
 *   トップページ: サイト名・キャッチフレーズ (リード文)・サイトアイコン。
 *   作品 (投稿) と固定ページ: タイトル・抜粋 (無ければリード文)・アイキャッチ (無ければサイトアイコン)。
 * title と canonical はコアが出すのでそのまま。JSON-LD は作らない (消費する検索機能が無く、OGP で足りる)。
 * 成人向けの印が付いたサイトには <meta name="rating" content="adult"> も付ける (spec §9.5)。
 * SEO 系のプラグインは入れない前提なので、重複の気遣いはしない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * この表示の説明文・画像など。出さない表示なら null。
 *
 * @return array{title: string, description: string, url: string, type: string, image: array{url: string, width: int, height: int}|null}|null
 */
function doujin_profile_head_data() {
	if ( ! ( is_front_page() || is_singular() ) ) {
		return null;
	}

	$tagline = (string) get_bloginfo( 'description', 'display' );
	$image   = null;

	if ( is_singular() && ! is_front_page() ) {
		$post        = get_queried_object();
		$title       = get_the_title( $post );
		$description = (string) get_the_excerpt( $post );
		$url         = (string) get_permalink( $post );
		$type        = 'article';

		$thumbnail_id = get_post_thumbnail_id( $post );
		$src          = $thumbnail_id ? wp_get_attachment_image_src( $thumbnail_id, 'large' ) : false;
		if ( $src ) {
			$image = array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	} else {
		$title       = (string) get_bloginfo( 'name', 'display' );
		$description = $tagline;
		$url         = home_url( '/' );
		$type        = 'website';
	}

	if ( ! $image ) {
		$icon_id = (int) get_option( 'site_icon' );
		$src     = $icon_id ? wp_get_attachment_image_src( $icon_id, array( 512, 512 ) ) : false;
		if ( $src ) {
			$image = array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	$description = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $description ) ) );
	if ( '' === $description ) {
		$description = $tagline;
	}

	return array(
		'title'       => $title,
		'description' => $description,
		'url'         => $url,
		'type'        => $type,
		'image'       => $image,
	);
}

/**
 * head に meta を出す。
 */
function doujin_profile_head_meta() {
	if ( is_multisite() && ! is_main_site() ) {
		$site = get_site();
		if ( $site && 1 === (int) $site->mature ) {
			echo '<meta name="rating" content="adult" />' . "\n";
		}
	}

	$data = doujin_profile_head_data();
	if ( ! $data ) {
		return;
	}

	$meta = array(
		array( 'name', 'description', $data['description'] ),
		array( 'property', 'og:site_name', (string) get_bloginfo( 'name', 'display' ) ),
		array( 'property', 'og:locale', 'ja_JP' ),
		array( 'property', 'og:type', $data['type'] ),
		array( 'property', 'og:url', $data['url'] ),
		array( 'property', 'og:title', $data['title'] ),
		array( 'property', 'og:description', $data['description'] ),
	);
	if ( $data['image'] ) {
		$meta[] = array( 'property', 'og:image', $data['image']['url'] );
		if ( $data['image']['width'] && $data['image']['height'] ) {
			$meta[] = array( 'property', 'og:image:width', (string) $data['image']['width'] );
			$meta[] = array( 'property', 'og:image:height', (string) $data['image']['height'] );
		}
	}
	$meta[] = array( 'name', 'twitter:card', $data['image'] ? 'summary_large_image' : 'summary' );

	foreach ( $meta as $m ) {
		if ( '' === $m[2] ) {
			continue;
		}
		printf( '<meta %s="%s" content="%s" />' . "\n", $m[0], esc_attr( $m[1] ), esc_attr( $m[2] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 属性名は固定
	}
}
add_action( 'wp_head', 'doujin_profile_head_meta', 2 );
