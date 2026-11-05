<?php
/**
 * リンクの正規化 (メタ doujin_shop_links と設定 doujin_profile_links で共用。spec §6.2)。
 *
 * 1 件はサービス種別・URL (https: のみ)・表示名 (100 文字まで)・アフィリエイトの有無。一覧は上限 30 で、壊れた件は捨てる。
 * URL と文字列の検査そのもの (WordPress の sanitize_url() / sanitize_text_field()) は注入する。
 */

namespace DoujinProfile\Data;

final class Links {

	/** 一覧の上限。 */
	public const MAX = 30;

	/** 表示名の上限 (文字)。 */
	public const MAX_LABEL = 100;

	/** URL の上限 (文字。REST のスキーマ)。 */
	public const MAX_URL = 2000;

	/** @var callable URL を整える。https: 以外は空を返す (fn( string ): string)。 */
	private $sanitize_url;

	/** @var callable プレーンテキストにする (fn( string ): string)。 */
	private $sanitize_text;

	/**
	 * @param callable $sanitize_url  URL を整える。https: 以外は空を返す。
	 * @param callable $sanitize_text プレーンテキストにする。
	 */
	public function __construct( callable $sanitize_url, callable $sanitize_text ) {
		$this->sanitize_url  = $sanitize_url;
		$this->sanitize_text = $sanitize_text;
	}

	/**
	 * リンク 1 件の REST スキーマ。
	 *
	 * @return array
	 */
	public static function schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'service'   => array(
					'type' => 'string',
					'enum' => array_keys( Services::all() ),
				),
				'url'       => array(
					'type'      => 'string',
					'pattern'   => '^https://',
					'maxLength' => self::MAX_URL,
				),
				'label'     => array(
					'type'      => 'string',
					'maxLength' => self::MAX_LABEL,
				),
				'sponsored' => array(
					'type' => 'boolean',
				),
			),
			'required'             => array( 'service', 'url' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * リンク 1 件を正規化する。URL が https: でなければ捨てる (null)。
	 *
	 * @param mixed $link 入力値。
	 * @return array|null
	 */
	public function one( $link ): ?array {
		if ( ! is_array( $link ) ) {
			return null;
		}

		$url = isset( $link['url'] ) && is_string( $link['url'] ) ? (string) ( $this->sanitize_url )( trim( $link['url'] ) ) : '';
		if ( '' === $url ) {
			return null;
		}

		$service = isset( $link['service'] ) && is_string( $link['service'] ) ? $link['service'] : Services::OTHER;
		if ( ! Services::has( $service ) ) {
			$service = Services::OTHER;
		}

		$label = isset( $link['label'] ) && is_string( $link['label'] ) ? mb_substr( (string) ( $this->sanitize_text )( $link['label'] ), 0, self::MAX_LABEL ) : '';

		return array(
			'service'   => $service,
			'url'       => $url,
			'label'     => $label,
			'sponsored' => ! empty( $link['sponsored'] ) && 'false' !== $link['sponsored'],
		);
	}

	/**
	 * リンクの一覧を正規化する (上限 30。壊れた件は捨てる)。
	 *
	 * @param mixed $links 入力値。
	 * @return array
	 */
	public function many( $links ): array {
		if ( ! is_array( $links ) ) {
			return array();
		}

		$clean = array();
		foreach ( $links as $link ) {
			$link = $this->one( $link );
			if ( $link ) {
				$clean[] = $link;
			}
			if ( count( $clean ) >= self::MAX ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * サイトのリンク集を正規化する (shops と accounts の 2 つの一覧。それ以外のキーは持たない)。
	 *
	 * @param mixed $value 入力値。
	 * @return array{shops: array, accounts: array}
	 */
	public function profile( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return array(
			'shops'    => $this->many( $value['shops'] ?? array() ),
			'accounts' => $this->many( $value['accounts'] ?? array() ),
		);
	}
}
