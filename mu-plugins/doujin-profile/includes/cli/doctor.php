<?php
/**
 * wp dp doctor: 点検 (読み取りだけ。--fix は安全な 3 つだけ直す)。
 *
 * ここは WordPress から事実を読む側。1 件ずつの記録 (OK / NG / FIXED と NG の数) は lib/Cli/DoctorReport.php、
 * 読まなくても決まる判定 (画像でない形式・https とドメイン・書き換え規則・容量の割合・まとめの文) は lib/Cli/DoctorRules.php。
 */

namespace DoujinProfile\Commands;

use DoujinProfile\Cli\DoctorReport;
use DoujinProfile\Cli\DoctorRules;
use WP_Block_Type_Registry;
use WP_CLI;
use WP_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Doctor {

	/** @var DoctorReport 記録。 */
	private $report;

	/**
	 * 点検 (読み取りだけ)。--url でサイト 1 つ、無ければネットワークの設定と全サイト。直し方を添える。
	 *
	 * 見るもの: ネットワーク設定 (登録停止・画像だけ・容量)、堅牢化のプラグイン、本体のファイルとビルド、子テーマ、
	 * サイトごとに home/siteurl の https・トップページ・パーマリンクと分類 artist の書き換え規則・テーマ・初期データの残り・
	 * 準備中/公開・成人向けの印と年齢確認・検索エンジン・容量・作品 (本文の作品カード、サンプルの消えた添付、公開日時のルール)。
	 *
	 * ## OPTIONS
	 *
	 * [--fix]
	 * : 安全な直しだけ行う: 書き換え規則の作り直し、発売日を過ぎた作品の公開日時の再計算 (保存し直すだけ)、
	 * サンプル画像の一覧から消えた添付を外す。ほかは直し方を表示するだけ。
	 *
	 * ## EXAMPLES
	 *
	 *     wp dp doctor
	 *     wp dp doctor --url=https://ftsf.dp.no32.tk
	 *     wp dp doctor --url=https://ftsf.dp.no32.tk --fix
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->report = new DoctorReport(
			(bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fix', false ),
			static function ( $line ) {
				WP_CLI::log( $line );
			}
		);

		WP_CLI::log( sprintf( '== 本体 (WordPress %s, PHP %s, doujin-profile %s)', get_bloginfo( 'version' ), PHP_VERSION, DOUJIN_PROFILE_VERSION ) );
		$this->doctor_plugin();

		if ( is_multisite() && is_main_site() ) {
			WP_CLI::log( '== ネットワーク ' . network_home_url( '/' ) );
			$this->doctor_network();
			$sites = get_sites(
				array(
					'number'       => 500,
					'site__not_in' => array( get_main_site_id() ),
				)
			);
			$this->report->info( sprintf( 'プロフィールのサイト %d 件', count( $sites ) ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				WP_CLI::log( "== サイト https://{$site->domain}{$site->path} (blog_id {$site->blog_id})" );
				$this->doctor_site( $site );
				restore_current_blog();
			}
		} else {
			WP_CLI::log( '== サイト ' . home_url( '/' ) );
			$this->doctor_site( is_multisite() ? get_site() : null );
		}

		if ( $this->report->ng() ) {
			WP_CLI::warning( $this->report->summary() );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( 'NG はありません。' );
	}

	/**
	 * 本体のファイルとビルド (どのサイトでも同じ)。
	 */
	private function doctor_plugin() {
		$this->check( is_file( WPMU_PLUGIN_DIR . '/doujin-profile.php' ), 'ローダ mu-plugins/doujin-profile.php がある', 'scripts/deploy.sh --go' );
		$this->check( is_file( WPMU_PLUGIN_DIR . '/doujin-profile-discard-original-image.php' ), '元の巨大画像を消す mu-plugin がある (T51.5)', 'scripts/deploy.sh --go' );
		$this->check( is_readable( DOUJIN_PROFILE_DIR . '/build/my-page.asset.php' ) && is_readable( DOUJIN_PROFILE_DIR . '/build/my-page.js' ), 'マイページのビルド build/my-page.js がある', 'npm run build → コミット → scripts/deploy.sh --go' );
		foreach ( array( 'work-card', 'links', 'more-works' ) as $block ) {
			$dir = DOUJIN_PROFILE_DIR . "/build/blocks/{$block}";
			$ok  = is_readable( "{$dir}/block.json" ) && is_readable( "{$dir}/render.php" ) && is_readable( "{$dir}/index.js" )
				&& WP_Block_Type_Registry::get_instance()->is_registered( "doujin-profile/{$block}" );
			$this->check( $ok, "ブロック doujin-profile/{$block} のビルドがあり、登録されている", 'npm run build → コミット → scripts/deploy.sh --go' );
		}
		$this->check( taxonomy_exists( 'artist' ) && registered_meta_key_exists( 'post', 'doujin_release_at', 'post' ), '分類 artist と作品のメタが登録されている', '本体 (includes/data/) の読み込みを確かめる' );
		$this->check( has_action( 'wp_footer', 'doujin_profile_age_gate_dialog' ) && has_action( 'template_redirect', 'doujin_profile_preparing_gate' ), '年齢確認と準備中の仕組みが読み込まれている', '本体の配備 (scripts/deploy.sh --go)' );
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			$this->check( false, 'PHP 8.0 以上', 'ホストの PHP の設定を上げる' );
		}
	}

	/**
	 * ネットワークの設定と堅牢化 (spec §10)。
	 */
	private function doctor_network() {
		$this->check( is_subdomain_install(), 'サブドメイン型の Multisite', 'spec §4 (サブディレクトリ型は想定外)' );
		$this->check( 'none' === get_site_option( 'registration' ), 'ユーザ登録は停止 (招待制)', 'wp network meta update 1 registration none' );
		$this->check( ! (int) get_site_option( 'add_new_users' ), 'サイト管理者は新しいユーザを作れない', 'wp network meta update 1 add_new_users 0' );

		$bad = DoctorRules::nonImageTypes( (string) get_site_option( 'upload_filetypes' ) );
		$this->check( ! $bad, 'アップロードできる形式は画像だけ' . ( $bad ? ' (画像でない: ' . implode( ' ', $bad ) . ')' : '' ), 'wp network meta update 1 upload_filetypes "jpg jpeg jpe png gif webp avif heic heif"' );
		$space = (int) get_site_option( 'blog_upload_space' );
		$this->check( $space > 0 && ! (int) get_site_option( 'upload_space_check_disabled' ), sprintf( 'サイトあたりの容量の上限が効いている (%d MB)', $space ), 'wp network meta update 1 upload_space_check_disabled 0; wp network meta update 1 blog_upload_space 1024' );
		$illegal = (array) get_site_option( 'illegal_names', array() );
		$this->report->info( sprintf( '禁止名 %d 語 (%s …)', count( $illegal ), implode( ' ', array_slice( $illegal, 0, 8 ) ) ) );

		$active = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		foreach ( array(
			'disable-comments'              => 'コメントとピンバックを止める (Disable Comments)',
			'disable-xml-rpc'               => 'XML-RPC を止める (Disable XML-RPC)',
			'limit-login-attempts-reloaded' => 'ログイン試行の制限 (Limit Login Attempts Reloaded)',
		) as $slug => $what ) {
			$this->check( DoctorRules::pluginActive( $active, $slug ), "{$what} がネットワークで有効", "wp plugin install {$slug} --activate-network" );
		}

		$theme = wp_get_theme( 'doujin-profile-tt5' );
		$this->check( $theme->exists(), '子テーマ doujin-profile-tt5 がある', 'scripts/deploy.sh --themes --go' );
		if ( $theme->exists() ) {
			$this->check( array_key_exists( 'doujin-profile-tt5', WP_Theme::get_allowed_on_network() ), '子テーマ doujin-profile-tt5 がネットワークで有効', 'wp theme enable doujin-profile-tt5 --network' );
		}
	}

	/**
	 * サイト 1 つ (いまのサイト)。
	 *
	 * @param \WP_Site|null $site サイト (単一サイトなら null)。
	 */
	private function doctor_site( $site ) {
		$url      = $site ? "https://{$site->domain}{$site->path}" : home_url( '/' );
		$fix_init = "wp dp init-site --url={$url}";

		// URL。
		foreach ( array( 'home', 'siteurl' ) as $name ) {
			$value = (string) get_option( $name );
			$this->check( DoctorRules::isHttpsForDomain( $value, $site ? $site->domain : null ), "{$name} が https でサイトのドメインと一致 ({$value})", $fix_init . ' (https にする) / ドメインが違うなら wp option update ' . $name );
		}

		// 状態。
		if ( $site ) {
			$preparing = (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
			$this->report->info( $preparing ? '準備中 (ログインしていない人には「準備中です」だけ。公開は持ち主のマイページか wp dp publish ' . strtok( $site->domain, '.' ) . ')' : '公開中' );
			$this->report->info( '成人向けの印: ' . ( (int) $site->mature ? 'オン (年齢確認と rating=adult が出る)' : 'オフ (年齢確認は出ない。付けるなら wp site mature ' . $site->blog_id . ')' ) );
			$this->check( ! (int) $site->archived && ! (int) $site->spam && ! (int) $site->deleted, 'アーカイブ・スパム・削除の印が無い', "wp site unarchive/unspam {$site->blog_id}" );
		}
		$this->check( 1 === (int) get_option( 'blog_public' ), '検索エンジンに載せる (blog_public = 1。所有者の裁定「広報なんだから載せろ」)', "wp option update blog_public 1 --url={$url}" );

		// 初期設定。
		$this->check( DoctorRules::hasTimezone( (string) get_option( 'timezone_string' ), (float) get_option( 'gmt_offset' ) ), 'タイムゾーンが設定されている (' . DoctorRules::timezoneLabel( (string) get_option( 'timezone_string' ), get_option( 'gmt_offset' ) ) . ')', $fix_init );
		$structure = (string) get_option( 'permalink_structure' );
		$this->check( str_contains( $structure, '%postname%' ), "パーマリンクに %postname% が入っている ({$structure})", $fix_init . " / wp rewrite structure '/%postname%/' --url={$url}" );
		$this->check(
			DoctorRules::hasArtistRules( get_option( 'rewrite_rules' ) ),
			'書き換え規則に分類 artist (/artist/<slug>/) が入っている',
			"wp rewrite flush --url={$url}",
			static function () {
				$GLOBALS['wp_rewrite']->init();
				flush_rewrite_rules();
			}
		);

		$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$this->check( $front_id && 'publish' === get_post_status( $front_id ), 'トップページが公開済みの固定ページ' . ( $front_id ? " (ID {$front_id})" : '' ), $fix_init );
		if ( $front_id ) {
			$front = get_post( $front_id );
			$this->check( $front && has_block( 'doujin-profile/work-card', $front ), 'トップページに作品カード (クエリーループ) がある', 'ブロックエディタでパターン「サークル紹介のトップページ」を入れる' );
		}
		foreach ( array( 'post' => 'hello-world', 'page' => 'sample-page' ) as $type => $slug ) {
			$left = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'any',
					'name'           => $slug,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$this->check( ! $left, "初期の{$slug} が残っていない", $fix_init );
		}

		$theme = wp_get_theme();
		$this->check( $theme->exists() && ( ! is_multisite() || $theme->is_allowed() ), "テーマ {$theme->get_stylesheet()} があり、使える", 'wp theme enable <テーマ> --network / wp theme activate <テーマ> --url=' . $url );

		// 容量。
		if ( is_multisite() && function_exists( 'get_space_used' ) ) {
			$used    = get_space_used();
			$allowed = get_space_allowed();
			$percent = DoctorRules::spacePercent( (float) $used, (float) $allowed );
			$this->check( $percent < 90, sprintf( '容量 %.0f MB / %d MB (%.0f%%)', $used, $allowed, $percent ), '不要な画像を消す / wp site meta update ' . ( $site ? $site->blog_id : '' ) . ' blog_upload_space <MB>' );
		}

		// 作品。
		$this->doctor_works( $url );

		// 管理者。
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'user_login' ),
			)
		);
		$this->check( (bool) $admins, 'このサイトの administrator: ' . implode( ', ', wp_list_pluck( $admins, 'user_login' ) ), "wp user set-role <ログイン名> administrator --url={$url}" );
	}

	/**
	 * 作品 (投稿) の点検。
	 *
	 * @param string $url サイトの URL (直し方の表示用)。
	 */
	private function doctor_works( $url ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$counts   = array_count_values( wp_list_pluck( $posts, 'post_status' ) );
		$upcoming = 0;
		$no_cover = 0;
		$now      = time();

		foreach ( $posts as $post ) {
			$label = sprintf( '作品「%s」(ID %d)', $post->post_title, $post->ID );

			$this->check( has_block( 'doujin-profile/work-card', $post ), "{$label} の本文に作品カードがある", 'マイページで作品を開いて保存する (本文が空なら作品カードが入る) / ブロックエディタで「作品カード」を足す' );

			$ids     = doujin_profile_sanitize_sample_ids( get_post_meta( $post->ID, 'doujin_sample_ids', true ) );
			$missing = array_values( array_filter( $ids, static fn( $id ) => ! wp_attachment_is_image( $id ) ) );
			$this->check(
				! $missing,
				"{$label} のサンプル画像 " . count( $ids ) . ' 枚' . ( $missing ? ' (消えた・画像でない添付: ' . implode( ', ', $missing ) . ')' : '' ),
				'マイページでサンプルを並べ直して保存する',
				static function () use ( $post, $ids, $missing ) {
					update_post_meta( $post->ID, 'doujin_sample_ids', array_values( array_diff( $ids, $missing ) ) );
				}
			);

			if ( ! has_post_thumbnail( $post ) ) {
				++$no_cover;
			}

			// 公開日時のルール (spec §6.0): 公開 = min(発売日時, 今)。予告は保存のたびに「今」になる (並びは最後に直した順) のは仕様。
			if ( ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				continue;
			}
			$release = doujin_profile_parse_datetime( get_post_meta( $post->ID, 'doujin_release_at', true ) );
			$resave  = static fn() => wp_update_post( array( 'ID' => $post->ID ) ); // 保存し直すだけ。公開日時は保存時のルール (includes/data/release-date.php) が決める。
			$this->check( 'future' !== $post->post_status, "{$label} が予約 (future) になっていない", $release ? "保存し直す (wp post update {$post->ID} --post_status=publish --url={$url})" : '発売日時を入れて保存する (発売日時が無いと公開日時はコアの既定)', $release ? $resave : null );
			if ( ! $release ) {
				continue;
			}
			if ( $release->getTimestamp() > $now ) {
				++$upcoming;
				continue;
			}
			$expected = doujin_profile_release_post_date_gmt( $release->format( DATE_ATOM ) );
			$this->check(
				$expected === $post->post_date_gmt,
				"{$label} の公開日時が発売日時 (" . $release->format( 'Y-m-d H:i' ) . ') と同じ' . ( $expected !== $post->post_date_gmt ? " (いまは {$post->post_date} = 発売前の最後の保存のまま)" : '' ),
				"保存し直す (wp post update {$post->ID} --post_status=publish --url={$url})",
				$resave
			);
		}

		$this->report->info( DoctorRules::worksSummary( $counts, count( $posts ), $upcoming, $no_cover ) );
	}

	/**
	 * 点検 1 件 (記録は DoctorReport)。
	 *
	 * @param mixed         $ok    合格か。
	 * @param string        $what  見たこと。
	 * @param string        $fix   直し方 (人向け)。
	 * @param callable|null $fixer --fix で行う直し (安全なものだけ)。
	 */
	private function check( $ok, $what, $fix = '', $fixer = null ) {
		$this->report->check( $ok, $what, $fix, $fixer );
	}
}
