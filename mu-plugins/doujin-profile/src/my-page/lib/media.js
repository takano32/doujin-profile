/**
 * 添付 (REST の wp/v2/media) の読み方と、ファイル名の並べ方。
 */

// ファイル名の自然順 (01.jpg, 02.jpg, … 10.jpg の連番がそのまま並ぶ。spec §6.0)。
const collator = new Intl.Collator( undefined, { numeric: true, sensitivity: 'base' } );

/**
 * ファイル名の自然順に並べた新しい配列。
 *
 * @param {Array}    items   並べるもの。
 * @param {Function} getName 1 件からファイル名を取る。
 * @return {Array}
 */
export function sortByFileName( items, getName ) {
	return [ ...items ].sort( ( a, b ) => collator.compare( getName( a ) || '', getName( b ) || '' ) );
}

/**
 * 添付の縮小画像の URL。
 *
 * @param {Object} attachment REST の添付。
 * @param {string} size       欲しいサイズ (thumbnail / medium)。
 * @return {string} 無ければ空。
 */
export function thumbnailUrl( attachment, size = 'thumbnail' ) {
	if ( ! attachment ) {
		return '';
	}
	const sizes = ( attachment.media_details && attachment.media_details.sizes ) || {};
	const hit = sizes[ size ] || sizes.medium || sizes.thumbnail;
	return ( hit && hit.source_url ) || attachment.source_url || '';
}

/**
 * 問い合わせる添付 ID: 重複と 0 以下を除き、昇順にそろえる (並びが変わっただけで取り直さないため)。
 *
 * @param {number[]} ids 添付 ID。
 * @return {number[]}
 */
export function attachmentIds( ids ) {
	return [ ...new Set( ids ) ].filter( ( id ) => id > 0 ).sort( ( a, b ) => a - b );
}
