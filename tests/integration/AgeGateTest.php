<?php

namespace DoujinProfile\Tests\Integration;

/**
 * 年齢確認 (spec §9.5): 成人向けの印の付いたサイトの訪問者にだけ出し、編集できる人には出さない。head には rating=adult。
 */
final class AgeGateTest extends IntegrationTestCase {

	public function test_needed_only_for_visitors_of_mature_sites(): void {
		$this->useSite();
		$this->assertSame( 1, (int) get_site()->mature, 'invite --mature で成人向けの印が付いているはず' );

		wp_set_current_user( 0 );
		$this->assertTrue( doujin_profile_needs_age_gate() );
		$this->loginAs( 'reader' ); // 購読者は編集できないので訪問者と同じ
		$this->assertTrue( doujin_profile_needs_age_gate() );
		$this->loginAs( 'owner' );
		$this->assertFalse( doujin_profile_needs_age_gate() );
		$this->loginAs( 'admin' );
		$this->assertFalse( doujin_profile_needs_age_gate() );

		$this->restoreSite();
		wp_set_current_user( 0 );
		$this->assertFalse( doujin_profile_needs_age_gate(), 'メインサイトには出さない' );
	}

	public function test_markup_for_visitors_and_nothing_for_editors(): void {
		$this->useSite();
		wp_set_current_user( 0 );

		ob_start();
		doujin_profile_age_gate_head();
		$head = ob_get_clean();
		$this->assertStringContainsString( 'doujin_profile_age_ok_v1', $head );
		$this->assertStringContainsString( 'doujin-profile-age-pending', $head );

		ob_start();
		doujin_profile_age_gate_dialog();
		$dialog = ob_get_clean();
		$this->assertStringContainsString( '<dialog id="doujin-profile-age-gate"', $dialog );
		$this->assertStringContainsString( 'はい、18歳以上です', $dialog );
		$this->assertStringContainsString( 'data-doujin-age="no"', $dialog );

		$this->loginAs( 'owner' );
		ob_start();
		doujin_profile_age_gate_head();
		doujin_profile_age_gate_dialog();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_head_has_rating_adult_on_mature_site_only(): void {
		$this->useSite();
		wp_set_current_user( 0 );
		ob_start();
		doujin_profile_head_meta();
		$this->assertStringContainsString( '<meta name="rating" content="adult" />', ob_get_clean() );

		$this->restoreSite();
		ob_start();
		doujin_profile_head_meta();
		$this->assertSame( '', ob_get_clean(), 'メインサイト (印なし・トップでも単体でもない) には何も出ない' );
	}
}
