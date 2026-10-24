/**
 * 作品の一覧 (新しい順)。各行に書影・タイトル・状態 (下書き / 公開 / 予告)。「作品を追加」。
 *
 * 作品 = 投稿 (spec §6.0)。一覧は標準の wp/v2/posts (context=edit で下書きも)。
 * 「作品を追加」はその場で下書きの投稿を作ってから編集に入る (画像を上げるのに投稿 ID が要るため)。
 */

import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { Button, Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

import { StatusNotice, WORK_CARD_BLOCK, errorText, useNotice } from './util';
import { thumbnailUrl, useAttachments } from './media';

// 100 件が REST の上限。これを超える作品は、投稿一覧 (標準) で探す。
export const WORKS_QUERY = {
	per_page: 100,
	status: 'publish,draft,future,pending,private',
	orderby: 'date',
	order: 'desc',
};

const EMPTY = []; // 読み込み中の「まだ無い」。毎回 [] を作ると useSelect が変化と見るため。

/**
 * 作品の状態の印。予告は「公開 + 発売日時が未来」から導く (別の状態は持たない。spec §6.0)。
 *
 * @param {Object} work 投稿 (保存済みでも編集中でも)。
 * @return {{ key: string, label: string }}
 */
export function workStatus( work ) {
	const status = work && work.status;
	if ( 'publish' === status ) {
		const releaseAt = work.meta && work.meta.doujin_release_at;
		if ( releaseAt && new Date( releaseAt ) > new Date() ) {
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
 * @param {Object} work 投稿。
 * @return {string}
 */
export function workTitle( work ) {
	const title = work && work.title;
	const text = typeof title === 'string' ? title : ( title && ( title.raw || decodeEntities( title.rendered ) ) ) || '';
	return text.trim() || '(タイトルなし)';
}

/**
 * @param {Object}   props
 * @param {Function} props.onSelect 作品 (投稿 ID) を開く。
 */
export function WorksList( { onSelect } ) {
	const { works, hasResolved } = useSelect( ( select ) => {
		const { getEntityRecords, hasFinishedResolution } = select( coreStore );
		return {
			works: getEntityRecords( 'postType', 'post', WORKS_QUERY ) || EMPTY,
			hasResolved: hasFinishedResolution( 'getEntityRecords', [ 'postType', 'post', WORKS_QUERY ] ),
		};
	}, [] );
	const { byId: covers } = useAttachments( works.map( ( work ) => work.featured_media ).filter( Boolean ) );
	const { saveEntityRecord } = useDispatch( coreStore );
	const [ isCreating, setIsCreating ] = useState( false );
	const [ notice, setNotice, clearNotice ] = useNotice();

	const create = async () => {
		setIsCreating( true );
		setNotice( null );
		try {
			const created = await saveEntityRecord(
				'postType',
				'post',
				{ status: 'draft', title: '', content: WORK_CARD_BLOCK },
				{ throwOnError: true }
			);
			onSelect( created.id );
		} catch ( error ) {
			setNotice( { status: 'error', text: errorText( error, '作品を追加できませんでした。' ) } );
		}
		setIsCreating( false );
	};

	return (
		<Card>
			<CardHeader>
				<h2 className="doujin-profile-card-title">作品</h2>
				<Button variant="primary" __next40pxDefaultSize onClick={ create } isBusy={ isCreating } disabled={ isCreating }>
					作品を追加
				</Button>
			</CardHeader>
			<CardBody>
				<StatusNotice notice={ notice } onRemove={ clearNotice } />
				{ ! hasResolved && <Spinner /> }
				{ hasResolved && ! works.length && (
					<p className="description">まだ作品がありません。「作品を追加」から始めてください。</p>
				) }
				<ul className="doujin-profile-works">
					{ works.map( ( work ) => {
						const status = workStatus( work );
						const cover = thumbnailUrl( covers[ work.featured_media ], 'thumbnail' );
						return (
							<li key={ work.id }>
								<button type="button" className="doujin-profile-work-row" onClick={ () => onSelect( work.id ) }>
									<span className="doujin-profile-work-cover">
										{ cover ? <img src={ cover } alt="" loading="lazy" /> : <span className="doujin-profile-work-nocover">書影なし</span> }
									</span>
									<span className="doujin-profile-work-text">
										<span className="doujin-profile-work-title">{ workTitle( work ) }</span>
										{ work.meta && work.meta.doujin_release_label && (
											<span className="description">{ work.meta.doujin_release_label }</span>
										) }
									</span>
									<span className={ `doujin-profile-status doujin-profile-status-${ status.key }` }>{ status.label }</span>
								</button>
							</li>
						);
					} ) }
				</ul>
			</CardBody>
		</Card>
	);
}
