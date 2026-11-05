<?php
/**
 * メールアドレスをログ用に伏せる (先頭 1 文字とドメインだけ)。
 */

namespace DoujinProfile\Cli;

final class EmailMask {

	/**
	 * @param string $email メールアドレス。
	 * @return string
	 */
	public static function mask( string $email ): string {
		$at = strpos( $email, '@' );

		return false === $at ? '***' : mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}
}
