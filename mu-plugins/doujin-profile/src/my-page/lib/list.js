/**
 * 並び (配列) の操作。WordPress の部品は読まない (vitest で試験する純粋なロジック)。
 */

/**
 * 配列の from 番目を to 番目へ動かした新しい配列。動かせない指定ならそのまま返す。
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
 * ID を重複を除いて末尾に足す (正の数だけ)。上限を超える分は捨てる。
 *
 * @param {number[]} list 今の並び。
 * @param {number[]} ids  足す ID。
 * @param {number}   max  上限。
 * @return {{ list: number[], overflow: boolean }} 新しい並びと、上限で捨てた分があったか。
 */
export function appendUnique( list, ids, max ) {
	const next = [ ...list ];
	let overflow = false;
	ids.forEach( ( id ) => {
		if ( ! ( id > 0 ) || next.includes( id ) ) {
			return;
		}
		if ( next.length >= max ) {
			overflow = true;
			return;
		}
		next.push( id );
	} );
	return { list: next, overflow };
}
