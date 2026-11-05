<?php
/**
 * サンプル画像の添付 ID の並び (メタ doujin_sample_ids。順 = 表示の並び。spec §6.0)。
 */

namespace DoujinProfile\Data;

final class SampleIds {

	/** 枚数の上限。 */
	public const MAX = 80;

	/**
	 * 正規化する (正の整数、重複なし、上限 80)。
	 *
	 * @param mixed $ids 入力値。
	 * @return int[]
	 */
	public static function sanitize( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$clean = array();
		foreach ( $ids as $id ) {
			if ( ! is_numeric( $id ) ) {
				continue;
			}
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $clean, true ) ) {
				$clean[] = $id;
			}
			if ( count( $clean ) >= self::MAX ) {
				break;
			}
		}

		return $clean;
	}
}
