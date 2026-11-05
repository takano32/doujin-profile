import { describe, expect, it } from 'vitest';

import { attachmentIds, sortByFileName, thumbnailUrl } from './media';

describe( 'sortByFileName', () => {
	it( '連番を自然順に並べる (10 が 2 の前に来ない)', () => {
		const files = [ { name: '10.jpg' }, { name: '2.jpg' }, { name: '01.jpg' }, { name: 'B.jpg' }, { name: 'a.jpg' } ];
		expect( sortByFileName( files, ( f ) => f.name ).map( ( f ) => f.name ) ).toEqual( [ '01.jpg', '2.jpg', '10.jpg', 'a.jpg', 'B.jpg' ] );
	} );

	it( '名前が無いものは空として扱い、元の配列は変えない', () => {
		const files = [ { name: 'b' }, {}, { name: 'a' } ];
		expect( sortByFileName( files, ( f ) => f.name ) ).toEqual( [ {}, { name: 'a' }, { name: 'b' } ] );
		expect( files[ 0 ] ).toEqual( { name: 'b' } );
	} );
} );

describe( 'thumbnailUrl', () => {
	const attachment = {
		source_url: 'https://x/full.jpg',
		media_details: { sizes: { thumbnail: { source_url: 'https://x/t.jpg' }, medium: { source_url: 'https://x/m.jpg' } } },
	};

	it( '欲しいサイズ → medium → thumbnail → 元画像の順', () => {
		expect( thumbnailUrl( attachment, 'medium' ) ).toBe( 'https://x/m.jpg' );
		expect( thumbnailUrl( attachment ) ).toBe( 'https://x/t.jpg' );
		expect( thumbnailUrl( attachment, 'large' ) ).toBe( 'https://x/m.jpg' );
		expect( thumbnailUrl( { source_url: 'https://x/full.jpg', media_details: { sizes: { thumbnail: { source_url: 'https://x/t.jpg' } } } }, 'medium' ) ).toBe( 'https://x/t.jpg' );
		expect( thumbnailUrl( { source_url: 'https://x/full.jpg' } ) ).toBe( 'https://x/full.jpg' );
	} );

	it( '無ければ空', () => {
		expect( thumbnailUrl( null ) ).toBe( '' );
		expect( thumbnailUrl( {} ) ).toBe( '' );
	} );
} );

describe( 'attachmentIds', () => {
	it( '重複と 0 以下を除き、昇順にそろえる', () => {
		expect( attachmentIds( [ 5, 3, 5, 0, -1, 1 ] ) ).toEqual( [ 1, 3, 5 ] );
		expect( attachmentIds( [] ) ).toEqual( [] );
	} );
} );
