/**
 * 作品番号からの取り込み (spec §8.3)。書影とサンプル画像。
 *
 * 独自の REST (includes/import/rest.php) を呼ぶ唯一の所。書影はサーバがメディアに保存してアイキャッチにし、
 * 販売ページのリンク・題名・発売日時を返す。サンプルはまず枚数を聞き、1 枚ずつ取る (さくらの実行時間の上限に収めるため)。
 * どちらも、投稿に書く分 (リンク・題名・発売日時・サンプルの並び) は親が未保存の編集として受け、持ち主が「保存」で書く。
 * 応答の読み方と知らせの文は lib/import.js。
 */

import { useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Button, TextControl } from '@wordpress/components';

import { data, StatusNotice, useNotice } from './util';
import { errorText } from './lib/errors';
import { importPath, importedIds, samplesNotice } from './lib/import';

/**
 * @param {Object}   props
 * @param {number}   props.workId    作品の投稿 ID。
 * @param {string}   props.title     入力中のタイトル (FANZA の添付の題名に使う)。
 * @param {Function} props.onCover   書影の取り込みの結果 (REST の応答) を受け取る。
 * @param {Function} props.onSamples 取り込んだサンプルの添付 ID の配列を受け取り、足したあとの枚数を返す。
 * @param {boolean}  props.disabled  保存中など。
 */
export function ImportField( { workId, title, onCover, onSamples, disabled } ) {
	const [ productId, setProductId ] = useState( '' );
	const [ busy, setBusy ] = useState( '' ); // '' | 'cover' | 'samples'
	const [ progress, setProgress ] = useState( null ); // { done, total }
	const [ notice, setNotice, clearNotice ] = useNotice();
	const cancelled = useRef( false );
	const max = data.maxSamples || 80;
	const id = productId.trim();
	const locked = disabled || !! busy;
	const common = { product_id: id, title: String( title || '' ).trim() };

	const importCover = async () => {
		setBusy( 'cover' );
		setNotice( null );
		try {
			const result = await apiFetch( {
				path: importPath( data.coverImportPath || '/doujin-profile/v1/works/%d/cover', workId ),
				method: 'POST',
				data: common,
			} );
			onCover( result );
			setNotice( { status: 'success', text: '書影を取り込みました。販売先リンクも足したので、内容を確かめて「保存」を押してください。' } );
		} catch ( error ) {
			setNotice( { status: 'error', text: errorText( error, '取り込めませんでした。しばらくしてからもう一度お試しください。' ) } );
		}
		setBusy( '' );
	};

	const importSamples = async () => {
		const path = importPath( data.sampleImportPath || '/doujin-profile/v1/works/%d/samples', workId );
		setBusy( 'samples' );
		setNotice( null );
		cancelled.current = false;
		let done = 0;
		let total = 0;
		try {
			const list = await apiFetch( { path: addQueryArgs( path, common ) } );
			const samples = Array.isArray( list.samples ) ? list.samples : [];
			total = samples.length;
			if ( ! total ) {
				setNotice( { status: 'warning', text: 'この作品のサンプル画像は見つかりませんでした。' } );
			}
			// 取り込み済みの分はそのまま並びに足し、残りを 1 枚ずつ取る。
			let count = onSamples( importedIds( samples ) );
			let full = false;
			for ( const sample of samples ) {
				if ( cancelled.current ) {
					break;
				}
				if ( ! sample.attachment_id ) {
					if ( count >= max ) {
						full = true;
						break;
					}
					const result = await apiFetch( { path, method: 'POST', data: { ...common, index: sample.index } } );
					count = onSamples( [ result.attachment_id ] );
				}
				done += 1;
				setProgress( { done, total } );
			}
			const summary = samplesNotice( { done, total, full, max } );
			if ( summary ) {
				setNotice( summary );
			}
		} catch ( error ) {
			const partial = done ? ` (${ done } / ${ total } 枚までは取り込めています)` : '';
			setNotice( { status: 'error', text: errorText( error, '取り込めませんでした。しばらくしてからもう一度お試しください。' ) + partial } );
		}
		setBusy( '' );
		setProgress( null );
	};

	return (
		<section className="doujin-profile-section doujin-profile-import">
			<h3>作品番号から取り込む</h3>
			<p className="description">
				FANZA・DLsite で販売中なら、作品番号で書影 (と販売先リンク。DLsite はタイトル・発売日時も) やサンプル画像を取り込めます。取り込んだものは「保存」で確定します。
			</p>
			<StatusNotice notice={ notice } onRemove={ clearNotice } />
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label="作品番号"
				help="FANZA は d_ で始まる番号 (例 d_737824)、DLsite は RJ などで始まる番号 (例 RJ01634826)。"
				value={ productId }
				onChange={ setProductId }
				placeholder="d_737824 / RJ01634826"
				disabled={ locked }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' && id ) {
						event.preventDefault();
						importCover();
					}
				} }
			/>
			<div className="doujin-profile-actions">
				<Button variant="secondary" __next40pxDefaultSize onClick={ importCover } disabled={ locked || ! id } isBusy={ busy === 'cover' }>
					書影を取り込む
				</Button>
				<Button variant="secondary" __next40pxDefaultSize onClick={ importSamples } disabled={ locked || ! id } isBusy={ busy === 'samples' }>
					サンプル画像を取り込む
				</Button>
				{ busy === 'samples' && (
					<>
						<span className="description" role="status">
							{ progress ? `${ progress.done } / ${ progress.total } 枚…` : '枚数を調べています…' }
						</span>
						<Button variant="tertiary" __next40pxDefaultSize onClick={ () => ( cancelled.current = true ) }>
							中止
						</Button>
					</>
				) }
			</div>
		</section>
	);
}
