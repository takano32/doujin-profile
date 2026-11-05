<?php
/**
 * リンクのサービス種別 (spec §6.2)。キーが保存される値、値が表示名。
 */

namespace DoujinProfile\Data;

final class Services {

	/** 種別が分からないときに使う値。 */
	public const OTHER = 'other';

	/**
	 * すべての種別。
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'fanza'      => 'FANZA',
			'dlsite'     => 'DLsite',
			'melonbooks' => 'メロンブックス',
			'toranoana'  => 'とらのあな',
			'booth'      => 'BOOTH',
			'pixiv'      => 'pixiv',
			'x'          => 'X',
			'note'       => 'note',
			'circlems'   => 'Circle.ms',
			'bluesky'    => 'Bluesky',
			'misskey'    => 'Misskey',
			'fantia'     => 'Fantia',
			'fanbox'     => 'FANBOX',
			'skeb'       => 'Skeb',
			'youtube'    => 'YouTube',
			self::OTHER  => 'その他',
		);
	}

	/**
	 * 知っている種別か。
	 *
	 * @param string $service 種別。
	 * @return bool
	 */
	public static function has( string $service ): bool {
		return array_key_exists( $service, self::all() );
	}

	/**
	 * 表示名。知らない種別は「その他」の表示名。
	 *
	 * @param string $service 種別。
	 * @return string
	 */
	public static function name( string $service ): string {
		$all = self::all();

		return $all[ $service ] ?? $all[ self::OTHER ];
	}
}
