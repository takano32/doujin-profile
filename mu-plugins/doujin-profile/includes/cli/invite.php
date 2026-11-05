<?php
/**
 * wp dp invite: 招待。サイトと持ち主を作り、準備中で始める (spec §7.4)。
 *
 * コアの検証 (wpmu_validate_user_signup / wpmu_validate_blog_signup) と作成 (wpmu_create_user / wpmu_create_blog) に乗る。
 * wpmu_create_blog → wp_initialize_site で includes/init-site.php の初期設定が自動で走るので、ここでは呼ばない (二重にしない)。
 * メールはコアの wp_new_user_notification() (パスワード設定のリンク。文面の作り込みは T33)。
 */

namespace DoujinProfile\Commands;

use DoujinProfile\Cli\EmailMask;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Invite {

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
	 * : 成人向けの印を付ける (年齢確認と rating=adult が出る。T21)。
	 *
	 * [--user=<login>]
	 * : 既存のユーザを持ち主にする。
	 *
	 * [--dry-run]
	 * : 検査と予定の表示だけで、何も作らない。
	 *
	 * ## EXAMPLES
	 *
	 *     wp dp invite example circle@example.com --title="サークル名" --mature --dry-run
	 *     wp dp invite example circle@example.com --title="サークル名" --mature
	 */
	public function __invoke( $args, $assoc_args ) {
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
				WP_CLI::error( 'ユーザを作れません: ' . doujin_profile_cli_error_text( $check['errors'] ) . $hint );
			}
		}

		// 2. サイト名。コアの検証 (禁止名・既存のサイト・ほかのユーザの名前の予約)。
		$check = wpmu_validate_blog_signup( $name, $title, $user ? $user : '' );
		if ( $check['errors']->has_errors() ) {
			WP_CLI::error( 'サイトを作れません: ' . doujin_profile_cli_error_text( $check['errors'] ) );
		}
		$domain = $check['domain'];
		$path   = $check['path'];
		$url    = "https://{$domain}{$path}";

		WP_CLI::log( '- サイト: ' . $url . " (タイトル「{$title}」、成人向け " . ( $mature ? 'オン' : 'オフ' ) . '、準備中で始める)' );
		WP_CLI::log( '- 持ち主: ' . ( $user ? "既存のユーザ {$user->user_login} (ID {$user->ID})" : "新しいユーザ {$name} (メール " . EmailMask::mask( $email ) . ')' ) . ' をこのサイトの administrator に' );
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
				'public'                        => 1, // 検索エンジンには載せる (所有者の裁定)。準備中の間は準備中のページが noindex。
				'mature'                        => $mature ? 1 : 0,
				DOUJIN_PROFILE_PREPARING_OPTION => 1, // 最初から準備中 (populate_options で入る)。
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

		WP_CLI::success( "{$url} を準備中で作りました。持ち主 {$user->user_login}。点検: wp dp doctor --url={$url}" );
	}
}
