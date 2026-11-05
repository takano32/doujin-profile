<?php
/**
 * DLsite: 作品情報の JSON を読んで、画像の URL・題名・発売日時・販売ページの URL・サンプルの URL を得る。
 *
 * JSON は [ { work_name, regist_date, site_id, image_main: { url }, image_samples: [ { url } ], … } ] の形 (無い番号は [])。
 * 画像の URL は // 始まりなので https: を補い、ホストが img.dlsite.jp でなければ受けない。
 * HTTP とキャッシュ (トランジェント) は糊 (includes/import/sources.php) が持ち、ここは解釈だけ。
 */

namespace DoujinProfile\Import;

use DateTimeZone;
use DoujinProfile\Data\ReleaseDate;
use DoujinProfile\Failure;

final class Dlsite {

	/** 画像を受けるホスト。 */
	public const IMAGE_HOST = 'img.dlsite.jp';

	/**
	 * 作品情報の JSON の URL。
	 *
	 * @param string $id 作品番号 (例 RJ01634826)。
	 * @return string
	 */
	public static function apiUrl( string $id ): string {
		return 'https://www.dlsite.com/maniax/api/=/product.json?workno=' . rawurlencode( $id );
	}

	/**
	 * 販売ページの URL。
	 *
	 * @param string $site_id JSON の site_id (例 maniax)。
	 * @param string $id      作品番号。
	 * @return string
	 */
	public static function shopUrl( string $site_id, string $id ): string {
		return "https://www.dlsite.com/{$site_id}/work/=/product_id/{$id}.html";
	}

	/**
	 * 画像の URL を整える。https で img.dlsite.jp のものだけ通す。
	 *
	 * @param mixed $url JSON の値 (// 始まり)。
	 * @return string 受けられなければ空。
	 */
	public static function imageUrl( $url ): string {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		if ( ! str_starts_with( $url, 'https://' ) || self::IMAGE_HOST !== parse_url( $url, PHP_URL_HOST ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * 作品情報の JSON (json_decode 済み) を作品情報にする。
	 *
	 * @param mixed        $json          json_decode( …, true ) の結果。
	 * @param string       $id            作品番号。
	 * @param callable     $sanitize_text 題名をプレーンテキストにする (fn( string ): string)。
	 * @param DateTimeZone $timezone      発売日時を読むタイムゾーン。
	 * @param int          $max_samples   サンプルの上限。
	 * @return array{image_url: string, shop_url: string, title: string, release_at: string, samples: string[]}|Failure
	 */
	public static function parse( $json, string $id, callable $sanitize_text, DateTimeZone $timezone, int $max_samples ) {
		$info = is_array( $json ) && isset( $json[0] ) && is_array( $json[0] ) ? $json[0] : null;
		$main = $info && isset( $info['image_main']['url'] ) ? self::imageUrl( $info['image_main']['url'] ) : '';
		if ( ! $info ) {
			return new Failure( 'doujin_profile_not_found', "DLsite に作品番号 {$id} の作品が見つかりません。番号を確かめてください。", 404 );
		}
		if ( '' === $main ) {
			return new Failure( 'doujin_profile_bad_image_url', 'DLsite の画像の置き場所が想定と違うため、取り込みを中止しました。画像を手でドロップしてください。', 502 );
		}

		$samples = array();
		if ( isset( $info['image_samples'] ) && is_array( $info['image_samples'] ) ) {
			foreach ( $info['image_samples'] as $sample ) {
				$url = isset( $sample['url'] ) ? self::imageUrl( $sample['url'] ) : '';
				if ( '' !== $url ) {
					$samples[] = $url;
				}
			}
		}

		$site_id = isset( $info['site_id'] ) && is_string( $info['site_id'] ) && preg_match( '/^[a-z0-9-]{1,20}$/', $info['site_id'] ) ? $info['site_id'] : 'maniax';
		$title   = isset( $info['work_name'] ) && is_string( $info['work_name'] ) ? (string) $sanitize_text( $info['work_name'] ) : '';
		$release = isset( $info['regist_date'] ) && is_string( $info['regist_date'] ) ? ReleaseDate::normalize( $info['regist_date'], $timezone ) : '';

		return array(
			'image_url'  => $main,
			'shop_url'   => self::shopUrl( $site_id, $id ),
			'title'      => $title,
			'release_at' => $release,
			'samples'    => array_slice( $samples, 0, $max_samples ),
		);
	}

	/**
	 * 接続できなかったときの失敗。
	 *
	 * @return Failure
	 */
	public static function connectionFailure(): Failure {
		return new Failure( 'doujin_profile_fetch_failed', 'DLsite に接続できませんでした。しばらくしてからもう一度お試しください。', 502 );
	}

	/**
	 * 200 以外が返ったときの失敗。
	 *
	 * @param int $code HTTP の状態。
	 * @return Failure
	 */
	public static function httpFailure( int $code ): Failure {
		return new Failure( 'doujin_profile_fetch_failed', "DLsite から作品情報を取れませんでした (HTTP {$code})。", 502 );
	}
}
