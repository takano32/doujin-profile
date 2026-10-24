<?php
/**
 * 作品のデータ層 (spec §6.0)。
 *
 * 作品 1 件 = 投稿 (post) 1 件。データは「標準の項目 + 投稿メタ + 分類 + サイト設定」に置き、
 * 型・上限・サニタイズはここの register_post_meta() / register_setting() に 1 度だけ書く。
 * マイページ (REST)・ブロックエディタ・WP-CLI のどこから書いても同じ検査を通る。
 *
 *   分類     artist                 作家 (タグ型。一覧ページは /artist/<slug>/)
 *   メタ     doujin_release_at      発売日時 (ISO 8601。サイトのタイムゾーン)
 *   メタ     doujin_release_label   発売日の表記 (空なら発売日時から自動)
 *   メタ     doujin_shop_links      販売先リンク (上限 30)
 *   メタ     doujin_sample_ids      サンプル画像の添付 ID (順 = 並び。上限 80)
 *   設定     doujin_profile_links   サイトのリンク集 (shops[] と accounts[]。各 上限 30)
 *
 * 保存時の 1 ルール: 公開日時 = 発売日時と今の早いほう (未来のままだと「予約」になって公開されないため)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_MAX_LINKS   = 30;
const DOUJIN_PROFILE_MAX_SAMPLES = 80;

/**
 * リンクのサービス種別 (spec §6.2)。キーが保存される値、値が表示名。
 *
 * @return array<string, string>
 */
function doujin_profile_services() {
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
		'other'      => 'その他',
	);
}

/**
 * リンク 1 件の REST スキーマ (メタの doujin_shop_links と設定の doujin_profile_links で共用)。
 *
 * @return array
 */
