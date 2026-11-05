import { describe, expect, it } from 'vitest';

import { releaseInput } from './release';

describe( 'releaseInput', () => {
	it( 'ISO 8601 を datetime-local の値 (分まで) にする', () => {
		expect( releaseInput( '2026-05-29T00:00:00+09:00' ) ).toBe( '2026-05-29T00:00' );
		expect( releaseInput( '2026-05-29T10:30' ) ).toBe( '2026-05-29T10:30' );
	} );

	it( '空や無い値は空', () => {
		expect( releaseInput( '' ) ).toBe( '' );
		expect( releaseInput( undefined ) ).toBe( '' );
		expect( releaseInput( null ) ).toBe( '' );
	} );
} );
