import { describe, expect, it } from 'vitest';

import { coverEdits, importPath, importedIds, samplesNotice } from './import';

describe( 'importPath', () => {
	it( '%d に作品の ID を入れる', () => {
		expect( importPath( '/doujin-profile/v1/works/%d/cover', 12 ) ).toBe( '/doujin-profile/v1/works/12/cover' );
	} );
} );

describe( 'importedIds', () => {
	it( '取り込み済み (attachment_id > 0) だけを順に返す', () => {
		expect( importedIds( [ { index: 1, attachment_id: 0 }, { index: 2, attachment_id: 9 }, { index: 3, attachment_id: 4 } ] ) ).toEqual( [ 9, 4 ] );
		expect( importedIds( [] ) ).toEqual( [] );
	} );
} );

describe( 'samplesNotice', () => {
	it( '全部取り込めたら success', () => {
		expect( samplesNotice( { done: 3, total: 3, full: false, max: 80 } ) ).toEqual( {
			status: 'success',
			text: 'サンプル画像を 3 枚取り込みました。並びを確かめて「保存」を押してください。',
		} );
	} );

	it( '上限で止めた・途中で中止したときは warning', () => {
		expect( samplesNotice( { done: 2, total: 5, full: true, max: 80 } ).text ).toBe( 'サンプル画像は 80 枚までなので、2 / 5 枚で止めました。並びを確かめて「保存」を押してください。' );
		expect( samplesNotice( { done: 2, total: 5, full: false, max: 80 } ) ).toEqual( {
			status: 'warning',
			text: '2 / 5 枚で中止しました。取り込めた分は並びに入っています。並びを確かめて「保存」を押してください。',
		} );
	} );

	it( '枚数が 0 なら null', () => {
		expect( samplesNotice( { done: 0, total: 0, full: false, max: 80 } ) ).toBeNull();
	} );
} );

describe( 'coverEdits', () => {
	const result = {
		featured_media: 55,
		shop_link: { service: 'dlsite', url: 'https://www.dlsite.com/maniax/work/=/product_id/RJ1.html', label: '', sponsored: false },
		title: '取り込んだ題名',
		release_at: '2026-05-29T00:00:00+09:00',
	};

	it( '空の作品には、アイキャッチ・リンク・発売日時・題名をすべて埋める', () => {
		expect( coverEdits( { title: '', meta: {} }, result ) ).toEqual( {
			featured_media: 55,
			title: '取り込んだ題名',
			meta: { doujin_shop_links: [ result.shop_link ], doujin_release_at: result.release_at },
		} );
	} );

	it( 'すでにある題名・発売日時・同じ URL のリンクは上書きしない', () => {
		const work = { title: '持ち主の題名', meta: { doujin_release_at: '2026-01-01T00:00:00+09:00', doujin_shop_links: [ { url: result.shop_link.url } ] } };
		expect( coverEdits( work, result ) ).toEqual( { featured_media: 55, meta: {} } );
	} );

	it( '別の URL のリンクがあれば末尾に足す', () => {
		const work = { title: 't', meta: { doujin_shop_links: [ { url: 'https://other/' } ] } };
		expect( coverEdits( work, result ).meta.doujin_shop_links ).toEqual( [ { url: 'https://other/' }, result.shop_link ] );
	} );

	it( 'FANZA (題名・発売日時・リンク以外は空) でもアイキャッチとリンクは入る', () => {
		const fanza = { featured_media: 7, shop_link: { service: 'fanza', url: 'https://www.dmm.co.jp/x/', label: '', sponsored: false }, title: '', release_at: '' };
		expect( coverEdits( { title: '', meta: {} }, fanza ) ).toEqual( { featured_media: 7, meta: { doujin_shop_links: [ fanza.shop_link ] } } );
	} );
} );
