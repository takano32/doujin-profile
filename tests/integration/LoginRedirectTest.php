<?php

namespace DoujinProfile\Tests\Integration;

use WP_Error;

/**
 * ログイン後の直行 (spec §7.3): プロフィールのサイトで編集できる人はマイページへ、それ以外はコアの既定のまま。
 */
final class LoginRedirectTest extends IntegrationTestCase {

	public function test_profile_site_sends_editors_to_my_page(): void {
		$this->useSite();
		$owner  = get_user_by( 'login', 'owner' );
		$reader = get_user_by( 'login', 'reader' );
		$my     = admin_url( 'admin.php?page=doujin-profile' ); // スキームは要求が SSL か次第 (CLI では http、本番は https)
		$this->assertStringEndsWith( '://ftsf.dp.test/wp-admin/admin.php?page=doujin-profile', $my );

		$this->assertSame( $my, doujin_profile_login_redirect( admin_url(), '', $owner ) );
		$this->assertSame( $my, doujin_profile_login_redirect( admin_url(), admin_url(), $owner ) ); // ダッシュボードを求めたらマイページ
		$this->assertSame( $my, doujin_profile_login_redirect( admin_url( 'index.php' ), admin_url( 'index.php' ), $owner ) );

		$edit = admin_url( 'post.php?post=1&action=edit' );
		$this->assertSame( $edit, doujin_profile_login_redirect( $edit, $edit, $owner ), 'ほかの画面を求めていれば邪魔しない' );
		$this->assertSame( admin_url(), doujin_profile_login_redirect( admin_url(), '', $reader ), '購読者はコアの既定' );
		$this->assertSame( admin_url(), doujin_profile_login_redirect( admin_url(), '', new WP_Error( 'x' ) ) );
	}

	public function test_main_site_keeps_the_core_default(): void {
		$admin = get_user_by( 'login', 'admin' );
		$this->assertSame( admin_url(), doujin_profile_login_redirect( admin_url(), '', $admin ) );
	}
}
