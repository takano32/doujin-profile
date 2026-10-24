/**
 * リンクボタン (エディタ側)。属性は「どちらの一覧か」だけ。中身はサーバの描画を見せる。
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title="一覧">
					<RadioControl
						label="どちらの一覧を出すか"
						help="中身はマイページの「リンク集」で編集します。"
						selected={ attributes.list }
						options={ [
							{ label: '販売ページ', value: 'shops' },
							{ label: 'お知らせ・活動情報', value: 'accounts' },
						] }
						onChange={ ( list ) => setAttributes( { list } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender block={ metadata.name } attributes={ attributes } skipBlockSupportAttributes />
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
