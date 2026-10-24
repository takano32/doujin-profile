/**
 * ドラッグで並べ替え (SortableJS。唯一の同梱する外部 JS。owner-editor-design §7.1 Q4)。
 *
 * SortableJS は DOM を直接動かすので、そのままだと React と二重管理になる。
 * ここでは「ドラッグが終わったら DOM を元の位置に戻し、並びの変更だけを React に渡す」ことで、
 * DOM の管理を React に一本化する。要素には安定した key を付けること。
 */

import Sortable from 'sortablejs';
import { useEffect, useRef } from '@wordpress/element';

/**
 * 並べ替えできる一覧の入れ物 (ul など) に付ける ref を返す。
 *
 * @param {Function} onMove            (from, to) => void。並びを変える処理 (状態の更新)。
 * @param {Object}   [options]
 * @param {string}   [options.handle]  つまむ所のセレクタ (無ければ要素全体)。
 * @param {string}   [options.filter]  ドラッグを始めない所のセレクタ (ボタンなど)。
 * @return {Object} ref。
 */
export function useSortable( onMove, { handle, filter } = {} ) {
	const ref = useRef( null );
	const onMoveRef = useRef( onMove );
	onMoveRef.current = onMove;

	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return undefined;
		}
		const sortable = Sortable.create( el, {
			animation: 150,
			delay: 150,
			delayOnTouchOnly: true, // スマホは長押しで持ち上げる (ふつうのスクロールを邪魔しない)。
			touchStartThreshold: 4,
			handle,
			filter,
			preventOnFilter: false, // filter の所 (ボタン) のクリックは通す。
			ghostClass: 'doujin-profile-sortable-ghost',
			onEnd: ( { item, from, oldIndex, newIndex } ) => {
				if ( oldIndex === newIndex || oldIndex === undefined || newIndex === undefined ) {
					return;
				}
				// SortableJS が動かした DOM を元に戻してから、React に並びを渡す。
				from.removeChild( item );
				from.insertBefore( item, from.children[ oldIndex ] || null );
				onMoveRef.current( oldIndex, newIndex );
			},
		} );
		return () => sortable.destroy();
	}, [ handle, filter ] );

	return ref;
}
