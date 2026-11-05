<?php
/**
 * 発売日時 (メタ doujin_release_at。ISO 8601、サイトのタイムゾーン。spec §6.0)。
 *
 * 読み方・正規化と、保存時の 1 ルール「公開日時 = 発売日時と今の早いほう」の計算。
 * タイムゾーンと「今」は引数で受ける (WordPress の wp_timezone() / time() は糊が渡す)。
 */

namespace DoujinProfile\Data;

use DateTimeImmutable;
use DateTimeZone;

final class ReleaseDate {

	/** 保存する形 (例 2026-05-29T00:00:00+09:00)。 */
	public const FORMAT = 'Y-m-d\TH:i:sP';

	/** REST のスキーマ: 空か ISO 8601 (分まで必須。秒とオフセットは任意)。 */
	public const REST_PATTERN = '^$|^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}';

	/** REST のスキーマ: 長さの上限。 */
	public const REST_MAX_LENGTH = 32;

	/**
	 * 文字列を DateTimeImmutable にする。オフセットの無い値は指定のタイムゾーンで読む。
	 *
	 * @param mixed        $value    ISO 8601 の文字列。
	 * @param DateTimeZone $timezone サイトのタイムゾーン。
	 * @return DateTimeImmutable|null 読めないときは null。
	 */
	public static function parse( $value, DateTimeZone $timezone ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		// date_create_immutable() は PHP 8.3 でも例外を投げず false を返す (new DateTimeImmutable() は 8.3 で投げる)。
		$datetime = date_create_immutable( trim( $value ), $timezone );

		return $datetime ? $datetime : null;
	}

	/**
	 * ISO 8601 に正規化する (指定のタイムゾーン)。読めない値は空にする。
	 *
	 * @param mixed        $value    入力値。
	 * @param DateTimeZone $timezone サイトのタイムゾーン。
	 * @return string
	 */
	public static function normalize( $value, DateTimeZone $timezone ): string {
		$datetime = self::parse( $value, $timezone );
		if ( ! $datetime ) {
			return '';
		}

		return $datetime->setTimezone( $timezone )->format( self::FORMAT );
	}

	/**
	 * 発売日時から、ルールに従う公開日時 (GMT、MySQL 形式) を求める: 発売日時と今の早いほう。
	 *
	 * @param mixed        $release_at 発売日時 (ISO 8601)。
	 * @param DateTimeZone $timezone   サイトのタイムゾーン。
	 * @param int          $now        今 (UNIX 時刻)。
	 * @return string|null 発売日時が無い・読めないときは null。
	 */
	public static function postDateGmt( $release_at, DateTimeZone $timezone, int $now ): ?string {
		$datetime = self::parse( $release_at, $timezone );
		if ( ! $datetime ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', min( $datetime->getTimestamp(), $now ) );
	}

	/**
	 * 未来 (予告) か。
	 *
	 * @param DateTimeImmutable|null $datetime 発売日時。
	 * @param int                    $now      今 (UNIX 時刻)。
	 * @return bool
	 */
	public static function isUpcoming( ?DateTimeImmutable $datetime, int $now ): bool {
		return null !== $datetime && $datetime->getTimestamp() > $now;
	}
}
