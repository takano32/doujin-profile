/**
 * 作品カードの表示側の計算 (view.js が使う。DOM に触らないので vitest で試験できる)。
 *
 *   - サンプルビューアのページの数え方: 見開きかどうか、見えるページの範囲、めくる幅、「いま / 全体」の文
 *   - クリック (左半分 = 次、右半分 = 前) とスワイプ (右へ = 次、左へ = 前) の向き
 *   - 「予告」の印を現在時刻で見直す
 */

export const SWIPE_PX = 40;

/**
 * 見開きにするか: 横長で、ある程度の幅があるとき。
 *
 * @param {number} width  画面の幅。
 * @param {number} height 画面の高さ。
 * @return {boolean}
 */
export function isSpread( width, height ) {
	return width >= 768 && width > height;
}

/**
 * index を 0〜length-1 に収める。
 *
 * @param {number} index  ページ (0 から)。
 * @param {number} length 枚数。
 * @return {number}
 */
export function clampIndex( index, length ) {
	return Math.max( 0, Math.min( length - 1, index ) );
}

/**
 * 1 回めくる枚数 (見開きなら 2)。
 *
 * @param {boolean} spread 見開きか。
 * @param {number}  length 枚数。
 * @return {number}
 */
export function stepSize( spread, length ) {
	return spread && length > 1 ? 2 : 1;
}

/**
 * いま見えているページの範囲。見開きなら index を偶数にそろえ、次のページも見せる。
 *
 * @param {number}  index  ページ (0 から)。
 * @param {number}  length 枚数。
 * @param {boolean} spread 見開きか (枚数 1 なら見開きにしない)。
 * @return {{ first: number, last: number, spread: boolean }}
 */
export function visibleRange( index, length, spread ) {
	const twoPages = spread && length > 1;
	const first = twoPages ? index - ( index % 2 ) : index;
	const last = twoPages ? Math.min( first + 1, length - 1 ) : first;
	return { first, last, spread: twoPages };
}

/**
 * 「いま / 全体」の文 (例 1-2 / 10、3 / 10)。
 *
 * @param {number} first  最初に見えているページ (0 から)。
 * @param {number} last   最後に見えているページ (0 から)。
 * @param {number} length 枚数。
 * @return {string}
 */
export function countText( first, last, length ) {
	return ( last > first ? `${ first + 1 }-${ last + 1 }` : `${ first + 1 }` ) + ` / ${ length }`;
}

/**
 * 画像の上でのポインタの動きから、めくる向き。右から左へ読むので、右へのスワイプと左半分のクリックが「次」。
 *
 * @param {number} dx       押してから離すまでの横の動き (px)。
 * @param {number} clientX  離した位置。
 * @param {number} left     画像の置き場の左端。
 * @param {number} width    画像の置き場の幅。
 * @param {number} [swipePx] スワイプとみなす動きの大きさ。
 * @return {number} 1 なら次、-1 なら前。
 */
export function pointerDirection( dx, clientX, left, width, swipePx = SWIPE_PX ) {
	if ( Math.abs( dx ) >= swipePx ) {
		return dx > 0 ? 1 : -1;
	}
	return clientX < left + width / 2 ? 1 : -1;
}

/**
 * キーの割り当て。右から左なので、← が次、→ が前。
 *
 * @param {string} key event.key。
 * @return {string|null} next / prev / first / last。関係ないキーは null。
 */
export function keyAction( key ) {
	return { ArrowLeft: 'next', ArrowRight: 'prev', Home: 'first', End: 'last' }[ key ] || null;
}

/**
 * 「予告」の印を隠すか (発売日時を過ぎた HTML がキャッシュから出ても印が残らないように)。
 *
 * @param {string} releaseAt data-release-at (ISO 8601)。
 * @param {number} now       今 (ミリ秒)。
 * @return {boolean|null} 読めない日時なら null (触らない)。
 */
export function upcomingHidden( releaseAt, now ) {
	const at = Date.parse( releaseAt );
	if ( Number.isNaN( at ) ) {
		return null;
	}
	return at <= now;
}
