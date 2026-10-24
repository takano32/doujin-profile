/**
 * 作品カード (エディタ側)。属性は無いので、サーバの描画をそのまま見せるだけ。
 *
 * クエリーループの中では、ループの投稿 (context.postId) で描く。作品の本文の中では、編集中の投稿で描く
 * (ServerSideRender が post_id を送る)。
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css'; // build/blocks/work-card/style-index.css になる (表示側とエディタの両方で読まれる)。

function Edit( { context } ) {
	const postId = context?.postId;
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender
				block={ metadata.name }
				skipBlockSupportAttributes
				urlQueryArgs={ postId ? { post_id: postId } : undefined }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
