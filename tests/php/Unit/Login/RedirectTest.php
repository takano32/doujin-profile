<?php

namespace DoujinProfile\Tests\Unit\Login;

use DoujinProfile\Login\Redirect;
use PHPUnit\Framework\TestCase;

final class RedirectTest extends TestCase {

	public function test_dashboard_paths(): void {
		$this->assertTrue( Redirect::isDashboard( '/wp-admin/', '/wp-admin/' ) );
		$this->assertTrue( Redirect::isDashboard( '/wp-admin', '/wp-admin/' ) );
		$this->assertTrue( Redirect::isDashboard( '/wp-admin/index.php', '/wp-admin/' ) );
	}

	public function test_other_screens_are_not_dashboard(): void {
		$this->assertFalse( Redirect::isDashboard( '/wp-admin/post.php', '/wp-admin/' ) );
		$this->assertFalse( Redirect::isDashboard( '/wp-admin/admin.php', '/wp-admin/' ) );
		$this->assertFalse( Redirect::isDashboard( '/', '/wp-admin/' ) );
		$this->assertFalse( Redirect::isDashboard( '', '/wp-admin/' ) ); // パスの無い redirect_to もダッシュボードではない (求めた先へ)
	}
}
