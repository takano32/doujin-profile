<?php
/**
 * 公開側の独自ブロック (spec §6.2。T.32.3)。
 *
 * ブロックは属性にデータを持たず、投稿の項目・メタ・分類・サイト設定を読んで描くだけ (動的ブロック)。
 * 定義は src/blocks/<名前>/block.json で、@wordpress/scripts が build/blocks/<名前>/ に
 * block.json・render.php・index.js (エディタ)・view.js (表示側) を出す。ここは登録と、
 * render.php が共用する描き方の関数だけ。
 *
 *   doujin-profile/work-card   作品カード (サンプルビューアを含む)
 *   doujin-profile/links       リンクボタン (サイト設定 doujin_profile_links の shops / accounts)
 *   doujin-profile/more-works  さらに作品を見る (クエリーループの次のページを下へ継ぎ足す。T.31.4)
 *
 * 見た目はテーマに任せる: ボタンはコアのボタンブロックのクラス (wp-block-button__link wp-element-button) を
 * 借りて、テーマの theme.json のボタンの装いを継ぐ。色・大きさ・余白の独自の指定はしない (T.32.3.3.2)。
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

/**
 * ブロックエディタからのプレビュー描画 (REST の block-renderer) かどうか。
 * 公開側では何も出さない場面で、エディタにだけ案内を出すのに使う。
 *
 * @return bool
 */
function doujin_profile_is_editor_preview() {
	return defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_GET['context'] ) && 'edit' === $_GET['context']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * エディタにだけ出す案内 (公開側では空)。
 *
 * @param string $message 文。
 * @return string
 */
function doujin_profile_editor_notice( $message ) {
	if ( ! doujin_profile_is_editor_preview() ) {
		return '';
	}

	return '<p class="doujin-profile-editor-notice">' . esc_html( $message ) . '</p>';
}

/**
 * リンク 1 件の表示名。表示名が無ければサービス名 (作品カードでは「FANZAで見る」の形)。
 *
 * @param array $link       正規化済みのリンク。
 * @param bool  $with_verb  「で見る」を付けるか。
 * @return string
 */
function doujin_profile_link_label( $link, $with_verb = false ) {
	if ( '' !== $link['label'] ) {
		return $link['label'];
	}

	if ( 'other' === $link['service'] ) {
		return $with_verb ? '販売ページを見る' : 'リンク';
	}

	$services = doujin_profile_services();
	$name     = $services[ $link['service'] ];

	return $with_verb ? $name . 'で見る' : $name;
}

/**
 * リンクの一覧をコアのボタンブロックの形で描く (テーマのボタンの装いを継ぐ)。
 *
 * rel は noopener を常に、アフィリエイトなら sponsored を足す (spec §6.2)。外部の販売ページなので新しいタブで開く。
 * アフィリエイトのリンクが 1 つでもあれば注記を添える。
 *
 * @param array  $links     正規化済みのリンク (doujin_profile_sanitize_links() を通したもの)。
 * @param string $classes   wp-block-buttons に足すクラス (例 is-vertical)。
 * @param bool   $with_verb 表示名に「で見る」を付けるか。
 * @return string 空の一覧なら空文字。
 */
function doujin_profile_render_link_buttons( $links, $classes = '', $with_verb = false ) {
	if ( empty( $links ) ) {
		return '';
	}

	$buttons   = '';
	$sponsored = false;
	foreach ( $links as $link ) {
		$rel = 'noopener';
		if ( $link['sponsored'] ) {
			$rel      .= ' sponsored';
			$sponsored = true;
		}
		$buttons .= sprintf(
			'<div class="wp-block-button doujin-profile-link doujin-profile-link--%1$s"><a class="wp-block-button__link wp-element-button" href="%2$s" target="_blank" rel="%3$s">%4$s</a></div>',
			esc_attr( $link['service'] ),
			esc_url( $link['url'] ),
			esc_attr( $rel ),
			esc_html( doujin_profile_link_label( $link, $with_verb ) )
		);
	}

	$html = '<div class="wp-block-buttons is-layout-flex ' . esc_attr( trim( $classes ) ) . '">' . $buttons . '</div>';
	if ( $sponsored ) {
		$html .= '<p class="doujin-profile-affiliate-note"><small>※ アフィリエイトリンクを含みます</small></p>';
	}

	return $html;
}

/**
 * 作品の発売日の表記 (spec §6.0)。
 *
 * 表記 (doujin_release_label) があればそれを、空なら発売日時 (doujin_release_at) から
 * 「2026年5月30日発売」、未来なら「発売予定日: 2026年5月30日」を作る。
 *
 * @param int $post_id 作品の投稿 ID。
 * @return array{label: string, upcoming: bool, release_at: string} release_at は ISO 8601 (無ければ空)。
 */
function doujin_profile_release_info( $post_id ) {
	$label    = doujin_profile_sanitize_release_label( get_post_meta( $post_id, 'doujin_release_label', true ) );
	$datetime = doujin_profile_parse_datetime( get_post_meta( $post_id, 'doujin_release_at', true ) );
	$upcoming = $datetime && $datetime->getTimestamp() > time();

	if ( '' === $label && $datetime ) {
		$date  = wp_date( 'Y年n月j日', $datetime->getTimestamp() );
		$label = $upcoming ? '発売予定日: ' . $date : $date . '発売';
	}

	return array(
		'label'      => $label,
		'upcoming'   => (bool) $upcoming,
		'release_at' => $datetime ? $datetime->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i:sP' ) : '',
	);
}

/**
 * サンプル画像 (doujin_sample_ids) を、ビューアが読む形にする。画像でない・消された添付は飛ばす。
 *
 * @param int $post_id 作品の投稿 ID。
 * @return array<int, array{src: string, srcset: string, alt: string}>
 */
function doujin_profile_sample_pages( $post_id ) {
	$pages = array();
	foreach ( doujin_profile_sanitize_sample_ids( get_post_meta( $post_id, 'doujin_sample_ids', true ) ) as $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			continue;
		}
		$src = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( ! $src ) {
			continue;
		}
		$srcset  = wp_get_attachment_image_srcset( $attachment_id, 'large' );
		$pages[] = array(
			'src'    => $src,
			'srcset' => is_string( $srcset ) ? $srcset : '',
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	return $pages;
}
