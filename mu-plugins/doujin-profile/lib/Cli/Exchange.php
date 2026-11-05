<?php
/**
 * 交換形式 (spec §6.4) の読み書きの決まり。WP-CLI の import / export が使う。
 *
 * 文は文字列か { "ja": …, "en": … } (en は読み飛ばす)。作品の slug は key → 書影の作品番号 → タイトル。
 * 画像は 3 つの書き方: 作品番号 {"service","id"}、https の URL、JSON からの相対の道筋。
 */

namespace DoujinProfile\Cli;

final class Exchange {

	/** 画像の指定の種類。 */
	public const IMAGE_PRODUCT = 'product';
	public const IMAGE_URL     = 'url';
	public const IMAGE_PATH    = 'path';
	public const IMAGE_BAD     = 'bad';

	/**
	 * 日本語の文を取り出す。文字列ならそのまま、{ "ja": …, "en": … } なら ja (en は読み飛ばす)。
	 *
	 * @param mixed $value JSON の値。
	 * @return string
	 */
	public static function ja( $value ): string {
		if ( is_array( $value ) ) {
			$value = isset( $value['ja'] ) ? $value['ja'] : '';
		}

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * 作品の slug: key → 書影の作品番号 → タイトル。
	 *
	 * @param array    $work           JSON の作品。
	 * @param string   $title          日本語のタイトル。
	 * @param callable $sanitize_title slug にする (fn( string ): string。WordPress の sanitize_title())。
	 * @return string 決められなければ空。
	 */
	public static function workKey( array $work, string $title, callable $sanitize_title ): string {
		if ( ! empty( $work['key'] ) && is_string( $work['key'] ) ) {
			$key = $work['key'];
		} elseif ( isset( $work['cover']['id'] ) && is_string( $work['cover']['id'] ) ) {
			$key = $work['cover']['id'];
		} else {
			$key = $title;
		}

		return (string) $sanitize_title( $key );
	}

	/**
	 * 作品の状態: publish か draft。
	 *
	 * @param array $work JSON の作品。
	 * @return string
	 */
	public static function importStatus( array $work ): string {
		return isset( $work['status'] ) && 'publish' === $work['status'] ? 'publish' : 'draft';
	}

	/**
	 * 書き出すときの状態: publish・future は publish、それ以外は draft。
	 *
	 * @param string $post_status 投稿の状態。
	 * @return string
	 */
	public static function exportStatus( string $post_status ): string {
		return in_array( $post_status, array( 'publish', 'future' ), true ) ? 'publish' : 'draft';
	}

	/**
	 * リンクの一覧を JSON の形から整える (label の {ja,en} を文字列に)。検査はデータ層の正規化に任せる。
	 *
	 * @param mixed $list JSON の値。
	 * @return array
	 */
	public static function linksFromJson( $list ): array {
		$links = array();
		foreach ( is_array( $list ) ? $list : array() as $link ) {
			if ( is_array( $link ) ) {
				$link['label'] = self::ja( isset( $link['label'] ) ? $link['label'] : '' );
				$links[]       = $link;
			}
		}

		return $links;
	}

	/**
	 * リンクの一覧を JSON の形にする (label は {ja}、sponsored は true のときだけ)。
	 *
	 * @param array $links 正規化済みのリンク。
	 * @return array
	 */
	public static function linksToJson( array $links ): array {
		$out = array();
		foreach ( $links as $link ) {
			$item = array(
				'service' => $link['service'],
				'url'     => $link['url'],
			);
			if ( '' !== $link['label'] ) {
				$item['label'] = array( 'ja' => $link['label'] );
			}
			if ( $link['sponsored'] ) {
				$item['sponsored'] = true;
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * 画像の指定の種類: 作品番号 ({service,id}) / https の URL / 道筋 / 形が違う。
	 *
	 * @param mixed $spec JSON の値。
	 * @return string IMAGE_PRODUCT | IMAGE_URL | IMAGE_PATH | IMAGE_BAD。
	 */
	public static function imageKind( $spec ): string {
		if ( is_array( $spec ) && isset( $spec['id'] ) ) {
			return self::IMAGE_PRODUCT;
		}
		if ( ! is_string( $spec ) || '' === trim( $spec ) ) {
			return self::IMAGE_BAD;
		}

		return str_starts_with( trim( $spec ), 'https://' ) ? self::IMAGE_URL : self::IMAGE_PATH;
	}

	/**
	 * 相対の道筋を起点から解く (絶対の道筋はそのまま)。
	 *
	 * @param string $spec   JSON の道筋。
	 * @param string $images 起点のディレクトリ (末尾 / 無し)。
	 * @return string
	 */
	public static function resolvePath( string $spec, string $images ): string {
		return str_starts_with( $spec, '/' ) ? $spec : $images . '/' . $spec;
	}

	/**
	 * 画像の指定をログ用に短く。
	 *
	 * @param mixed $spec JSON の値。
	 * @return string
	 */
	public static function describe( $spec ): string {
		if ( is_array( $spec ) ) {
			return isset( $spec['id'] ) ? '作品番号 ' . $spec['id'] : '(形が違う)';
		}

		return is_string( $spec ) ? $spec : '(形が違う)';
	}

	/**
	 * サンプルの一覧のログ: 「N 件 (最初 … 最後)」。
	 *
	 * @param array $items サンプルの指定の並び。
	 * @return string
	 */
	public static function describeSamples( array $items ): string {
		$range = '';
		if ( $items ) {
			$range = ' (' . self::describe( $items[0] ) . ( count( $items ) > 1 ? ' … ' . self::describe( end( $items ) ) : '' ) . ')';
		}

		return sprintf( '  サンプル: %d 件%s', count( $items ), $range );
	}
}
