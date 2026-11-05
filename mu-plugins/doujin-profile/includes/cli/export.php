<?php
/**
 * wp doujin export: いまのサイトの内容を交換形式の JSON (spec §6.4) で書き出す。形の決まりは lib/Cli/Exchange.php。
 */

namespace DoujinProfile\Commands;

use DoujinProfile\Cli\Exchange;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Export {

	/**
	 * いまのサイトの内容を交換形式の JSON (spec §6.4) で書き出す。画像は URL。
	 *
	 * ## OPTIONS
	 *
	 * [--out=<file>]
	 * : 書き出す先。省くと標準出力。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin export --url=https://ftsf.dp.no32.tk --out=ftsf.json
	 */
	public function __invoke( $args, $assoc_args ) {
		doujin_profile_cli_require_profile_site();

		$links = doujin_profile_sanitize_profile_links( get_option( 'doujin_profile_links', array() ) );
		$data  = array(
			'name'     => doujin_profile_cli_site_name(),
			'title'    => array( 'ja' => (string) get_option( 'blogname' ) ),
			'lead'     => array( 'ja' => (string) get_option( 'blogdescription' ) ),
			'mature'   => is_multisite() && (bool) get_site()->mature,
			'theme'    => get_stylesheet(),
			'shops'    => Exchange::linksToJson( $links['shops'] ),
			'accounts' => Exchange::linksToJson( $links['accounts'] ),
			'works'    => array(),
		);

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$data['works'][] = $this->work( $post );
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
		if ( isset( $assoc_args['out'] ) ) {
			if ( false === file_put_contents( (string) $assoc_args['out'], $json ) ) {
				WP_CLI::error( "書き出せませんでした: {$assoc_args['out']}" );
			}
			WP_CLI::success( sprintf( '%s に書き出しました (作品 %d 件)。', $assoc_args['out'], count( $posts ) ) );
		} else {
			WP_CLI::line( rtrim( $json, "\n" ) );
		}
	}

	/**
	 * 作品 1 件を交換形式にする。
	 *
	 * @param \WP_Post $post 投稿。
	 * @return array
	 */
	private function work( $post ) {
		$artists = wp_get_post_terms( $post->ID, 'artist', array( 'fields' => 'names' ) );
		$work    = array(
			'key'           => $post->post_name,
			'status'        => Exchange::exportStatus( $post->post_status ),
			'title'         => array( 'ja' => $post->post_title ),
			'summary'       => array( 'ja' => $post->post_excerpt ),
			'artists'       => is_array( $artists ) ? array_values( $artists ) : array(),
			'release_at'    => (string) get_post_meta( $post->ID, 'doujin_release_at', true ),
			'release_label' => array( 'ja' => (string) get_post_meta( $post->ID, 'doujin_release_label', true ) ),
		);
		$cover   = get_post_thumbnail_id( $post );
		if ( $cover && wp_get_attachment_url( $cover ) ) {
			$work['cover'] = wp_get_attachment_url( $cover );
		}
		$work['links']   = Exchange::linksToJson( doujin_profile_sanitize_links( get_post_meta( $post->ID, 'doujin_shop_links', true ) ) );
		$work['samples'] = array();
		foreach ( doujin_profile_sanitize_sample_ids( get_post_meta( $post->ID, 'doujin_sample_ids', true ) ) as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$work['samples'][] = $url;
			}
		}

		return $work;
	}
}
