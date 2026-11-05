import { describe, expect, it } from 'vitest';

import { EMPTY_LINK, cleanLinks, findBadLink, withShopLink } from './links';

describe( 'cleanLinks', () => {
	it( 'URL が空の行と壊れた行を落とす', () => {
		expect( cleanLinks( [ { url: 'https://a/' }, { url: '  ' }, null, { label: 'x' } ] ) ).toEqual( [ { url: 'https://a/' } ] );
		expect( cleanLinks( undefined ) ).toEqual( [] );
	} );
} );

describe( 'findBadLink', () => {
	it( 'https でない URL を見つけて説明する', () => {
		expect( findBadLink( [ { url: 'https://a/' }, { url: 'http://b/' } ], '「販売ページ」' ) ).toBe(
			'「販売ページ」の URL は https:// から始まるように入れてください (http://b/)。'
		);
		expect( findBadLink( [ { url: 'https://a b/' } ], 'x' ) ).toContain( 'https://a b/' ); // 空白入りも弾く
	} );

	it( '問題が無ければ null (空の行は見ない)', () => {
		expect( findBadLink( [ { url: 'https://a/' }, { url: '' } ], 'x' ) ).toBeNull();
		expect( findBadLink( [], 'x' ) ).toBeNull();
	} );
} );

describe( 'withShopLink', () => {
	const shop = { service: 'fanza', url: 'https://www.dmm.co.jp/x/', label: '', sponsored: false };

	it( '同じ URL が無ければ末尾に足す', () => {
		expect( withShopLink( [ { url: 'https://other/' } ], shop ) ).toEqual( [ { url: 'https://other/' }, shop ] );
		expect( withShopLink( undefined, shop ) ).toEqual( [ shop ] );
	} );

	it( '同じ URL があるか、足すものが無ければ null', () => {
		expect( withShopLink( [ { url: shop.url } ], shop ) ).toBeNull();
		expect( withShopLink( [], null ) ).toBeNull();
	} );
} );

describe( 'EMPTY_LINK', () => {
	it( 'サービスは「その他」で、URL と表示名は空', () => {
		expect( EMPTY_LINK ).toEqual( { service: 'other', url: '', label: '', sponsored: false } );
	} );
} );
