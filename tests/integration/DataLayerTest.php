<?php

namespace DoujinProfile\Tests\Integration;

/**
 * 作品のデータ層 (spec §6.0): 保存時の 1 ルール (公開日時 = 発売日時と今の早いほう) と、メタ・設定の正規化が
 * wp_insert_post・update_post_meta・標準の REST のどこから書いても効くこと。
 */
final class DataLayerTest extends IntegrationTestCase {

	/** @var int[] 作った投稿 (tearDown で消す)。 */
	private $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->useSite();
		$this->loginAs( 'admin' );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		$this->posts = array();
		parent::tearDown();
	}

	private function insert( array $postarr ): int {
		$id = wp_insert_post( $postarr + array( 'post_type' => 'post', 'post_title' => '試験の作品' ), true );
		$this->assertIsInt( $id );
		$this->posts[] = $id;

		return $id;
	}

	public function test_future_release_is_published_now_not_scheduled(): void {
		$id   = $this->insert(
			array(
				'post_status' => 'publish',
				'post_date'   => '2099-01-01 00:00:00', // コアはこれを予約 (future) にするが、ルールが公開に戻す
				'meta_input'  => array( 'doujin_release_at' => '2099-01-01T00:00:00+09:00' ),
			)
		);
		$post = get_post( $id );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertEqualsWithDelta( time(), strtotime( $post->post_date_gmt . ' UTC' ), 10 );
		$this->assertSame( '2099-01-01T00:00:00+09:00', get_post_meta( $id, 'doujin_release_at', true ) );
	}

	public function test_past_release_becomes_the_publish_date(): void {
		$id   = $this->insert(
			array(
				'post_status' => 'publish',
				'meta_input'  => array( 'doujin_release_at' => '2026-01-10' ), // 日付だけ → サイトのタイムゾーン (Asia/Tokyo) の 0 時
			)
		);
		$post = get_post( $id );
		$this->assertSame( '2026-01-09 15:00:00', $post->post_date_gmt );
		$this->assertSame( '2026-01-10 00:00:00', $post->post_date );
		$this->assertSame( '2026-01-10T00:00:00+09:00', get_post_meta( $id, 'doujin_release_at', true ) ); // 正規化される
	}

	public function test_drafts_and_posts_without_release_keep_core_dates(): void {
		$draft = get_post( $this->insert( array( 'post_status' => 'draft', 'meta_input' => array( 'doujin_release_at' => '2026-01-10T00:00:00+09:00' ) ) ) );
		$this->assertSame( 'draft', $draft->post_status );
		$this->assertNotSame( '2026-01-09 15:00:00', $draft->post_date_gmt );

		$plain = get_post( $this->insert( array( 'post_status' => 'publish' ) ) );
		$this->assertEqualsWithDelta( time(), strtotime( $plain->post_date_gmt . ' UTC' ), 10 );
	}

	public function test_changing_release_meta_later_resaves_the_publish_date(): void {
		$id = $this->insert( array( 'post_status' => 'publish' ) );
		update_post_meta( $id, 'doujin_release_at', '2026-01-10T00:00:00+09:00' ); // REST と同じ順 (投稿のあとにメタ)
		$this->assertSame( '2026-01-09 15:00:00', get_post( $id )->post_date_gmt );

		update_post_meta( $id, 'doujin_release_at', '2026-03-01T12:00:00+09:00' );
		$this->assertSame( '2026-03-01 03:00:00', get_post( $id )->post_date_gmt );
	}

	public function test_rest_create_applies_the_rule_and_schema(): void {
		$response = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'status' => 'publish',
				'title'  => 'REST の作品',
				'meta'   => array( 'doujin_release_at' => '2026-01-10T00:00', 'doujin_release_label' => ' 1月 ', 'doujin_sample_ids' => array( 5, 5, 7 ) ),
			)
		);
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data          = $response->get_data();
		$this->posts[] = $data['id'];
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( '2026-01-09T15:00:00', $data['date_gmt'] );
		$this->assertSame( '2026-01-10T00:00:00+09:00', $data['meta']['doujin_release_at'] );
		$this->assertSame( '1月', $data['meta']['doujin_release_label'] );
		$this->assertSame( array( 5, 7 ), $data['meta']['doujin_sample_ids'] );

		// スキーマ: https でない URL と知らないサービスは REST が 400 で断る (壊れた値を保存しない)
		$bad = $this->rest( 'POST', '/wp/v2/posts', array( 'title' => 'x', 'meta' => array( 'doujin_shop_links' => array( array( 'service' => 'fanza', 'url' => 'http://insecure/' ) ) ) ) );
		$this->assertSame( 400, $bad->get_status(), wp_json_encode( $bad->get_data() ) );
		$this->assertStringStartsWith( 'rest_', $bad->get_data()['code'] );
		$bad = $this->rest( 'POST', '/wp/v2/posts', array( 'title' => 'x', 'meta' => array( 'doujin_shop_links' => array( array( 'service' => 'nope', 'url' => 'https://a/' ) ) ) ) );
		$this->assertSame( 400, $bad->get_status(), wp_json_encode( $bad->get_data() ) );
	}

	public function test_meta_sanitizers_run_on_update_post_meta(): void {
		$id = $this->insert( array( 'post_status' => 'draft' ) );
		update_post_meta(
			$id,
			'doujin_shop_links',
			array(
				array( 'service' => 'fanza', 'url' => ' https://www.dmm.co.jp/x/ ', 'label' => ' <b>店</b> ', 'sponsored' => 'true' ),
				array( 'service' => 'nope', 'url' => 'https://a/' ),
				array( 'url' => 'http://insecure/' ),
				'junk',
			)
		);
		$this->assertSame(
			array(
				array( 'service' => 'fanza', 'url' => 'https://www.dmm.co.jp/x/', 'label' => '店', 'sponsored' => true ),
				array( 'service' => 'other', 'url' => 'https://a/', 'label' => '', 'sponsored' => false ),
			),
			get_post_meta( $id, 'doujin_shop_links', true )
		);

		update_post_meta( $id, 'doujin_sample_ids', array( 3, '3', 0, 'x', 5 ) );
		$this->assertSame( array( 3, 5 ), get_post_meta( $id, 'doujin_sample_ids', true ) );

		update_post_meta( $id, 'doujin_release_label', ' <em>5月下旬</em> ' );
		$this->assertSame( '5月下旬', get_post_meta( $id, 'doujin_release_label', true ) );

		update_post_meta( $id, 'doujin_release_at', 'nonsense' );
		$this->assertSame( '', get_post_meta( $id, 'doujin_release_at', true ) );
	}

	public function test_site_links_setting_via_option_and_rest(): void {
		$before = get_option( 'doujin_profile_links' );

		update_option( 'doujin_profile_links', array( 'shops' => array( array( 'service' => 'booth', 'url' => 'https://b.booth.pm/' ), array( 'url' => 'ftp://x/' ) ), 'extra' => 1 ) );
		$this->assertSame(
			array( 'shops' => array( array( 'service' => 'booth', 'url' => 'https://b.booth.pm/', 'label' => '', 'sponsored' => false ) ), 'accounts' => array() ),
			get_option( 'doujin_profile_links' )
		);

		$response = $this->rest( 'POST', '/wp/v2/settings', array( 'doujin_profile_links' => array( 'shops' => array(), 'accounts' => array( array( 'service' => 'x', 'url' => 'https://x.com/a' ) ) ) ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'https://x.com/a', $response->get_data()['doujin_profile_links']['accounts'][0]['url'] );
		$this->assertArrayHasKey( 'doujin_profile_preparing', $response->get_data() );

		update_option( 'doujin_profile_links', $before );
	}
}
