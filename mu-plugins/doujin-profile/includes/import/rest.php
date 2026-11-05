<?php
/**
 * 作品番号からの取り込み: 独自 REST の入口 (spec §8.3。T.36.4 の下調べは owner-editor-design.md §9)。
 *
 * マイページで作品番号 (FANZA の cid 例 d_737824、DLsite の例 RJ01634826) を入れてボタンを押すと、
 * サーバが販売サイトから画像を取ってメディアライブラリに保存する (添付の親 = その作品)。
 * 標準の REST に無い処理なので、ここだけが独自の保存口:
 *
 *   POST /wp-json/doujin-profile/v1/works/<投稿 ID>/cover    { "product_id": "d_737824", "title": "…" }   書影 → メディア + アイキャッチ
 *   GET  /wp-json/doujin-profile/v1/works/<投稿 ID>/samples  ?product_id=d_737824                        サンプルの枚数と取り込み済みの添付
 *   POST /wp-json/doujin-profile/v1/works/<投稿 ID>/samples  { "product_id": "d_737824", "index": 3, "title": "…" }  サンプル 1 枚 → メディア
 *
 * サンプルは 1 回の要求で 1 枚だけ取る (1 枚 0.3〜0.9MB。10〜30 枚をまとめると、さくらの max_execution_time 60 秒に届きかねない)。
 * 画面が GET で枚数を見て POST を 1 枚ずつ呼び、進み具合を出す。
 *
 * 守り: 権限は upload_files + その投稿の edit_post、nonce は標準の REST Cookie 認証 (X-WP-Nonce)。
 * 取る元は FANZA・DLsite の決まった URL だけ (作品番号は正規表現で検査し、任意の URL は受けない。lib/Import/ProductId.php)。
 *
 * 返すもの: 添付 ID と画像の URL、販売ページへのリンク (素の URL。アフィリエイト ID は付けない — T.36.6 は未決)、
 * DLsite なら題名と発売日時。リンク・題名・発売日時・サンプルの並びは画面が投稿の編集として受け取り、持ち主が「保存」で
 * 標準の REST から書く (サーバが勝手に投稿の中身まで書き換えない。アイキャッチだけはここで設定する)。
 * 作品情報の取り方は includes/import/sources.php、メディアへの保存は includes/import/media.php。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Import\ImportTitle;
use DoujinProfile\Import\ProductId;
use DoujinProfile\Import\SampleUrls;

const DOUJIN_PROFILE_REST_NAMESPACE = 'doujin-profile/v1';

/**
 * 作品番号の形。キーがサービス種別 (doujin_profile_services() のキー)。
 *
 * @return array<string, string>
 */
function doujin_profile_product_id_patterns() {
	return ProductId::patterns();
}

/**
 * 作品番号を整えて、どのサービスの番号かを判定する。
 *
 * @param string $product_id 入力値。
 * @return array{service: string, id: string}|null 形が合わなければ null。
 */
function doujin_profile_parse_product_id( $product_id ) {
	return ProductId::parse( $product_id );
}

/**
 * REST の経路を登録する。
 */
function doujin_profile_register_import_routes() {
	$args = array(
		'id'         => array(
			'type'     => 'integer',
			'required' => true,
			'minimum'  => 1,
		),
		'product_id' => array(
			'type'              => 'string',
			'required'          => true,
			'description'       => '作品番号 (FANZA の cid か DLsite の番号)',
			'maxLength'         => 50,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'doujin_profile_validate_product_id',
		),
		'title'      => array(
			'type'              => 'string',
			'required'          => false,
			'default'           => '',
			'description'       => '画面で入力中の作品のタイトル (添付の題名に使う。FANZA は作品情報を返さないため)',
			'maxLength'         => 200,
			'sanitize_callback' => 'sanitize_text_field',
		),
	);

	register_rest_route(
		DOUJIN_PROFILE_REST_NAMESPACE,
		'/works/(?P<id>\d+)/cover',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'doujin_profile_rest_import_cover',
			'permission_callback' => 'doujin_profile_rest_import_permission',
			'args'                => $args,
		)
	);

	register_rest_route(
		DOUJIN_PROFILE_REST_NAMESPACE,
		'/works/(?P<id>\d+)/samples',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'doujin_profile_rest_list_samples',
				'permission_callback' => 'doujin_profile_rest_import_permission',
				'args'                => $args,
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'doujin_profile_rest_import_sample',
				'permission_callback' => 'doujin_profile_rest_import_permission',
				'args'                => $args + array(
					'index' => array(
						'type'        => 'integer',
						'required'    => true,
						'description' => '何枚目か (1 から)',
						'minimum'     => 1,
						'maximum'     => DOUJIN_PROFILE_MAX_SAMPLES,
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'doujin_profile_register_import_routes' );

/**
 * 作品番号の形の検査 (REST の args)。
 *
 * コアはこの WP_Error を「無効なパラメータ: product_id」(rest_invalid_param) に包み、この文は data.params.product_id に入る。
 * 画面側 (src/my-page/lib/errors.js の errorText) がそこから取り出して見せる。
 *
 * @param mixed $value 入力値。
 * @return true|WP_Error
 */
function doujin_profile_validate_product_id( $value ) {
	if ( is_string( $value ) && ProductId::parse( $value ) ) {
		return true;
	}

	return new WP_Error( 'doujin_profile_bad_product_id', ProductId::BAD_FORMAT_MESSAGE, array( 'status' => 400 ) );
}

/**
 * 権限: 画像を上げられて (upload_files)、その作品を編集できる人だけ。
 *
 * @param WP_REST_Request $request リクエスト。
 * @return true|WP_Error
 */
function doujin_profile_rest_import_permission( $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'doujin_profile_no_work', 'その作品が見つかりません。', array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'doujin_profile_forbidden', 'この作品に画像を取り込む権限がありません。', array( 'status' => rest_authorization_required_code() ) );
	}

	return true;
}

