import { describe, expect, it } from 'vitest';

import { workStatus, workTitle } from './status';

const NOW = Date.parse( '2026-05-29T00:00:00+09:00' );

describe( 'workStatus', () => {
	it( '公開で発売日時が未来なら予告', () => {
		expect( workStatus( { status: 'publish', meta: { doujin_release_at: '2026-06-01T00:00:00+09:00' } }, NOW ) ).toEqual( { key: 'upcoming', label: '予告' } );
	} );

	it( '公開で発売日時が過去・無しなら公開', () => {
		expect( workStatus( { status: 'publish', meta: { doujin_release_at: '2026-05-01T00:00:00+09:00' } }, NOW ) ).toEqual( { key: 'publish', label: '公開' } );
		expect( workStatus( { status: 'publish', meta: { doujin_release_at: '' } }, NOW ) ).toEqual( { key: 'publish', label: '公開' } );
		expect( workStatus( { status: 'publish' }, NOW ) ).toEqual( { key: 'publish', label: '公開' } );
	} );

	it( '予約・非公開・下書き (それ以外も下書き扱い)', () => {
		expect( workStatus( { status: 'future' }, NOW ) ).toEqual( { key: 'future', label: '予約' } );
		expect( workStatus( { status: 'private' }, NOW ) ).toEqual( { key: 'private', label: '非公開' } );
		expect( workStatus( { status: 'draft' }, NOW ) ).toEqual( { key: 'draft', label: '下書き' } );
		expect( workStatus( { status: 'pending' }, NOW ) ).toEqual( { key: 'draft', label: '下書き' } );
		expect( workStatus( null, NOW ) ).toEqual( { key: 'draft', label: '下書き' } );
	} );
} );

describe( 'workTitle', () => {
	it( '文字列・raw・rendered の順に読み、rendered は decode を通す', () => {
		const decode = ( text ) => text.replace( '&amp;', '&' );
		expect( workTitle( { title: ' 題名 ' } ) ).toBe( '題名' );
		expect( workTitle( { title: { raw: 'raw', rendered: 'rendered' } }, decode ) ).toBe( 'raw' );
		expect( workTitle( { title: { raw: '', rendered: 'A &amp; B' } }, decode ) ).toBe( 'A & B' );
	} );

	it( '空なら (タイトルなし)', () => {
		expect( workTitle( { title: '   ' } ) ).toBe( '(タイトルなし)' );
		expect( workTitle( { title: { rendered: '' } } ) ).toBe( '(タイトルなし)' );
		expect( workTitle( {} ) ).toBe( '(タイトルなし)' );
		expect( workTitle( null ) ).toBe( '(タイトルなし)' );
	} );
} );
