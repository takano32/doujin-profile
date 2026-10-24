/**
 * リンク集: サイト設定 doujin_profile_links の shops[] (販売ページ) と accounts[] (お知らせ・活動情報)。
 * 保存は標準の wp/v2/settings (登録と検査は includes/data.php)。
 */

import { useEntityProp } from '@wordpress/core-data';
import { Button, Card, CardBody, CardHeader, Spinner } from '@wordpress/components';

import { StatusNotice } from './util';
import { LinkList, cleanLinks, findBadLink } from './link-list';
import { useSiteSave } from './site';

const LISTS = [
	{ key: 'shops', title: '販売ページ', help: 'FANZA・DLsite・BOOTH など、サークルのページへのリンク。' },
	{ key: 'accounts', title: 'お知らせ・活動情報', help: 'X・pixiv・Bluesky など、ふだん活動している所へのリンク。' },
];

export function LinksCard() {
	const [ links, setLinks ] = useEntityProp( 'root', 'site', 'doujin_profile_links' );
	const value = links && typeof links === 'object' ? links : {};

	const beforeSave = () => {
		for ( const list of LISTS ) {
			const problem = findBadLink( value[ list.key ], `「${ list.title }」` );
			if ( problem ) {
				return problem;
			}
		}
		// URL が空の行 (足しただけの行) は落として保存する。
		setLinks( {
			shops: cleanLinks( value.shops ),
			accounts: cleanLinks( value.accounts ),
		} );
		return null;
	};
	const { isLoaded, hasEdits, isSaving, save, notice, clearNotice } = useSiteSave( beforeSave );

	return (
		<Card>
			<CardHeader>
				<h2 className="doujin-profile-card-title">リンク集</h2>
			</CardHeader>
			<CardBody>
				{ ! isLoaded && <Spinner /> }
				{ isLoaded && (
					<>
						<StatusNotice notice={ notice } onRemove={ clearNotice } />
						{ LISTS.map( ( list ) => (
							<section key={ list.key } className="doujin-profile-section">
								<h3>{ list.title }</h3>
								<p className="description">{ list.help }</p>
								<LinkList
									links={ value[ list.key ] || [] }
									onChange={ ( next ) => setLinks( { shops: [], accounts: [], ...value, [ list.key ]: next } ) }
									addLabel="リンクを足す"
									disabled={ isSaving }
								/>
							</section>
						) ) }
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
						</div>
					</>
				) }
			</CardBody>
		</Card>
	);
}
