<?php
/**
 * 結合試験の共通部分: プロフィールのサイト (ftsf.dp.test) への切り替え、ユーザの切り替え、REST の呼び出し、wp の実行。
 */

namespace DoujinProfile\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

abstract class IntegrationTestCase extends TestCase {

	/** プロフィールのサイトのホスト名 (tests/remote/integration.sh の invite で作る)。 */
	public const SITE_HOST = 'ftsf.dp.test';

	/** @var int|null 切り替える前のサイト。 */
	private $switched_from = null;

	protected function tearDown(): void {
		$this->restoreSite();
		wp_set_current_user( 0 );
		wp_cache_flush(); // wp (子プロセス) が書いた分を、この process のキャッシュに残さない
		parent::tearDown();
	}

	/**
	 * プロフィールのサイトの blog_id。
	 *
	 * @return int
	 */
	protected static function siteId(): int {
		$id = (int) get_blog_id_from_url( self::SITE_HOST, '/' );
		if ( ! $id ) {
			self::fail( self::SITE_HOST . ' が無い (integration.sh の invite が失敗している)。' );
		}

		return $id;
	}

	/**
	 * プロフィールのサイトに切り替える (tearDown で戻す)。
	 */
	protected function useSite(): void {
		if ( null === $this->switched_from ) {
			wp_cache_flush(); // wp (子プロセス) が書いた分を読み直す
			$this->switched_from = get_current_blog_id();
			switch_to_blog( self::siteId() );
		}
	}

	/**
	 * メインサイトに戻す。
	 */
	protected function restoreSite(): void {
		if ( null !== $this->switched_from ) {
			restore_current_blog();
			$this->switched_from = null;
		}
	}

	/**
	 * ログイン名でユーザを切り替える (0 なら訪問者)。
	 *
	 * @param string $login ログイン名 (admin = 特権管理者、owner = ftsf の administrator、reader = ftsf の購読者)。
	 * @return int ユーザ ID。
	 */
	protected function loginAs( string $login ): int {
		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			self::fail( "ユーザ {$login} がいない (integration.sh が作るはず)。" );
		}
		wp_set_current_user( $user->ID );

		return (int) $user->ID;
	}

	/**
	 * REST を内部で呼ぶ (Cookie の nonce は要らない。wp_set_current_user() の人として扱われる)。
	 *
	 * @param string $method GET / POST など。
	 * @param string $route  例 /wp/v2/posts。
	 * @param array  $params 本文 (JSON) か問い合わせ。
	 * @return WP_REST_Response
	 */
	protected function rest( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}

		return rest_do_request( $request );
	}

	/**
	 * wp (WP-CLI) を子プロセスで実行する。
	 *
	 * @param string $args  wp のあとの引数 (sh の形。例 'doujin doctor --url=http://ftsf.dp.test')。
	 * @return array{code: int, out: string}
	 */
	protected static function wp( string $args ): array {
		$cache = getenv( 'WP_CLI_CACHE_DIR' );
		$wp    = getenv( 'DOUJIN_PROFILE_WP_CMD' ) ?: 'wp'; // integration.sh が渡す (php の引数つきで phar を直接)
		$cmd   = 'PAGER=cat ' . ( $cache ? 'WP_CLI_CACHE_DIR=' . escapeshellarg( $cache ) . ' ' : '' )
			. $wp . ' ' . $args . ' --path=' . escapeshellarg( (string) getenv( 'DOUJIN_PROFILE_WP' ) ) . ' 2>&1';
		exec( $cmd, $lines, $code );
		wp_cache_flush(); // 子プロセスが書いた分を、この process のキャッシュに残さない

		return array(
			'code' => (int) $code,
			'out'  => implode( "\n", $lines ),
		);
	}

	/**
	 * プロフィールのサイトを指して wp を実行する。
	 *
	 * @param string $args wp のあとの引数。
	 * @return array{code: int, out: string}
	 */
	protected static function wpSite( string $args ): array {
		return self::wp( $args . ' --url=http://' . self::SITE_HOST );
	}

	/**
	 * 見本 (交換形式の JSON と画像) の置き場。
	 *
	 * @return string
	 */
	protected static function fixtures(): string {
		return rtrim( (string) getenv( 'DOUJIN_PROFILE_FIXTURES' ), '/' );
	}

	/**
	 * 作品 (投稿) を slug で探す (いまのサイト)。
	 *
	 * @param string $slug 作品の slug。
	 * @return \WP_Post|null
	 */
	protected static function workBySlug( string $slug ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'name'           => $slug,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * ブロックのマークアップを描く (いまのサイト・いまの投稿で)。
	 *
	 * @param string        $content ブロックのマークアップ。
	 * @param \WP_Post|null $post    「いまの投稿」にする投稿 (作品カードは context か the_ID() で作品を決める)。
	 * @return string
	 */
	protected static function renderBlocks( string $content, $post = null ): string {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- null なら「いまの投稿」は無い
		if ( $post ) {
			setup_postdata( $post );
		}
		$html = do_blocks( $content );
		if ( $post ) {
			wp_reset_postdata();
		}
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $html;
	}

	/**
	 * 小さな JPEG を作って道筋を返す (取り込みの試験で「販売サイトの画像」の代わりにする)。
	 *
	 * @param int $width  幅。
	 * @param int $height 高さ。
	 * @return string
	 */
	protected static function makeJpeg( int $width = 320, int $height = 420 ): string {
		$path  = tempnam( get_temp_dir(), 'doujin-profile-test-' ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagefilledrectangle( $image, 0, 0, $width, $height, imagecolorallocate( $image, 200, 120, 160 ) );
		imagejpeg( $image, $path, 80 );
		imagedestroy( $image );

		return $path;
	}
}
