/**
 * サンプル画像 (メタ doujin_sample_ids。配列の順 = 表示の並び)。
 *
 * まとめてドロップ / ファイルを選ぶ (複数選択) / メディアから選ぶ / 作品番号から取り込む (import.js)。初期の並びはファイル名の自然順。
 * ドラッグで並べ替え (SortableJS)、← → ボタンも。1 枚ずつ外す (画像はメディアに残る)。
 * 上げた画像の親は作品 (アップロード時に post を付ける。モーダルで上げた分はあとから付ける)。
 */

import { useRef, useState } from '@wordpress/element';
import { MediaUpload } from '@wordpress/media-utils';
import { BaseControl, Button, DropZone, Spinner } from '@wordpress/components';

import { data, move, StatusNotice, useNotice } from './util';
import { adoptAttachment, sortByFileName, thumbnailUrl, uploadImages, useAttachments } from './media';
import { useSortable } from './sortable';

/**
 * @param {Object}   props
 * @param {number}   props.workId   作品の投稿 ID。
 * @param {number[]} props.ids      添付 ID の並び。
 * @param {Function} props.onChange 新しい並びを受け取る。
 * @param {boolean}  props.disabled 保存中など。
 */
export function SamplesField( { workId, ids, onChange, disabled } ) {
	const list = Array.isArray( ids ) ? ids : [];
	const max = data.maxSamples || 80;
	const { byId } = useAttachments( list );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice, clearNotice ] = useNotice();
	const ref = useSortable( ( from, to ) => onChange( move( list, from, to ) ), { filter: 'button' } );
	const fileInput = useRef( null ); // 「ファイルを選んで追加」。複数選択を確かに付けるため、自前の <input type="file" multiple>。
	const locked = disabled || busy;

	// 重複を除いて末尾に足す。上限を超える分は捨てて知らせる。
	const append = ( newIds ) => {
		const next = [ ...list ];
		newIds.forEach( ( id ) => {
			if ( id > 0 && ! next.includes( id ) ) {
				next.push( id );
			}
		} );
		if ( next.length > max ) {
			setNotice( { status: 'warning', text: `サンプル画像は ${ max } 枚までです。超えた分は入れていません。` } );
		}
		onChange( next.slice( 0, max ) );
	};

	const upload = async ( files ) => {
		if ( ! files || ! files.length ) {
			return;
		}
		setBusy( true );
		setNotice( null );
		const { ids: uploaded, errors } = await uploadImages( files, workId );
		append( uploaded );
		if ( errors.length ) {
			setNotice( { status: 'error', text: `上げられなかった画像があります: ${ errors.join( ' / ' ) }` } );
		}
		setBusy( false );
	};

	const onSelectFromLibrary = ( selection ) => {
		const picked = Array.isArray( selection ) ? selection : [ selection ];
		picked.forEach( ( attachment ) => adoptAttachment( attachment, workId ) );
		append( sortByFileName( picked, ( attachment ) => attachment.filename || attachment.title ).map( ( attachment ) => attachment.id ) );
	};

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id="doujin-profile-samples"
			label={ `サンプル画像 (${ list.length } / ${ max } 枚)` }
			help="ファイル名の順 (01, 02, …) で足されます。並べ替えはドラッグ (スマホは長押ししてから動かす) か ← → で。外しても画像はメディアに残ります。"
		>
			<StatusNotice notice={ notice } onRemove={ clearNotice } />
			<div className="doujin-profile-samples-field">
				<DropZone label="ここに画像をまとめてドロップ" onFilesDrop={ upload } />
				<ul ref={ ref } className="doujin-profile-samples">
					{ list.map( ( id, index ) => (
						<li key={ id } className="doujin-profile-sample">
							{ thumbnailUrl( byId[ id ], 'thumbnail' ) ? (
								<img src={ thumbnailUrl( byId[ id ], 'thumbnail' ) } alt="" loading="lazy" />
							) : (
								<span className="doujin-profile-sample-loading" />
							) }
							<span className="doujin-profile-sample-number">{ index + 1 }</span>
							<div className="doujin-profile-sample-tools">
								<Button
									icon="arrow-left-alt2"
									label="前へ"
									size="small"
									onClick={ () => onChange( move( list, index, index - 1 ) ) }
									disabled={ locked || index === 0 }
								/>
								<Button
									icon="arrow-right-alt2"
									label="後ろへ"
									size="small"
									onClick={ () => onChange( move( list, index, index + 1 ) ) }
									disabled={ locked || index === list.length - 1 }
								/>
								<Button
									icon="no-alt"
									label="この画像を外す"
									size="small"
									isDestructive
									onClick={ () => onChange( list.filter( ( other ) => other !== id ) ) }
									disabled={ locked }
								/>
							</div>
						</li>
					) ) }
				</ul>
				{ busy && (
					<p className="doujin-profile-uploading">
						<Spinner /> 画像を上げています…
					</p>
				) }
				{ ! list.length && ! busy && <p className="description">サンプル画像はまだありません。</p> }
				<div className="doujin-profile-actions">
					<Button
						variant="secondary"
						__next40pxDefaultSize
						disabled={ locked || list.length >= max }
						onClick={ () => fileInput.current && fileInput.current.click() }
					>
						ファイルを選んで追加
					</Button>
					<input
						ref={ fileInput }
						type="file"
						accept="image/*"
						multiple
						hidden
						onChange={ ( event ) => {
							const files = Array.from( event.target.files || [] );
							event.target.value = '';
							upload( files );
						} }
					/>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						multiple
						value={ list }
						title="サンプル画像"
						onSelect={ onSelectFromLibrary }
						render={ ( { open } ) => (
							<Button variant="tertiary" __next40pxDefaultSize onClick={ open } disabled={ locked || list.length >= max }>
								メディアから選ぶ
							</Button>
						) }
					/>
				</div>
			</div>
		</BaseControl>
	);
}
