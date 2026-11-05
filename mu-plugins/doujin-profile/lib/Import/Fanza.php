<?php
/**
 * FANZA (同人): 作品番号 (cid) から URL を組むだけ。作品情報の API は無い (owner-editor-design §9)。
 *
 * 画像は年齢確認なしで取れる。無い番号の画像は「準備中」の画像へ 302 で転送されるので、取るときは転送を追わない。
 */

namespace DoujinProfile\Import;

use DoujinProfile\Failure;

final class Fanza {

	/**
	 * 作品情報 (題名・発売日時は無い。サンプルの一覧は null = 連番を探る)。
	 *
	 * @param string $cid 作品番号 (例 d_737824)。
	 * @return array{image_url: string, shop_url: string, title: string, release_at: string, samples: null}
	 */
	public static function source( string $cid ): array {
		return array(
			'image_url'  => "https://doujin-assets.dmm.co.jp/digital/comic/{$cid}/{$cid}pl.jpg",
			'shop_url'   => "https://www.dmm.co.jp/dc/doujin/-/detail/=/cid={$cid}/",
			'title'      => '',
			'release_at' => '',
			'samples'    => null,
		);
	}

	/**
	 * サンプル画像の URL (jp-001.jpg からの連番)。
	 *
	 * @param string $cid   作品番号。
	 * @param int    $index 何枚目か (1 から)。
	 * @return string
	 */
	public static function sampleUrl( string $cid, int $index ): string {
		return sprintf( 'https://doujin-assets.dmm.co.jp/digital/comic/%1$s/%1$sjp-%2$03d.jpg', $cid, $index );
	}

	/**
	 * 接続できなかったときの失敗。
	 *
	 * @return Failure
	 */
	public static function connectionFailure(): Failure {
		return new Failure( 'doujin_profile_fetch_failed', 'FANZA に接続できませんでした。しばらくしてからもう一度お試しください。', 502 );
	}
}
