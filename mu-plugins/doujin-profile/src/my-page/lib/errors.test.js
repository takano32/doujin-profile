import { describe, expect, it } from 'vitest';

import { errorText } from './errors';

describe( 'errorText', () => {
	it( 'REST の引数の検査の文 (data.params) を取り出す', () => {
		const error = { code: 'rest_invalid_param', message: '無効なパラメータ: product_id', data: { params: { product_id: '作品番号の形が違います。' } } };
		expect( errorText( error, 'fallback' ) ).toBe( '作品番号の形が違います。' );
	} );

	it( '複数の引数の文は / でつなぐ。文の無い params は message に戻る', () => {
		expect( errorText( { code: 'rest_invalid_param', message: 'm', data: { params: { a: 'A', b: 'B', c: 7 } } }, 'f' ) ).toBe( 'A / B' );
		expect( errorText( { code: 'rest_invalid_param', message: 'm', data: { params: { a: '' } } }, 'f' ) ).toBe( 'm' );
	} );

	it( 'message があればそれ、無ければ fallback', () => {
		expect( errorText( { code: 'x', message: 'だめ' }, 'f' ) ).toBe( 'だめ' );
		expect( errorText( { code: 'x', message: '' }, 'f' ) ).toBe( 'f' );
		expect( errorText( new Error( '例外' ), 'f' ) ).toBe( '例外' );
		expect( errorText( null, 'f' ) ).toBe( 'f' );
		expect( errorText( undefined, 'f' ) ).toBe( 'f' );
	} );
} );
