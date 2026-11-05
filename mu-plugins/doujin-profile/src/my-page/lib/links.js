/**
 * リンク (サイトのリンク集 shops / accounts と、作品の販売先リンク) の決まり。
 * 1 件はサービス種別・URL (https: のみ)・表示名・アフィリエイトの有無 (PHP 側の登録は includes/data/meta.php・settings.php)。
 */

export const EMPTY_LINK = { service: 'other', url: '', label: '', sponsored: false };

/**
 * 保存の前に、URL が空の行を落とす (足しただけの行)。
 *
 * @param {Array} links リンク。
 * @return {Array}
 */
export function cleanLinks( links ) {
	return ( links || [] ).filter( ( link ) => link && String( link.url || '' ).trim() !== '' );
}

/**
 * 保存できない行があれば、その説明を返す (REST のスキーマが https: 以外を弾くため、先に知らせる)。
 *
 * @param {Array}  links リンク。
 * @param {string} where どの一覧か (文に入れる)。
 * @return {string|null}
 */
export function findBadLink( links, where ) {
	const bad = cleanLinks( links ).find( ( link ) => ! /^https:\/\/\S+$/.test( String( link.url ).trim() ) );
	if ( ! bad ) {
		return null;
	}
	return `${ where }の URL は https:// から始まるように入れてください (${ bad.url })。`;
}

/**
 * 取り込みで分かった販売ページのリンクを、同じ URL が無ければ末尾に足す。
 *
 * @param {Array}  links    今のリンク。
 * @param {Object} shopLink 足すリンク (無ければ何もしない)。
 * @return {Array|null} 足した新しい一覧。足さなかったら null。
 */
export function withShopLink( links, shopLink ) {
	const list = Array.isArray( links ) ? links : [];
	if ( ! shopLink || list.some( ( link ) => link.url === shopLink.url ) ) {
		return null;
	}
	return [ ...list, shopLink ];
}
