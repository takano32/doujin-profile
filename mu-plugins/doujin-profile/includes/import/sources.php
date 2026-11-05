<?php
/**
 * 作品情報の取り方 (spec §8.3): FANZA は番号から URL を組むだけ、DLsite は作品情報の JSON を読む。
 *
 * ここは HTTP (wp_safe_remote_get / wp_safe_remote_head) とトランジェントだけで、URL の組み立てと JSON の解釈は
 * lib/Import/ (Fanza・Dlsite・SampleUrls)。DLsite は画像のホストも img.dlsite.jp に限る。転送 (302) は追わない
 * (FANZA は無い画像を「準備中」の画像へ転送するので、追うとそれを取り込んでしまう)。
 * 相手の URL の規則が変わると取り込みが失敗するだけで、取り込み済みの画像は残る。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Failure;
use DoujinProfile\Import\Dlsite;
use DoujinProfile\Import\Fanza;
use DoujinProfile\Import\SampleUrls;

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
	return Fanza::source( $cid );
}

/**
 * FANZA のサンプル画像の URL (jp-001.jpg からの連番。無い番号は「準備中」の画像へ 302 で転送される)。
 *
 * @param string $cid   作品番号。
 * @param int    $index 何枚目か (1 から)。
 * @return string
 */
function doujin_profile_fanza_sample_url( $cid, $index ) {
	return Fanza::sampleUrl( $cid, (int) $index );
}

/**
 * DLsite: 作品情報の JSON を読んで、画像の URL・題名・発売日時・販売ページの URL・サンプルの URL を得る。
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

	$response = wp_safe_remote_get( Dlsite::apiUrl( $id ), array( 'timeout' => 15 ) );
	if ( is_wp_error( $response ) ) {
		return doujin_profile_wp_error( Dlsite::connectionFailure() );
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return doujin_profile_wp_error( Dlsite::httpFailure( (int) $code ) );
	}

	$source = Dlsite::parse( json_decode( wp_remote_retrieve_body( $response ), true ), $id, 'sanitize_text_field', wp_timezone(), DOUJIN_PROFILE_MAX_SAMPLES );
	if ( $source instanceof Failure ) {
		return doujin_profile_wp_error( $source );
	}
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
	return Dlsite::imageUrl( $url );
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
		$count = SampleUrls::fanzaCount(
			$cid,
			DOUJIN_PROFILE_MAX_SAMPLES,
			static function ( $url ) {
				$response = wp_safe_remote_head( $url, array( 'timeout' => 10, 'redirection' => 0 ) );
				if ( is_wp_error( $response ) ) {
					return null;
				}

				return array(
					'code' => (int) wp_remote_retrieve_response_code( $response ),
					'type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
				);
			}
		);
		if ( $count instanceof Failure ) {
			return doujin_profile_wp_error( $count );
		}
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
	}

	return SampleUrls::fanzaList( $cid, (int) $count );
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
	return SampleUrls::one( $product, $source, (int) $index );
}
