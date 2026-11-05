<?php
/**
 * wp dp import: 交換形式の JSON (spec §6.4) から、サークル名・リード文・リンク集・作品を入れる。
 *
 * 照合: 作品は slug (JSON の key。無ければ書影の作品番号、それも無ければタイトル) で探し、あれば更新・無ければ作る。
 * 画像は出どころ (作品番号の URL、https の URL、JSON に書かれた道筋) を添付のメタ _doujin_profile_source_url に持つので、
 * 同じ JSON を 2 回入れても二重にならない (includes/import/media.php)。英語 (en) は読み飛ばす (spec §6.0)。
 * 形の決まり (ja・key・リンク・画像の指定) は lib/Cli/Exchange.php。
 */

namespace DoujinProfile\Commands;

use DoujinProfile\Cli\Exchange;
use DoujinProfile\Import\ProductId;
use WP_CLI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import {

	/** @var bool dry-run か。 */
	private $dry = false;

	/** @var string 画像の相対の道筋の起点。 */
	private $images = '';

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
	 *     wp dp import ~/dumps/doujin-profile-import-f_tsf/f_tsf.json --url=https://ftsf.dp.no32.tk --dry-run
	 *     wp dp import ~/dumps/doujin-profile-import-f_tsf/f_tsf.json --url=https://ftsf.dp.no32.tk
	 */
	public function __invoke( $args, $assoc_args ) {
		doujin_profile_cli_require_profile_site();

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

		$site_name = doujin_profile_cli_site_name();
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
	 * ログ 1 行 (dry-run なら印を付ける)。
	 *
	 * @param string $line 文。
	 */
	private function log( $line ) {
		WP_CLI::log( ( $this->dry ? '[dry-run] ' : '' ) . $line );
	}

	/**
	 * リンクの一覧を JSON の形から正規化する (label の {ja,en} を文字列に。検査はデータ層のサニタイズ)。
	 *
	 * @param mixed $list JSON の値。
	 * @return array
	 */
	private static function links( $list ) {
		return doujin_profile_sanitize_links( Exchange::linksFromJson( $list ) );
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
		$title = Exchange::ja( isset( $data['title'] ) ? $data['title'] : null );
		if ( '' !== $title ) {
			$this->set_option( 'blogname', $title, "サークル名「{$title}」" );
		}
		if ( array_key_exists( 'lead', $data ) ) {
			$this->set_option( 'blogdescription', Exchange::ja( $data['lead'] ), 'リード文' );
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
		$title = Exchange::ja( isset( $work['title'] ) ? $work['title'] : null );
		$key   = Exchange::workKey( $work, $title, 'sanitize_title' );
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

		$status  = Exchange::importStatus( $work );
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
			$postarr['post_excerpt'] = Exchange::ja( $work['summary'] );
		}
		if ( array_key_exists( 'release_at', $work ) ) {
			$release_at = is_string( $work['release_at'] ) ? trim( $work['release_at'] ) : '';
			if ( '' !== $release_at && '' === doujin_profile_sanitize_release_at( $release_at ) ) {
				WP_CLI::warning( "{$label}: 発売日時「{$release_at}」が読めないので空にします。" );
			}
			$postarr['meta_input']['doujin_release_at'] = $release_at;
		}
		if ( array_key_exists( 'release_label', $work ) ) {
			$postarr['meta_input']['doujin_release_label'] = Exchange::ja( $work['release_label'] );
		}
		if ( array_key_exists( 'links', $work ) ) {
			$postarr['meta_input']['doujin_shop_links'] = self::links( $work['links'] );
		}
		if ( ! $post_id ) {
			$postarr['post_content'] = '<!-- wp:doujin-profile/work-card /-->'; // 本文に作品カードを 1 つ (spec §6.0。マイページと同じ)。
			$postarr['post_author']  = doujin_profile_cli_author_id();
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
		$kind = Exchange::imageKind( $spec );

		if ( Exchange::IMAGE_PRODUCT === $kind ) {
			$product = ProductId::parse( (string) $spec['id'] );
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

		if ( Exchange::IMAGE_BAD === $kind ) {
			return new WP_Error( 'doujin_profile_bad_image', '画像の書き方が違います (作品番号 {"service","id"}・https の URL・道筋 のどれか)。' );
		}
		$spec = trim( $spec );

		if ( Exchange::IMAGE_URL === $kind ) {
			$own = (int) attachment_url_to_postid( $spec ); // export したものを同じサイトに戻すときは、取り直さずその添付を使う。
			if ( '' !== $what ) {
				$this->log( "  {$what}: " . ( $own ? "このサイトの添付 {$own}" : "URL {$spec} から取り込む" ) );
			}
			if ( $this->dry ) {
				return 0;
			}

			return $own ? $own : doujin_profile_import_image( $spec, $post_id, $title );
		}

		$path = Exchange::resolvePath( $spec, $this->images );
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

		if ( Exchange::IMAGE_PRODUCT === Exchange::imageKind( $spec ) ) {
			$product = ProductId::parse( (string) $spec['id'] );
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
		$this->log( Exchange::describeSamples( $items ) );

		return $ids;
	}
}
