import { describe, expect, it } from 'vitest';

import { uploadImagesWith } from './upload';

/**
 * wp.mediaUtils.uploadMedia の代わり: 検査で弾くもの (reject) は同期で onError、残りは 1 件ずつ onFileChange で届ける。
 *
 * @param {Object}   options
 * @param {string[]} options.reject 検査で弾くファイル名。
 * @param {string[]} options.fail   上げる途中で失敗するファイル名。
 * @return {Function} uploadMedia と同じ形の関数 (呼ばれたときの引数を .calls に残す)。
 */
function fakeUploadMedia( { reject = [], fail = [] } = {} ) {
	const fake = ( { filesList, onError, onFileChange } ) => {
		fake.calls.push( { filesList } );
		const accepted = filesList.filter( ( file ) => {
			if ( reject.includes( file.name ) ) {
				onError( { file, message: '形式が違います' } );
				return false;
			}
			return true;
		} );
		let state = accepted.map( () => ( { id: undefined } ) ); // 仮の要素 (id なし)
		onFileChange( state );
		accepted.forEach( ( file, i ) => {
			setTimeout( () => {
				if ( fail.includes( file.name ) ) {
					onError( { file, message: '通信に失敗' } );
					state = state.filter( ( _, j ) => j !== i );
				} else {
					state = state.map( ( item, j ) => ( j === i ? { id: 100 + i } : item ) );
				}
				onFileChange( state );
			}, 0 );
		} );
		return Promise.resolve();
	};
	fake.calls = [];
	return fake;
}

describe( 'uploadImagesWith', () => {
	it( 'ファイル名の自然順に上げ、上がった ID を順に返す', async () => {
		const upload = fakeUploadMedia();
		const result = await uploadImagesWith( upload, [ { name: '10.jpg' }, { name: '2.jpg' } ], 7 );
		expect( upload.calls[ 0 ].filesList.map( ( f ) => f.name ) ).toEqual( [ '2.jpg', '10.jpg' ] );
		expect( result ).toEqual( { ids: [ 100, 101 ], errors: [] } );
	} );

	it( '検査で弾かれたものは errors に入り、残りの完了を待つ', async () => {
		const result = await uploadImagesWith( fakeUploadMedia( { reject: [ 'bad.txt' ] } ), [ { name: 'bad.txt' }, { name: 'a.jpg' } ], 7 );
		expect( result.ids ).toEqual( [ 100 ] );
		expect( result.errors ).toEqual( [ 'bad.txt: 形式が違います' ] );
	} );

	it( '全部弾かれたら待たずに終わる', async () => {
		const result = await uploadImagesWith( fakeUploadMedia( { reject: [ 'a.txt' ] } ), [ { name: 'a.txt' } ], 7 );
		expect( result ).toEqual( { ids: [], errors: [ 'a.txt: 形式が違います' ] } );
	} );

	it( '途中で失敗したものは外れ、文が残る', async () => {
		const result = await uploadImagesWith( fakeUploadMedia( { fail: [ 'b.jpg' ] } ), [ { name: 'a.jpg' }, { name: 'b.jpg' } ], 7 );
		expect( result.ids ).toEqual( [ 100 ] );
		expect( result.errors ).toEqual( [ 'b.jpg: 通信に失敗' ] );
	} );

	it( 'ファイルが無ければ何もしない', async () => {
		const upload = fakeUploadMedia();
		expect( await uploadImagesWith( upload, [], 7 ) ).toEqual( { ids: [], errors: [] } );
		expect( upload.calls ).toEqual( [] );
	} );

	it( '添付の親に作品の ID を付ける', async () => {
		let seen = null;
		const upload = ( options ) => {
			seen = options;
			options.onFileChange( [ { id: undefined } ] ); // 仮の要素は同期で届く
			setTimeout( () => options.onFileChange( [ { id: 1 } ] ), 0 ); // 上げ終わりは uploadMedia() が返ったあとに届く
		};
		await uploadImagesWith( upload, [ { name: 'a.jpg' } ], 42 );
		expect( seen.additionalData ).toEqual( { post: 42 } );
		expect( seen.allowedTypes ).toEqual( [ 'image' ] );
	} );
} );
