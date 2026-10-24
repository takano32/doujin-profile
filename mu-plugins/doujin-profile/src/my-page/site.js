/**
 * サイト設定 (REST の wp/v2/settings = core-data の root/site) の保存。プロフィールとリンク集で共用。
 *
 * どちらのカードも同じ 1 つのレコードを編集するので、「保存」はどちらから押しても両方の変更を保存する。
 */

import { useSelect, useDispatch, useRegistry } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import { errorText, useNotice } from './util';

/**
 * @param {Function} [beforeSave] 保存の前の検査。エラーの文を返すと保存しない。
 * @return {Object} { isLoaded, hasEdits, isSaving, save, notice, clearNotice }。
 */
export function useSiteSave( beforeSave ) {
	const registry = useRegistry();
	const { isLoaded, hasEdits, isSaving } = useSelect( ( select ) => {
		const { getEntityRecord, hasEditsForEntityRecord, isSavingEntityRecord } = select( coreStore );
		return {
			isLoaded: !! getEntityRecord( 'root', 'site' ),
			hasEdits: hasEditsForEntityRecord( 'root', 'site' ),
			isSaving: isSavingEntityRecord( 'root', 'site' ),
		};
	}, [] );
	const { saveEditedEntityRecord } = useDispatch( coreStore );
	const [ notice, setNotice, clearNotice ] = useNotice();

	const save = async () => {
		setNotice( null );
		const problem = beforeSave ? beforeSave() : null;
		if ( problem ) {
			setNotice( { status: 'error', text: problem } );
			return;
		}
		const saved = await saveEditedEntityRecord( 'root', 'site' );
		if ( saved ) {
			setNotice( { status: 'success', text: '保存しました。' } );
		} else {
			const error = registry.select( coreStore ).getLastEntitySaveError( 'root', 'site' );
			setNotice( { status: 'error', text: errorText( error, '保存できませんでした。もう一度お試しください。' ) } );
		}
	};

	return { isLoaded, hasEdits, isSaving, save, notice, clearNotice };
}
