/**
 * プロフィール: サークル名 (blogname)・リード文 (blogdescription)・アイコン (site_icon。サイトロゴ site_logo にも同じ画像)。
 * どれもコアが REST の wp/v2/settings に載せている項目で、独自の設定は無い (spec §6.0)。
 * 準備中のサイトには「公開する」(設定 doujin_profile_preparing を偽に。同じ wp/v2/settings。includes/preparing.php)。
 * 準備中へ戻すのは運営者だけ (WP-CLI) なので、戻すボタンは無い。
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { MediaUpload } from '@wordpress/media-utils';
import {
	BaseControl,
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

import { data, StatusNotice } from './util';
import { thumbnailUrl } from './media';
import { useSiteSave } from './site';

export function ProfileCard() {
	const [ title, setTitle ] = useEntityProp( 'root', 'site', 'title' );
	const [ description, setDescription ] = useEntityProp( 'root', 'site', 'description' );
	const [ iconId, setSiteIcon ] = useEntityProp( 'root', 'site', 'site_icon' );
	// サイトロゴ (site_logo) も同じ画像にする。公開側の「プロフィールの見出し」はコアのサイトロゴブロックで出すため (includes/patterns.php)。
	const [ , setSiteLogo ] = useEntityProp( 'root', 'site', 'site_logo' );
	const setIconId = ( id ) => {
		setSiteIcon( id );
		setSiteLogo( id );
	};
	const icon = useSelect( ( select ) => ( iconId ? select( coreStore ).getMedia( iconId ) : null ), [ iconId ] );
	const { isLoaded, hasEdits, isSaving, save, notice, clearNotice } = useSiteSave();

	// 準備中 → 公開 (T.11)。true / false 以外 (古い本体で設定が無い) なら何も出さない。
	const [ preparing, setPreparing ] = useEntityProp( 'root', 'site', 'doujin_profile_preparing' );
	const publish = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'サイトを公開しますか？\n\nログインしていない人にも、トップページと作品が見えるようになります。準備中に戻したいときは運営者に連絡してください。' ) ) {
			return;
		}
		setPreparing( false );
		save();
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
						<StatusNotice notice={ notice } onRemove={ clearNotice } />
						{ true === preparing && (
							<Notice status="warning" isDismissible={ false } className="doujin-profile-notice">
								<p>
									このサイトは<strong>準備中</strong>です。ログインしていない人には「準備中です」とだけ表示され、検索にも載りません。内容がそろったら公開してください。
								</p>
								<Button variant="primary" __next40pxDefaultSize onClick={ publish } disabled={ isSaving } isBusy={ isSaving }>
									公開する
								</Button>
							</Notice>
						) }
						{ false === preparing && <p className="description doujin-profile-field-help">このサイトは<strong>公開中</strong>です。</p> }
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
						<BaseControl
							__nextHasNoMarginBottom
							id="doujin-profile-site-icon"
							label="アイコン"
							help="正方形の画像 (512×512 ピクセル以上)。プロフィールの顔写真の位置と、ブラウザのタブに使われます。"
						>
							<div className="doujin-profile-icon-field">
								{ iconId > 0 && (
									<img
										className="doujin-profile-icon-preview"
										src={ thumbnailUrl( icon, 'thumbnail' ) }
										alt=""
										width={ 96 }
										height={ 96 }
									/>
								) }
								<div className="doujin-profile-actions">
									<MediaUpload
										allowedTypes={ [ 'image' ] }
										value={ iconId || undefined }
										title="アイコンの画像"
										onSelect={ ( attachment ) => setIconId( attachment.id ) }
										render={ ( { open } ) => (
											<Button variant="secondary" __next40pxDefaultSize onClick={ open } disabled={ isSaving }>
												{ iconId > 0 ? '画像を変える' : '画像を選ぶ' }
											</Button>
										) }
									/>
									{ iconId > 0 && (
										<Button variant="tertiary" __next40pxDefaultSize isDestructive onClick={ () => setIconId( 0 ) } disabled={ isSaving }>
											外す
										</Button>
									) }
								</div>
							</div>
						</BaseControl>
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
