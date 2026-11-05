<?php
/**
 * リンクボタンの表示名と rel (公開側のブロック。spec §6.2)。
 */

namespace DoujinProfile\Blocks;

use DoujinProfile\Data\Services;

final class LinkLabel {

	/**
	 * リンク 1 件の表示名。表示名が無ければサービス名 (作品カードでは「FANZAで見る」の形)。
	 *
	 * @param array $link      正規化済みのリンク。
	 * @param bool  $with_verb 「で見る」を付けるか。
	 * @return string
	 */
	public static function of( array $link, bool $with_verb = false ): string {
		if ( '' !== $link['label'] ) {
			return $link['label'];
		}

		if ( Services::OTHER === $link['service'] ) {
			return $with_verb ? '販売ページを見る' : 'リンク';
		}

		$name = Services::name( $link['service'] );

		return $with_verb ? $name . 'で見る' : $name;
	}

	/**
	 * rel 属性: noopener を常に、アフィリエイトなら sponsored を足す。
	 *
	 * @param bool $sponsored アフィリエイトか。
	 * @return string
	 */
	public static function rel( bool $sponsored ): string {
		return $sponsored ? 'noopener sponsored' : 'noopener';
	}
}
