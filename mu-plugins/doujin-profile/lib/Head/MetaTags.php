<?php
/**
 * head の説明文・OGP・Twitter カードの組み立て (spec §9.4)。出力 (エスケープと echo) は糊。
 */

namespace DoujinProfile\Head;

final class MetaTags {

	/**
	 * 説明文: タグを外し、空白をまとめる。空ならキャッチフレーズ。
	 *
	 * @param string   $raw        抜粋など。
	 * @param string   $tagline    キャッチフレーズ (リード文)。
	 * @param callable $strip_tags タグを外す (fn( string ): string。WordPress の wp_strip_all_tags())。
	 * @return string
	 */
	public static function description( string $raw, string $tagline, callable $strip_tags ): string {
		$description = trim( (string) preg_replace( '/\s+/u', ' ', (string) $strip_tags( $raw ) ) );

		return '' === $description ? $tagline : $description;
	}

	/**
	 * meta の一覧。content が空のものは除く。
	 *
	 * @param array{title: string, description: string, url: string, type: string, image: array{url: string, width: int, height: int}|null} $data 表示の情報。
	 * @param string $site_name サイト名。
	 * @return array<int, array{0: string, 1: string, 2: string}> [属性名 (name / property), 名前, content]。
	 */
	public static function build( array $data, string $site_name ): array {
		$meta = array(
			array( 'name', 'description', $data['description'] ),
			array( 'property', 'og:site_name', $site_name ),
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

		return array_values(
			array_filter(
				$meta,
				static function ( $m ) {
					return '' !== $m[2];
				}
			)
		);
	}
}
