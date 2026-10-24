/**
 * 画像のアップロードと添付の読み出し (WordPress 同梱の @wordpress/media-utils と標準 REST だけ)。
 */

import apiFetch from '@wordpress/api-fetch';
import { uploadMedia } from '@wordpress/media-utils';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

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
 * 画像をまとめて上げる (標準の wp/v2/media。添付の親は作品)。ファイル名の自然順に上げる。
 *
 * @param {FileList|File[]} files  ファイル。
 * @param {number}          postId 添付の親にする作品の ID。
 * @return {Promise<{ids: number[], errors: string[]}>} 上がった添付 ID (上げた順) と、失敗の文。
 */
export function uploadImages( files, postId ) {
	return new Promise( ( resolve ) => {
		const list = sortByFileName( Array.from( files ), ( file ) => file.name );
		const errors = [];
		if ( ! list.length ) {
			resolve( { ids: [], errors } );
			return;
		}

		// uploadMedia() の返す Promise は、上げ終わりを待たない (1 件ずつの送信を待たずに返る)。
		// 進み具合は onFileChange で届く: 呼んだ直後までに「ファイルの検査」と「仮の要素 (id なし) の追加」が同期で済み、
		// その後は 1 件が終わる (成功なら id 付き、失敗なら外れる) ごとに 1 回呼ばれる。
		// なので、検査を通った件数ぶんの呼び出しを数えて完了とする。
		let latest = [];
		let started = false;
		let expected = 0;
		let settled = 0;
		const finish = () => resolve( { ids: latest.filter( ( file ) => file && file.id ).map( ( file ) => file.id ), errors } );

		uploadMedia( {
			filesList: list,
			allowedTypes: [ 'image' ],
			additionalData: { post: postId },
			onError: ( error ) => {
				const name = error && error.file ? error.file.name : '';
				errors.push( `${ name }: ${ ( error && error.message ) || '上げられませんでした。' }` );
			},
			onFileChange: ( uploaded ) => {
				latest = uploaded;
				if ( started ) {
					settled += 1;
					if ( settled >= expected ) {
						finish();
					}
				}
			},
		} );

		started = true;
		expected = list.length - errors.length; // ここまでの errors は検査で弾かれた分。
		if ( expected <= 0 ) {
			finish();
		}
	} );
}

/**
 * メディアのモーダルで上げた画像 (親が無い) の親を作品にする。選び直しただけの既存の画像は触らない。
 *
 * @param {Object} attachment wp.media の添付 (uploadedTo が親の投稿 ID)。
 * @param {number} postId     作品の ID。
 */
export function adoptAttachment( attachment, postId ) {
	if ( ! attachment || ! attachment.id || attachment.uploadedTo ) {
		return;
	}
	apiFetch( { path: `/wp/v2/media/${ attachment.id }`, method: 'POST', data: { post: postId } } ).catch( () => {
		// 親を付けられなくても、画像自体は使える。黙って続ける。
	} );
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
 * 添付 ID の一覧を、1 回の REST (wp/v2/media?include=…) で読む。
 *
 * @param {number[]} ids 添付 ID。
 * @return {{ byId: Object, isLoading: boolean }} ID → 添付。
 */
export function useAttachments( ids ) {
	// 並びが変わっただけで取り直さないように、キーは ID を昇順に揃える。
	const sorted = [ ...new Set( ids ) ].filter( ( id ) => id > 0 ).sort( ( a, b ) => a - b );
	const key = sorted.join( ',' );
	return useSelect(
		( select ) => {
			if ( ! sorted.length ) {
				return { byId: {}, isLoading: false };
			}
			const query = { include: sorted, per_page: 100, context: 'view' };
			const records = select( coreStore ).getEntityRecords( 'postType', 'attachment', query );
			const byId = {};
			( records || [] ).forEach( ( record ) => {
				byId[ record.id ] = record;
			} );
			return {
				byId,
				isLoading: ! select( coreStore ).hasFinishedResolution( 'getEntityRecords', [ 'postType', 'attachment', query ] ),
			};
		},
		[ key ] // eslint-disable-line react-hooks/exhaustive-deps
	);
}
