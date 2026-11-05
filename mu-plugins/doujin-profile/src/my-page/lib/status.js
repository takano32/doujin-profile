/**
 * 作品の状態の印とタイトルの文 (一覧と編集で共用)。
 */

/**
 * 作品の状態の印。予告は「公開 + 発売日時が未来」から導く (別の状態は持たない。spec §6.0)。
 *
 * @param {Object} work  投稿 (保存済みでも編集中でも)。
 * @param {number} [now] 今 (ミリ秒)。省くと現在時刻。
 * @return {{ key: string, label: string }}
 */
export function workStatus( work, now = Date.now() ) {
	const status = work && work.status;
	if ( 'publish' === status ) {
		const releaseAt = work.meta && work.meta.doujin_release_at;
		if ( releaseAt && new Date( releaseAt ).getTime() > now ) {
			return { key: 'upcoming', label: '予告' };
		}
		return { key: 'publish', label: '公開' };
	}
	if ( 'future' === status ) {
		return { key: 'future', label: '予約' }; // 保存のルールで publish になるはずだが、念のため。
	}
	if ( 'private' === status ) {
		return { key: 'private', label: '非公開' };
	}
	return { key: 'draft', label: '下書き' };
}

/**
 * 一覧のタイトル (REST の一覧は title が { raw, rendered })。
 *
 * @param {Object}   work     投稿。
 * @param {Function} [decode] rendered の HTML 実体を戻す (wp.htmlEntities.decodeEntities)。省くとそのまま。
 * @return {string}
 */
export function workTitle( work, decode = ( text ) => text ) {
	const title = work && work.title;
	const text = typeof title === 'string' ? title : ( title && ( title.raw || decode( title.rendered || '' ) ) ) || '';
	return text.trim() || '(タイトルなし)';
}
