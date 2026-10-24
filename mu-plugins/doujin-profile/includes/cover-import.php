<?php
/**
 * 作品番号からの取り込み: 書影とサンプル画像 (spec §8.3。T.36.4 の下調べは owner-editor-design.md §9)。
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
 * 取る元は FANZA・DLsite の決まった URL だけ (作品番号は正規表現で検査し、任意の URL は受けない)。
 * DLsite は作品情報の JSON から画像の URL を読むが、画像のホストも img.dlsite.jp に限る。転送 (302) は追わない
 * (FANZA は無い画像を「準備中」の画像へ転送するので、追うとそれを取り込んでしまう)。
 *
 * 取り込んだ添付には出どころの URL をメタ _doujin_profile_source_url に持つ。同じ番号を 2 度取り込んでも、
 * 同じ URL の添付がその作品の下にあればそれを返し、二重にはしない。書影を取り込み直したときは、前のアイキャッチが
 * この作品の取り込みでできたもので、ほかで使われていなければ消す (手で上げた画像は消さない)。
 *
 * 返すもの: 添付 ID と画像の URL、販売ページへのリンク (素の URL。アフィリエイト ID は付けない — T.36.6 は未決)、
 * DLsite なら題名と発売日時。リンク・題名・発売日時・サンプルの並びは画面が投稿の編集として受け取り、持ち主が「保存」で
 * 標準の REST から書く (サーバが勝手に投稿の中身まで書き換えない。アイキャッチだけはここで設定する)。
 * 相手の URL の規則が変わると取り込みが失敗するだけで、取り込み済みの画像は残る。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_REST_NAMESPACE = 'doujin-profile/v1';
const DOUJIN_PROFILE_SOURCE_URL_META = '_doujin_profile_source_url';

/**
 * 作品番号の形。キーがサービス種別 (doujin_profile_services() のキー)。
 *
 * @return array<string, string>
 */
function doujin_profile_product_id_patterns() {
	return array(
		'fanza'  => '/^d_[a-z0-9_]{1,40}$/',   // 例 d_737824
		'dlsite' => '/^[A-Z]{2}\d{6,8}$/',      // 例 RJ01634826 (RJ・BJ・VJ など)
	);
}

/**
 * 作品番号を整えて、どのサービスの番号かを判定する。
 *
 * @param string $product_id 入力値。
 * @return array{service: string, id: string}|null 形が合わなければ null。
 */
