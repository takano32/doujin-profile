<?php
/**
 * 書影の取り込み (spec §8.3。T.36.4 の下調べは owner-editor-design.md §9)。
 *
 * マイページで作品番号 (FANZA の cid 例 d_737824、DLsite の例 RJ01634826) を入れて「書影を取り込む」を押すと、
 * サーバが販売サイトから書影を取ってメディアライブラリに保存し (添付の親 = その作品)、その作品のアイキャッチにする。
 * 標準の REST に無い処理なので、これだけが唯一の独自の保存口:
 *
 *   POST /wp-json/doujin-profile/v1/works/<投稿 ID>/cover   { "product_id": "d_737824" }
 *
 * 守り: 権限は upload_files + その投稿の edit_post、nonce は標準の REST Cookie 認証 (X-WP-Nonce)。
 * 取る元は FANZA・DLsite の決まった URL だけ (作品番号は正規表現で検査し、任意の URL は受けない)。
 * DLsite は作品情報の JSON から画像の URL を読むが、画像のホストも img.dlsite.jp に限る。
 *
 * 返すもの: 添付 ID と画像の URL、販売ページへのリンク (素の URL。アフィリエイト ID は付けない — T.36.6 は未決)、
 * DLsite なら題名と発売日時。リンク・題名・発売日時は画面が投稿の編集として受け取り、持ち主が「保存」で
 * 標準の REST から書く (サーバが勝手に投稿の中身まで書き換えない。アイキャッチだけはここで設定する)。
 * 相手の URL の規則が変わると取り込みが失敗するだけで、取り込み済みの画像は残る。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_REST_NAMESPACE = 'doujin-profile/v1';

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
function doujin_profile_register_cover_import_route() {
	register_rest_route(
		DOUJIN_PROFILE_REST_NAMESPACE,
		'/works/(?P<id>\d+)/cover',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'doujin_profile_rest_import_cover',
			'permission_callback' => 'doujin_profile_rest_cover_permission',
			'args'                => array(
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
			),
		)
	);
}
add_action( 'rest_api_init', 'doujin_profile_register_cover_import_route' );

/**
 * 作品番号の形の検査 (REST の args)。
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
function doujin_profile_rest_cover_permission( $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'doujin_profile_no_work', 'その作品が見つかりません。', array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'doujin_profile_forbidden', 'この作品の書影を取り込む権限がありません。', array( 'status' => rest_authorization_required_code() ) );
	}

	return true;
}

/**
 * 取り込みの本体。
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

	$source = 'fanza' === $product['service']
		? doujin_profile_fanza_source( $product['id'] )
		: doujin_profile_dlsite_source( $product['id'] );
	if ( is_wp_error( $source ) ) {
		return $source;
	}

	$attachment_id = doujin_profile_sideload_cover( $source['image_url'], $source['file_name'], $post_id, $source['title'] );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	set_post_thumbnail( $post_id, $attachment_id );

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
 * FANZA: 番号から URL を組むだけ (画像は年齢確認なしで取れる。owner-editor-design §9)。
 *
 * @param string $cid 作品番号 (例 d_737824)。
 * @return array{image_url: string, file_name: string, shop_url: string, title: string, release_at: string}
 */
function doujin_profile_fanza_source( $cid ) {
	return array(
		'image_url'  => "https://doujin-assets.dmm.co.jp/digital/comic/{$cid}/{$cid}pl.jpg",
		'file_name'  => "{$cid}pl.jpg",
		'shop_url'   => "https://www.dmm.co.jp/dc/doujin/-/detail/=/cid={$cid}/",
		'title'      => '',
		'release_at' => '',
	);
}

/**
 * DLsite: 作品情報の JSON を読んで、画像の URL・題名・発売日時・販売ページの URL を得る。
 *
 * JSON は { "RJ…": { work_name, work_image, regist_date, site_id, … } } の形 (無い番号は [])。
 * 画像の URL は // 始まりなので https: を補い、ホストが img.dlsite.jp でなければ受けない。
 *
 * @param string $id 作品番号 (例 RJ01634826)。
 * @return array{image_url: string, file_name: string, shop_url: string, title: string, release_at: string}|WP_Error
 */
