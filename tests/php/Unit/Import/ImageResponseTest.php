<?php

namespace DoujinProfile\Tests\Unit\Import;

use DoujinProfile\Import\ImageResponse;
use PHPUnit\Framework\TestCase;

final class ImageResponseTest extends TestCase {

	public function test_image_200_is_ok(): void {
		$this->assertTrue( ImageResponse::isImage( 200, 'image/jpeg' ) );
		$this->assertNull( ImageResponse::failure( 200, 'image/png' ) );
	}

	public function test_redirect_or_not_found_hints_at_the_number(): void {
		foreach ( array( 302, 404 ) as $code ) {
			$failure = ImageResponse::failure( $code, 'text/html' );
			$this->assertSame( 'doujin_profile_download_failed', $failure->code );
			$this->assertSame( 502, $failure->status );
			$this->assertStringContainsString( '番号を確かめてください', $failure->message );
			$this->assertStringContainsString( "(HTTP {$code})", $failure->message );
		}
	}

	public function test_other_failures_ask_to_retry(): void {
		$failure = ImageResponse::failure( 500, '' );
		$this->assertStringContainsString( 'もう一度お試しください', $failure->message );
		$this->assertStringContainsString( '(HTTP 500)', $failure->message );

		$failure = ImageResponse::failure( 0, '' ); // 接続できなかった
		$this->assertStringEndsWith( 'お試しください。', $failure->message );
	}

	public function test_200_without_image_type_fails(): void {
		$this->assertNotNull( ImageResponse::failure( 200, 'text/html' ) );
	}
}
