<?php
/**
 * サンプル画像の URL。DLsite は作品情報の一覧から、FANZA は連番を HEAD で探る (探る処理は注入。結果の保存は糊)。
 */

namespace DoujinProfile\Import;

use DoujinProfile\Failure;

final class SampleUrls {

	/**
	 * FANZA のサンプルの枚数。1 枚目から順に、画像が返るところまで数える。
	 *
	 * @param string   $cid   作品番号。
	 * @param int      $max   上限。
	 * @param callable $probe URL を HEAD で見る (fn( string $url ): array{code: int, type: string}|null。null は接続できなかった)。
	 * @return int|Failure
	 */
	public static function fanzaCount( string $cid, int $max, callable $probe ) {
		$count = 0;
		for ( $i = 1; $i <= $max; $i++ ) {
			$response = $probe( Fanza::sampleUrl( $cid, $i ) );
			if ( null === $response ) {
				return Fanza::connectionFailure();
			}
			if ( 200 !== (int) $response['code'] || ! str_starts_with( (string) $response['type'], 'image/' ) ) {
				break;
			}
			$count = $i;
		}

		return $count;
	}

	/**
	 * FANZA のサンプルの URL の一覧 (1 枚目から count 枚)。
	 *
	 * @param string $cid   作品番号。
	 * @param int    $count 枚数。
	 * @return string[]
	 */
	public static function fanzaList( string $cid, int $count ): array {
		$urls = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$urls[] = Fanza::sampleUrl( $cid, $i );
		}

		return $urls;
	}

	/**
	 * サンプル画像 1 枚の URL。DLsite は一覧から、FANZA は番号から組む (無ければ取るときに 302 で分かる)。
	 *
	 * @param array $product 作品番号 (ProductId::parse() の結果)。
	 * @param array $source  作品情報。
	 * @param int   $index   何枚目か (1 から)。
	 * @return string 無ければ空。
	 */
	public static function one( array $product, array $source, int $index ): string {
		if ( is_array( $source['samples'] ) ) {
			return isset( $source['samples'][ $index - 1 ] ) ? $source['samples'][ $index - 1 ] : '';
		}

		return Fanza::sampleUrl( $product['id'], $index );
	}
}