function doujin_profile_link_schema() {
	return array(
		'type'                 => 'object',
		'properties'           => array(
			'service'   => array(
				'type' => 'string',
				'enum' => array_keys( doujin_profile_services() ),
			),
			'url'       => array(
				'type'      => 'string',
				'pattern'   => '^https://',
				'maxLength' => 2000,
			),
			'label'     => array(
				'type'      => 'string',
				'maxLength' => 100,
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
 * 分類・メタ・設定の登録。
 */
function doujin_profile_register_data() {
	register_taxonomy(
		'artist',
		'post',
		array(
			'labels'            => array(
				'name'          => '作家',
				'singular_name' => '作家',
				'search_items'  => '作家を検索',
				'all_items'     => 'すべての作家',
				'edit_item'     => '作家を編集',
				'update_item'   => '作家を更新',
				'add_new_item'  => '作家を追加',
				'new_item_name' => '新しい作家の名前',
				'not_found'     => '作家が見つかりません',
				'menu_name'     => '作家',
			),
			'description'       => '作品の作画担当。1 作品に複数可、役割の区別はしない (T.31.1)',
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'artist' ), // /artist/<slug>/ (T.31.3)。配備後に既存のサイトで一度 wp rewrite flush
		)
	);

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
					'pattern'   => '^$|^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}', // 空か ISO 8601 (分まで必須。秒とオフセットは任意)
					'maxLength' => 32,
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
					'maxLength' => 200,
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
add_action( 'init', 'doujin_profile_register_data' );

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

/**
 * 発売日時の文字列を DateTimeImmutable にする。オフセットの無い値はサイトのタイムゾーンで読む。
 *
 * @param mixed $value ISO 8601 の文字列。
 * @return DateTimeImmutable|null 読めないときは null。
 */
function doujin_profile_parse_datetime( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return null;
	}
	// date_create_immutable() は PHP 8.3 でも例外を投げず false を返す (new DateTimeImmutable() は 8.3 で投げる)。
	$datetime = date_create_immutable( trim( $value ), wp_timezone() );

	return $datetime ? $datetime : null;
}

/**
 * 発売日時: ISO 8601 に正規化する (サイトのタイムゾーン。例 2026-05-29T00:00:00+09:00)。読めない値は空にする。
 *
 * @param mixed $value 入力値。
 * @return string
 */
function doujin_profile_sanitize_release_at( $value ) {
	$datetime = doujin_profile_parse_datetime( $value );
	if ( ! $datetime ) {
		return '';
	}

	return $datetime->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i:sP' );
}

/**
 * 発売日の表記: プレーンテキスト 200 文字まで。
 *
 * @param mixed $value 入力値。
 * @return string
 */
function doujin_profile_sanitize_release_label( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	return mb_substr( sanitize_text_field( $value ), 0, 200 );
}

/**
 * リンク 1 件を正規化する。URL が https: でなければ捨てる (null)。
 *
 * @param mixed $link 入力値。
 * @return array|null
 */
function doujin_profile_sanitize_link( $link ) {
	if ( ! is_array( $link ) ) {
		return null;
	}

	$url = isset( $link['url'] ) && is_string( $link['url'] ) ? sanitize_url( trim( $link['url'] ), array( 'https' ) ) : '';
	if ( '' === $url ) {
		return null;
	}

	$service = isset( $link['service'] ) && is_string( $link['service'] ) ? $link['service'] : 'other';
	if ( ! array_key_exists( $service, doujin_profile_services() ) ) {
		$service = 'other';
	}

	$label = isset( $link['label'] ) && is_string( $link['label'] ) ? mb_substr( sanitize_text_field( $link['label'] ), 0, 100 ) : '';

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
function doujin_profile_sanitize_links( $links ) {
	if ( ! is_array( $links ) ) {
		return array();
	}

	$clean = array();
	foreach ( $links as $link ) {
		$link = doujin_profile_sanitize_link( $link );
		if ( $link ) {
			$clean[] = $link;
		}
		if ( count( $clean ) >= DOUJIN_PROFILE_MAX_LINKS ) {
			break;
		}
	}

	return $clean;
}

/**
 * サンプル画像の添付 ID の並びを正規化する (正の整数、重複なし、上限 80)。
 *
 * @param mixed $ids 入力値。
 * @return int[]
 */
function doujin_profile_sanitize_sample_ids( $ids ) {
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
		if ( count( $clean ) >= DOUJIN_PROFILE_MAX_SAMPLES ) {
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
function doujin_profile_sanitize_profile_links( $value ) {
	if ( ! is_array( $value ) ) {
		$value = array();
	}

	return array(
		'shops'    => doujin_profile_sanitize_links( $value['shops'] ?? array() ),
		'accounts' => doujin_profile_sanitize_links( $value['accounts'] ?? array() ),
	);
}

/**
 * 保存時の 1 ルール: 公開日時 = 発売日時と今の早いほう (spec §6.0)。
 *
 * 公開する作品 (publish。コアが未来の日付で future に変えたものも含む) で、発売日時があるときだけ効く。
 * 下書きの日付はコアの既定に任せる。発売日時は、同じ保存で渡された meta_input があればそれを、
 * 無ければ保存済みのメタを見る (REST はメタを投稿のあとに保存するので、そちらは
 * doujin_profile_release_at_changed() が受けて再保存する)。
 *
 * @param array $data    保存される投稿データ (サニタイズ済み)。
 * @param array $postarr 渡された投稿データ。
 * @return array
 */
function doujin_profile_apply_release_date( $data, $postarr ) {
	if ( 'post' !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
		return $data;
	}

	if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( 'doujin_release_at', $postarr['meta_input'] ) ) {
		$release_at = doujin_profile_sanitize_release_at( $postarr['meta_input']['doujin_release_at'] );
	} elseif ( ! empty( $postarr['ID'] ) ) {
		$release_at = get_post_meta( (int) $postarr['ID'], 'doujin_release_at', true );
	} else {
		return $data;
	}

	$date_gmt = doujin_profile_release_post_date_gmt( $release_at );
	if ( null === $date_gmt ) {
		return $data;
	}

	$data['post_date_gmt'] = $date_gmt;
	$data['post_date']     = get_date_from_gmt( $date_gmt );
	$data['post_status']   = 'publish'; // 今より先にはならないので、予約 (future) にはしない。

	return $data;
}
add_filter( 'wp_insert_post_data', 'doujin_profile_apply_release_date', 10, 2 );

/**
 * 発売日時から、ルールに従う公開日時 (GMT、MySQL 形式) を求める。
 *
 * @param mixed $release_at 発売日時 (ISO 8601)。
 * @return string|null 発売日時が無い・読めないときは null。
 */
function doujin_profile_release_post_date_gmt( $release_at ) {
	$datetime = doujin_profile_parse_datetime( $release_at );
	if ( ! $datetime ) {
		return null;
	}

	return gmdate( 'Y-m-d H:i:s', min( $datetime->getTimestamp(), time() ) );
}

/**
 * 発売日時のメタが (REST などで投稿の保存のあとに) 変わったら、公開日時をルールに合わせて再保存する。
 *
 * 再保存は wp_update_post() → doujin_profile_apply_release_date() の経路で、ここでは日付を計算しない。
 * 公開日時がすでに合っているときは何もしない (無駄な保存をしない)。
 *
 * @param int    $meta_id  メタ ID。
 * @param int    $post_id  投稿 ID。
 * @param string $meta_key メタのキー。
 */
function doujin_profile_release_at_changed( $meta_id, $post_id, $meta_key ) {
	if ( 'doujin_release_at' !== $meta_key ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}

	$date_gmt = doujin_profile_release_post_date_gmt( get_post_meta( $post_id, 'doujin_release_at', true ) );
	if ( null === $date_gmt || ( $date_gmt === $post->post_date_gmt && 'publish' === $post->post_status ) ) {
		return;
	}

	wp_update_post( array( 'ID' => $post_id ) );
}
add_action( 'added_post_meta', 'doujin_profile_release_at_changed', 10, 3 );
add_action( 'updated_post_meta', 'doujin_profile_release_at_changed', 10, 3 );
