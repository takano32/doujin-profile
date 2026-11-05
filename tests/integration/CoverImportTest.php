<?php

namespace DoujinProfile\Tests\Integration;

use DoujinProfile\Import\Dlsite;
use WP_Error;

/**
 * 書影・サンプルの取り込み (spec §8.3。独自 REST)。FANZA・DLsite には行かず、pre_http_request で代える。
 */
final class CoverImportTest extends IntegrationTestCase {

	/** @var int 取り込み先の作品。 */
	private $work = 0;

	/** @var string 「販売サイトの画像」の代わりの JPEG。 */
	private $jpeg = '';

	/** @var string[] 代えた HTTP の記録 (メソッド URL)。 */
	private $http = array();

	/** @var callable */
	private $stub;

	protected function setUp(): void {
		parent::setUp();
		$this->useSite();
		$this->loginAs( 'owner' );
		$this->work = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => '取り込みの試験' ), true );
		$this->jpeg = self::makeJpeg();
		$this->http = array();
		$this->stub = function ( $pre, $args, $url ) {
			return $this->fakeHttp( $args, $url );
		};
		add_filter( 'pre_http_request', $this->stub, 10, 3 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->stub, 10 );
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_parent' => $this->work, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_attachment( $id, true );
		}
		wp_delete_post( $this->work, true );
		delete_transient( 'doujin_profile_dlsite_RJ01634826' );
		delete_transient( 'doujin_profile_dlsite_RJ00000002' );
		delete_transient( 'doujin_profile_fanza_samples_d_737824' );
		@unlink( $this->jpeg ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		parent::tearDown();
	}

	/**
	 * FANZA・DLsite の代わり。知らない URL は外に出さずに失敗にする。
	 *
	 * @param array  $args wp_remote_* の引数 (method・stream・filename)。
	 * @param string $url  URL。
	 * @return array|WP_Error
	 */
	private function fakeHttp( array $args, string $url ) {
		$method       = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$this->http[] = "{$method} {$url}";
		$host         = (string) parse_url( $url, PHP_URL_HOST );

		if ( Dlsite::apiUrl( 'RJ01634826' ) === $url ) {
			$body = array(
				array(
					'work_name'     => ' DLsite の作品 ',
					'regist_date'   => '2026-02-03 00:00:00',
					'site_id'       => 'maniax',
					'image_main'    => array( 'url' => '//img.dlsite.jp/x/RJ01634826_img_main.jpg' ),
					'image_samples' => array( array( 'url' => '//img.dlsite.jp/x/RJ01634826_img_smp1.jpg' ), array( 'url' => '//img.dlsite.jp/x/RJ01634826_img_smp2.jpg' ) ),
				),
			);

			return $this->response( 200, 'application/json', wp_json_encode( $body ) );
		}
		if ( Dlsite::apiUrl( 'RJ00000002' ) === $url ) {
			return $this->response( 200, 'application/json', '[]' );
		}
		if ( Dlsite::apiUrl( 'RJ00000003' ) === $url ) {
			return new WP_Error( 'http_request_failed', '試験: つながらない' );
		}

		if ( in_array( $host, array( 'img.dlsite.jp', 'doujin-assets.dmm.co.jp' ), true ) ) {
			$is_image = ! preg_match( '/jp-(\d{3})\.jpg$/', $url, $m ) || (int) $m[1] <= 2; // FANZA のサンプルは 2 枚まで、3 枚目からは「準備中」へ 302
			if ( ! $is_image ) {
				return $this->response( 302, 'text/html', '', array( 'location' => 'https://doujin-assets.dmm.co.jp/now_printing.jpg' ) );
			}
			if ( 'GET' === $method && ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				copy( $this->jpeg, $args['filename'] );
			}

			return $this->response( 200, 'image/jpeg', '' );
		}

		return new WP_Error( 'doujin_profile_test_blocked', "試験では外に出ない: {$url}" );
	}

	private function response( int $code, string $type, string $body, array $headers = array() ): array {
		return array(
			'headers'  => array( 'content-type' => $type ) + $headers,
			'body'     => $body,
			'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Found' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function attachments(): array {
		return get_posts( array( 'post_type' => 'attachment', 'post_parent' => $this->work, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	}

	public function test_dlsite_cover_import_sets_the_thumbnail_and_returns_source_data(): void {
		$response = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'rj01634826', 'title' => '入力中' ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();

		$this->assertGreaterThan( 0, $data['featured_media'] );
		$this->assertSame( $data['featured_media'], (int) get_post_thumbnail_id( $this->work ) );
		$this->assertSame( 'dlsite', $data['service'] );
		$this->assertSame( 'RJ01634826', $data['product_id'] );
		$this->assertSame( array( 'service' => 'dlsite', 'url' => 'https://www.dlsite.com/maniax/work/=/product_id/RJ01634826.html', 'label' => '', 'sponsored' => false ), $data['shop_link'] );
		$this->assertSame( 'DLsite の作品', $data['title'] );
		$this->assertSame( '2026-02-03T00:00:00+09:00', $data['release_at'] );
		$this->assertStringContainsString( '/uploads/sites/', $data['cover_url'] );

		$attachment = get_post( $data['featured_media'] );
		$this->assertSame( $this->work, (int) $attachment->post_parent );
		$this->assertSame( 'DLsite の作品', $attachment->post_title );
		$this->assertSame( 'DLsite の作品', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'https://img.dlsite.jp/x/RJ01634826_img_main.jpg', get_post_meta( $attachment->ID, '_doujin_profile_source_url', true ) );

		// 同じ番号をもう一度: 取りに行かず、同じ添付。
		$count = count( $this->http );
		$again = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ01634826' ) );
		$this->assertSame( $data['featured_media'], $again->get_data()['featured_media'] );
		$this->assertCount( 1, $this->attachments() );
		$this->assertCount( $count, $this->http, '作品情報はトランジェント、画像は照合で済む' );
	}

	public function test_replacing_the_cover_deletes_the_previous_imported_one(): void {
		$first = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ01634826' ) )->get_data()['featured_media'];
		$this->assertGreaterThan( 0, $first );

		$response = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'D_737824', 'title' => '入力中' ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( 'fanza', $data['service'] );
		$this->assertSame( 'd_737824', $data['product_id'] );
		$this->assertSame( 'https://www.dmm.co.jp/dc/doujin/-/detail/=/cid=d_737824/', $data['shop_link']['url'] );
		$this->assertSame( '', $data['title'] );
		$this->assertSame( '', $data['release_at'] );
		$this->assertSame( '入力中', get_post( $data['featured_media'] )->post_title ); // FANZA は作品情報が無いので入力中のタイトル
		$this->assertNull( get_post( $first ), '前の書影 (取り込んだもの) は消える' );
		$this->assertCount( 1, $this->attachments() );
		$this->assertContains( 'GET https://doujin-assets.dmm.co.jp/digital/comic/d_737824/d_737824pl.jpg', $this->http );
	}

	public function test_samples_are_listed_then_imported_one_at_a_time(): void {
		$list = $this->rest( 'GET', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'd_737824', 'title' => '入力中' ) );
		$this->assertSame( 200, $list->get_status(), wp_json_encode( $list->get_data() ) );
		$this->assertSame( 'fanza', $list->get_data()['service'] );
		$this->assertSame( array( array( 'index' => 1, 'attachment_id' => 0 ), array( 'index' => 2, 'attachment_id' => 0 ) ), $list->get_data()['samples'] );
		$this->assertSame( 2, (int) get_transient( 'doujin_profile_fanza_samples_d_737824' ) );
		$this->assertContains( 'HEAD https://doujin-assets.dmm.co.jp/digital/comic/d_737824/d_737824jp-003.jpg', $this->http );

		$one = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'd_737824', 'title' => '入力中', 'index' => 2 ) );
		$this->assertSame( 200, $one->get_status(), wp_json_encode( $one->get_data() ) );
		$this->assertSame( 2, $one->get_data()['index'] );
		$id = $one->get_data()['attachment_id'];
		$this->assertSame( '入力中 サンプル 02', get_post( $id )->post_title );

		$list = $this->rest( 'GET', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'd_737824' ) );
		$this->assertSame( $id, $list->get_data()['samples'][1]['attachment_id'] );

		// FANZA は番号から URL を組むので、無い番号は取るときに 302 で分かる (番号を確かめて、の 502)。
		$gone = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'd_737824', 'index' => 3 ) );
		$this->assertSame( 502, $gone->get_status() );
		$this->assertSame( 'doujin_profile_download_failed', $gone->get_data()['code'] );
		$this->assertStringContainsString( '(HTTP 302)', $gone->get_data()['message'] );

		$dlsite = $this->rest( 'GET', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'RJ01634826' ) );
		$this->assertCount( 2, $dlsite->get_data()['samples'] );
		$this->assertSame( 'DLsite の作品', $dlsite->get_data()['title'] );

		// DLsite は作品情報の一覧にある分だけ (無い番号は 404)。
		$none = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/samples", array( 'product_id' => 'RJ01634826', 'index' => 3 ) );
		$this->assertSame( 404, $none->get_status() );
		$this->assertSame( 'doujin_profile_no_sample', $none->get_data()['code'] );
	}

	public function test_validation_and_permissions(): void {
		$bad = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'https://www.dlsite.com/' ) );
		$this->assertSame( 400, $bad->get_status() );
		$this->assertSame( 'rest_invalid_param', $bad->get_data()['code'] );
		$this->assertStringContainsString( '作品番号の形が違います', $bad->get_data()['data']['params']['product_id'] );

		$missing = $this->rest( 'POST', '/doujin-profile/v1/works/999999/cover', array( 'product_id' => 'RJ01634826' ) );
		$this->assertSame( 404, $missing->get_status() );
		$this->assertSame( 'doujin_profile_no_work', $missing->get_data()['code'] );

		$this->loginAs( 'reader' );
		$forbidden = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ01634826' ) );
		$this->assertSame( 403, $forbidden->get_status() );
		$this->assertSame( 'doujin_profile_forbidden', $forbidden->get_data()['code'] );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ01634826' ) )->get_status() );
		$this->assertSame( array(), $this->attachments() );
	}

	public function test_unreachable_or_unknown_products_fail_cleanly(): void {
		$gone = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ00000002' ) );
		$this->assertSame( 404, $gone->get_status() );
		$this->assertSame( 'doujin_profile_not_found', $gone->get_data()['code'] );

		$down = $this->rest( 'POST', "/doujin-profile/v1/works/{$this->work}/cover", array( 'product_id' => 'RJ00000003' ) );
		$this->assertSame( 502, $down->get_status() );
		$this->assertSame( 'doujin_profile_fetch_failed', $down->get_data()['code'] );
		$this->assertStringContainsString( 'DLsite に接続できませんでした', $down->get_data()['message'] );

		$this->assertSame( array(), $this->attachments() );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $this->work ) );
	}
}
