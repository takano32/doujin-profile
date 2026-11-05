/**
 * 画像をまとめて上げる流れ。上げる部品 (wp.mediaUtils.uploadMedia) は引数で受けるので、試験では代わりのものを渡せる。
 */

import { sortByFileName } from './media';

/**
 * 画像をまとめて上げる (標準の wp/v2/media。添付の親は作品)。ファイル名の自然順に上げる。
 *
 * uploadMedia() の返す Promise は、上げ終わりを待たない (1 件ずつの送信を待たずに返る)。
 * 進み具合は onFileChange で届く: 呼んだ直後までに「ファイルの検査」と「仮の要素 (id なし) の追加」が同期で済み、
 * その後は 1 件が終わる (成功なら id 付き、失敗なら外れる) ごとに 1 回呼ばれる。
 * なので、検査を通った件数ぶんの呼び出しを数えて完了とする。
 *
 * @param {Function}        uploadMedia wp.mediaUtils.uploadMedia (か同じ形のもの)。
 * @param {FileList|File[]} files       ファイル。
 * @param {number}          postId      添付の親にする作品の ID。
 * @return {Promise<{ids: number[], errors: string[]}>} 上がった添付 ID (上げた順) と、失敗の文。
 */
export function uploadImagesWith( uploadMedia, files, postId ) {
	return new Promise( ( resolve ) => {
		const list = sortByFileName( Array.from( files ), ( file ) => file.name );
		const errors = [];
		if ( ! list.length ) {
			resolve( { ids: [], errors } );
			return;
		}

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
