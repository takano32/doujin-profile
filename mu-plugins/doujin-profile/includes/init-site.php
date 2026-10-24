<?php
/**
 * 新しいサイトの初期設定 (spec §6.1。T.32.5)。
 *
 * コアは新しいサイトに「Hello world!」などの初期データを入れ、URL を http:// で登録する
 * (サブドメイン型ではネットワークの URL が https でも http になる。WP-CLI の wp site create でも同じ)。
 * サイト作成のフック wp_initialize_site (コアの処理のあと。優先度 100) で整える:
 *
 *   1. home・siteurl を https に
 *   2. 初期の「Hello world!」(コメントごと) と「サンプルページ」を消す。プライバシーポリシーの下書きは残す
 *   3. タイムゾーンが未設定なら Asia/Tokyo
 *   4. パーマリンクがコアの既定のままなら /%postname%/
 *   5. 固定ページ「ホーム」(パターン doujin-profile/front-page、テンプレート page-no-title) を作ってトップにする
 *   6. 書き換え規則の作り直し (分類 artist の /artist/<slug>/ も入る)
 *
 * 同じ処理を WP-CLI の `wp doujin init-site --url=…` からも呼べる (includes/cli.php。作成済みのサイト向け)。
 * 何度呼んでも壊れない: あるものは作らず、持ち主が変えた設定 (パーマリンク・トップページ) は上書きしない。
 * メインサイトには効かせない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DOUJIN_PROFILE_PERMALINK_STRUCTURE = '/%postname%/';
const DOUJIN_PROFILE_FRONT_PAGE_SLUG     = 'home';
const DOUJIN_PROFILE_FRONT_PAGE_TEMPLATE = 'page-no-title'; // TT5 のカスタムテンプレート。無いテーマではコアの既定に任せる。

/**
 * サイト作成時 (wp_initialize_site)。コアはこの時点で新しいサイトに switch 済みだが、念のため確かめる。
 *
 * @param WP_Site $new_site 新しいサイト。
 * @param array   $args     wp_insert_site() に渡された引数 (user_id など)。
 */
function doujin_profile_on_initialize_site( $new_site, $args ) {
	if ( is_main_site( $new_site->id ) ) {
		return;
	}

	$switched = get_current_blog_id() !== (int) $new_site->id;
	if ( $switched ) {
		switch_to_blog( $new_site->id );
	}
	doujin_profile_init_site( isset( $args['user_id'] ) ? (int) $args['user_id'] : 0 );
	if ( $switched ) {
		restore_current_blog();
	}
}
add_action( 'wp_initialize_site', 'doujin_profile_on_initialize_site', 100, 2 );

/**
 * いまのサイトを整える (上の 1〜6)。何度呼んでも壊れない。
 *
 * @param int $author_id 「ホーム」の作者にするユーザ。0 ならサイトの管理者 (最初の 1 人)。
 * @return array{done: string[], errors: string[]} 行ったことと、できなかったこと (ログ用)。
 */
function doujin_profile_init_site( $author_id = 0 ) {
	$done   = array();
	$errors = array();

	// 1. https。
	foreach ( array( 'home', 'siteurl' ) as $name ) {
		$url = (string) get_option( $name );
		if ( str_starts_with( $url, 'http://' ) ) {
			update_option( $name, set_url_scheme( $url, 'https' ) );
			$done[] = "{$name} を https に";
		}
	}

	// 2. 初期データ。コアが入れる投稿とページを slug で探して消す (wp_delete_post() はコメントも一緒に消す)。
	foreach ( array( 'post' => 'hello-world', 'page' => 'sample-page' ) as $type => $slug ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$done[] = sprintf( '初期の%s「%s」を削除', 'post' === $type ? '投稿' : '固定ページ', get_the_title( $id ) ?: $slug );
			}
		}
	}

	// 3. タイムゾーン (発売日時の読み方に効く)。
	if ( '' === (string) get_option( 'timezone_string' ) && 0 === (int) get_option( 'gmt_offset' ) ) {
		update_option( 'timezone_string', 'Asia/Tokyo' );
		$done[] = 'タイムゾーンを Asia/Tokyo に';
	}

	// 4. パーマリンク (コアの既定のままのときだけ。持ち主が変えたものは触らない)。
	$structure = (string) get_option( 'permalink_structure' );
	if ( in_array( $structure, array( '', '/%year%/%monthnum%/%day%/%postname%/' ), true ) ) {
		$GLOBALS['wp_rewrite']->set_permalink_structure( DOUJIN_PROFILE_PERMALINK_STRUCTURE );
		$done[] = 'パーマリンクを ' . DOUJIN_PROFILE_PERMALINK_STRUCTURE . ' に';
	}

	// 5. トップページ。
	$front = doujin_profile_ensure_front_page( $author_id );
	if ( is_wp_error( $front ) ) {
		$errors[] = '固定ページ「ホーム」を作れませんでした: ' . $front->get_error_message();
	} elseif ( $front ) {
		$done[] = $front;
	}

	// 6. 書き換え規則。
	$GLOBALS['wp_rewrite']->init();
	flush_rewrite_rules();
	$done[] = '書き換え規則を作り直した';

	return array(
		'done'   => $done,
		'errors' => $errors,
	);
}

/**
 * 固定ページ「ホーム」をトップページにする。すでにトップが固定ページなら何もしない。
 *
 * @param int $author_id 作者。0 ならサイトの管理者 (最初の 1 人)。
 * @return string|null|WP_Error 行ったこと。何もしなかったら null。
 */
function doujin_profile_ensure_front_page( $author_id = 0 ) {
	$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	if ( $front_id && 'publish' === get_post_status( $front_id ) ) {
		return null;
	}

	$page    = get_page_by_path( DOUJIN_PROFILE_FRONT_PAGE_SLUG, OBJECT, 'page' );
	$page_id = $page && 'trash' !== $page->post_status ? (int) $page->ID : 0;
	$what    = '既にある固定ページ「ホーム」をトップに';
	if ( ! $page_id ) {
		if ( ! $author_id ) {
			$admins    = get_users(
				array(
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
					'fields'  => 'ID',
				)
			);
			$author_id = $admins ? (int) $admins[0] : 0;
		}
		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => 'ホーム',
					'post_name'      => DOUJIN_PROFILE_FRONT_PAGE_SLUG,
					'post_content'   => doujin_profile_pattern_front_page(),
					'post_author'    => $author_id,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}
		// テンプレートは、いまのテーマにあるときだけ (無いテーマでは存在しない名前を残さない)。
		$templates = wp_get_theme()->get_page_templates( null, 'page' );
		if ( isset( $templates[ DOUJIN_PROFILE_FRONT_PAGE_TEMPLATE ] ) ) {
			update_post_meta( $page_id, '_wp_page_template', DOUJIN_PROFILE_FRONT_PAGE_TEMPLATE );
		}
		$what = '固定ページ「ホーム」を作ってトップに';
	} elseif ( 'publish' !== $page->post_status ) {
		wp_publish_post( $page_id );
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_id );

	return $what . " (ID {$page_id})";
}
