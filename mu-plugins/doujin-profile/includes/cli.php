<?php
/**
 * WP-CLI (spec §8.5。T.32.9)。運営者の道具。コア標準のコマンドで足りるものは作らない。
 *
 *   wp doujin init-site --url=<サイト>                                        新しいサイトの初期設定を、作成済みのサイトに施す (includes/init-site.php)
 *   wp doujin import <file.json> --url=<サイト> [--images=<dir>] [--dry-run]  交換形式 (spec §6.4) から入れる
 *   wp doujin export --url=<サイト> [--out=<file>]                            同じ形で書き出す (画像は URL)
 *   wp doujin invite <名前> <メール> [--title=] [--mature] [--user=] [--dry-run] 招待: サイトと持ち主を作り、準備中で始める (spec §7.4)
 *   wp doujin publish <名前> [--unpublish]                                    準備中を解く / 戻す (spec §7.6。持ち主はマイページでも公開できる)
 *   wp doujin doctor [--url=<サイト>] [--fix]                                 点検 (読み取り。--fix は安全な 3 つだけ直す)
 *
 * invite はコアの検証 (wpmu_validate_user_signup / wpmu_validate_blog_signup) と作成 (wpmu_create_user / wpmu_create_blog) に乗る。
 * wpmu_create_blog → wp_initialize_site で includes/init-site.php の初期設定が自動で走るので、ここでは呼ばない (二重にしない)。
 * メールはコアの wp_new_user_notification() (パスワード設定のリンク。文面の作り込みは T.33)。
 *
 * import の照合: 作品は slug (JSON の key。無ければ書影の作品番号、それも無ければタイトル) で探し、あれば更新・無ければ作る。
 * 画像は出どころ (作品番号の URL、https の URL、JSON に書かれた道筋) を添付のメタ _doujin_profile_source_url に持つので、
 * 同じ JSON を 2 回入れても二重にならない (includes/cover-import.php)。英語 (en) は読み飛ばす (spec §6.0)。
 * 書き込みはすべて標準の経路 (wp_insert_post の meta_input、wp_set_object_terms、update_option) で、
 * 検査は register_post_meta() / register_setting() のサニタイズに任せる。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * サークル紹介サイト (doujin-profile) の運営の道具。
 */
class Doujin_Profile_CLI {

	/** @var bool dry-run か。 */
	private $dry = false;

	/** @var string 画像の相対の道筋の起点。 */
	private $images = '';

	/** @var bool doctor: --fix か。 */
	private $fix = false;

	/** @var int doctor: NG の数。 */
	private $ng = 0;

	/**
	 * 新しいサイトの初期設定を、いまのサイトに施す。
	 *
	 * home・siteurl を https に、初期の「Hello world!」とサンプルページを消し、固定ページ「ホーム」をトップにし、
	 * パーマリンクと書き換え規則を整える (spec §6.1)。何度呼んでも壊れない (あるものは作らない)。メインサイトには効かない。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin init-site --url=https://ftsf.dp.no32.tk
	 *
	 * @subcommand init-site
	 */
	public function init_site( $args, $assoc_args ) {
		$this->require_profile_site();

		$result = doujin_profile_init_site( get_current_user_id() );
		foreach ( $result['done'] as $line ) {
			WP_CLI::log( '- ' . $line );
		}
		foreach ( $result['errors'] as $line ) {
			WP_CLI::warning( $line );
		}
		WP_CLI::success( sprintf( '%s の初期設定を整えました。', home_url( '/' ) ) );
	}

	/**
	 * 交換形式の JSON (spec §6.4) から、サークル名・リード文・リンク集・作品を入れる。
	 *
	 * 作品は key (無ければ書影の作品番号、それも無ければタイトル) を slug として照合し、あれば更新・無ければ作る。
	 * 同じ JSON を 2 回入れても二重にならない。書影・サンプルは 3 つの書き方を受ける:
	 * 作品番号 {"service":"fanza","id":"d_737824"} (サンプルはその作品の全部)、https の URL、JSON からの相対の道筋。
	 * 英語 (en) は読み飛ばす。
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : 交換形式の JSON ファイル。
	 *
	 * [--images=<dir>]
	 * : 画像の相対の道筋の起点。既定は JSON のあるディレクトリ。
	 *
	 * [--dry-run]
	 * : 何をするかを表示するだけで、何も書かない (画像も取らない)。
	 *
	 * [--force]
	 * : JSON の name とサイトの名前が違っても続ける。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin import ~/dumps/doujin-profile-import-f_tsf/f_tsf.json --url=https://ftsf.dp.no32.tk --dry-run
	 *     wp doujin import ~/dumps/doujin-profile-import-f_tsf/f_tsf.json --url=https://ftsf.dp.no32.tk
	 */
	public function import( $args, $assoc_args ) {
		$this->require_profile_site();

		$file = $args[0];
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( "ファイルが読めません: {$file}" );
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( 'JSON を読めません: ' . json_last_error_msg() );
		}

		$this->dry    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$this->images = isset( $assoc_args['images'] ) ? rtrim( (string) $assoc_args['images'], '/' ) : dirname( (string) realpath( $file ) );

