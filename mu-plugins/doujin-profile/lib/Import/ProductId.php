<?php
/**
 * 作品番号 (FANZA の cid 例 d_737824、DLsite の例 RJ01634826。spec §8.3)。
 *
 * 形を正規表現で検査し、どのサービスの番号かを判定する。任意の URL は受けない (取る元を決まった URL に限るため)。
 */

namespace DoujinProfile\Import;

final class ProductId {

	/** 形が違うときの案内 (REST の validate_callback と WP-CLI で同じ文)。 */
	public const BAD_FORMAT_MESSAGE = '作品番号の形が違います。FANZA は d_ で始まる番号 (例 d_737824)、DLsite は RJ などで始まる番号 (例 RJ01634826) を入れてください。';

	/**
	 * 作品番号の形。キーがサービス種別 (Services のキー)。
	 *
	 * @return array<string, string>
	 */
	public static function patterns(): array {
		return array(
			'fanza'  => '/^d_[a-z0-9_]{1,40}$/',   // 例 d_737824
			'dlsite' => '/^[A-Z]{2}\d{6,8}$/',      // 例 RJ01634826 (RJ・BJ・VJ など)
		);
	}

	/**
	 * 作品番号を整えて、どのサービスの番号かを判定する。
	 *
	 * @param mixed $product_id 入力値。
	 * @return array{service: string, id: string}|null 形が合わなければ null。
	 */
	public static function parse( $product_id ): ?array {
		$id = trim( (string) $product_id );
		if ( str_starts_with( strtolower( $id ), 'd_' ) ) {
			$id = strtolower( $id );
		} else {
			$id = strtoupper( $id );
		}

		foreach ( self::patterns() as $service => $pattern ) {
			if ( preg_match( $pattern, $id ) ) {
				return array(
					'service' => $service,
					'id'      => $id,
				);
			}
		}

		return null;
	}
}
