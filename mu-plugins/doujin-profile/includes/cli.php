<?php
/**
 * WP-CLI (spec §8.5。T.32.9)。運営者の道具。コア標準のコマンドで足りるものは作らない。
 *
 *   wp doujin init-site --url=<サイト>                                        新しいサイトの初期設定を、作成済みのサイトに施す (includes/init-site.php)
 *   wp doujin import <file.json> --url=<サイト> [--images=<dir>] [--dry-run]  交換形式 (spec §6.4) から入れる
 *   wp doujin export --url=<サイト> [--out=<file>]                            同じ形で書き出す (画像は URL)
 *
 * invite・publish・doctor はまだ無い (T.32.9.1・T.32.9.3・T.32.9.4)。
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

	/* ------------------------------------------------------------------ 内部 */

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
