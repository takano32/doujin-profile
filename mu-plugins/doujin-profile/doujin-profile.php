<?php
/**
 * doujin-profile 本体。mu-plugins/doujin-profile.php (ローダ) から読み込まれる。
 *
 * 構成 (spec §12)。lib/ は WordPress を読まない純粋なロジック (tests/php の PHPUnit で試験)、includes/ はその糊 (フック・REST・WP-CLI):
 *   lib/Autoloader.php                lib/ のクラスの読み込み (名前空間 DoujinProfile\。Composer は使わない)
 *   lib/Failure.php                   失敗の値 (糊が WP_Error にする。includes/failure.php)
 *   lib/Data/                         サービス種別・リンクの正規化・サンプル ID・発売日時 (公開日時のルール)・発売日の表記
 *   lib/Import/                       作品番号・FANZA と DLsite の URL と JSON の解釈・サンプルの URL・画像の応答の判定・添付の題名
 *   lib/Blocks/LinkLabel.php          リンクボタンの表示名と rel
 *   lib/Head/MetaTags.php             head の meta の組み立て
 *   lib/Login/Redirect.php            ログイン後の送り先の判定
 *   lib/Search/ArtistSearch.php       作家名の検索の SQL
 *   lib/Cli/                          交換形式の決まり・メールの伏せ字・点検の記録と判定
 *   includes/data/                    作品のデータ層 (分類 artist・投稿メタ・サイト設定・保存時の 1 ルール・正規化の橋)
 *   includes/search.php               サイト内検索で作家名でも当たる手当て
 *   includes/login-redirect.php       ログイン後にマイページへ直行
 *   includes/my-page/                 マイページの入れ物 (メニュー・スクリプトの読み込み)
 *   includes/import/                  書影・サンプルの取り込み (REST の入口・作品情報の取り方・メディアへの保存。唯一の独自 REST)
 *   includes/blocks/                  公開側の独自ブロックの登録と、render.php が共用する描き方 (ブロックの定義は src/blocks/)
 *   includes/patterns.php             パターン (プロフィールの見出し・トップページ)
 *   includes/init-site.php            新しいサイトの初期設定 (wp_initialize_site。https・初期データの削除・トップページ)
 *   includes/preparing.php            準備中 (設定 doujin_profile_preparing。編集できない訪問者には「準備中です」だけ)
 *   includes/cli/                     WP-CLI (wp dp init-site / import / export / invite / publish / doctor。子コマンドごとに 1 ファイル)
 *   includes/age-gate.php             年齢確認 (成人向けの印が付いたサイトだけ)
 *   includes/head.php                 head の説明文・OGP・Twitter カード (テーマに依存しない)
 *   src/                              JS のソース。build/ に手元でビルドしてコミットする (サーバに node は無い)。純粋なロジックは src/∗/lib/ (jest で試験)
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

require_once DOUJIN_PROFILE_DIR . '/lib/Autoloader.php';
DoujinProfile\Autoloader::register( DOUJIN_PROFILE_DIR . '/lib' );

require_once DOUJIN_PROFILE_DIR . '/includes/failure.php';
require_once DOUJIN_PROFILE_DIR . '/includes/data/sanitize.php';
require_once DOUJIN_PROFILE_DIR . '/includes/data/taxonomy.php';
require_once DOUJIN_PROFILE_DIR . '/includes/data/meta.php';
require_once DOUJIN_PROFILE_DIR . '/includes/data/settings.php';
require_once DOUJIN_PROFILE_DIR . '/includes/data/release-date.php';
require_once DOUJIN_PROFILE_DIR . '/includes/search.php';
require_once DOUJIN_PROFILE_DIR . '/includes/login-redirect.php';
require_once DOUJIN_PROFILE_DIR . '/includes/my-page/menu.php';
require_once DOUJIN_PROFILE_DIR . '/includes/my-page/assets.php';
require_once DOUJIN_PROFILE_DIR . '/includes/import/rest.php';
require_once DOUJIN_PROFILE_DIR . '/includes/import/sources.php';
require_once DOUJIN_PROFILE_DIR . '/includes/import/media.php';
require_once DOUJIN_PROFILE_DIR . '/includes/blocks/register.php';
require_once DOUJIN_PROFILE_DIR . '/includes/blocks/render.php';
require_once DOUJIN_PROFILE_DIR . '/includes/patterns.php';
require_once DOUJIN_PROFILE_DIR . '/includes/init-site.php';
require_once DOUJIN_PROFILE_DIR . '/includes/preparing.php';
require_once DOUJIN_PROFILE_DIR . '/includes/cli/cli.php';
require_once DOUJIN_PROFILE_DIR . '/includes/age-gate.php';
require_once DOUJIN_PROFILE_DIR . '/includes/head.php';