function doujin_profile_parse_product_id( $product_id ) {
	$id = trim( (string) $product_id );
	if ( str_starts_with( strtolower( $id ), 'd_' ) ) {
		$id = strtolower( $id );
	} else {
		$id = strtoupper( $id );
	}

	foreach ( doujin_profile_product_id_patterns() as $service => $pattern ) {
		if ( preg_match( $pattern, $id ) ) {
			return array(
				'service' => $service,
				'id'      => $id,
			);
		}
	}

	return null;
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
 * 画面側 (src/my-page/util.js の errorText) がそこから取り出して見せる。
 *
 * @param mixed $value 入力値。
 * @return true|WP_Error
 */
function doujin_profile_validate_product_id( $value ) {
	if ( is_string( $value ) && doujin_profile_parse_product_id( $value ) ) {
		return true;
	}

	return new WP_Error(
		'doujin_profile_bad_product_id',
		'作品番号の形が違います。FANZA は d_ で始まる番号 (例 d_737824)、DLsite は RJ などで始まる番号 (例 RJ01634826) を入れてください。',
		array( 'status' => 400 )
	);
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
	$product = doujin_profile_parse_product_id( $request['product_id'] );
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
	$product = doujin_profile_parse_product_id( $request['product_id'] );
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
	$product = doujin_profile_parse_product_id( $request['product_id'] );
	if ( ! $product ) {
		return doujin_profile_validate_product_id( '' );
	}

	$source = doujin_profile_source( $product );
	if ( is_wp_error( $source ) ) {
		return $source;
	}
	$url = doujin_profile_sample_url( $product, $source, $index );
	if ( ! $url ) {
		return new WP_Error( 'doujin_profile_no_sample', "{$index} 枚目のサンプル画像はありません。", array( 'status' => 404 ) );
	}

	$title         = sprintf( '%s サンプル %02d', doujin_profile_import_title( $source, $request, $product ), $index );
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
	if ( '' !== $source['title'] ) {
		return $source['title'];
	}
	$title = trim( (string) $request['title'] );

	return '' !== $title ? $title : $product['id'];
}

/**
 * 作品情報 (書影の URL・販売ページ・題名・発売日時・サンプルの URL)。
 *
 * @param array $product doujin_profile_parse_product_id() の結果。
 * @return array{image_url: string, shop_url: string, title: string, release_at: string, samples: string[]|null}|WP_Error
 *         samples は FANZA では null (枚数は doujin_profile_sample_urls() で探る)。
 */
function doujin_profile_source( $product ) {
	return 'fanza' === $product['service']
		? doujin_profile_fanza_source( $product['id'] )
		: doujin_profile_dlsite_source( $product['id'] );
}

/**
 * FANZA: 番号から URL を組むだけ (画像は年齢確認なしで取れる。owner-editor-design §9)。作品情報の API は無い。
 *
 * @param string $cid 作品番号 (例 d_737824)。
 * @return array{image_url: string, shop_url: string, title: string, release_at: string, samples: null}
 */
function doujin_profile_fanza_source( $cid ) {
	return array(
		'image_url'  => "https://doujin-assets.dmm.co.jp/digital/comic/{$cid}/{$cid}pl.jpg",
		'shop_url'   => "https://www.dmm.co.jp/dc/doujin/-/detail/=/cid={$cid}/",
		'title'      => '',
		'release_at' => '',
		'samples'    => null,
	);
}

/**
 * FANZA のサンプル画像の URL (jp-001.jpg からの連番。無い番号は「準備中」の画像へ 302 で転送される)。
 *
 * @param string $cid   作品番号。
 * @param int    $index 何枚目か (1 から)。
 * @return string
 */
function doujin_profile_fanza_sample_url( $cid, $index ) {
	return sprintf( 'https://doujin-assets.dmm.co.jp/digital/comic/%1$s/%1$sjp-%2$03d.jpg', $cid, $index );
}

/**
 * DLsite: 作品情報の JSON を読んで、画像の URL・題名・発売日時・販売ページの URL・サンプルの URL を得る。
 *
 * JSON は [ { work_name, regist_date, site_id, image_main: { url }, image_samples: [ { url } ], … } ] の形 (無い番号は [])。
 * 画像の URL は // 始まりなので https: を補い、ホストが img.dlsite.jp でなければ受けない。
 * 1 時間はトランジェントに持つ (サンプルを 1 枚ずつ取るたびに読み直さないため)。
 *
 * @param string $id 作品番号 (例 RJ01634826)。
 * @return array{image_url: string, shop_url: string, title: string, release_at: string, samples: string[]}|WP_Error
 */
function doujin_profile_dlsite_source( $id ) {
	$cache_key = 'doujin_profile_dlsite_' . $id;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_safe_remote_get(
		'https://www.dlsite.com/maniax/api/=/product.json?workno=' . rawurlencode( $id ),
		array( 'timeout' => 15 )
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'doujin_profile_fetch_failed', 'DLsite に接続できませんでした。しばらくしてからもう一度お試しください。', array( 'status' => 502 ) );
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'doujin_profile_fetch_failed', "DLsite から作品情報を取れませんでした (HTTP {$code})。", array( 'status' => 502 ) );
	}

	$json = json_decode( wp_remote_retrieve_body( $response ), true );
	$info = is_array( $json ) && isset( $json[0] ) && is_array( $json[0] ) ? $json[0] : null;
	$main = $info && isset( $info['image_main']['url'] ) ? doujin_profile_dlsite_image_url( $info['image_main']['url'] ) : '';
	if ( ! $info ) {
		return new WP_Error( 'doujin_profile_not_found', "DLsite に作品番号 {$id} の作品が見つかりません。番号を確かめてください。", array( 'status' => 404 ) );
	}
	if ( '' === $main ) {
		return new WP_Error( 'doujin_profile_bad_image_url', 'DLsite の画像の置き場所が想定と違うため、取り込みを中止しました。画像を手でドロップしてください。', array( 'status' => 502 ) );
	}

	$samples = array();
	if ( isset( $info['image_samples'] ) && is_array( $info['image_samples'] ) ) {
		foreach ( $info['image_samples'] as $sample ) {
			$url = isset( $sample['url'] ) ? doujin_profile_dlsite_image_url( $sample['url'] ) : '';
			if ( '' !== $url ) {
				$samples[] = $url;
			}
		}
	}

	$site_id = isset( $info['site_id'] ) && is_string( $info['site_id'] ) && preg_match( '/^[a-z0-9-]{1,20}$/', $info['site_id'] ) ? $info['site_id'] : 'maniax';
	$title   = isset( $info['work_name'] ) && is_string( $info['work_name'] ) ? sanitize_text_field( $info['work_name'] ) : '';
	$release = isset( $info['regist_date'] ) && is_string( $info['regist_date'] ) ? doujin_profile_sanitize_release_at( $info['regist_date'] ) : '';

	$source = array(
		'image_url'  => $main,
		'shop_url'   => "https://www.dlsite.com/{$site_id}/work/=/product_id/{$id}.html",
		'title'      => $title,
		'release_at' => $release,
		'samples'    => array_slice( $samples, 0, DOUJIN_PROFILE_MAX_SAMPLES ),
	);
	set_transient( $cache_key, $source, HOUR_IN_SECONDS );

	return $source;
}

