/**
 * 「さらに作品を見る」(エディタ側)。属性はボタンの文言だけ。
 * 次のページがあるかはサーバが表示のときに決めるので、エディタではボタンをそのまま見せる。
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title="ボタン">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label="文言"
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'wp-block-buttons is-layout-flex is-content-justification-center' } ) }>
				<div className="wp-block-button">
					<span className="wp-block-button__link wp-element-button">{ attributes.label || 'さらに作品を見る' }</span>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
