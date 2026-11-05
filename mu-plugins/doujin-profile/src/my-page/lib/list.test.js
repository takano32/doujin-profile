import { describe, expect, it } from 'vitest';

import { appendUnique, move } from './list';

describe( 'move', () => {
	it( '前から後ろへ・後ろから前へ動かす', () => {
		expect( move( [ 'a', 'b', 'c', 'd' ], 0, 2 ) ).toEqual( [ 'b', 'c', 'a', 'd' ] );
		expect( move( [ 'a', 'b', 'c', 'd' ], 3, 1 ) ).toEqual( [ 'a', 'd', 'b', 'c' ] );
	} );

	it( '動かせない指定は元の配列をそのまま返す', () => {
		const list = [ 'a', 'b' ];
		expect( move( list, 0, 0 ) ).toBe( list );
		expect( move( list, -1, 0 ) ).toBe( list );
		expect( move( list, 0, 2 ) ).toBe( list );
		expect( move( list, 2, 0 ) ).toBe( list );
	} );

	it( '元の配列は変えない', () => {
		const list = [ 1, 2, 3 ];
		move( list, 0, 2 );
		expect( list ).toEqual( [ 1, 2, 3 ] );
	} );
} );

describe( 'appendUnique', () => {
	it( '重複と 0 以下を除いて末尾に足す', () => {
		expect( appendUnique( [ 3, 1 ], [ 1, 0, -2, 5, 5, 3 ], 80 ) ).toEqual( { list: [ 3, 1, 5 ], overflow: false } );
	} );

	it( '上限で止めて overflow を立てる (並びは先頭から上限まで)', () => {
		expect( appendUnique( [ 1, 2 ], [ 3, 4, 5 ], 4 ) ).toEqual( { list: [ 1, 2, 3, 4 ], overflow: true } );
		expect( appendUnique( [ 1, 2 ], [ 1, 2 ], 2 ) ).toEqual( { list: [ 1, 2 ], overflow: false } ); // 重複は上限に当たらない
	} );

	it( '元の並びは変えない', () => {
		const list = [ 1 ];
		appendUnique( list, [ 2 ], 80 );
		expect( list ).toEqual( [ 1 ] );
	} );
} );
