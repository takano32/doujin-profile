<?php
/**
 * WordPress の関数の代わり (lib/ が callable で受ける所に渡す)。本物と同じ結果を保証するものではなく、
 * 「渡した関数が使われること」と lib/ 側の決まりを確かめるための小さなもの。
 */

namespace DoujinProfile\Tests;

final class Stubs {

	/**
	 * sanitize_url( $url, array( 'https' ) ) の代わり: https: 以外は空。
	 *
	 * @return callable
	 */
	public static function httpsOnly(): callable {
		return static function ( $url ) {
			return preg_match( '#^https://\S+$#', (string) $url ) ? (string) $url : '';
		};
	}

	/**
	 * sanitize_text_field() の代わり: タグを外して前後の空白を落とす。
	 *
	 * @return callable
	 */
	public static function text(): callable {
		return static function ( $text ) {
			return trim( strip_tags( (string) $text ) );
		};
	}

	/**
	 * sanitize_title() の代わり: 小文字にして空白を - に。
	 *
	 * @return callable
	 */
	public static function slug(): callable {
		return static function ( $text ) {
			return trim( preg_replace( '/\s+/u', '-', mb_strtolower( trim( (string) $text ) ) ), '-' );
		};
	}
}
