<?php

namespace DoujinProfile\Tests\Integration;

/**
 * 準備中 (spec §7.6): 持ち主は公開にできるが準備中には戻せず、戻せるのは特権管理者と WP-CLI だけ。訪問者には「準備中です」だけ。
 */
final class PreparingTest extends IntegrationTestCase {

	protected function tearDown(): void {
		// 公開中に戻しておく (ほかの試験は公開中を前提にする)。
		$this->useSite();
		$this->loginAs( 'admin' );
		update_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
		parent::tearDown();
	}

	public function test_owner_can_publish_but_not_go_back_to_preparing(): void {
		$this->useSite();
		$this->loginAs( 'admin' );
		update_option( DOUJIN_PROFILE_PREPARING_OPTION, true );
		$this->assertTrue( doujin_profile_is_preparing() );

		$this->loginAs( 'owner' );
		$this->assertFalse( is_super_admin() );
		update_option( DOUJIN_PROFILE_PREPARING_OPTION, false ); // 公開する
		$this->assertFalse( doujin_profile_is_preparing() );

		update_option( DOUJIN_PROFILE_PREPARING_OPTION, true ); // 戻そうとしても戻らない
		$this->assertFalse( (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION ) );

		$response = $this->rest( 'POST', '/wp/v2/settings', array( 'doujin_profile_preparing' => true ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['doujin_profile_preparing'] );
		$this->assertFalse( (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION ) );

		$this->loginAs( 'admin' ); // 特権管理者は戻せる
		update_option( DOUJIN_PROFILE_PREPARING_OPTION, true );
		$this->assertTrue( doujin_profile_is_preparing() );
	}

	public function test_main_site_is_never_preparing(): void {
		$this->assertTrue( is_main_site() );
		$this->assertFalse( doujin_profile_is_preparing() );
	}

	public function test_gate_shows_only_the_preparing_page_to_visitors(): void {
		// 設定を変えずに「準備中」として、訪問者の表示を wp eval で取る (gate は exit するので子プロセスで)。
		$php = 'add_filter( "pre_option_doujin_profile_preparing", "__return_true" ); wp_set_current_user( 0 ); doujin_profile_preparing_gate(); echo "NOT-GATED";';
		$r   = self::wpSite( 'eval ' . escapeshellarg( $php ) );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( '<meta name="robots" content="noindex, nofollow">', $r['out'] );
		$this->assertStringContainsString( '<title>試験サークル — 準備中</title>', $r['out'] );
		$this->assertStringContainsString( '<p>準備中です。</p>', $r['out'] );
		$this->assertStringNotContainsString( 'NOT-GATED', $r['out'] );

		// 編集できる人には出さない。
		$php = 'add_filter( "pre_option_doujin_profile_preparing", "__return_true" ); wp_set_current_user( get_user_by( "login", "owner" )->ID ); doujin_profile_preparing_gate(); echo "NOT-GATED";';
		$r   = self::wpSite( 'eval ' . escapeshellarg( $php ) );
		$this->assertSame( 'NOT-GATED', trim( $r['out'] ) );
	}

	public function test_publish_command_toggles_preparing(): void {
		$r = self::wp( 'doujin publish ftsf --unpublish' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( 'https://ftsf.dp.test/ を準備中に戻しました。', $r['out'] );
		$this->assertStringContainsString( 'すでに準備中です', self::wp( 'doujin publish ftsf --unpublish' )['out'] );

		$r = self::wp( 'doujin publish ftsf' );
		$this->assertStringContainsString( 'https://ftsf.dp.test/ を公開しました。', $r['out'] );
		$this->useSite();
		$this->assertFalse( doujin_profile_is_preparing() );

		$r = self::wp( 'doujin publish nosuchsite' );
		$this->assertSame( 1, $r['code'] );
		$this->assertStringContainsString( 'サイト「nosuchsite」はありません', $r['out'] );
	}
}
