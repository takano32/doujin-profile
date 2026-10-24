/**
 * 書影 (アイキャッチ画像)。ドロップ / メディアから選ぶ (spec §8.3)。作品番号からの取り込みは import.js。
 */

import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { MediaUpload } from '@wordpress/media-utils';
import { BaseControl, Button, DropZone, Spinner } from '@wordpress/components';

import { StatusNotice, useNotice } from './util';
import { adoptAttachment, thumbnailUrl, uploadImages } from './media';

/**
 * @param {Object}   props
 * @param {number}   props.workId   作品の投稿 ID。
 * @param {number}   props.value    アイキャッチの添付 ID (0 なら無し)。
 * @param {Function} props.onChange 新しい添付 ID を受け取る。
 * @param {boolean}  props.disabled 保存中など。
 */
export function CoverField( { workId, value, onChange, disabled } ) {
	const media = useSelect( ( select ) => ( value ? select( coreStore ).getMedia( value ) : null ), [ value ] );
	const [ busy, setBusy ] = useState( false );
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

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id="doujin-profile-cover"
			label="書影"
			help="表紙の画像。ここにドロップするか、メディアから選びます。FANZA・DLsite で販売中なら、上の「作品番号から取り込む」でも入ります。"
		>
			<StatusNotice notice={ notice } onRemove={ clearNotice } />
			<div className="doujin-profile-cover-field">
				<DropZone label="ここに画像をドロップ" onFilesDrop={ onDrop } />
				<div className="doujin-profile-cover-preview">
					{ busy && <Spinner /> }
					{ ! busy && value > 0 && ( thumbnailUrl( media, 'medium' ) ? <img src={ thumbnailUrl( media, 'medium' ) } alt="" /> : <Spinner /> ) }
					{ ! busy && ! value && <span className="description">書影はまだありません</span> }
				</div>
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
			</div>
		</BaseControl>
	);
}
