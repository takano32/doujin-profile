/**
 * 書影 (アイキャッチ画像)。ドロップ / メディアから選ぶ / 作品番号から取り込む (spec §8.3)。
 *
 * 取り込みだけが独自の REST (includes/cover-import.php)。サーバが画像をメディアに保存してアイキャッチにし、
 * 販売ページのリンク・題名・発売日時を返すので、ここでは投稿の編集 (未保存) として受け取り、持ち主が「保存」で書く。
 */

import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';
import { MediaUpload } from '@wordpress/media-utils';
import { BaseControl, Button, DropZone, Spinner, TextControl } from '@wordpress/components';

import { data, StatusNotice, errorText, useNotice } from './util';
import { adoptAttachment, thumbnailUrl, uploadImages } from './media';

/**
 * @param {Object}   props
 * @param {number}   props.workId     作品の投稿 ID。
 * @param {number}   props.value      アイキャッチの添付 ID (0 なら無し)。
 * @param {Function} props.onChange   新しい添付 ID を受け取る。
 * @param {Function} props.onImported 取り込みの結果 (REST の応答) を受け取る。
 * @param {boolean}  props.disabled   保存中など。
 */
export function CoverField( { workId, value, onChange, onImported, disabled } ) {
	const media = useSelect( ( select ) => ( value ? select( coreStore ).getMedia( value ) : null ), [ value ] );
	const [ busy, setBusy ] = useState( false );
	const [ productId, setProductId ] = useState( '' );
	const [ notice, setNotice, clearNotice ] = useNotice();
	const locked = disabled || busy;

	const onDrop = async ( files ) => {
		setBusy( true );
		setNotice( null );
		const { ids, errors } = await uploadImages( Array.from( files ).slice( 0, 1 ), workId );
		if ( ids[ 0 ] ) {
			onChange( ids[ 0 ] );
		}
		if ( errors.length ) {
			setNotice( { status: 'error', text: errors.join( ' / ' ) } );
		}
		setBusy( false );
	};

	const importCover = async () => {
		setBusy( true );
		setNotice( null );
		try {
			const result = await apiFetch( {
				path: ( data.coverImportPath || '/doujin-profile/v1/works/%d/cover' ).replace( '%d', String( workId ) ),
				method: 'POST',
				data: { product_id: productId.trim() },
			} );
			onImported( result );
			setProductId( '' );
			setNotice( { status: 'success', text: '書影を取り込みました。販売先リンクも足したので、内容を確かめて「保存」を押してください。' } );
		} catch ( error ) {
			setNotice( { status: 'error', text: errorText( error, '取り込めませんでした。しばらくしてからもう一度お試しください。' ) } );
		}
		setBusy( false );
	};

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id="doujin-profile-cover"
			label="書影"
			help="表紙の画像。ここにドロップするか、メディアから選びます。FANZA・DLsite で販売中なら、作品番号から取り込めます。"
		>
			<StatusNotice notice={ notice } onRemove={ clearNotice } />
			<div className="doujin-profile-cover-field">
				<DropZone label="ここに画像をドロップ" onFilesDrop={ onDrop } />
				<div className="doujin-profile-cover-preview">
					{ busy && <Spinner /> }
					{ ! busy && value > 0 && ( thumbnailUrl( media, 'medium' ) ? <img src={ thumbnailUrl( media, 'medium' ) } alt="" /> : <Spinner /> ) }
					{ ! busy && ! value && <span className="description">書影はまだありません</span> }
				</div>
				<div className="doujin-profile-cover-tools">
					<div className="doujin-profile-actions">
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ value || undefined }
							title="書影の画像"
							onSelect={ ( attachment ) => {
								adoptAttachment( attachment, workId );
								onChange( attachment.id );
							} }
							render={ ( { open } ) => (
								<Button variant="secondary" __next40pxDefaultSize onClick={ open } disabled={ locked }>
									{ value > 0 ? '画像を変える' : '画像を選ぶ' }
								</Button>
							) }
						/>
						{ value > 0 && (
							<Button variant="tertiary" __next40pxDefaultSize isDestructive onClick={ () => onChange( 0 ) } disabled={ locked }>
								外す
							</Button>
						) }
					</div>
					<div className="doujin-profile-import">
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label="作品番号から取り込む"
							help="FANZA は d_ で始まる番号 (例 d_737824)、DLsite は RJ などで始まる番号 (例 RJ01634826)。DLsite はタイトルと発売日時も、空なら埋めます。"
							value={ productId }
							onChange={ setProductId }
							placeholder="d_737824 / RJ01634826"
							disabled={ locked }
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' && productId.trim() ) {
									event.preventDefault();
									importCover();
								}
							} }
						/>
						<Button
							variant="secondary"
							__next40pxDefaultSize
							onClick={ importCover }
							disabled={ locked || ! productId.trim() }
							isBusy={ busy }
						>
							書影を取り込む
						</Button>
					</div>
				</div>
			</div>
		</BaseControl>
	);
}