		$site_name = $this->site_name();
		if ( isset( $data['name'] ) && $data['name'] !== $site_name && ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			WP_CLI::error( sprintf( 'JSON の name「%s」とこのサイト「%s」(%s) が違います。このサイトで合っているなら --force を付けてください。', $data['name'], $site_name, home_url( '/' ) ) );
		}
		if ( $this->dry ) {
			WP_CLI::log( '== dry-run: 何も書きません ==' );
		}

		$this->import_site( $data );

		$works   = isset( $data['works'] ) && is_array( $data['works'] ) ? array_values( $data['works'] ) : array();
		$created = 0;
		$updated = 0;
		$failed  = 0;
		foreach ( $works as $i => $work ) {
			$result = is_array( $work ) ? $this->import_work( $work, $i ) : new WP_Error( 'doujin_profile_bad_work', sprintf( '%d 番目の作品の形が違います。', $i + 1 ) );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( $result->get_error_message() );
				++$failed;
			} elseif ( 'created' === $result ) {
				++$created;
			} else {
				++$updated;
			}
		}

		$summary = sprintf( '作品 %d 件 (作成 %d・更新 %d・失敗 %d)', count( $works ), $created, $updated, $failed );
		WP_CLI::success( $this->dry ? "dry-run: {$summary} の予定。" : "{$summary}。" );
	}

	/**
	 * いまのサイトの内容を交換形式の JSON (spec §6.4) で書き出す。画像は URL。
	 *
	 * ## OPTIONS
	 *
	 * [--out=<file>]
	 * : 書き出す先。省くと標準出力。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin export --url=https://ftsf.dp.no32.tk --out=ftsf.json
	 */
	public function export( $args, $assoc_args ) {
		$this->require_profile_site();

		$links = doujin_profile_sanitize_profile_links( get_option( 'doujin_profile_links', array() ) );
		$data  = array(
			'name'     => $this->site_name(),
			'title'    => array( 'ja' => (string) get_option( 'blogname' ) ),
			'lead'     => array( 'ja' => (string) get_option( 'blogdescription' ) ),
			'mature'   => is_multisite() && (bool) get_site()->mature,
			'theme'    => get_stylesheet(),
			'shops'    => self::export_links( $links['shops'] ),
			'accounts' => self::export_links( $links['accounts'] ),
			'works'    => array(),
		);

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
		foreach ( $posts as $post ) {
			$artists = wp_get_post_terms( $post->ID, 'artist', array( 'fields' => 'names' ) );
			$work    = array(
				'key'           => $post->post_name,
				'status'        => in_array( $post->post_status, array( 'publish', 'future' ), true ) ? 'publish' : 'draft',
				'title'         => array( 'ja' => $post->post_title ),
				'summary'       => array( 'ja' => $post->post_excerpt ),
				'artists'       => is_array( $artists ) ? array_values( $artists ) : array(),
				'release_at'    => (string) get_post_meta( $post->ID, 'doujin_release_at', true ),
				'release_label' => array( 'ja' => (string) get_post_meta( $post->ID, 'doujin_release_label', true ) ),
			);
			$cover   = get_post_thumbnail_id( $post );
			if ( $cover && wp_get_attachment_url( $cover ) ) {
				$work['cover'] = wp_get_attachment_url( $cover );
			}
			$work['links']   = self::export_links( doujin_profile_sanitize_links( get_post_meta( $post->ID, 'doujin_shop_links', true ) ) );
			$work['samples'] = array();
			foreach ( doujin_profile_sanitize_sample_ids( get_post_meta( $post->ID, 'doujin_sample_ids', true ) ) as $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$work['samples'][] = $url;
				}
			}
			$data['works'][] = $work;
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
		if ( isset( $assoc_args['out'] ) ) {
			if ( false === file_put_contents( (string) $assoc_args['out'], $json ) ) {
				WP_CLI::error( "書き出せませんでした: {$assoc_args['out']}" );
			}
			WP_CLI::success( sprintf( '%s に書き出しました (作品 %d 件)。', $assoc_args['out'], count( $posts ) ) );
		} else {
			WP_CLI::line( rtrim( $json, "\n" ) );
		}
	}

	/**
	 * 招待: サイト <名前>.dp.no32.tk を作り、持ち主 (無ければユーザを作る) をその管理者にして、パスワード設定のリンクをメールで送る。
	 *
	 * 人のアカウントを作り、権限・所属を変える操作です。運営者が本人の了承を得てから使ってください。
	 *
	 * 名前の規則はコアの検証 (英小文字と数字、4 文字以上、数字だけは不可) とネットワークの禁止名 (spec §5.2)。
	 * サイトはコアの wpmu_create_blog() で作り、初期設定 (https・初期データの削除・トップページ。includes/init-site.php) は
	 * サイト作成のフックで自動で走る。新しいサイトは「準備中」で始まり、持ち主がマイページの「公開する」で公開する (spec §7.6)。
	 * 持ち主は特権管理者にはしない。既存のユーザを持ち主にするときは --user (ユーザは作らず、メールも送らない)。
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : サイトの名前 (URL の <名前>.dp.no32.tk)。新しいユーザのログイン名にもなる。
	 *
	 * <email>
	 * : 持ち主のメールアドレス。--user のときは登録済みのものを使う (この値は照合だけ。- で省ける)。
	 *
	 * [--title=<title>]
	 * : サークル名 (サイトのタイトル)。省くと名前。
	 *
	 * [--mature]
	 * : 成人向けの印を付ける (年齢確認と rating=adult が出る。T.21)。
	 *
	 * [--user=<login>]
	 * : 既存のユーザを持ち主にする。
	 *
	 * [--dry-run]
	 * : 検査と予定の表示だけで、何も作らない。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin invite example circle@example.com --title="サークル名" --mature --dry-run
	 *     wp doujin invite example circle@example.com --title="サークル名" --mature
	 */
	public function invite( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'Multisite でだけ使えます。' );
		}
		$name   = strtolower( trim( (string) $args[0] ) );
		$email  = trim( (string) $args[1] );
		$title  = isset( $assoc_args['title'] ) && '' !== trim( (string) $assoc_args['title'] ) ? trim( (string) $assoc_args['title'] ) : $name;
		$mature = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'mature', false );
		$dry    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$login  = isset( $assoc_args['user'] ) ? trim( (string) $assoc_args['user'] ) : '';

		// 1. 持ち主。既存なら照合、新規ならコアの検証。
		$user = null;
		if ( '' !== $login ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user ) {
				WP_CLI::error( "ユーザ「{$login}」はいません (--user は既存のログイン名)。新しく作るなら --user を外してください。" );
			}
			if ( '' !== $email && '-' !== $email && 0 !== strcasecmp( $user->user_email, $email ) ) {
				WP_CLI::error( "ユーザ「{$login}」の登録済みメールと、指定のメールが違います。本人のアカウントか確かめてください。" );
			}
		} else {
			$check = wpmu_validate_user_signup( $name, $email );
			if ( $check['errors']->has_errors() ) {
				$hint = username_exists( $name ) ? ' 既にいるユーザをこのサイトの持ち主にするなら --user=' . $name . ' を付けてください。' : '';
				WP_CLI::error( 'ユーザを作れません: ' . wp_strip_all_tags( implode( ' / ', $check['errors']->get_error_messages() ) ) . $hint );
			}
		}

		// 2. サイト名。コアの検証 (禁止名・既存のサイト・ほかのユーザの名前の予約)。
		$check = wpmu_validate_blog_signup( $name, $title, $user ? $user : '' );
		if ( $check['errors']->has_errors() ) {
			WP_CLI::error( 'サイトを作れません: ' . wp_strip_all_tags( implode( ' / ', $check['errors']->get_error_messages() ) ) );
		}
		$domain = $check['domain'];
		$path   = $check['path'];
		$url    = "https://{$domain}{$path}";

		WP_CLI::log( '- サイト: ' . $url . " (タイトル「{$title}」、成人向け " . ( $mature ? 'オン' : 'オフ' ) . '、準備中で始める)' );
		WP_CLI::log( '- 持ち主: ' . ( $user ? "既存のユーザ {$user->user_login} (ID {$user->ID})" : "新しいユーザ {$name} (メール " . self::mask_email( $email ) . ')' ) . ' をこのサイトの administrator に' );
		WP_CLI::log( '- メール: ' . ( $user ? '送らない (既存のユーザ)' : 'パスワード設定のリンク (コアの通知。期限 24 時間)' ) );
		if ( $dry ) {
			WP_CLI::success( 'dry-run: 作れます。何も作っていません。' );
			return;
		}

		// 3. 作る。ユーザ → サイト。サイトで失敗したら、作ったユーザは消す。
		$created_user = false;
		if ( ! $user ) {
			$user_id = wpmu_create_user( $name, wp_generate_password( 24 ), $email ); // ネットワークのユーザ。いまのサイトの権限は持たない。
			if ( ! $user_id ) {
				WP_CLI::error( 'ユーザを作れませんでした。' );
			}
			$user         = get_user_by( 'id', $user_id );
			$created_user = true;
			WP_CLI::log( "ユーザ {$name} (ID {$user_id}) を作りました。" );
		}

		$blog_id = wpmu_create_blog(
			$domain,
			$path,
			$title,
			$user->ID,
			array(
				'public'                          => 1, // 検索エンジンには載せる (所有者の裁定)。準備中の間は準備中のページが noindex。
				'mature'                          => $mature ? 1 : 0,
				DOUJIN_PROFILE_PREPARING_OPTION   => 1, // 最初から準備中 (populate_options で入る)。
			),
			get_current_network_id()
		);
		if ( is_wp_error( $blog_id ) ) {
			if ( $created_user ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
				wpmu_delete_user( $user->ID );
				WP_CLI::log( "作ったユーザ {$name} は消しました。" );
			}
			WP_CLI::error( 'サイトを作れませんでした: ' . wp_strip_all_tags( $blog_id->get_error_message() ) );
		}
		WP_CLI::log( "サイト {$url} (blog_id {$blog_id}) を作り、初期設定を施しました。" );

		// 4. メール (新しいユーザだけ)。
		if ( $created_user ) {
			wp_new_user_notification( $user->ID, null, 'user' );
			WP_CLI::log( 'パスワード設定のリンクをメールで送りました。' );
		}

		WP_CLI::success( "{$url} を準備中で作りました。持ち主 {$user->user_login}。点検: wp doujin doctor --url={$url}" );
	}

	/**
	 * 準備中のサイトを公開する (--unpublish で準備中に戻す)。
	 *
	 * 持ち主はマイページの「公開する」で自分でも公開できる。準備中に戻せるのは運営者 (このコマンド) だけ。
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : サイトの名前 (<名前>.dp.no32.tk)。
	 *
	 * [--unpublish]
	 * : 準備中に戻す。
	 *
	 * ## EXAMPLES
	 *
	 *     wp doujin publish example
	 *     wp doujin publish example --unpublish
	 */
	public function publish( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'Multisite でだけ使えます。' );
		}
		$name  = strtolower( trim( (string) $args[0] ) );
		$sites = get_sites(
			array(
				'domain' => $name . '.' . get_network()->domain,
				'number' => 1,
			)
		);
		if ( ! $sites ) {
			WP_CLI::error( "サイト「{$name}」はありません (wp site list で確かめてください)。" );
		}
		$site = $sites[0];
		if ( is_main_site( $site->blog_id ) ) {
			WP_CLI::error( 'メインサイトには効かせません。' );
		}
		$preparing = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'unpublish', false );

		switch_to_blog( $site->blog_id );
		$current = (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
		if ( $current === $preparing ) {
			WP_CLI::log( "https://{$site->domain}/ はすでに" . ( $preparing ? '準備中' : '公開中' ) . 'です。' );
		} else {
			update_option( DOUJIN_PROFILE_PREPARING_OPTION, $preparing );
		}
		restore_current_blog();
		WP_CLI::success( "https://{$site->domain}/ を" . ( $preparing ? '準備中に戻しました' : '公開しました' ) . '。' );
	}

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
	 *     wp doujin doctor
	 *     wp doujin doctor --url=https://ftsf.dp.no32.tk
	 *     wp doujin doctor --url=https://ftsf.dp.no32.tk --fix
	 */
	public function doctor( $args, $assoc_args ) {
		$this->fix = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fix', false );
		$this->ng  = 0;

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
			$this->info( sprintf( 'プロフィールのサイト %d 件', count( $sites ) ) );
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

		if ( $this->ng ) {
			WP_CLI::warning( sprintf( 'NG %d 件。直し方は各行の → のあと%s。', $this->ng, $this->fix ? '' : ' (--fix で直せるものは [FIX] と付けた)' ) );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( 'NG はありません。' );
	}

	/* ------------------------------------------------------------------ doctor の中身 */

	/**
	 * 本体のファイルとビルド (どのサイトでも同じ)。
	 */
	private function doctor_plugin() {
		$this->check( is_file( WPMU_PLUGIN_DIR . '/doujin-profile.php' ), 'ローダ mu-plugins/doujin-profile.php がある', 'scripts/deploy.sh --go' );
		$this->check( is_file( WPMU_PLUGIN_DIR . '/doujin-profile-discard-original-image.php' ), '元の巨大画像を消す mu-plugin がある (T.51.5)', 'scripts/deploy.sh --go' );
		$this->check( is_readable( DOUJIN_PROFILE_DIR . '/build/my-page.asset.php' ) && is_readable( DOUJIN_PROFILE_DIR . '/build/my-page.js' ), 'マイページのビルド build/my-page.js がある', 'npm run build → コミット → scripts/deploy.sh --go' );
		foreach ( array( 'work-card', 'links', 'more-works' ) as $block ) {
			$dir = DOUJIN_PROFILE_DIR . "/build/blocks/{$block}";
			$ok  = is_readable( "{$dir}/block.json" ) && is_readable( "{$dir}/render.php" ) && is_readable( "{$dir}/index.js" )
				&& WP_Block_Type_Registry::get_instance()->is_registered( "doujin-profile/{$block}" );
			$this->check( $ok, "ブロック doujin-profile/{$block} のビルドがあり、登録されている", 'npm run build → コミット → scripts/deploy.sh --go' );
		}
		$this->check( taxonomy_exists( 'artist' ) && registered_meta_key_exists( 'post', 'doujin_release_at', 'post' ), '分類 artist と作品のメタが登録されている', '本体 (includes/data.php) の読み込みを確かめる' );
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

		$types = preg_split( '/\s+/', trim( (string) get_site_option( 'upload_filetypes' ) ) );
		$bad   = array_diff( $types, array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'svg', 'bmp', 'tif', 'tiff', 'ico' ) );
		$this->check( ! $bad, 'アップロードできる形式は画像だけ' . ( $bad ? ' (画像でない: ' . implode( ' ', $bad ) . ')' : '' ), 'wp network meta update 1 upload_filetypes "jpg jpeg jpe png gif webp avif heic heif"' );
		$space = (int) get_site_option( 'blog_upload_space' );
		$this->check( $space > 0 && ! (int) get_site_option( 'upload_space_check_disabled' ), sprintf( 'サイトあたりの容量の上限が効いている (%d MB)', $space ), 'wp network meta update 1 upload_space_check_disabled 0; wp network meta update 1 blog_upload_space 1024' );
		$illegal = (array) get_site_option( 'illegal_names', array() );
		$this->info( sprintf( '禁止名 %d 語 (%s …)', count( $illegal ), implode( ' ', array_slice( $illegal, 0, 8 ) ) ) );

		$active = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		foreach ( array(
			'disable-comments'              => 'コメントとピンバックを止める (Disable Comments)',
			'disable-xml-rpc'               => 'XML-RPC を止める (Disable XML-RPC)',
			'limit-login-attempts-reloaded' => 'ログイン試行の制限 (Limit Login Attempts Reloaded)',
		) as $slug => $what ) {
			$on = (bool) array_filter( $active, static fn( $file ) => str_starts_with( $file, $slug . '/' ) );
			$this->check( $on, "{$what} がネットワークで有効", "wp plugin install {$slug} --activate-network" );
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
	 * @param WP_Site|null $site サイト (単一サイトなら null)。
	 */
	private function doctor_site( $site ) {
		$url = $site ? "https://{$site->domain}{$site->path}" : home_url( '/' );
		$fix_init = "wp doujin init-site --url={$url}";

		// URL。
		foreach ( array( 'home', 'siteurl' ) as $name ) {
			$value = (string) get_option( $name );
			$ok    = str_starts_with( $value, 'https://' ) && ( ! $site || wp_parse_url( $value, PHP_URL_HOST ) === $site->domain );
			$this->check( $ok, "{$name} が https でサイトのドメインと一致 ({$value})", $fix_init . ' (https にする) / ドメインが違うなら wp option update ' . $name );
		}

		// 状態。
		if ( $site ) {
			$preparing = (bool) get_option( DOUJIN_PROFILE_PREPARING_OPTION, false );
			$this->info( $preparing ? '準備中 (ログインしていない人には「準備中です」だけ。公開は持ち主のマイページか wp doujin publish ' . strtok( $site->domain, '.' ) . ')' : '公開中' );
			$this->info( '成人向けの印: ' . ( (int) $site->mature ? 'オン (年齢確認と rating=adult が出る)' : 'オフ (年齢確認は出ない。付けるなら wp site mature ' . $site->blog_id . ')' ) );
			$this->check( ! (int) $site->archived && ! (int) $site->spam && ! (int) $site->deleted, 'アーカイブ・スパム・削除の印が無い', "wp site unarchive/unspam {$site->blog_id}" );
		}
		$this->check( 1 === (int) get_option( 'blog_public' ), '検索エンジンに載せる (blog_public = 1。所有者の裁定「広報なんだから載せろ」)', "wp option update blog_public 1 --url={$url}" );

		// 初期設定。
		$this->check( '' !== (string) get_option( 'timezone_string' ) || 0.0 !== (float) get_option( 'gmt_offset' ), 'タイムゾーンが設定されている (' . ( get_option( 'timezone_string' ) ?: 'UTC' . get_option( 'gmt_offset' ) ) . ')', $fix_init );
		$structure = (string) get_option( 'permalink_structure' );
		$this->check( str_contains( $structure, '%postname%' ), "パーマリンクに %postname% が入っている ({$structure})", $fix_init . " / wp rewrite structure '/%postname%/' --url={$url}" );
		$rules     = get_option( 'rewrite_rules' );
		$has_rules = is_array( $rules ) && array_filter( array_keys( $rules ), static fn( $key ) => str_starts_with( (string) $key, 'artist/' ) );
		$this->check(
			(bool) $has_rules,
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
			$percent = $allowed > 0 ? $used / $allowed * 100 : 0;
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
			$resave  = static fn() => wp_update_post( array( 'ID' => $post->ID ) ); // 保存し直すだけ。公開日時は保存時のルール (includes/data.php) が決める。
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

		$this->info( sprintf(
			'作品 %d 件 (公開 %d・予約 %d・下書き %d・予告 %d・書影なし %d)%s',
			count( $posts ),
			$counts['publish'] ?? 0,
			$counts['future'] ?? 0,
			( $counts['draft'] ?? 0 ) + ( $counts['pending'] ?? 0 ) + ( $counts['private'] ?? 0 ),
			$upcoming,
			$no_cover,
			$upcoming ? '。予告の公開日時は最後に保存した時刻 (予告どうしの並びは保存順。spec §6.0)' : ''
		) );
	}

	/**
	 * 点検 1 件。NG なら直し方を添え、--fix と直す処理があれば直す。
	 *
	 * @param bool          $ok    合格か。
	 * @param string        $what  見たこと。
	 * @param string        $fix   直し方 (人向け)。
	 * @param callable|null $fixer --fix で行う直し (安全なものだけ)。
	 */
	private function check( $ok, $what, $fix = '', $fixer = null ) {
		if ( $ok ) {
			WP_CLI::log( "[OK] {$what}" );
			return;
		}
		if ( $fixer && $this->fix ) {
			$fixer();
			WP_CLI::log( "[FIXED] {$what} → 直した" );
			return;
		}
		++$this->ng;
		WP_CLI::log( "[NG] {$what}" . ( '' !== $fix ? " → {$fix}" : '' ) . ( $fixer ? ' [FIX]' : '' ) );
	}

	/**
	 * 判定のない情報 1 行。
	 *
	 * @param string $what 文。
	 */
	private function info( $what ) {
		WP_CLI::log( "[--] {$what}" );
	}

	/* ------------------------------------------------------------------ 内部 */

	/**
	 * メールアドレスをログ用に伏せる (先頭 1 文字とドメインだけ)。
	 *
	 * @param string $email メールアドレス。
	 * @return string
	 */
	private static function mask_email( $email ) {
		$at = strpos( $email, '@' );

		return false === $at ? '***' : mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * メインサイトでは止める (プロフィールのサイトで使うコマンド)。
	 */
	private function require_profile_site() {
		if ( is_multisite() && is_main_site() ) {
			WP_CLI::error( 'このコマンドはプロフィールのサイトで使います。--url=https://<名前>.dp.no32.tk を付けてください (メインサイトには効かせません)。' );
		}
	}

	/**
	 * サイトの名前 (サブドメインの最初のラベル。例 ftsf)。
	 *
	 * @return string
	 */
	private function site_name() {
		$domain = is_multisite() ? get_site()->domain : (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return (string) strtok( $domain, '.' );
	}

	/**
	 * 新しく作る投稿の作者: いまのユーザ (--user)、無ければサイトの管理者 (最初の 1 人)。
	 *
	 * @return int
	 */
	private function author_id() {
		if ( get_current_user_id() ) {
			return get_current_user_id();
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);

		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * ログ 1 行 (dry-run なら印を付ける)。
	 *
	 * @param string $line 文。
	 */
	private function log( $line ) {
		WP_CLI::log( ( $this->dry ? '[dry-run] ' : '' ) . $line );
	}

	/**
	 * 日本語の文を取り出す。文字列ならそのまま、{ "ja": …, "en": … } なら ja (en は読み飛ばす)。
	 *
	 * @param mixed $value JSON の値。
	 * @return string
	 */
	private static function ja( $value ) {
		if ( is_array( $value ) ) {
			$value = isset( $value['ja'] ) ? $value['ja'] : '';
		}

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * リンクの一覧を JSON の形から正規化する (label の {ja,en} を文字列に。検査はデータ層のサニタイズ)。
	 *
	 * @param mixed $list JSON の値。
	 * @return array
	 */
	private static function links( $list ) {
		$links = array();
		foreach ( is_array( $list ) ? $list : array() as $link ) {
			if ( is_array( $link ) ) {
				$link['label'] = self::ja( isset( $link['label'] ) ? $link['label'] : '' );
				$links[]       = $link;
			}
		}

		return doujin_profile_sanitize_links( $links );
	}

	/**
	 * リンクの一覧を JSON の形にする (label は {ja}、sponsored は true のときだけ)。
	 *
	 * @param array $links 正規化済みのリンク。
	 * @return array
	 */
	private static function export_links( $links ) {
		$out = array();
		foreach ( $links as $link ) {
			$item = array(
				'service' => $link['service'],
				'url'     => $link['url'],
			);
			if ( '' !== $link['label'] ) {
				$item['label'] = array( 'ja' => $link['label'] );
			}
			if ( $link['sponsored'] ) {
				$item['sponsored'] = true;
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * 設定を 1 つ入れる (同じ値なら何もしない)。
	 *
	 * @param string $name  設定名。
	 * @param mixed  $value 値。
	 * @param string $label ログに出す名前。
	 */
	private function set_option( $name, $value, $label ) {
		if ( get_option( $name ) === $value ) {
			return;
		}
		$this->log( "{$label} を設定" );
		if ( ! $this->dry ) {
			update_option( $name, $value );
		}
	}

	/**
	 * サイトの項目: サークル名・リード文・リンク集・成人向けの印・テーマ。
	 *
	 * @param array $data JSON 全体。
	 */
	private function import_site( $data ) {
		$title = self::ja( isset( $data['title'] ) ? $data['title'] : null );
		if ( '' !== $title ) {
			$this->set_option( 'blogname', $title, "サークル名「{$title}」" );
		}
		if ( array_key_exists( 'lead', $data ) ) {
			$this->set_option( 'blogdescription', self::ja( $data['lead'] ), 'リード文' );
		}

		if ( array_key_exists( 'shops', $data ) || array_key_exists( 'accounts', $data ) ) {
			$current = doujin_profile_sanitize_profile_links( get_option( 'doujin_profile_links', array() ) );
			$links   = doujin_profile_sanitize_profile_links(
				array(
					'shops'    => array_key_exists( 'shops', $data ) ? self::links( $data['shops'] ) : $current['shops'],
					'accounts' => array_key_exists( 'accounts', $data ) ? self::links( $data['accounts'] ) : $current['accounts'],
				)
			);
			$this->set_option( 'doujin_profile_links', $links, sprintf( 'リンク集 (販売ページ %d 件・お知らせ %d 件)', count( $links['shops'] ), count( $links['accounts'] ) ) );
		}

		if ( array_key_exists( 'mature', $data ) && is_multisite() ) {
			$mature = empty( $data['mature'] ) ? 0 : 1;
			if ( (int) get_site()->mature !== $mature ) {
				$this->log( '成人向けの印を ' . ( $mature ? 'オン' : 'オフ' ) . ' に' );
				if ( ! $this->dry ) {
					update_blog_status( get_current_blog_id(), 'mature', $mature );
				}
			}
		}

		if ( ! empty( $data['theme'] ) && is_string( $data['theme'] ) && get_stylesheet() !== $data['theme'] ) {
			$theme = wp_get_theme( $data['theme'] );
			if ( ! $theme->exists() || ! $theme->is_allowed() ) {
				WP_CLI::warning( sprintf( 'テーマ %s はこのサイトで使えません (無いか、ネットワークで有効化されていない)。いまのテーマ %s のままにします。', $data['theme'], get_stylesheet() ) );
			} else {
				$this->log( "テーマを {$data['theme']} に" );
				if ( ! $this->dry ) {
					switch_theme( $theme->get_stylesheet() );
				}
			}
		}
	}

	/**
	 * 作品 1 件。slug で照合して作る・更新する。
	 *
	 * @param array $work  JSON の作品。
	 * @param int   $index 何番目か (0 から。ログ用)。
	 * @return string|WP_Error 'created' | 'updated'。
	 */
	private function import_work( $work, $index ) {
		$title = self::ja( isset( $work['title'] ) ? $work['title'] : null );
		$key   = self::work_key( $work, $title );
		if ( '' === $key ) {
			return new WP_Error( 'doujin_profile_no_key', sprintf( '%d 番目の作品: key もタイトルも無いので入れられません。', $index + 1 ) );
		}
		$label = sprintf( '作品「%s」(%s)', '' !== $title ? $title : $key, $key );

		$existing = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'name'           => $key,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$post_id  = $existing ? (int) $existing[0] : 0;

		$status  = isset( $work['status'] ) && 'publish' === $work['status'] ? 'publish' : 'draft';
		$postarr = array(
			'post_type'   => 'post',
			'post_name'   => $key,
			'post_status' => $status,
			'meta_input'  => array(),
		);
		if ( '' !== $title ) {
			$postarr['post_title'] = $title;
		}
		if ( array_key_exists( 'summary', $work ) ) {
			$postarr['post_excerpt'] = self::ja( $work['summary'] );
		}
		if ( array_key_exists( 'release_at', $work ) ) {
			$release_at = is_string( $work['release_at'] ) ? trim( $work['release_at'] ) : '';
			if ( '' !== $release_at && '' === doujin_profile_sanitize_release_at( $release_at ) ) {
				WP_CLI::warning( "{$label}: 発売日時「{$release_at}」が読めないので空にします。" );
			}
			$postarr['meta_input']['doujin_release_at'] = $release_at;
		}
		if ( array_key_exists( 'release_label', $work ) ) {
			$postarr['meta_input']['doujin_release_label'] = self::ja( $work['release_label'] );
		}
		if ( array_key_exists( 'links', $work ) ) {
			$postarr['meta_input']['doujin_shop_links'] = self::links( $work['links'] );
		}
		if ( ! $post_id ) {
			$postarr['post_content'] = '<!-- wp:doujin-profile/work-card /-->'; // 本文に作品カードを 1 つ (spec §6.0。マイページと同じ)。
			$postarr['post_author']  = $this->author_id();
		}

		$verb = $post_id ? '更新' : '作成';
		$this->log( "{$label} を{$verb} (状態 {$status}" . ( $post_id ? "、ID {$post_id}" : '' ) . ')' );
		if ( ! $this->dry ) {
			$postarr = wp_slash( $postarr );
			$result  = $post_id ? wp_update_post( array( 'ID' => $post_id ) + $postarr, true ) : wp_insert_post( $postarr, true );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'doujin_profile_save_failed', "{$label}: 保存できませんでした: " . $result->get_error_message() );
			}
			$post_id = (int) $result;
		}

		if ( array_key_exists( 'artists', $work ) ) {
			$artists = array_values( array_filter( array_map( 'strval', (array) $work['artists'] ), 'strlen' ) );
			$this->log( '  作家: ' . ( $artists ? implode( '、', $artists ) : '(なし)' ) );
			if ( ! $this->dry ) {
				wp_set_object_terms( $post_id, $artists, 'artist' );
			}
		}

		$image_title = '' !== $title ? $title : $key;
		if ( ! empty( $work['cover'] ) ) {
			$cover_id = $this->image( $work['cover'], $post_id, $image_title, $key, '書影' );
			if ( is_wp_error( $cover_id ) ) {
				WP_CLI::warning( "{$label}: 書影: " . $cover_id->get_error_message() );
			} elseif ( $cover_id && (int) get_post_thumbnail_id( $post_id ) !== $cover_id ) {
				set_post_thumbnail( $post_id, $cover_id );
			}
		}

		if ( array_key_exists( 'samples', $work ) ) {
			$ids = $this->samples( $work['samples'], $post_id, $image_title, $key, $label );
			if ( ! $this->dry ) {
				update_post_meta( $post_id, 'doujin_sample_ids', $ids );
			}
		}

		return '更新' === $verb ? 'updated' : 'created';
	}

	/**
	 * 作品の slug: key → 書影の作品番号 → タイトル。
	 *
	 * @param array  $work  JSON の作品。
	 * @param string $title 日本語のタイトル。
	 * @return string 決められなければ空。
	 */
	private static function work_key( $work, $title ) {
		if ( ! empty( $work['key'] ) && is_string( $work['key'] ) ) {
			$key = $work['key'];
		} elseif ( isset( $work['cover']['id'] ) && is_string( $work['cover']['id'] ) ) {
			$key = $work['cover']['id'];
		} else {
			$key = $title;
		}

		return sanitize_title( $key );
	}

	/**
	 * 画像 1 枚を添付にする。作品番号 ({service,id}) / https の URL / JSON からの相対の道筋。
	 *
	 * @param mixed  $spec    JSON の値。
	 * @param int    $post_id 作品 (dry-run で新規なら 0)。
	 * @param string $title   添付の題名。
	 * @param string $key     作品の slug (メディアのファイル名の頭に付ける)。
	 * @param string $what    ログに出す名前 (空ならログを出さない)。
	 * @return int|WP_Error 添付 ID (dry-run では 0)。
	 */
	private function image( $spec, $post_id, $title, $key, $what ) {
		if ( is_array( $spec ) && isset( $spec['id'] ) ) {
			$product = doujin_profile_parse_product_id( (string) $spec['id'] );
			if ( ! $product ) {
				return new WP_Error( 'doujin_profile_bad_product_id', sprintf( '作品番号「%s」の形が違います。', (string) $spec['id'] ) );
			}
			if ( '' !== $what ) {
				$this->log( "  {$what}: 作品番号 {$product['id']} ({$product['service']}) から取り込む" );
			}
			if ( $this->dry ) {
				return 0;
			}
			$source = doujin_profile_source( $product );

			return is_wp_error( $source ) ? $source : doujin_profile_import_image( $source['image_url'], $post_id, $title );
		}

		if ( ! is_string( $spec ) || '' === trim( $spec ) ) {
			return new WP_Error( 'doujin_profile_bad_image', '画像の書き方が違います (作品番号 {"service","id"}・https の URL・道筋 のどれか)。' );
		}
		$spec = trim( $spec );

		if ( str_starts_with( $spec, 'https://' ) ) {
			$own = (int) attachment_url_to_postid( $spec ); // export したものを同じサイトに戻すときは、取り直さずその添付を使う。
			if ( '' !== $what ) {
				$this->log( "  {$what}: " . ( $own ? "このサイトの添付 {$own}" : "URL {$spec} から取り込む" ) );
			}
			if ( $this->dry ) {
				return 0;
			}

			return $own ? $own : doujin_profile_import_image( $spec, $post_id, $title );
		}

		$path = str_starts_with( $spec, '/' ) ? $spec : $this->images . '/' . $spec;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'doujin_profile_file_missing', "ファイルが読めません: {$path}" );
		}
		if ( '' !== $what ) {
			$this->log( "  {$what}: ファイル {$spec}" );
		}
		if ( $this->dry ) {
			return 0;
		}

		return doujin_profile_import_file( $path, $post_id, $title, $spec, $key . '-' . wp_basename( $spec ) );
	}

	/**
	 * サンプル画像。作品番号なら全部、配列なら 1 枚ずつ。
	 *
	 * @param mixed  $spec    JSON の値。
	 * @param int    $post_id 作品。
	 * @param string $title   作品の題名 (添付の題名「<題名> サンプル 01」に使う)。
	 * @param string $key     作品の slug。
	 * @param string $label   ログに出す作品の名前。
	 * @return int[] 添付 ID の並び (dry-run では空)。
	 */
	private function samples( $spec, $post_id, $title, $key, $label ) {
		$ids = array();

		if ( is_array( $spec ) && isset( $spec['id'] ) ) {
			$product = doujin_profile_parse_product_id( (string) $spec['id'] );
			if ( ! $product ) {
				WP_CLI::warning( sprintf( '%s: サンプル: 作品番号「%s」の形が違います。', $label, (string) $spec['id'] ) );
				return $ids;
			}
			$this->log( "  サンプル: 作品番号 {$product['id']} ({$product['service']}) から全部取り込む" );
			if ( $this->dry ) {
				return $ids;
			}
			$source = doujin_profile_source( $product );
			$urls   = is_wp_error( $source ) ? $source : doujin_profile_sample_urls( $product, $source );
			if ( is_wp_error( $urls ) ) {
				WP_CLI::warning( "{$label}: サンプル: " . $urls->get_error_message() );
				return $ids;
			}
			foreach ( $urls as $i => $url ) {
				$id = doujin_profile_import_image( $url, $post_id, sprintf( '%s サンプル %02d', $title, $i + 1 ) );
				if ( is_wp_error( $id ) ) {
					WP_CLI::warning( sprintf( '%s: サンプル %d 枚目: %s', $label, $i + 1, $id->get_error_message() ) );
				} else {
					$ids[] = $id;
				}
			}
			$this->log( sprintf( '  サンプル: %d 枚', count( $ids ) ) );

			return $ids;
		}

		$items = is_array( $spec ) ? array_values( $spec ) : array();
		foreach ( $items as $i => $item ) {
			$id = $this->image( $item, $post_id, sprintf( '%s サンプル %02d', $title, $i + 1 ), $key, '' );
			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( sprintf( '%s: サンプル %d 枚目: %s', $label, $i + 1, $id->get_error_message() ) );
			} elseif ( $id ) {
				$ids[] = $id;
			}
		}
		$this->log( sprintf( '  サンプル: %d 件%s', count( $items ), $items ? ' (' . self::describe( $items[0] ) . ( count( $items ) > 1 ? ' … ' . self::describe( end( $items ) ) : '' ) . ')' : '' ) );

		return $ids;
	}

	/**
	 * 画像の指定をログ用に短く。
	 *
	 * @param mixed $spec JSON の値。
	 * @return string
	 */
	private static function describe( $spec ) {
		if ( is_array( $spec ) ) {
			return isset( $spec['id'] ) ? '作品番号 ' . $spec['id'] : '(形が違う)';
		}

		return is_string( $spec ) ? $spec : '(形が違う)';
	}
}

WP_CLI::add_command( 'doujin', 'Doujin_Profile_CLI' );
