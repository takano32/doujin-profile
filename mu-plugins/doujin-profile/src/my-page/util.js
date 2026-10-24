/**
 * 画面の各所で使う小さな道具。
 */

import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';

// PHP 側 (includes/my-page.php) が渡す、保存しないサイトの情報。
export const data = window.doujinProfileData || {};

// 作品の本文に置く作品カード (spec §6.2)。ブロック自体はまだ無い (次の段)。本文が空のときだけ入れる。
export const WORK_CARD_BLOCK = '<!-- wp:doujin-profile/work-card /-->';

/**
 * 配列の from 番目を to 番目へ動かした新しい配列。
 *
 * @param {Array}  list 元の配列。
 * @param {number} from 動かす位置。
 * @param {number} to   動かした先の位置。
 * @return {Array} 新しい配列。
 */
export function move( list, from, to ) {
	if ( from === to || from < 0 || to < 0 || from >= list.length || to >= list.length ) {
		return list;
	}
	const next = [ ...list ];
	const [ item ] = next.splice( from, 1 );
	next.splice( to, 0, item );
	return next;
}

/**
 * 保存の成否などを 1 件だけ出す Notice の状態。
 *
 * @return {Array} [notice, setNotice, clear]。
 */
export function useNotice() {
	const [ notice, setNotice ] = useState( null );
	return [ notice, setNotice, () => setNotice( null ) ];
}

/**
 * useNotice() の中身を出す。
 *
 * @param {Object}   props
 * @param {Object}   props.notice   { status, text }。null なら何も出さない。
 * @param {Function} props.onRemove 閉じたとき。
 */
export function StatusNotice( { notice, onRemove } ) {
	if ( ! notice ) {
		return null;
	}
	return (
		<Notice status={ notice.status } onRemove={ onRemove } className="doujin-profile-notice">
			{ notice.text }
		</Notice>
	);
}

/**
 * REST のエラーから、人に見せる文を取り出す。
 *
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
