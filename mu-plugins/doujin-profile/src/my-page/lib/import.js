/**
 * 作品番号からの取り込み (includes/import/rest.php の応答) を画面がどう受けるかの決まり。
 */

/**
 * 独自 REST の道筋 (%d に作品の投稿 ID を入れる)。
 *
 * @param {string} template PHP 側が渡す道筋 (例 /doujin-profile/v1/works/%d/cover)。
 * @param {number} workId   作品の投稿 ID。
 * @return {string}
 */
export function importPath( template, workId ) {
	return template.replace( '%d', String( workId ) );
}

/**
 * サンプルの一覧 (GET の応答) のうち、取り込み済みの添付 ID。
 *
 * @param {Array} samples [ { index, attachment_id } ]。
 * @return {number[]}
 */
export function importedIds( samples ) {
	return samples.filter( ( sample ) => sample.attachment_id > 0 ).map( ( sample ) => sample.attachment_id );
}

/**
 * サンプルの取り込みが終わったあとの知らせ。
 *
 * @param {Object}  progress
 * @param {number}  progress.done  進んだ枚数。
 * @param {number}  progress.total 全体の枚数。
 * @param {boolean} progress.full  上限で止めたか。
 * @param {number}  progress.max   上限。
 * @return {{ status: string, text: string }|null} 枚数が 0 なら null (「見つからない」は先に出している)。
 */
export function samplesNotice( { done, total, full, max } ) {
	if ( ! total ) {
		return null;
	}
	const tail = '並びを確かめて「保存」を押してください。';
	if ( full ) {
		return { status: 'warning', text: `サンプル画像は ${ max } 枚までなので、${ done } / ${ total } 枚で止めました。${ tail }` };
	}
	if ( done < total ) {
		return { status: 'warning', text: `${ done } / ${ total } 枚で中止しました。取り込めた分は並びに入っています。${ tail }` };
	}
	return { status: 'success', text: `サンプル画像を ${ total } 枚取り込みました。${ tail }` };
}

/**
 * 書影の取り込みの結果を、作品の未保存の編集にする。アイキャッチはサーバが設定済みで、
 * リンク・題名・発売日時はまだ無いときだけ埋める (持ち主が「保存」で書く)。
 *
 * @param {Object} work   編集中の作品 (title・meta)。
 * @param {Object} result REST の応答 (featured_media・shop_link・release_at・title)。
 * @return {Object} editEntityRecord に渡す差分。
 */
export function coverEdits( work, result ) {
	const meta = ( work && work.meta ) || {};
	const edits = { featured_media: result.featured_media, meta: {} };
	const links = Array.isArray( meta.doujin_shop_links ) ? meta.doujin_shop_links : [];
	if ( result.shop_link && ! links.some( ( link ) => link.url === result.shop_link.url ) ) {
		edits.meta.doujin_shop_links = [ ...links, result.shop_link ];
	}
	if ( result.release_at && ! meta.doujin_release_at ) {
		edits.meta.doujin_release_at = result.release_at;
	}
	if ( result.title && ! String( ( work && work.title ) || '' ).trim() ) {
		edits.title = result.title;
	}
	return edits;
}
