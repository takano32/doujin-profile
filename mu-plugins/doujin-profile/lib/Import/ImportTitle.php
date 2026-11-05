<?php
/**
 * 取り込んだ添付の題名。
 */

namespace DoujinProfile\Import;

final class ImportTitle {

	/**
	 * 作品情報の題名 (DLsite) → 画面で入力中のタイトル → 作品番号。
	 *
	 * @param string $source_title  作品情報の題名 (FANZA は空)。
	 * @param string $request_title 画面で入力中のタイトル。
	 * @param string $product_id    作品番号。
	 * @return string
	 */
	public static function resolve( string $source_title, string $request_title, string $product_id ): string {
		if ( '' !== $source_title ) {
			return $source_title;
		}
		$title = trim( $request_title );

		return '' !== $title ? $title : $product_id;
	}

	/**
	 * サンプル 1 枚の題名 (「<題名> サンプル 01」)。
	 *
	 * @param string $title 作品の題名。
	 * @param int    $index 何枚目か (1 から)。
	 * @return string
	 */
	public static function sample( string $title, int $index ): string {
		return sprintf( '%s サンプル %02d', $title, $index );
	}
}
