/**
 * 画像のアップロードと添付の読み出し (WordPress 同梱の @wordpress/media-utils と標準 REST だけ)。
 * 上げる流れそのものは lib/upload.js、添付の読み方は lib/media.js (WordPress を読まないので試験できる)。
 */

import apiFetch from '@wordpress/api-fetch';
import { uploadMedia } from '@wordpress/media-utils';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import { uploadImagesWith } from './lib/upload';
import { attachmentIds } from './lib/media';

/**
 * 画像をまとめて上げる (標準の wp/v2/media。添付の親は作品)。ファイル名の自然順に上げる。
 *
 * @param {FileList|File[]} files  ファイル。
 * @param {number}          postId 添付の親にする作品の ID。
 * @return {Promise<{ids: number[], errors: string[]}>} 上がった添付 ID (上げた順) と、失敗の文。
 */
export function uploadImages( files, postId ) {
	return uploadImagesWith( uploadMedia, files, postId );
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
 * 添付 ID の一覧を、1 回の REST (wp/v2/media?include=…) で読む。
 *
 * @param {number[]} ids 添付 ID。
 * @return {{ byId: Object, isLoading: boolean }} ID → 添付。
 */
export function useAttachments( ids ) {
	// 並びが変わっただけで取り直さないように、キーは ID を昇順に揃える。
	const sorted = attachmentIds( ids );
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
