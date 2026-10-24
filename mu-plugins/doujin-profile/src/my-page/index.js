/**
 * マイページ (spec §8) の最小の画面。
 *
 * この段では「WordPress 同梱の部品 + 標準 REST で読んで保存できる」芯を通すだけ:
 * サイト設定の blogname (サークル名) と blogdescription (リード文) を wp.coreData で読み、
 * 保存は wp/v2/settings へ (独自の保存口は無い)。リンク集・作品・サンプル画像は次の段で足す。
 *
 * WordPress 同梱の部品は externals (wp.*) なので、ビルド物には含まれない。
 */

import { createRoot, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import './my-page.css';

// PHP 側 (includes/my-page.php) が渡す、保存しないサイトの情報。
const data = window.doujinProfileData || {};

/**
 * プロフィール: サークル名とリード文。
 */
function ProfileCard() {
	const [ title, setTitle ] = useEntityProp( 'root', 'site', 'title' );
	const [ description, setDescription ] = useEntityProp( 'root', 'site', 'description' );
	const { isLoaded, hasEdits, isSaving } = useSelect( ( select ) => {
		const { getEntityRecord, hasEditsForEntityRecord, isSavingEntityRecord } = select( coreStore );
		return {
			isLoaded: !! getEntityRecord( 'root', 'site' ),
			hasEdits: hasEditsForEntityRecord( 'root', 'site' ),
			isSaving: isSavingEntityRecord( 'root', 'site' ),
		};
	}, [] );
	const { saveEditedEntityRecord } = useDispatch( coreStore );
	const [ notice, setNotice ] = useState( null );

	const save = async () => {
		setNotice( null );
		const saved = await saveEditedEntityRecord( 'root', 'site' );
		setNotice(
			saved
				? { status: 'success', text: '保存しました。' }
				: { status: 'error', text: '保存できませんでした。もう一度お試しください。' }
		);
	};

	return (
		<Card>
			<CardHeader>
				<h2 className="doujin-profile-card-title">プロフィール</h2>
			</CardHeader>
			<CardBody>
				{ ! isLoaded && <Spinner /> }
				{ isLoaded && (
					<>
						{ notice && (
							<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
								{ notice.text }
							</Notice>
						) }
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label="サークル名"
							value={ title || '' }
							onChange={ setTitle }
							disabled={ isSaving }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label="リード文"
							help="サイトの説明。トップページの見出しの下や、検索結果・SNS の説明文に使われます。"
							value={ description || '' }
							onChange={ setDescription }
							disabled={ isSaving }
							rows={ 3 }
						/>
						<div className="doujin-profile-actions">
							<Button
								variant="primary"
								__next40pxDefaultSize
								onClick={ save }
								disabled={ ! hasEdits || isSaving }
								isBusy={ isSaving }
							>
								保存
							</Button>
							{ data.homeUrl && <ExternalLink href={ data.homeUrl }>サイトを見る</ExternalLink> }
						</div>
					</>
				) }
			</CardBody>
		</Card>
	);
}

function MyPage() {
	return (
		<div className="doujin-profile-my-page">
			<h1>マイページ</h1>
			<ProfileCard />
			<p className="description">リンク集と作品の編集は、次の段で足します。</p>
		</div>
	);
}

const container = document.getElementById( 'doujin-profile-my-page' );
if ( container ) {
	createRoot( container ).render( <MyPage /> );
}
