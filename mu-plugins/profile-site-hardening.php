<?php
/**
 * Plugin Name: profile-site-hardening
 * Description: profile.xwp.jp のネットワーク全体に効かせる最小限の堅牢化。XML-RPC の停止と、コメント・ピンバックの閉鎖。
 *              doujin-profile 本体とは独立 (設計: docs/spec.md §10.3)。
 * Version:     0.1.0
 * Requires PHP: 8.0
 *
 * リポジトリの mu-plugins/ が正本で、本番へは scripts/deploy.sh が rsync する。
 * サーバ上で直接編集しないこと (次の配備で上書きされる)。
 * 外すときは、サーバ上のこのファイルを消すだけでよい (DB には何も書かない)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// XML-RPC: 認証の要るメソッドを止める。ユーザ名が公開情報である以上、
// パスワード総当たりの経路 (system.multicall でまとめて試せる) は塞いでおく。
add_filter( 'xmlrpc_enabled', '__return_false' );

// XML-RPC: ピンバック系のメソッドも外す (他サイトへの攻撃の踏み台にされるのを防ぐ)。
add_filter(
	'xmlrpc_methods',
	static function ( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}
);

// ピンバックの受付先を知らせるヘッダを出さない。
add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

// コメントとピンバックは、ネットワーク全体で使わない。
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
