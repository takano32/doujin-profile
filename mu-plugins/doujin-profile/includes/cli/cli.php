<?php
/**
 * WP-CLI (spec §8.5。T.32.9)。運営者の道具。コア標準のコマンドで足りるものは作らない。
 *
 *   wp dp init-site --url=<サイト>                                        新しいサイトの初期設定を、作成済みのサイトに施す (includes/cli/init-site.php)
 *   wp dp import <file.json> --url=<サイト> [--images=<dir>] [--dry-run]  交換形式 (spec §6.4) から入れる (includes/cli/import.php)
 *   wp dp export --url=<サイト> [--out=<file>]                            同じ形で書き出す (画像は URL) (includes/cli/export.php)
 *   wp dp invite <名前> <メール> [--title=] [--mature] [--user=] [--dry-run] 招待: サイトと持ち主を作り、準備中で始める (spec §7.4) (includes/cli/invite.php)
 *   wp dp publish <名前> [--unpublish]                                    準備中を解く / 戻す (spec §7.6。持ち主はマイページでも公開できる) (includes/cli/publish.php)
 *   wp dp doctor [--url=<サイト>] [--fix]                                 点検 (読み取り。--fix は安全な 3 つだけ直す) (includes/cli/doctor.php)
 *
 * 親の「doujin」は説明だけの入れ物 (includes/cli/command.php) で、子は 1 つずつ別のクラス (__invoke) として登録する。
 * 交換形式の決まり (lib/Cli/Exchange.php)・点検の記録と判定 (lib/Cli/DoctorReport.php・DoctorRules.php) は WordPress を読まない純粋なロジック。
 * 書き込みはすべて標準の経路 (wp_insert_post の meta_input、wp_set_object_terms、update_option) で、
 * 検査は register_post_meta() / register_setting() のサニタイズに任せる。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/command.php';
require_once __DIR__ . '/init-site.php';
require_once __DIR__ . '/import.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/invite.php';
require_once __DIR__ . '/publish.php';
require_once __DIR__ . '/doctor.php';

WP_CLI::add_command( 'dp', 'DoujinProfile\Commands\Command' );
WP_CLI::add_command( 'dp init-site', 'DoujinProfile\Commands\InitSite' );
WP_CLI::add_command( 'dp import', 'DoujinProfile\Commands\Import' );
WP_CLI::add_command( 'dp export', 'DoujinProfile\Commands\Export' );
WP_CLI::add_command( 'dp invite', 'DoujinProfile\Commands\Invite' );
WP_CLI::add_command( 'dp publish', 'DoujinProfile\Commands\Publish' );
WP_CLI::add_command( 'dp doctor', 'DoujinProfile\Commands\Doctor' );
