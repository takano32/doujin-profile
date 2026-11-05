/**
 * 発売日時 (メタ doujin_release_at。ISO 8601) と入力欄 (datetime-local) のやり取り。
 */

/**
 * 保存されている ISO 8601 (例 2026-05-29T00:00:00+09:00) を datetime-local の値 (分まで) にする。
 *
 * @param {*} value メタの値。
 * @return {string}
 */
export function releaseInput( value ) {
	return String( value || '' ).slice( 0, 16 );
}