/**
 * DLsite の画像の URL を整える。https で img.dlsite.jp のものだけ通す。
 *
 * @param mixed $url JSON の値 (// 始まり)。
 * @return string 受けられなければ空。
 */
function doujin_profile_dlsite_image_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}
	if ( str_starts_with( $url, '//' ) ) {
		$url = 'https:' . $url;
	}
	if ( ! str_starts_with( $url, 'https://' ) || 'img.dlsite.jp' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
		return '';
	}

	return $url;
}

/**
 * サンプル画像の URL の一覧。DLsite は作品情報から、FANZA は連番を HEAD で探る (結果は 1 時間トランジェントに持つ)。
 *
 * @param array $product 作品番号。
 * @param array $source  作品情報。
 * @return string[]|WP_Error
 */
function doujin_profile_sample_urls( $product, $source ) {
	if ( is_array( $source['samples'] ) ) {
		return $source['samples'];
	}

	$cid       = $product['id'];
	$cache_key = 'doujin_profile_fanza_samples_' . $cid;
	$count     = get_transient( $cache_key );
	if ( false === $count ) {
		$count = 0;
		for ( $i = 1; $i <= DOUJIN_PROFILE_MAX_SAMPLES; $i++ ) {
			$response = wp_safe_remote_head( doujin_profile_fanza_sample_url( $cid, $i ), array( 'timeout' => 10, 'redirection' => 0 ) );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'doujin_profile_fetch_failed', 'FANZA に接続できませんでした。しばらくしてからもう一度お試しください。', array( 'status' => 502 ) );
			}
			if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! str_starts_with( (string) wp_remote_retrieve_header( $response, 'content-type' ), 'image/' ) ) {
				break;
			}
			$count = $i;
		}
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	}

	$urls = array();
	for ( $i = 1; $i <= (int) $count; $i++ ) {
		$urls[] = doujin_profile_fanza_sample_url( $cid, $i );
	}

	return $urls;
}

/**
 * サンプル画像 1 枚の URL。FANZA は番号から組む (無ければ取るときに 302 で分かる)。
 *
 * @param array $product 作品番号。
 * @param array $source  作品情報。
 * @param int   $index   何枚目か (1 から)。
 * @return string 無ければ空。
 */
function doujin_profile_sample_url( $product, $source, $index ) {
	if ( is_array( $source['samples'] ) ) {
		return isset( $source['samples'][ $index - 1 ] ) ? $source['samples'][ $index - 1 ] : '';
	}

	return doujin_profile_fanza_sample_url( $product['id'], $index );
}

/**
 * この作品の下にある、取り込みでできた添付。
 *
 * @param int $post_id 作品。
 * @return array<string, int> 出どころの URL → 添付 ID。
 */
function doujin_profile_imported_attachments( $post_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => DOUJIN_PROFILE_SOURCE_URL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'no_found_rows'  => true,
		)
	);

	$map = array();
	foreach ( $ids as $id ) {
		$url = (string) get_post_meta( $id, DOUJIN_PROFILE_SOURCE_URL_META, true );
		if ( '' !== $url && ! isset( $map[ $url ] ) ) {
			$map[ $url ] = (int) $id;
		}
	}

	return $map;
}

