<?php

namespace DoujinProfile\Tests\Unit\Cli;

use DoujinProfile\Cli\DoctorRules;
use PHPUnit\Framework\TestCase;

final class DoctorRulesTest extends TestCase {

	public function test_non_image_types(): void {
		$this->assertSame( array(), DoctorRules::nonImageTypes( 'jpg jpeg jpe png gif webp avif heic heif' ) );
		$this->assertSame( array( 'pdf', 'mp4' ), DoctorRules::nonImageTypes( " jpg  pdf\tpng mp4 " ) );
		$this->assertSame( array( '' ), DoctorRules::nonImageTypes( '' ) ); // 空の設定は「画像でない」扱い (何も許していないのではなく、設定が壊れている)
	}

	public function test_plugin_active(): void {
		$active = array( 'disable-comments/disable-comments.php', 'wp-mail-smtp/wp_mail_smtp.php' );
		$this->assertTrue( DoctorRules::pluginActive( $active, 'disable-comments' ) );
		$this->assertFalse( DoctorRules::pluginActive( $active, 'disable-xml-rpc' ) );
		$this->assertFalse( DoctorRules::pluginActive( $active, 'disable' ) ); // 前方一致ではなくディレクトリ名
	}

	public function test_https_for_domain(): void {
		$this->assertTrue( DoctorRules::isHttpsForDomain( 'https://ftsf.dp.no32.tk', 'ftsf.dp.no32.tk' ) );
		$this->assertFalse( DoctorRules::isHttpsForDomain( 'http://ftsf.dp.no32.tk', 'ftsf.dp.no32.tk' ) );
		$this->assertFalse( DoctorRules::isHttpsForDomain( 'https://other.dp.no32.tk', 'ftsf.dp.no32.tk' ) );
		$this->assertTrue( DoctorRules::isHttpsForDomain( 'https://anything.example', null ) );
	}

	public function test_artist_rules(): void {
		$this->assertTrue( DoctorRules::hasArtistRules( array( 'artist/([^/]+)/?$' => 'index.php?artist=$matches[1]' ) ) );
		$this->assertFalse( DoctorRules::hasArtistRules( array( 'category/(.+?)/?$' => 'x' ) ) );
		$this->assertFalse( DoctorRules::hasArtistRules( '' ) );
		$this->assertFalse( DoctorRules::hasArtistRules( false ) );
	}

	public function test_timezone(): void {
		$this->assertTrue( DoctorRules::hasTimezone( 'Asia/Tokyo', 0.0 ) );
		$this->assertTrue( DoctorRules::hasTimezone( '', 9.0 ) );
		$this->assertFalse( DoctorRules::hasTimezone( '', 0.0 ) );
		$this->assertSame( 'Asia/Tokyo', DoctorRules::timezoneLabel( 'Asia/Tokyo', '9' ) );
		$this->assertSame( 'UTC9', DoctorRules::timezoneLabel( '', '9' ) );
		$this->assertSame( 'UTC0', DoctorRules::timezoneLabel( '', '0' ) );
	}

	public function test_space_percent(): void {
		$this->assertSame( 50.0, DoctorRules::spacePercent( 512.0, 1024.0 ) );
		$this->assertSame( 0.0, DoctorRules::spacePercent( 10.0, 0.0 ) );
	}

	public function test_works_summary(): void {
		$this->assertSame(
			'作品 5 件 (公開 2・予約 1・下書き 2・予告 0・書影なし 1)',
			DoctorRules::worksSummary( array( 'publish' => 2, 'future' => 1, 'draft' => 1, 'private' => 1 ), 5, 0, 1 )
		);
		$this->assertSame(
			'作品 1 件 (公開 1・予約 0・下書き 0・予告 1・書影なし 0)。予告の公開日時は最後に保存した時刻 (予告どうしの並びは保存順。spec §6.0)',
			DoctorRules::worksSummary( array( 'publish' => 1 ), 1, 1, 0 )
		);
	}
}
