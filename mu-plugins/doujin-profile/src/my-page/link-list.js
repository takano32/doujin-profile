/**
 * リンクの一覧の編集 (サイトのリンク集 shops / accounts と、作品の販売先リンクで共用)。
 *
 * 1 件はサービス種別・URL (https: のみ)・表示名・アフィリエイトの有無 (includes/data.php の doujin_profile_link_schema())。
 * 足す・消す・並べ替え (つまみをドラッグ、← → ボタン)。保存は呼ぶ側 (標準の REST)。
 */

import { Button, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

import { data, move } from './util';
import { useSortable } from './sortable';

const EMPTY_LINK = { service: 'other', url: '', label: '', sponsored: false };

/**
 * 保存の前に、URL が空の行を落とす (足しただけの行)。
 *
 * @param {Array} links リンク。
 * @return {Array}
 */
export function cleanLinks( links ) {
	return ( links || [] ).filter( ( link ) => link && String( link.url || '' ).trim() !== '' );
}

/**
 * 保存できない行があれば、その説明を返す (REST のスキーマが https: 以外を弾くため、先に知らせる)。
 *
 * @param {Array}  links リンク。
 * @param {string} where どの一覧か (文に入れる)。
 * @return {string|null}
 */
export function findBadLink( links, where ) {
	const bad = cleanLinks( links ).find( ( link ) => ! /^https:\/\/\S+$/.test( String( link.url ).trim() ) );
	if ( ! bad ) {
		return null;
	}
	return `${ where }の URL は https:// から始まるように入れてください (${ bad.url })。`;
}

/**
 * リンクの一覧。
 *
 * @param {Object}   props
 * @param {Array}    props.links    リンク。
 * @param {Function} props.onChange 新しい一覧を受け取る。
 * @param {string}   props.addLabel 「足す」ボタンの文言。
 * @param {boolean}  props.disabled 保存中など。
 */
export function LinkList( { links, onChange, addLabel = 'リンクを足す', disabled = false } ) {
	const list = Array.isArray( links ) ? links : [];
	const max = data.maxLinks || 30;
	const services = data.services || { other: 'その他' };
	const options = Object.keys( services ).map( ( value ) => ( { value, label: services[ value ] } ) );
	const ref = useSortable( ( from, to ) => onChange( move( list, from, to ) ), { handle: '.doujin-profile-drag-handle' } );

	const update = ( index, patch ) => onChange( list.map( ( link, i ) => ( i === index ? { ...link, ...patch } : link ) ) );
	const remove = ( index ) => onChange( list.filter( ( _, i ) => i !== index ) );

	return (
		<div className="doujin-profile-link-list">
			<ul ref={ ref } className="doujin-profile-link-rows">
				{ list.map( ( link, index ) => (
					// 行に ID は無いので位置が key。並べ替えでは DOM を戻してから描き直すので問題ない (sortable.js)。
					<li key={ index } className="doujin-profile-link-row">
						<div className="doujin-profile-row-tools">
							<Button
								className="doujin-profile-drag-handle"
								icon="move"
								label="ドラッグで並べ替え"
								showTooltip={ false }
								disabled={ disabled }
							/>
							<Button
								icon="arrow-left-alt2"
								label="前へ"
								onClick={ () => onChange( move( list, index, index - 1 ) ) }
								disabled={ disabled || index === 0 }
							/>
							<Button
								icon="arrow-right-alt2"
								label="後ろへ"
								onClick={ () => onChange( move( list, index, index + 1 ) ) }
								disabled={ disabled || index === list.length - 1 }
							/>
							<span className="doujin-profile-row-number">{ index + 1 }</span>
							<Button
								icon="no-alt"
								label="このリンクを消す"
								isDestructive
								onClick={ () => remove( index ) }
								disabled={ disabled }
								className="doujin-profile-row-remove"
							/>
						</div>
						<div className="doujin-profile-link-fields">
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label="サービス"
								value={ link.service || 'other' }
								options={ options }
								onChange={ ( service ) => update( index, { service } ) }
								disabled={ disabled }
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label="URL"
								type="url"
								placeholder="https://"
								maxLength={ 2000 }
								value={ link.url || '' }
								onChange={ ( url ) => update( index, { url } ) }
								disabled={ disabled }
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label="表示名 (空ならサービス名)"
								maxLength={ 100 }
								value={ link.label || '' }
								onChange={ ( label ) => update( index, { label } ) }
								disabled={ disabled }
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label="アフィリエイトのリンク"
								help="オンにすると、表示に「PR」などの注記と rel=sponsored が付きます。"
								checked={ !! link.sponsored }
								onChange={ ( sponsored ) => update( index, { sponsored } ) }
								disabled={ disabled }
							/>
						</div>
					</li>
				) ) }
			</ul>
			{ ! list.length && <p className="description">まだリンクがありません。</p> }
			<Button
				variant="secondary"
				__next40pxDefaultSize
				onClick={ () => onChange( [ ...list, { ...EMPTY_LINK } ] ) }
				disabled={ disabled || list.length >= max }
			>
				{ addLabel }
			</Button>
			{ list.length >= max && <p className="description">リンクは { max } 件までです。</p> }
		</div>
	);
}
