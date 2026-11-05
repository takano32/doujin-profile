<?php
/**
 * 作品のデータ層の正規化 (spec §6.0)。中身は lib/Data/ の純粋なロジックで、ここは WordPress の関数を注ぐ橋。
 *
 * 型・上限・サニタイズは register_post_meta() / register_setting() (includes/data/meta.php・settings.php) に 1 度だけ書き、
 * マイページ (REST)・ブロックエディタ・WP-CLI のどこから書いても同じ検査を通る。
 * ここの関数名は、公開側のブロック (src/blocks/∗/render.php)・WP-CLI・検収の検査 (scripts/check-site.sh) が呼ぶ入口。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoujinProfile\Data\Links;
use DoujinProfile\Data\ReleaseDate;
use DoujinProfile\Data\ReleaseLabel;
use DoujinProfile\Data\SampleIds;
use DoujinProfile\Data\Services;

const DOUJIN_PROFILE_MAX_LINKS   = Links::MAX;
const DOUJIN_PROFILE_MAX_SAMPLES = SampleIds::MAX;

/**
 * リンクのサービス種別 (spec §6.2)。キーが保存される値、値が表示名。
 *
 * @return array<string, string>
 */
function doujin_profile_services() {
	return Services::all();
}

/**
 * リンク 1 件の REST スキーマ (メタの doujin_shop_links と設定の doujin_profile_links で共用)。
 *
 * @return array
 */
function doujin_profile_link_schema() {
	return Links::schema();
}

/**
 * リンクの正規化 (WordPress の sanitize_url() / sanitize_text_field() を注いだもの)。
 *
 * @return Links
 */
function doujin_profile_links() {
	static $links = null;
	if ( null === $links ) {
		$links = new Links(
			static function ( $url ) {
				return sanitize_url( $url, array( 'https' ) );
			},
			'sanitize_text_field'
		);
	}

	return $links;
}

/**
 * 発売日時の文字列を DateTimeImmutable にする。オフセットの無い値はサイトのタイムゾーンで読む。
 *
 * @param mixed $value ISO 8601 の文字列。
 * @return DateTimeImmutable|null 読めないときは null。
 */
function doujin_profile_parse_datetime( $value ) {
	return ReleaseDate::parse( $value, wp_timezone() );
}

/**
 * 発売日時: ISO 8601 に正規化する (サイトのタイムゾーン。例 2026-05-29T00:00:00+09:00)。読めない値は空にする。
 *
 * @param mixed $value 入力値。
 * @return string
 */
function doujin_profile_sanitize_release_at( $value ) {
	return ReleaseDate::normalize( $value, wp_timezone() );
}

/**
 * 発売日時から、ルールに従う公開日時 (GMT、MySQL 形式) を求める: 発売日時と今の早いほう。
 *
 * @param mixed $release_at 発売日時 (ISO 8601)。
 * @return string|null 発売日時が無い・読めないときは null。
 */
function doujin_profile_release_post_date_gmt( $release_at ) {
	return ReleaseDate::postDateGmt( $release_at, wp_timezone(), time() );
}

/**
 * 発売日の表記: プレーンテキスト 200 文字まで。
 *
 * @param mixed $value 入力値。
 * @return string
 */
function doujin_profile_sanitize_release_label( $value ) {
	return ReleaseLabel::sanitize( $value, 'sanitize_text_field' );
}

/**
 * リンク 1 件を正規化する。URL が https: でなければ捨てる (null)。
 *
 * @param mixed $link 入力値。
 * @return array|null
 */
function doujin_profile_sanitize_link( $link ) {
	return doujin_profile_links()->one( $link );
}

/**
 * リンクの一覧を正規化する (上限 30。壊れた件は捨てる)。
 *
 * @param mixed $links 入力値。
 * @return array
 */
function doujin_profile_sanitize_links( $links ) {
	return doujin_profile_links()->many( $links );
}

/**
 * サンプル画像の添付 ID の並びを正規化する (正の整数、重複なし、上限 80)。
 *
 * @param mixed $ids 入力値。
 * @return int[]
 */
function doujin_profile_sanitize_sample_ids( $ids ) {
	return SampleIds::sanitize( $ids );
}

/**
 * サイトのリンク集を正規化する (shops と accounts の 2 つの一覧。それ以外のキーは持たない)。
 *
 * @param mixed $value 入力値。
 * @return array{shops: array, accounts: array}
 */
function doujin_profile_sanitize_profile_links( $value ) {
	return doujin_profile_links()->profile( $value );
}
