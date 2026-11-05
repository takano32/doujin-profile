<?php

namespace DoujinProfile\Tests\Integration;

/**
 * 公開側のブロック (spec §6.2): 見本 (works.json) を入れたサイトで、投稿のデータから描けること。
 */
final class BlocksTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->useSite();
		wp_set_current_user( 0 );
	}

	public function test_work_card_renders_from_post_data(): void {
		$post = self::workBySlug( 'work-one' );
		$this->assertNotNull( $post );
		$html = self::renderBlocks( '<!-- wp:doujin-profile/work-card /-->', $post );

		$this->assertStringContainsString( 'doujin-profile-work-card', $html );
		$this->assertStringContainsString( 'data-release-at="2026-01-10T00:00:00+09:00"', $html );
		$this->assertStringContainsString( '作品その一', $html );
		$this->assertStringContainsString( '作家: ', $html );
		$this->assertStringContainsString( '作家A', $html );
		$this->assertStringContainsString( '2026年1月10日発売', $html );
		$this->assertStringContainsString( '<span class="doujin-profile-work-card__upcoming" hidden>予告</span>', $html );
		$this->assertStringContainsString( 'あらすじ一', $html );
		$this->assertStringContainsString( 'doujin-profile-work-card__cover', $html ); // 単体ページではないので書影とタイトルを出す

		// 販売先: 縦に、DLsite はアフィリエイト、FANZA は表示名。
		$this->assertStringContainsString( 'is-vertical doujin-profile-work-card__shops', $html );
		$this->assertStringContainsString( 'doujin-profile-link--dlsite', $html );
		$this->assertStringContainsString( 'rel="noopener sponsored">DLsiteで見る</a>', $html );
		$this->assertStringContainsString( 'rel="noopener">FANZA で買う</a>', $html );
		$this->assertStringContainsString( '※ アフィリエイトリンクを含みます', $html );

		// サンプルビューア: 3 枚。
		$this->assertStringContainsString( 'data-doujin-viewer="open">サンプルを見る', $html );
		$this->assertStringContainsString( 'max="3"', $html );
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="doujin-profile-viewer__pages">(.*?)</script>#s', $html, $m ) );
		$pages = json_decode( $m[1], true );
		$this->assertCount( 3, $pages );
		$this->assertStringContainsString( '/uploads/sites/', $pages[0]['src'] );
		$this->assertSame( '作品その一 サンプル 01', $pages[0]['alt'] );
	}

	public function test_upcoming_work_uses_label_and_shows_the_badge(): void {
		$post = self::workBySlug( 'work-two' );
		$html = self::renderBlocks( '<!-- wp:doujin-profile/work-card /-->', $post );
		$this->assertStringContainsString( '<span class="doujin-profile-work-card__upcoming">予告</span>', $html );
		$this->assertStringContainsString( '2099年末', $html );
		$this->assertStringNotContainsString( 'サンプルを見る', $html );
		$this->assertStringNotContainsString( 'doujin-profile-link', $html ); // 販売先なし
	}

	public function test_work_card_outside_a_post_renders_nothing_for_visitors(): void {
		$this->assertSame( '', trim( self::renderBlocks( '<!-- wp:doujin-profile/work-card /-->' ) ) );
		$this->assertSame( '', doujin_profile_editor_notice( '案内' ) );
	}

	public function test_links_block_renders_the_site_links(): void {
		$shops = self::renderBlocks( '<!-- wp:doujin-profile/links {"list":"shops"} /-->' );
		$this->assertStringContainsString( 'doujin-profile-links doujin-profile-links--shops', $shops );
		$this->assertStringContainsString( 'is-content-justification-center', $shops );
		$this->assertStringContainsString( 'rel="noopener">FANZA の店</a>', $shops );
		$this->assertStringContainsString( 'doujin-profile-link--booth', $shops );
		$this->assertStringContainsString( 'rel="noopener sponsored">BOOTH</a>', $shops );
		$this->assertStringNotContainsString( 'insecure', $shops ); // http のリンクは入っていない

		$accounts = self::renderBlocks( '<!-- wp:doujin-profile/links {"list":"accounts"} /-->' );
		$this->assertStringContainsString( 'doujin-profile-links--accounts', $accounts );
		$this->assertStringContainsString( 'href="https://x.com/example"', $accounts );
		$this->assertStringContainsString( '>X</a>', $accounts );

		add_filter( 'pre_option_doujin_profile_links', '__return_empty_array' );
		$this->assertSame( '', trim( self::renderBlocks( '<!-- wp:doujin-profile/links {"list":"shops"} /-->' ) ), '空の一覧は公開側では何も出さない' );
		remove_filter( 'pre_option_doujin_profile_links', '__return_empty_array' );
	}

	public function test_front_page_pattern_renders_query_loop_and_more_works(): void {
		$front = get_post( (int) get_option( 'page_on_front' ) );
		$this->assertNotNull( $front );
		$this->assertTrue( has_block( 'doujin-profile/work-card', $front ) );
		$this->assertTrue( has_block( 'doujin-profile/more-works', $front ) );

		$html = self::renderBlocks( $front->post_content, $front );
		$this->assertStringContainsString( '試験サークル', $html ); // サイトのタイトル
		$this->assertStringContainsString( '結合試験のリード文', $html ); // キャッチフレーズ
		$this->assertSame( 2, preg_match_all( '/<article[^>]*doujin-profile-work-card/', $html ), '公開の作品 2 件 (下書きは出ない)' );
		$this->assertStringContainsString( 'doujin-profile-more-works', $html );
		$this->assertStringContainsString( 'data-query-id="1"', $html );
		$this->assertStringNotContainsString( 'data-doujin-more-works', $html ); // 6 件以下なので次のページは無い
		$this->assertStringContainsString( 'doujin-profile-links--shops', $html );
		$this->assertStringContainsString( 'doujin-profile-links--accounts', $html );
	}

	public function test_render_helpers(): void {
		$post = self::workBySlug( 'work-one' );
		$info = doujin_profile_release_info( $post->ID );
		$this->assertSame( array( 'label' => '2026年1月10日発売', 'upcoming' => false, 'release_at' => '2026-01-10T00:00:00+09:00' ), $info );

		$pages = doujin_profile_sample_pages( $post->ID );
		$this->assertCount( 3, $pages );
		$this->assertSame( array( 'src', 'srcset', 'alt' ), array_keys( $pages[2] ) );
		$this->assertSame( '作品その一 サンプル 03', $pages[2]['alt'] );

		$this->assertSame( '', doujin_profile_render_link_buttons( array() ) );
	}
}
