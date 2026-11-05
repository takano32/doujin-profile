<?php

namespace DoujinProfile\Tests\Integration;

use WP_Block_Patterns_Registry;
use WP_Block_Type_Registry;

/**
 * 本体が読み込まれ、登録 (フックと優先度・分類・メタ・設定・ブロック・パターン・REST の経路) が揃っていること。
 */
final class PluginLoadsTest extends IntegrationTestCase {

	public function test_constants_and_autoloader(): void {
		$this->assertSame( '0.1.0', DOUJIN_PROFILE_VERSION );
		$this->assertTrue( class_exists( \DoujinProfile\Data\Links::class ) );
		$this->assertSame( 30, DOUJIN_PROFILE_MAX_LINKS );
		$this->assertSame( 80, DOUJIN_PROFILE_MAX_SAMPLES );
		$this->assertSame( 'doujin-profile/v1', DOUJIN_PROFILE_REST_NAMESPACE );
		$this->assertSame( 'doujin_profile_preparing', DOUJIN_PROFILE_PREPARING_OPTION );
		$this->assertSame( '_doujin_profile_source_url', DOUJIN_PROFILE_SOURCE_URL_META );
	}

	public function test_hooks_keep_their_callbacks_and_priorities(): void {
		$expected = array(
			array( 'init', 'doujin_profile_register_taxonomy', 10 ),
			array( 'init', 'doujin_profile_register_meta', 10 ),
			array( 'init', 'doujin_profile_register_settings', 10 ),
			array( 'init', 'doujin_profile_register_blocks', 10 ),
			array( 'init', 'doujin_profile_register_patterns', 10 ),
			array( 'init', 'doujin_profile_register_preparing', 10 ),
			array( 'wp_insert_post_data', 'doujin_profile_apply_release_date', 10 ),
			array( 'added_post_meta', 'doujin_profile_release_at_changed', 10 ),
			array( 'updated_post_meta', 'doujin_profile_release_at_changed', 10 ),
			array( 'posts_search', 'doujin_profile_search_by_artist', 10 ),
			array( 'login_redirect', 'doujin_profile_login_redirect', 10 ),
			array( 'admin_menu', 'doujin_profile_add_my_page_menu', 10 ),
			array( 'admin_enqueue_scripts', 'doujin_profile_enqueue_my_page', 10 ),
			array( 'rest_api_init', 'doujin_profile_register_import_routes', 10 ),
			array( 'block_categories_all', 'doujin_profile_block_category', 10 ),
			array( 'wp_initialize_site', 'doujin_profile_on_initialize_site', 100 ),
			array( 'pre_update_option_doujin_profile_preparing', 'doujin_profile_guard_preparing', 10 ),
			array( 'template_redirect', 'doujin_profile_preparing_gate', 1 ),
			array( 'wp_head', 'doujin_profile_age_gate_head', 1 ),
			array( 'wp_footer', 'doujin_profile_age_gate_dialog', 1 ),
			array( 'wp_head', 'doujin_profile_head_meta', 2 ),
			array( 'wp_generate_attachment_metadata', 'doujin_profile_discard_original_image', 99 ),
		);
		foreach ( $expected as list( $hook, $callback, $priority ) ) {
			$this->assertSame( $priority, has_filter( $hook, $callback ), "{$hook} → {$callback}" );
		}
	}

