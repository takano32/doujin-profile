<?php
/**
 * 独自ブロックの render.php (build/blocks/∗/render.php) が共用する描き方 (spec §6.2)。
 *
 * 見た目はテーマに任せる: ボタンはコアのボタンブロックのクラス (wp-block-button__link wp-element-button) を
 * 借りて、テーマの theme.json のボタンの装いを継ぐ。色・大きさ・余白の独自の指定はしない (T.32.3.3.2)。
 * 表示名と rel の決まりは lib/Blocks/LinkLabel.php、発売日の表記は lib/Data/ReleaseLabel.php。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Blocks\LinkLabel;
use DoujinProfile\Data\ReleaseLabel;

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
	return LinkLabel::of( $link, (bool) $with_verb );
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
		if ( $link['sponsored'] ) {
			$sponsored = true;
		}
		$buttons .= sprintf(
			'<div class="wp-block-button doujin-profile-link doujin-profile-link--%1$s"><a class="wp-block-button__link wp-element-button" href="%2$s" target="_blank" rel="%3$s">%4$s</a></div>',
			esc_attr( $link['service'] ),
			esc_url( $link['url'] ),
			esc_attr( LinkLabel::rel( (bool) $link['sponsored'] ) ),
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
	return ReleaseLabel::info(
		doujin_profile_sanitize_release_label( get_post_meta( $post_id, 'doujin_release_label', true ) ),
		doujin_profile_parse_datetime( get_post_meta( $post_id, 'doujin_release_at', true ) ),
		time(),
		static function ( $timestamp ) {
			return wp_date( 'Y年n月j日', $timestamp );
		},
		wp_timezone()
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
