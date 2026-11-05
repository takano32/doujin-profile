import { describe, expect, it } from 'vitest';

import { SWIPE_PX, clampIndex, countText, isSpread, keyAction, pointerDirection, stepSize, upcomingHidden, visibleRange } from './logic';

describe( 'isSpread', () => {
	it( '768px 以上の横長だけ見開き', () => {
		expect( isSpread( 1024, 768 ) ).toBe( true );
		expect( isSpread( 768, 767 ) ).toBe( true );
		expect( isSpread( 767, 500 ) ).toBe( false );
		expect( isSpread( 800, 800 ) ).toBe( false ); // 正方形は見開きにしない
		expect( isSpread( 390, 844 ) ).toBe( false ); // スマホ縦
	} );
} );

describe( 'clampIndex / stepSize', () => {
	it( '範囲に収める', () => {
		expect( clampIndex( -1, 5 ) ).toBe( 0 );
		expect( clampIndex( 7, 5 ) ).toBe( 4 );
		expect( clampIndex( 2, 5 ) ).toBe( 2 );
	} );

	it( '見開きは 2 枚ずつ (1 枚だけなら 1)', () => {
		expect( stepSize( true, 10 ) ).toBe( 2 );
		expect( stepSize( true, 1 ) ).toBe( 1 );
		expect( stepSize( false, 10 ) ).toBe( 1 );
	} );
} );

describe( 'visibleRange', () => {
	it( '見開きは偶数にそろえ、次のページも見せる', () => {
		expect( visibleRange( 3, 10, true ) ).toEqual( { first: 2, last: 3, spread: true } );
		expect( visibleRange( 2, 10, true ) ).toEqual( { first: 2, last: 3, spread: true } );
		expect( visibleRange( 8, 9, true ) ).toEqual( { first: 8, last: 8, spread: true } ); // 最後の 1 枚
	} );

	it( '1 ページ表示はそのまま', () => {
		expect( visibleRange( 3, 10, false ) ).toEqual( { first: 3, last: 3, spread: false } );
		expect( visibleRange( 0, 1, true ) ).toEqual( { first: 0, last: 0, spread: false } ); // 1 枚だけなら見開きにしない
	} );
} );

describe( 'countText', () => {
	it( '「いま / 全体」', () => {
		expect( countText( 2, 3, 10 ) ).toBe( '3-4 / 10' );
		expect( countText( 4, 4, 10 ) ).toBe( '5 / 10' );
	} );
} );

describe( 'pointerDirection', () => {
	it( 'スワイプ: 右へ = 次、左へ = 前', () => {
		expect( pointerDirection( SWIPE_PX, 0, 0, 1000 ) ).toBe( 1 );
		expect( pointerDirection( -SWIPE_PX, 999, 0, 1000 ) ).toBe( -1 );
	} );

	it( 'クリック: 左半分 = 次、右半分 = 前', () => {
		expect( pointerDirection( 0, 100, 0, 1000 ) ).toBe( 1 );
		expect( pointerDirection( 10, 900, 0, 1000 ) ).toBe( -1 );
		expect( pointerDirection( 0, 600, 100, 1000 ) ).toBe( -1 ); // 置き場が 100〜1100 なら真ん中は 600
		expect( pointerDirection( 0, 599, 100, 1000 ) ).toBe( 1 );
	} );
} );

describe( 'keyAction', () => {
	it( '右から左なので ← が次、→ が前', () => {
		expect( keyAction( 'ArrowLeft' ) ).toBe( 'next' );
		expect( keyAction( 'ArrowRight' ) ).toBe( 'prev' );
		expect( keyAction( 'Home' ) ).toBe( 'first' );
		expect( keyAction( 'End' ) ).toBe( 'last' );
		expect( keyAction( 'Enter' ) ).toBeNull();
	} );
} );

describe( 'upcomingHidden', () => {
	const now = Date.parse( '2026-05-29T00:00:00+09:00' );

	it( '発売日時を過ぎたら印を隠す', () => {
		expect( upcomingHidden( '2026-05-28T00:00:00+09:00', now ) ).toBe( true );
		expect( upcomingHidden( '2026-05-29T00:00:00+09:00', now ) ).toBe( true ); // ちょうど今も「過ぎた」
		expect( upcomingHidden( '2026-05-30T00:00:00+09:00', now ) ).toBe( false );
	} );

	it( '読めない日時は触らない (null)', () => {
		expect( upcomingHidden( '', now ) ).toBeNull();
		expect( upcomingHidden( 'nonsense', now ) ).toBeNull();
	} );
} );
