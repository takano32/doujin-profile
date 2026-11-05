<?php
/**
 * 発売日の表記 (メタ doujin_release_label。空なら発売日時から自動。spec §6.0)。
 */

namespace DoujinProfile\Data;

use DateTimeImmutable;
use DateTimeZone;

final class ReleaseLabel {

	/** 表記の上限 (文字)。 */
	public const MAX_LENGTH = 200;

	/**
	 * プレーンテキスト 200 文字まで。
	 *
	 * @param mixed    $value         入力値。
	 * @param callable $sanitize_text プレーンテキストにする (fn( string ): string)。
	 * @return string
	 */
	public static function sanitize( $value, callable $sanitize_text ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return mb_substr( (string) $sanitize_text( $value ), 0, self::MAX_LENGTH );
	}

	/**
	 * 発売日時からの自動の表記: 「2026年5月30日発売」、未来なら「発売予定日: 2026年5月30日」。
	 *
	 * @param string $date     日付の文 (例 2026年5月30日)。
	 * @param bool   $upcoming 未来か。
	 * @return string
	 */
	public static function auto( string $date, bool $upcoming ): string {
		return $upcoming ? '発売予定日: ' . $date : $date . '発売';
	}

	/**
	 * 作品の発売日の表記 (作品カードが使う形)。
	 *
	 * @param string                 $label       正規化済みの表記 (空なら発売日時から自動)。
	 * @param DateTimeImmutable|null $datetime    発売日時 (無ければ null)。
	 * @param int                    $now         今 (UNIX 時刻)。
	 * @param callable               $format_date UNIX 時刻を「Y年n月j日」の形にする (サイトのタイムゾーンで。WordPress の wp_date())。
	 * @param DateTimeZone           $timezone    サイトのタイムゾーン (release_at の表記に使う)。
	 * @return array{label: string, upcoming: bool, release_at: string} release_at は ISO 8601 (無ければ空)。
	 */
	public static function info( string $label, ?DateTimeImmutable $datetime, int $now, callable $format_date, DateTimeZone $timezone ): array {
		$upcoming = ReleaseDate::isUpcoming( $datetime, $now );

		if ( '' === $label && $datetime ) {
			$label = self::auto( (string) $format_date( $datetime->getTimestamp() ), $upcoming );
		}

		return array(
			'label'      => $label,
			'upcoming'   => $upcoming,
			'release_at' => $datetime ? $datetime->setTimezone( $timezone )->format( ReleaseDate::FORMAT ) : '',
		);
	}
}
