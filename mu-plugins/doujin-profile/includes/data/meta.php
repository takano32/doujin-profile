<?php
/**
 * 作品 (投稿) のメタの登録 (spec §6.0)。型・上限・サニタイズはここに 1 度だけ書く。
 *
 *   doujin_release_at      発売日時 (ISO 8601。サイトのタイムゾーン)
 *   doujin_release_label   発売日の表記 (空なら発売日時から自動)
 *   doujin_shop_links      販売先リンク (上限 30)
 *   doujin_sample_ids      サンプル画像の添付 ID (順 = 並び。上限 80)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Data\ReleaseDate;
use DoujinProfile\Data\ReleaseLabel;

/**
 * メタの登録 (サイト単位。init で毎回)。
 */
function doujin_profile_register_meta() {
	$meta_common = array(
		'single'        => true,
		'auth_callback' => 'doujin_profile_meta_auth',
	);

	register_post_meta(
		'post',
		'doujin_release_at',
		$meta_common + array(
			'type'              => 'string',
			'label'             => '発売日時',
			'description'       => 'ISO 8601 (例 2026-05-29T00:00:00+09:00)。空なら未設定',
			'default'           => '',
			'sanitize_callback' => 'doujin_profile_sanitize_release_at',
			'show_in_rest'      => array(
				'schema' => array(
					'type'      => 'string',
					'pattern'   => ReleaseDate::REST_PATTERN, // 空か ISO 8601 (分まで必須。秒とオフセットは任意)
					'maxLength' => ReleaseDate::REST_MAX_LENGTH,
				),
			),
		)
	);

	register_post_meta(
		'post',
		'doujin_release_label',
		$meta_common + array(
			'type'              => 'string',
			'label'             => '発売日の表記',
			'description'       => '空なら発売日時から自動で作る',
			'default'           => '',
			'sanitize_callback' => 'doujin_profile_sanitize_release_label',
			'show_in_rest'      => array(
				'schema' => array(
					'type'      => 'string',
					'maxLength' => ReleaseLabel::MAX_LENGTH,
				),
			),
		)
	);

	register_post_meta(
		'post',
		'doujin_shop_links',
		$meta_common + array(
			'type'              => 'array',
			'label'             => '販売先リンク',
			'default'           => array(),
			'sanitize_callback' => 'doujin_profile_sanitize_links',
			'show_in_rest'      => array(
				'schema' => array(
					'type'     => 'array',
					'maxItems' => DOUJIN_PROFILE_MAX_LINKS,
					'items'    => doujin_profile_link_schema(),
				),
			),
		)
	);

	register_post_meta(
		'post',
		'doujin_sample_ids',
		$meta_common + array(
			'type'              => 'array',
			'label'             => 'サンプル画像',
			'description'       => '添付 ID の並び。順 = 表示の並び',
			'default'           => array(),
			'sanitize_callback' => 'doujin_profile_sanitize_sample_ids',
			'show_in_rest'      => array(
				'schema' => array(
					'type'     => 'array',
					'maxItems' => DOUJIN_PROFILE_MAX_SAMPLES,
					'items'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
		)
	);
}
add_action( 'init', 'doujin_profile_register_meta' );

/**
 * メタを書けるのは、その投稿を編集できる人だけ。
 *
 * @param bool   $allowed   既定の判定。
 * @param string $meta_key  メタのキー。
 * @param int    $object_id 投稿 ID。
 * @return bool
 */
function doujin_profile_meta_auth( $allowed, $meta_key, $object_id ) {
	return current_user_can( 'edit_post', (int) $object_id );
}
