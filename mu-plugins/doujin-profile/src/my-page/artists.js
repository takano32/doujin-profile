/**
 * 作家 (分類 artist) の欄。候補つき・複数・その場で新規 (ブロックエディタのタグ欄と同じ流儀)。
 *
 * 投稿には用語の ID の配列 (artist) で付く。無い名前は標準の REST (wp/v2/artist) で用語を作ってから付ける。
 */

import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { FormTokenField } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

import { StatusNotice, errorText, useNotice } from './util';

// 候補は 100 件まで (REST の上限)。それ以上の作家がいるサークルは想定しない。
const TERMS_QUERY = { per_page: 100, orderby: 'name', order: 'asc', context: 'view' };
const EMPTY = [];

const MESSAGES = {
	added: '作家を足しました。',
	removed: '作家を外しました。',
	remove: 'この作家を外す',
	__experimentalInvalid: '使えない名前です。',
};

/**
 * @param {Object}   props
 * @param {number[]} props.value    付いている用語の ID。
 * @param {Function} props.onChange 新しい ID の配列を受け取る。
 * @param {boolean}  props.disabled 保存中など。
 */
export function ArtistField( { value, onChange, disabled } ) {
	const ids = Array.isArray( value ) ? value : [];
	const terms = useSelect( ( select ) => select( coreStore ).getEntityRecords( 'taxonomy', 'artist', TERMS_QUERY ) || EMPTY, [] );
	const { saveEntityRecord } = useDispatch( coreStore );
	const [ pending, setPending ] = useState( [] ); // 作成中の名前 (作り終わるまで欄に出しておく)
	const [ notice, setNotice, clearNotice ] = useNotice();

	const nameOf = ( term ) => decodeEntities( term.name );
	const findByName = ( name ) => terms.find( ( term ) => nameOf( term ).toLowerCase() === name.toLowerCase() );
	const tokens = ids.map( ( id ) => terms.find( ( term ) => term.id === id ) ).filter( Boolean ).map( nameOf ).concat( pending );

	const onTokensChange = async ( next ) => {
		const names = [ ...new Set( next.map( ( token ) => ( typeof token === 'string' ? token : token.value ).trim() ).filter( Boolean ) ) ];
		const nextIds = [];
		const toCreate = [];
		names.forEach( ( name ) => {
			const found = findByName( name );
			if ( found ) {
				nextIds.push( found.id );
			} else {
				toCreate.push( name );
			}
		} );
		onChange( nextIds );
		if ( ! toCreate.length ) {
			return;
		}

		setPending( toCreate );
		setNotice( null );
		for ( const name of toCreate ) {
			try {
				const term = await saveEntityRecord( 'taxonomy', 'artist', { name }, { throwOnError: true } );
				nextIds.push( term.id );
			} catch ( error ) {
				// 同じ名前がすでにある (大文字小文字や空白の違いなど) ときは、REST がその ID を返す。
				const existing = error && error.data && error.data.term_id;
				if ( existing ) {
					nextIds.push( existing );
				} else {
					setNotice( { status: 'error', text: `作家「${ name }」を登録できませんでした: ${ errorText( error, '' ) }` } );
				}
			}
			onChange( [ ...nextIds ] );
		}
		setPending( [] );
	};

	return (
		<>
			<StatusNotice notice={ notice } onRemove={ clearNotice } />
			<FormTokenField
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				__experimentalExpandOnFocus
				label="作家"
				value={ tokens }
				suggestions={ terms.map( nameOf ) }
				onChange={ onTokensChange }
				messages={ MESSAGES }
				disabled={ disabled || pending.length > 0 }
			/>
			<p className="description doujin-profile-field-help">
				名前を入れて Enter (スマホは改行) で足します。複数可。はじめての名前はその場で登録されます。
			</p>
		</>
	);
}
