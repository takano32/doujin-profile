<?php
/**
 * doujin-profile 本体。mu-plugins/doujin-profile.php (ローダ) から読み込まれる。
 *
 * 構成 (spec §12):
 *   includes/data.php            作品のデータ層 (分類 artist・投稿メタ・サイト設定・保存時の 1 ルール)
 *   includes/search.php          サイト内検索で作家名でも当たる手当て
 *   includes/login-redirect.php  ログイン後にマイページへ直行
 *   includes/my-page.php         マイページの入れ物 (メニュー・スクリプトの読み込み)
 *   includes/cover-import.php    書影・サンプルの取り込み (作品番号から。唯一の独自 REST)
 *   includes/blocks.php          公開側の独自ブロックの登録と、描き方の共用関数 (ブロックの定義は src/blocks/)
 *   includes/patterns.php        パターン (プロフィールの見出し・トップページ)
 *   includes/init-site.php       新しいサイトの初期設定 (wp_initialize_site。https・初期データの削除・トップページ)
 *   includes/cli.php             WP-CLI (wp doujin init-site / import / export)
 *   src/                         JS のソース。build/ に手元でビルドしてコミットする (サーバに node は無い)
 *
 * Multisite ではネットワーク内の全サイトで読み込まれる。登録はどれもサイト単位 (init で毎回)。
 * 有効化フックは無いので、登録以外の初期化は持たない (サイト単位の初期設定はサイト作成時のフックで)。PHP 8.0 互換で書く。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DOUJIN_PROFILE_VERSION', '0.1.0' );
define( 'DOUJIN_PROFILE_FILE', __FILE__ );
define( 'DOUJIN_PROFILE_DIR', __DIR__ );

require_once DOUJIN_PROFILE_DIR . '/includes/data.php';
require_once DOUJIN_PROFILE_DIR . '/includes/search.php';
require_once DOUJIN_PROFILE_DIR . '/includes/login-redirect.php';
require_once DOUJIN_PROFILE_DIR . '/includes/my-page.php';
require_once DOUJIN_PROFILE_DIR . '/includes/cover-import.php';
require_once DOUJIN_PROFILE_DIR . '/includes/blocks.php';
require_once DOUJIN_PROFILE_DIR . '/includes/patterns.php';
require_once DOUJIN_PROFILE_DIR . '/includes/init-site.php';
require_once DOUJIN_PROFILE_DIR . '/includes/cli.php';
