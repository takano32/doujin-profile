/**
 * REST のエラーから、人に見せる文を取り出す。
 */

/**
 * @param {*}      error    apiFetch / core-data のエラー。
 * @param {string} fallback 文が無いときの文。
 * @return {string}
 */
export function errorText( error, fallback ) {
	// REST の引数の検査 (validate_callback) が返した文は、コアが「無効なパラメータ: …」に包んで data.params に入れる。
	if ( error && 'rest_invalid_param' === error.code && error.data && error.data.params ) {
		const texts = Object.values( error.data.params ).filter( ( text ) => typeof text === 'string' && text );
		if ( texts.length ) {
			return texts.join( ' / ' );
		}
	}
	if ( error && typeof error.message === 'string' && error.message ) {
		return error.message;
	}
	return fallback;
}