function doujin_profile_dlsite_source( $id ) {
	$response = wp_safe_remote_get(
		'https://www.dlsite.com/maniax/product/info/ajax?product_id=' . rawurlencode( $id ),
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
	$info = is_array( $json ) && isset( $json[ $id ] ) && is_array( $json[ $id ] ) ? $json[ $id ] : null;
	if ( ! $info || empty( $info['work_image'] ) || ! is_string( $info['work_image'] ) ) {
		return new WP_Error( 'doujin_profile_not_found', "DLsite に作品番号 {$id} の作品が見つかりません。番号を確かめてください。", array( 'status' => 404 ) );
	}

	$image_url = $info['work_image'];
	if ( str_starts_with( $image_url, '//' ) ) {
		$image_url = 'https:' . $image_url;
	}
	$host = wp_parse_url( $image_url, PHP_URL_HOST );
	if ( 'img.dlsite.jp' !== $host || ! str_starts_with( $image_url, 'https://' ) ) {
		return new WP_Error( 'doujin_profile_bad_image_url', 'DLsite の画像の置き場所が想定と違うため、取り込みを中止しました。画像を手でドロップしてください。', array( 'status' => 502 ) );
	}

	$site_id = isset( $info['site_id'] ) && is_string( $info['site_id'] ) && preg_match( '/^[a-z0-9-]{1,20}$/', $info['site_id'] ) ? $info['site_id'] : 'maniax';
	$title   = isset( $info['work_name'] ) && is_string( $info['work_name'] ) ? sanitize_text_field( $info['work_name'] ) : '';
	$release = isset( $info['regist_date'] ) && is_string( $info['regist_date'] ) ? doujin_profile_sanitize_release_at( $info['regist_date'] ) : '';

	return array(
		'image_url'  => $image_url,
		'file_name'  => sanitize_file_name( wp_basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) ),
		'shop_url'   => "https://www.dlsite.com/{$site_id}/work/=/product_id/{$id}.html",
		'title'      => $title,
		'release_at' => $release,
	);
}

/**
 * 画像を取ってメディアライブラリに保存する (コア標準の download_url() + media_handle_sideload())。
 *
 * @param string $image_url 画像の URL (決まったホストだけ)。
 * @param string $file_name 保存するファイル名。
 * @param int    $post_id   添付の親にする作品。
 * @param string $title     添付のタイトル・代替テキストにする題名 (空なら作品番号のファイル名のまま)。
 * @return int|WP_Error 添付 ID。
 */
function doujin_profile_sideload_cover( $image_url, $file_name, $post_id, $title ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $image_url, 30 );
	if ( is_wp_error( $tmp ) ) {
		$data = $tmp->get_error_data();
		$code = is_array( $data ) && isset( $data['code'] ) ? (int) $data['code'] : 0;
		$hint = 404 === $code ? 'その作品番号の書影が見つかりません。番号を確かめてください。' : '販売サイトから画像を取れませんでした。しばらくしてからもう一度お試しください。';

		return new WP_Error( 'doujin_profile_download_failed', $hint . ( $code ? " (HTTP {$code})" : '' ), array( 'status' => 502 ) );
	}

	$file = array(
		'name'     => '' !== $file_name ? $file_name : 'cover.jpg',
		'tmp_name' => $tmp,
	);
	// 画像以外 (エラーページなど) は media_handle_sideload() の中の wp_check_filetype_and_ext() で弾かれる。
	$attachment_id = media_handle_sideload( $file, $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'doujin_profile_sideload_failed', '取った画像をメディアに保存できませんでした: ' . $attachment_id->get_error_message(), array( 'status' => 500 ) );
	}

	if ( '' !== $title ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
	}

	return (int) $attachment_id;
}
