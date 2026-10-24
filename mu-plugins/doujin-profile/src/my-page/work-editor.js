/**
 * 作品の編集 (spec §8.2)。
 *
 * 投稿の標準の項目 (title・excerpt・featured_media・status・artist) とメタ (doujin_*) を core-data で編集し、
 * 保存は標準の wp/v2/posts 1 回 (検査は includes/data.php の登録に任せる)。
 * 公開日時は保存時にサーバのルールで決まる (発売日時と今の早いほう)。
 */

import { useSelect, useDispatch, useRegistry } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

import { data, StatusNotice, WORK_CARD_BLOCK, errorText, useNotice } from './util';
import { ArtistField } from './artists';
import { ImportField } from './import';
import { CoverField } from './cover';
import { SamplesField } from './samples';
import { LinkList, cleanLinks, findBadLink } from './link-list';
import { workStatus } from './works';

/**
 * @param {Object}   props
 * @param {number}   props.id     作品の投稿 ID。
 * @param {Function} props.onBack 一覧へ戻る。
 */
export function WorkEditor( { id, onBack } ) {
	const registry = useRegistry();
	const { work, saved, hasResolved, hasEdits, isSaving, isDeleting } = useSelect(
		( select ) => {
			const store = select( coreStore );
			return {
				work: store.getEditedEntityRecord( 'postType', 'post', id ),
				saved: store.getEntityRecord( 'postType', 'post', id ),
				hasResolved: store.hasFinishedResolution( 'getEntityRecord', [ 'postType', 'post', id ] ),
				hasEdits: store.hasEditsForEntityRecord( 'postType', 'post', id ),
				isSaving: store.isSavingEntityRecord( 'postType', 'post', id ),
				isDeleting: store.isDeletingEntityRecord( 'postType', 'post', id ),
			};
		},
		[ id ]
	);
	const { editEntityRecord, saveEditedEntityRecord, deleteEntityRecord } = useDispatch( coreStore );
	const [ notice, setNotice, clearNotice ] = useNotice();

	if ( hasResolved && ! saved ) {
		return (
			<Card>
				<CardBody>
					<p>この作品は見つかりませんでした (消されたか、開く権限がありません)。</p>
					<Button variant="secondary" __next40pxDefaultSize onClick={ onBack }>
						作品の一覧へ戻る
					</Button>
				</CardBody>
			</Card>
		);
	}
	if ( ! hasResolved || ! work ) {
		return (
			<Card>
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	const meta = work.meta || {};
	const locked = isSaving || isDeleting;
	const edit = ( patch ) => editEntityRecord( 'postType', 'post', id, patch );
	const setMeta = ( key, value ) => edit( { meta: { [ key ]: value } } ); // meta は部分的に書いてもマージされる。
	const isPublic = 'publish' === work.status;
	const status = workStatus( work );

	const save = async () => {
		setNotice( null );
		const problem = findBadLink( meta.doujin_shop_links, '販売先リンク' );
		if ( problem ) {
			setNotice( { status: 'error', text: problem } );
			return;
		}
		const edits = { meta: { doujin_shop_links: cleanLinks( meta.doujin_shop_links ) } };
		if ( ! String( work.content || '' ).trim() ) {
			edits.content = WORK_CARD_BLOCK; // 本文が空なら作品カードを 1 つ置く (spec §6.2)。
		}
		edit( edits );
		const result = await saveEditedEntityRecord( 'postType', 'post', id );
		if ( result ) {
			setNotice( { status: 'success', text: isPublic ? '保存しました。公開ページに反映されています。' : '下書きとして保存しました。' } );
		} else {
			const error = registry.select( coreStore ).getLastEntitySaveError( 'postType', 'post', id );
			setNotice( { status: 'error', text: errorText( error, '保存できませんでした。もう一度お試しください。' ) } );
		}
	};

	const remove = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'この作品を削除しますか？\n(投稿のゴミ箱へ移します。画像はメディアに残ります)' ) ) {
			return;
		}
		setNotice( null );
		const done = await deleteEntityRecord( 'postType', 'post', id );
		if ( done ) {
			onBack();
		} else {
			const error = registry.select( coreStore ).getLastEntityDeleteError( 'postType', 'post', id );
			setNotice( { status: 'error', text: errorText( error, '削除できませんでした。' ) } );
		}
	};

	// 書影の取り込みの結果 (import.js)。アイキャッチはサーバが設定済み。リンク・題名・発売日時は編集として受け、「保存」で書く。
	const onImported = ( result ) => {
		const edits = { featured_media: result.featured_media, meta: {} };
		const links = Array.isArray( meta.doujin_shop_links ) ? meta.doujin_shop_links : [];
		if ( result.shop_link && ! links.some( ( link ) => link.url === result.shop_link.url ) ) {
			edits.meta.doujin_shop_links = [ ...links, result.shop_link ];
		}
		if ( result.release_at && ! meta.doujin_release_at ) {
			edits.meta.doujin_release_at = result.release_at;
		}
		if ( result.title && ! String( work.title || '' ).trim() ) {
			edits.title = result.title;
		}
		edit( edits );
	};

	// サンプルの取り込み (import.js)。1 枚ずつ続けて届くので、閉じ込めた meta ではなくストアの今の並びに足す。
	// まだ入っていない ID だけを末尾に足し、足したあとの枚数を返す (上限の判定は呼ぶ側)。
	const appendSamples = ( newIds ) => {
		const current = registry.select( coreStore ).getEditedEntityRecord( 'postType', 'post', id );
		const list = current && current.meta && Array.isArray( current.meta.doujin_sample_ids ) ? current.meta.doujin_sample_ids : [];
		const max = data.maxSamples || 80;
		const next = [ ...list ];
		newIds.forEach( ( sampleId ) => {
			if ( sampleId > 0 && ! next.includes( sampleId ) && next.length < max ) {
				next.push( sampleId );
			}
		} );
		if ( next.length !== list.length ) {
			setMeta( 'doujin_sample_ids', next );
		}
		return next.length;
	};

	const viewUrl = saved && saved.link ? ( isPublic ? saved.link : addQueryArgs( saved.link, { preview: true } ) ) : '';

	return (
		<Card>
			<CardHeader>
				<Button variant="tertiary" __next40pxDefaultSize icon="arrow-left-alt2" onClick={ onBack } disabled={ locked }>
					作品の一覧
				</Button>
				<span className={ `doujin-profile-status doujin-profile-status-${ status.key }` }>{ status.label }</span>
			</CardHeader>
			<CardBody>
				<StatusNotice notice={ notice } onRemove={ clearNotice } />
				<ImportField workId={ id } title={ work.title } onCover={ onImported } onSamples={ appendSamples } disabled={ locked } />
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label="タイトル"
					value={ work.title || '' }
					onChange={ ( title ) => edit( { title } ) }
					disabled={ locked }
				/>
				<ArtistField value={ work.artist } onChange={ ( artist ) => edit( { artist } ) } disabled={ locked } />
				<TextareaControl
					__nextHasNoMarginBottom
					label="あらすじ"
					help="作品ページに出ます。検索結果や SNS の説明文にも使われます。"
					value={ work.excerpt || '' }
					onChange={ ( excerpt ) => edit( { excerpt } ) }
					rows={ 5 }
					disabled={ locked }
				/>
				<CoverField
					workId={ id }
					value={ work.featured_media || 0 }
					onChange={ ( featured_media ) => edit( { featured_media } ) }
					disabled={ locked }
				/>
				<div className="doujin-profile-release">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label="発売日時"
						type="datetime-local"
						help="未来の日時のまま公開すると「予告」の印が付きます。並びはこの日時 (と今の早いほう) で決まります。"
						value={ String( meta.doujin_release_at || '' ).slice( 0, 16 ) }
						onChange={ ( value ) => setMeta( 'doujin_release_at', value ) }
						disabled={ locked }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label="発売日の表記"
						help="空なら発売日時から自動で作ります。「2026年5月下旬」のような書き方にしたいときに。"
						maxLength={ 200 }
						value={ meta.doujin_release_label || '' }
						onChange={ ( value ) => setMeta( 'doujin_release_label', value ) }
						disabled={ locked }
					/>
				</div>
				<section className="doujin-profile-section">
					<h3>販売先リンク</h3>
					<p className="description">この作品の販売ページ。発売前は予約・予告のページ、発売後は手で直します。</p>
					<LinkList
						links={ meta.doujin_shop_links || [] }
						onChange={ ( links ) => setMeta( 'doujin_shop_links', links ) }
						addLabel="販売先を足す"
						disabled={ locked }
					/>
				</section>
				<SamplesField
					workId={ id }
					ids={ meta.doujin_sample_ids || [] }
					onChange={ ( ids ) => setMeta( 'doujin_sample_ids', ids ) }
					disabled={ locked }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label="公開する"
					help={ isPublic ? 'オフにして保存すると下書きに戻ります (自分だけが見られます)。' : 'オンにして保存すると、サイトに出ます。' }
					checked={ isPublic }
					onChange={ ( checked ) => edit( { status: checked ? 'publish' : 'draft' } ) }
					disabled={ locked }
				/>
				<div className="doujin-profile-actions doujin-profile-editor-actions">
					<Button variant="primary" __next40pxDefaultSize onClick={ save } disabled={ ! hasEdits || locked } isBusy={ isSaving }>
						保存
					</Button>
					{ viewUrl && <ExternalLink href={ viewUrl }>{ isPublic ? 'この作品を見る' : 'この作品を見る (下書き)' }</ExternalLink> }
					<Button variant="tertiary" __next40pxDefaultSize isDestructive onClick={ remove } disabled={ locked } isBusy={ isDeleting } className="doujin-profile-delete">
						削除
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
