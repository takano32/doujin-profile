<?php
/**
 * 画像を取ったときの応答の判定。200 で image/* が返ったときだけ保存する (転送 302 や 404 は「番号を確かめて」)。
 */

namespace DoujinProfile\Import;

use DoujinProfile\Failure;

final class ImageResponse {

	/**
	 * 保存できる応答か。
	 *
	 * @param int    $code HTTP の状態 (接続できなかったときは 0)。
	 * @param string $type Content-Type。
	 * @return bool
	 */
	public static function isImage( int $code, string $type ): bool {
		return 200 === $code && str_starts_with( $type, 'image/' );
	}

	/**
	 * 保存できない応答の失敗 (保存できるなら null)。
	 *
	 * @param int    $code HTTP の状態 (接続できなかったときは 0)。
	 * @param string $type Content-Type。
	 * @return Failure|null
	 */
	public static function failure( int $code, string $type ): ?Failure {
		if ( self::isImage( $code, $type ) ) {
			return null;
		}
		$hint = in_array( $code, array( 302, 404 ), true )
			? 'その作品番号の画像が見つかりません。番号を確かめてください。'
			: '販売サイトから画像を取れませんでした。しばらくしてからもう一度お試しください。';

		return new Failure( 'doujin_profile_download_failed', $hint . ( $code ? " (HTTP {$code})" : '' ), 502 );
	}
}
