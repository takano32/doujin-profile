<?php
/**
 * サイト設定 doujin_profile_links: リンク集 (shops[] と accounts[]。各 上限 30。spec §6.0)。
 * register_setting() で標準の REST (wp/v2/settings) に載せるので、マイページは標準の保存口だけで書く。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 設定の登録 (サイト単位。init で毎回)。
 */
function doujin_profile_register_settings() {
	$links_list_schema = array(
		'type'     => 'array',
		'maxItems' => DOUJIN_PROFILE_MAX_LINKS,
		'items'    => doujin_profile_link_schema(),
	);
	register_setting(
		'doujin_profile',
		'doujin_profile_links',
		array(
			'type'              => 'object',
			'label'             => 'リンク集',
			'description'       => '販売ページ (shops) とお知らせ・活動情報 (accounts) の 2 つの一覧',
			'default'           => array(
				'shops'    => array(),
				'accounts' => array(),
			),
			'sanitize_callback' => 'doujin_profile_sanitize_profile_links',
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'shops'    => $links_list_schema,
						'accounts' => $links_list_schema,
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'doujin_profile_register_settings' );