/**
 * 書影の取り込み。
 *
 * @param WP_REST_Request $request リクエスト。
 * @return WP_REST_Response|WP_Error
 */
function doujin_profile_rest_import_cover( $request ) {
	$post_id = (int) $request['id'];
	$product = ProductId::parse( $request['product_id'] );
	if ( ! $product ) {
		return doujin_profile_validate_product_id( '' );
	}

	$source = doujin_profile_source( $product );
	if ( is_wp_error( $source ) ) {
		return $source;
	}

	$title         = doujin_profile_import_title( $source, $request, $product );
	$previous_id   = (int) get_post_thumbnail_id( $post_id );
	$attachment_id = doujin_profile_import_image( $source['image_url'], $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	set_post_thumbnail( $post_id, $attachment_id );
	if ( $previous_id && $previous_id !== $attachment_id ) {
		doujin_profile_delete_imported_cover( $previous_id, $post_id );
	}

	return rest_ensure_response(
		array(
			'featured_media' => $attachment_id,
			'cover_url'      => (string) wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'service'        => $product['service'],
			'product_id'     => $product['id'],
			'shop_link'      => array(
				'service'   => $product['service'],
				'url'       => $source['shop_url'],
				'label'     => '',
				'sponsored' => false,
			),
			'title'          => $source['title'],
			'release_at'     => $source['release_at'],
		)
	);
}

/**
 * サンプルの枚数と、取り込み済みの添付 (画像は取らない)。
 *
 * @param WP_REST_Request $request リクエスト。
 * @return WP_REST_Response|WP_Error { title, samples: [ { index, attachment_id (未取り込みなら 0) } ] }
 */
function doujin_profile_rest_list_samples( $request ) {
	$post_id = (int) $request['id'];
	$product = ProductId::parse( $request['product_id'] );
	if ( ! $product ) {
		return doujin_profile_validate_product_id( '' );
	}

	$source = doujin_profile_source( $product );
	if ( is_wp_error( $source ) ) {
		return $source;
	}
	$urls = doujin_profile_sample_urls( $product, $source );
	if ( is_wp_error( $urls ) ) {
		return $urls;
	}

	$imported = doujin_profile_imported_attachments( $post_id );
	$samples  = array();
	foreach ( $urls as $i => $url ) {
		$samples[] = array(
			'index'         => $i + 1,
			'attachment_id' => isset( $imported[ $url ] ) ? $imported[ $url ] : 0,
		);
	}

	return rest_ensure_response(
		array(
			'service'    => $product['service'],
			'product_id' => $product['id'],
			'title'      => $source['title'],
			'samples'    => $samples,
		)
	);
}

/**
 * サンプル 1 枚の取り込み。
 *
 * @param WP_REST_Request $request リクエスト。
 * @return WP_REST_Response|WP_Error { index, attachment_id }
 */
function doujin_profile_rest_import_sample( $request ) {
	$post_id = (int) $request['id'];
	$index   = (int) $request['index'];
	$product = ProductId::parse( $request['product_id'] );
	if ( ! $product ) {
		return doujin_profile_validate_product_id( '' );
	}

	$source = doujin_profile_source( $product );
	if ( is_wp_error( $source ) ) {
		return $source;
	}
	$url = SampleUrls::one( $product, $source, $index );
	if ( ! $url ) {
		return new WP_Error( 'doujin_profile_no_sample', "{$index} 枚目のサンプル画像はありません。", array( 'status' => 404 ) );
	}

	$title         = ImportTitle::sample( doujin_profile_import_title( $source, $request, $product ), $index );
	$attachment_id = doujin_profile_import_image( $url, $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	return rest_ensure_response(
		array(
			'index'         => $index,
			'attachment_id' => $attachment_id,
		)
	);
}

/**
 * 添付の題名: 作品情報の題名 (DLsite) → 画面で入力中のタイトル → 作品番号。
 *
 * @param array           $source  作品情報。
 * @param WP_REST_Request $request リクエスト (title)。
 * @param array           $product 作品番号。
 * @return string
 */
function doujin_profile_import_title( $source, $request, $product ) {
	return ImportTitle::resolve( (string) $source['title'], (string) $request['title'], $product['id'] );
}