/**
 * 画像を取ってメディアライブラリに保存する。同じ URL から取り込んだ添付がこの作品の下にあれば、取らずにそれを返す。
 *
 * @param string $image_url 画像の URL (決まったホストだけ)。
 * @param int    $post_id   添付の親にする作品。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_import_image( $image_url, $post_id, $title ) {
	$imported = doujin_profile_imported_attachments( $post_id );
	if ( isset( $imported[ $image_url ] ) ) {
		return $imported[ $image_url ];
	}

	$attachment_id = doujin_profile_sideload_image( $image_url, $post_id, $title );
	if ( ! is_wp_error( $attachment_id ) ) {
		update_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, esc_url_raw( $image_url ) );
	}

	return $attachment_id;
}

/**
 * 画像を取ってメディアライブラリに保存する (コア標準の wp_safe_remote_get() + media_handle_sideload())。
 *
 * download_url() は転送を追ってしまうので使わない (FANZA の無い画像は「準備中」の画像へ 302 で転送される)。
 * 200 で image/* が返ったときだけ保存する。
 *
 * @param string $image_url 画像の URL (決まったホストだけ)。
 * @param int    $post_id   添付の親にする作品。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_sideload_image( $image_url, $post_id, $title ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$tmp = wp_tempnam( $image_url );
	if ( ! $tmp ) {
		return new WP_Error( 'doujin_profile_download_failed', '一時ファイルを作れませんでした。', array( 'status' => 500 ) );
	}

	$response = wp_safe_remote_get(
		$image_url,
		array(
			'timeout'     => 30,
			'redirection' => 0,
			'stream'      => true,
			'filename'    => $tmp,
		)
	);
	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$type = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'content-type' );
	if ( 200 !== $code || ! str_starts_with( $type, 'image/' ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$hint = in_array( $code, array( 302, 404 ), true )
			? 'その作品番号の画像が見つかりません。番号を確かめてください。'
			: '販売サイトから画像を取れませんでした。しばらくしてからもう一度お試しください。';

		return new WP_Error( 'doujin_profile_download_failed', $hint . ( $code ? " (HTTP {$code})" : '' ), array( 'status' => 502 ) );
	}

	$file_name = wp_basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) );

	return doujin_profile_attach_file( $tmp, $file_name, $post_id, $title );
}

/**
 * 手元のファイル (WP-CLI の import。spec §6.4) をメディアライブラリに保存する。
 * 同じ道筋から取り込んだ添付がこの作品の下にあれば、それを返す (二重にしない)。
 *
 * @param string $path       ファイルの道筋 (読める絶対パス)。
 * @param int    $post_id    添付の親にする作品。
 * @param string $title      添付のタイトル・代替テキスト。
 * @param string $source_key 出どころの印 (JSON に書かれた道筋。_doujin_profile_source_url に入れて照合に使う)。
 * @param string $file_name  メディアでのファイル名 (空なら元の名前)。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_import_file( $path, $post_id, $title, $source_key, $file_name = '' ) {
	$imported = doujin_profile_imported_attachments( $post_id );
	if ( isset( $imported[ $source_key ] ) ) {
		return $imported[ $source_key ];
	}
	if ( ! is_file( $path ) || ! is_readable( $path ) ) {
		return new WP_Error( 'doujin_profile_file_missing', "ファイルが読めません: {$path}" );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = wp_tempnam( $path );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return new WP_Error( 'doujin_profile_download_failed', '一時ファイルを作れませんでした。' );
	}

	$attachment_id = doujin_profile_attach_file( $tmp, '' !== $file_name ? $file_name : wp_basename( $path ), $post_id, $title );
	if ( ! is_wp_error( $attachment_id ) ) {
		update_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, $source_key );
	}

	return $attachment_id;
}

/**
 * 一時ファイルを添付にする (コア標準の media_handle_sideload())。失敗したら一時ファイルを消す。
 *
 * @param string $tmp       一時ファイル (成功すると uploads へ移される)。
 * @param string $file_name メディアでのファイル名。
 * @param int    $post_id   添付の親。
 * @param string $title     添付のタイトル・代替テキスト。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_attach_file( $tmp, $file_name, $post_id, $title ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$file_name = sanitize_file_name( $file_name );
	$file      = array(
		'name'     => '' !== $file_name ? $file_name : 'image.jpg',
		'tmp_name' => $tmp,
	);
	// 画像以外 (エラーページなど) は media_handle_sideload() の中の wp_check_filetype_and_ext() で弾かれる。
	$attachment_id = media_handle_sideload( $file, $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'doujin_profile_sideload_failed', '画像をメディアに保存できませんでした: ' . $attachment_id->get_error_message(), array( 'status' => 500 ) );
	}

	if ( '' !== $title ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
	}

	return (int) $attachment_id;
}

/**
 * 取り込み直して外れた前の書影を、安全なときだけメディアから消す。
 *
 * 消す条件: この作品の下にあり、取り込みでできたもので (手で上げた画像は消さない)、この作品のサンプルに入っておらず、
 * ほかの投稿のアイキャッチでもなく、この人が消せる。迷うものは残す (メディアから手で消せる)。
 *
 * @param int $attachment_id 前のアイキャッチ。
 * @param int $post_id       作品。
 * @return bool 消したか。
 */
function doujin_profile_delete_imported_cover( $attachment_id, $post_id ) {
	$attachment = get_post( $attachment_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type || (int) $attachment->post_parent !== $post_id ) {
		return false;
	}
	if ( '' === (string) get_post_meta( $attachment_id, DOUJIN_PROFILE_SOURCE_URL_META, true ) ) {
		return false;
	}
	if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
		return false;
	}
	$samples = get_post_meta( $post_id, 'doujin_sample_ids', true );
	if ( is_array( $samples ) && in_array( $attachment_id, array_map( 'intval', $samples ), true ) ) {
		return false;
	}
	$used_elsewhere = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => array( $post_id ),
			'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $attachment_id,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'  => true,
		)
	);
	if ( $used_elsewhere ) {
		return false;
	}

	return (bool) wp_delete_attachment( $attachment_id, true );
}
