/**
 * 画面の各所で使う小さな道具 (React の部品と、PHP 側から渡される情報)。WordPress を読まない純粋なロジックは lib/ に。
 */

import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';

// PHP 側 (includes/my-page/assets.php) が渡す、保存しないサイトの情報。
export const data = window.doujinProfileData || {};

// 作品の本文に置く作品カード (spec §6.2。src/blocks/work-card/)。本文が空のときだけ入れる。
export const WORK_CARD_BLOCK = '<!-- wp:doujin-profile/work-card /-->';

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
