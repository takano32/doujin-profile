<?php
/**
 * Plugin Name: doujin-profile
 * Description: 同人サークルの紹介サイト (Multisite の各サイト) の本体。作品のデータ層・マイページ・ログイン後の直行など。実体は doujin-profile/ の中にある。
 * Version:     0.1.0
 * Requires PHP: 8.0
 * License:     GPL-2.0-or-later
 *
 * mu-plugins/ は直下の *.php しか自動で読まないので、このファイルは本体を読み込むだけ (spec §3.2・§12)。
 * リポジトリの mu-plugins/ が正本で、本番へは scripts/deploy.sh が rsync する。サーバ上で直接編集しないこと。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/doujin-profile/doujin-profile.php';
