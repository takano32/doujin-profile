<?php
/**
 * 点検 (wp doujin doctor) の判定のうち、WordPress を読まなくても決まるもの。読むのは糊 (includes/cli/doctor.php)。
 */

namespace DoujinProfile\Cli;

final class DoctorRules {

	/** アップロードを許してよい形式 (画像だけ)。 */
	public const IMAGE_TYPES = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'svg', 'bmp', 'tif', 'tiff', 'ico' );

	/**
	 * ネットワーク設定 upload_filetypes のうち、画像でない形式。
	 *
	 * @param string $upload_filetypes 空白区切りの形式。
	 * @return string[] 無ければ空。
	 */
	public static function nonImageTypes( string $upload_filetypes ): array {
		$types = preg_split( '/\s+/', trim( $upload_filetypes ) );

		return array_values( array_diff( $types ? $types : array(), self::IMAGE_TYPES ) );
	}

	/**
	 * ネットワークで有効なプラグイン (ファイル名の一覧) に、そのスラッグのものがあるか。
	 *
	 * @param array  $active_files active_sitewide_plugins のキー (例 disable-comments/disable-comments.php)。
	 * @param string $slug         プラグインのスラッグ。
	 * @return bool
	 */
	public static function pluginActive( array $active_files, string $slug ): bool {
		foreach ( $active_files as $file ) {
			if ( str_starts_with( (string) $file, $slug . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * home / siteurl が https で、サイトのドメインと一致するか (ドメインが null なら https だけ見る)。
	 *
	 * @param string      $value  URL。
	 * @param string|null $domain サイトのドメイン (単一サイトなら null)。
	 * @return bool
	 */
	public static function isHttpsForDomain( string $value, ?string $domain ): bool {
		return str_starts_with( $value, 'https://' ) && ( null === $domain || parse_url( $value, PHP_URL_HOST ) === $domain );
	}

	/**
	 * 書き換え規則に分類 artist (/artist/<slug>/) が入っているか。
	 *
	 * @param mixed $rules rewrite_rules の値。
	 * @return bool
	 */
	public static function hasArtistRules( $rules ): bool {
		if ( ! is_array( $rules ) ) {
			return false;
		}
		foreach ( array_keys( $rules ) as $key ) {
			if ( str_starts_with( (string) $key, 'artist/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * タイムゾーンが設定されているか。
	 *
	 * @param string $timezone_string 設定 timezone_string。
	 * @param float  $gmt_offset      設定 gmt_offset。
	 * @return bool
	 */
	public static function hasTimezone( string $timezone_string, float $gmt_offset ): bool {
		return '' !== $timezone_string || 0.0 !== $gmt_offset;
	}

	/**
	 * タイムゾーンの表示 (名前が無ければ UTC+オフセット)。
	 *
	 * @param string $timezone_string 設定 timezone_string。
	 * @param mixed  $gmt_offset      設定 gmt_offset (そのまま)。
	 * @return string
	 */
	public static function timezoneLabel( string $timezone_string, $gmt_offset ): string {
		return '' !== $timezone_string ? $timezone_string : 'UTC' . $gmt_offset;
	}

	/**
	 * 容量の使用率 (%)。上限が無ければ 0。
	 *
	 * @param float $used    使用量 (MB)。
	 * @param float $allowed 上限 (MB)。
	 * @return float
	 */
	public static function spacePercent( float $used, float $allowed ): float {
		return $allowed > 0 ? $used / $allowed * 100 : 0.0;
	}

	/**
	 * 作品の点検のまとめの文。
	 *
	 * @param array $counts   状態ごとの数 (array_count_values の結果)。
	 * @param int   $total    作品の数。
	 * @param int   $upcoming 予告の数。
	 * @param int   $no_cover 書影なしの数。
	 * @return string
	 */
	public static function worksSummary( array $counts, int $total, int $upcoming, int $no_cover ): string {
		return sprintf(
			'作品 %d 件 (公開 %d・予約 %d・下書き %d・予告 %d・書影なし %d)%s',
			$total,
			$counts['publish'] ?? 0,
			$counts['future'] ?? 0,
			( $counts['draft'] ?? 0 ) + ( $counts['pending'] ?? 0 ) + ( $counts['private'] ?? 0 ),
			$upcoming,
			$no_cover,
			$upcoming ? '。予告の公開日時は最後に保存した時刻 (予告どうしの並びは保存順。spec §6.0)' : ''
		);
	}
}