	public function test_data_layer_is_registered(): void {
		$this->assertTrue( taxonomy_exists( 'artist' ) );
		$taxonomy = get_taxonomy( 'artist' );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertSame( 'artist', $taxonomy->rewrite['slug'] );
		$this->assertContains( 'post', $taxonomy->object_type );

		$keys = get_registered_meta_keys( 'post', 'post' );
		foreach ( array(
			'doujin_release_at'    => 'doujin_profile_sanitize_release_at',
			'doujin_release_label' => 'doujin_profile_sanitize_release_label',
			'doujin_shop_links'    => 'doujin_profile_sanitize_links',
			'doujin_sample_ids'    => 'doujin_profile_sanitize_sample_ids',
		) as $key => $sanitize ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $key, 'post' ), $key );
			$this->assertSame( $sanitize, $keys[ $key ]['sanitize_callback'] );
			$this->assertSame( 'doujin_profile_meta_auth', $keys[ $key ]['auth_callback'] );
			$this->assertTrue( $keys[ $key ]['single'] );
			$this->assertNotEmpty( $keys[ $key ]['show_in_rest'] );
		}
		$this->assertSame( 30, $keys['doujin_shop_links']['show_in_rest']['schema']['maxItems'] );
		$this->assertSame( 80, $keys['doujin_sample_ids']['show_in_rest']['schema']['maxItems'] );

		$settings = get_registered_settings();
		$this->assertSame( 'doujin_profile', $settings['doujin_profile_links']['group'] );
		$this->assertSame( 'doujin_profile_sanitize_profile_links', $settings['doujin_profile_links']['sanitize_callback'] );
		$this->assertSame( 'doujin_profile', $settings['doujin_profile_preparing']['group'] );
		$this->assertSame( 'boolean', $settings['doujin_profile_preparing']['type'] );
	}

	public function test_blocks_patterns_and_rest_routes_are_registered(): void {
		$blocks = WP_Block_Type_Registry::get_instance();
		foreach ( array( 'work-card', 'links', 'more-works' ) as $block ) {
			$this->assertTrue( $blocks->is_registered( "doujin-profile/{$block}" ), $block );
			$this->assertSame( 'doujin-profile', $blocks->get_registered( "doujin-profile/{$block}" )->category );
		}
		$this->assertSame( array( 'shops', 'accounts' ), $blocks->get_registered( 'doujin-profile/links' )->attributes['list']['enum'] );

		$patterns = WP_Block_Patterns_Registry::get_instance();
		$this->assertTrue( $patterns->is_registered( 'doujin-profile/profile-header' ) );
		$this->assertTrue( $patterns->is_registered( 'doujin-profile/front-page' ) );
		$this->assertStringContainsString( '<!-- wp:doujin-profile/work-card /-->', $patterns->get_registered( 'doujin-profile/front-page' )['content'] );

		$routes = rest_get_server()->get_routes( 'doujin-profile/v1' );
		$this->assertArrayHasKey( '/doujin-profile/v1/works/(?P<id>\d+)/cover', $routes );
		$this->assertArrayHasKey( '/doujin-profile/v1/works/(?P<id>\d+)/samples', $routes );
		$this->assertSame( array( 'POST' => true ), $routes['/doujin-profile/v1/works/(?P<id>\d+)/cover'][0]['methods'] );
		$this->assertSame( array( 'GET' => true ), $routes['/doujin-profile/v1/works/(?P<id>\d+)/samples'][0]['methods'] );
		$this->assertSame( array( 'POST' => true ), $routes['/doujin-profile/v1/works/(?P<id>\d+)/samples'][1]['methods'] );
		$this->assertSame( 'doujin_profile_validate_product_id', $routes['/doujin-profile/v1/works/(?P<id>\d+)/cover'][0]['args']['product_id']['validate_callback'] );
	}

	public function test_my_page_data_and_url(): void {
		$this->useSite();
		$data = doujin_profile_my_page_data();
		$this->assertSame( 30, $data['maxLinks'] );
		$this->assertSame( 80, $data['maxSamples'] );
		$this->assertSame( '/doujin-profile/v1/works/%d/cover', $data['coverImportPath'] );
		$this->assertSame( '/doujin-profile/v1/works/%d/samples', $data['sampleImportPath'] );
		$this->assertCount( 16, $data['services'] );
		$this->assertSame( 'https://ftsf.dp.test/', $data['homeUrl'] );
		// 管理画面の URL のスキームは要求が SSL かで決まる (CLI では http)。本番は https の要求なので https になる。
		$this->assertSame( admin_url( 'admin.php?page=doujin-profile' ), doujin_profile_my_page_url() );
		$this->assertStringEndsWith( '://ftsf.dp.test/wp-admin/admin.php?page=doujin-profile', doujin_profile_my_page_url() );
		$this->assertNotNull( doujin_profile_build_asset( 'my-page' ) );
	}
}
