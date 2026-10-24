/**
 * マイページ (spec §8)。1 画面のタブ: プロフィール / リンク集 / 作品。スマホでは 1 列。
 *
 * WordPress 同梱の部品 (wp.components・wp.coreData・wp.data・wp.apiFetch・wp.mediaUtils) で描き、
 * 保存はすべて標準の REST (wp/v2/settings・wp/v2/posts・wp/v2/media・wp/v2/artist)。
 * 例外は書影・サンプルの取り込み (import.js → includes/cover-import.php) だけ。
 * 外部の JS は、ドラッグで並べ替える SortableJS だけを npm で同梱する (sortable.js)。
 *
 * WordPress 同梱の部品は externals (wp.*) なので、ビルド物には含まれない。
 *
 *   profile.js      プロフィール (サークル名・リード文・アイコン)
 *   links.js        リンク集 (販売ページ・お知らせ)      link-list.js  リンク一覧の編集 (共用)
 *   works.js        作品の一覧・追加                       work-editor.js 作品の編集
 *   import.js       作品番号から取り込む (書影・サンプル)   cover.js      書影 (ドロップ・選ぶ)
 *   samples.js      サンプル画像 (まとめて上げる・並べ替え)  artists.js    作家 (候補つき・新規)
 *   media.js        アップロードと添付の読み出し           sortable.js   SortableJS の React 用の薄い包み
 *   site.js         サイト設定の保存 (共用)                 util.js       小物
 */

import { createRoot, useState } from '@wordpress/element';
import { ExternalLink, TabPanel } from '@wordpress/components';

import { data } from './util';
import { ProfileCard } from './profile';
import { LinksCard } from './links';
import { WorksList } from './works';
import { WorkEditor } from './work-editor';

import './my-page.css';

const TABS = [
	{ name: 'profile', title: 'プロフィール' },
	{ name: 'links', title: 'リンク集' },
	{ name: 'works', title: '作品' },
];

function MyPage() {
	// 開いている作品。タブを離れても覚えておく (タブの中身は切り替えで作り直されるため)。
	const [ selectedWork, setSelectedWork ] = useState( null );

	return (
		<div className="doujin-profile-my-page">
			{ /* 見出し「マイページ」は PHP 側 (includes/my-page.php) の h1。管理画面の通知はその直後に差し込まれる。 */ }
			<TabPanel className="doujin-profile-tabs" tabs={ TABS } initialTabName="profile">
				{ ( tab ) => {
					if ( 'links' === tab.name ) {
						return <LinksCard />;
					}
					if ( 'works' === tab.name ) {
						return selectedWork ? (
							<WorkEditor id={ selectedWork } onBack={ () => setSelectedWork( null ) } />
						) : (
							<WorksList onSelect={ setSelectedWork } />
						);
					}
					return <ProfileCard />;
				} }
			</TabPanel>
			<p className="description doujin-profile-footer">
				見た目を変える: { data.themesUrl && <a href={ data.themesUrl }>テーマを選ぶ</a> }
				{ data.siteEditorUrl && (
					<>
						{ ' / ' }
						<a href={ data.siteEditorUrl }>色と文字・ページの編集</a>
					</>
				) }
				{ data.homeUrl && (
					<>
						{ ' / ' }
						<ExternalLink href={ data.homeUrl }>サイトを見る</ExternalLink>
					</>
				) }
			</p>
		</div>
	);
}

const container = document.getElementById( 'doujin-profile-my-page' );
if ( container ) {
	createRoot( container ).render( <MyPage /> );
}
