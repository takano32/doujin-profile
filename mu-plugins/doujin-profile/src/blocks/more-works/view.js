/**
 * 「さらに作品を見る」の表示側 (素の JS)。
 *
 * リンク先 (クエリーループの次のページ) を fetch し、返ってきた HTML の同じクエリーループから
 * 投稿テンプレートの中身を取り出して、いま表示している一覧の下に継ぎ足す。ボタンのリンク先は
 * さらに次のページに差し替え、無ければボタンを消す。失敗したときは普通のリンクとして移動する。
 */

const SELECTOR = 'a[data-doujin-more-works]';

function listOf( wrapper ) {
	return wrapper?.closest( '.wp-block-query' )?.querySelector( '.wp-block-post-template' ) || null;
}

async function loadMore( link ) {
	const wrapper = link.closest( '.doujin-profile-more-works' );
	const list = listOf( wrapper );
	if ( ! list ) {
		return false;
	}

	const response = await fetch( link.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' } );
	if ( ! response.ok ) {
		return false;
	}
	const doc = new DOMParser().parseFromString( await response.text(), 'text/html' );
	const nextWrapper = doc.querySelector( `.doujin-profile-more-works[data-query-id="${ wrapper.dataset.queryId }"]` );
	const nextList = listOf( nextWrapper );
	if ( ! nextList ) {
		return false;
	}

	const added = Array.from( nextList.children );
	list.append( ...added );
	document.dispatchEvent( new CustomEvent( 'doujin-profile:appended', { detail: { container: list, items: added } } ) );

	const nextLink = nextWrapper.querySelector( SELECTOR );
	if ( nextLink ) {
		link.href = nextLink.href;
	} else {
		link.closest( '.wp-block-button' ).remove();
	}
	added[ 0 ]?.querySelector( 'a, button' )?.focus( { preventScroll: true } );
	return true;
}

document.addEventListener( 'click', async ( event ) => {
	const link = event.target.closest( SELECTOR );
	if ( ! link || link.getAttribute( 'aria-busy' ) === 'true' ) {
		return;
	}
	event.preventDefault();
	link.setAttribute( 'aria-busy', 'true' );
	let done = false;
	try {
		done = await loadMore( link );
	} catch {
		done = false;
	}
	link.removeAttribute( 'aria-busy' );
	if ( ! done ) {
		location.href = link.href;
	}
} );
