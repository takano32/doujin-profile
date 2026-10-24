/**
 * 作品カードの表示側 (素の JS。ビルドは @wordpress/scripts)。
 *
 *   - サンプルビューア (T.32.3.3.1): <dialog> を開き、PC は見開き・スマホは 1 ページ。右から左へめくる。
 *     クリック (左半分 = 次、右半分 = 前)・スワイプ・← → ・つまみ (range)・全画面・Esc で閉じる。
 *     自動めくりと目次は作らない。
 *   - シェア: Web Share API。無いブラウザでは URL をコピーする。
 *   - 「予告」の印: 現在時刻で見直す (サーバのキャッシュに備える。spec §9.5)。
 *
 * イベントは document で受ける (「さらに作品を見る」で継ぎ足したカードにもそのまま効く)。
 */

const SWIPE_PX = 40;

/* 見開きにするか: 横長で、ある程度の幅があるとき。 */
const isSpread = () => window.innerWidth >= 768 && window.innerWidth > window.innerHeight;

const viewers = new WeakMap();

function getViewer( dialog ) {
	let viewer = viewers.get( dialog );
	if ( viewer ) {
		return viewer;
	}

	let pages = [];
	try {
		pages = JSON.parse( dialog.querySelector( '.doujin-profile-viewer__pages' ).textContent ) || [];
	} catch {
		pages = [];
	}

	const stage = dialog.querySelector( '[data-doujin-viewer="stage"]' );
	const range = dialog.querySelector( '[data-doujin-viewer="range"]' );
	const count = dialog.querySelector( '.doujin-profile-viewer__count' );
	const fullscreenButton = dialog.querySelector( '[data-doujin-viewer="fullscreen"]' );
	if ( ! document.fullscreenEnabled || ! dialog.requestFullscreen ) {
		fullscreenButton.hidden = true;
	}

	viewer = {
		index: 0,

		/* 表示を描き直す。index は見開きなら偶数にそろえる。 */
		render() {
			const spread = isSpread() && pages.length > 1;
			if ( spread ) {
				this.index -= this.index % 2;
			}
			const last = spread ? Math.min( this.index + 1, pages.length - 1 ) : this.index;

			stage.classList.toggle( 'is-spread', spread );
			stage.replaceChildren();
			for ( let i = this.index; i <= last; i++ ) {
				const img = document.createElement( 'img' );
				img.src = pages[ i ].src;
				if ( pages[ i ].srcset ) {
					img.srcset = pages[ i ].srcset;
					img.sizes = spread ? '50vw' : '100vw';
				}
				img.alt = pages[ i ].alt || `${ i + 1 } ページ`;
				img.draggable = false;
				stage.append( img );
			}

			range.value = String( this.index + 1 );
			count.value = ( last > this.index ? `${ this.index + 1 }-${ last + 1 }` : `${ this.index + 1 }` ) + ` / ${ pages.length }`;
		},

		go( index ) {
			this.index = Math.max( 0, Math.min( pages.length - 1, index ) );
			this.render();
		},

		step( direction ) {
			const by = isSpread() && pages.length > 1 ? 2 : 1;
			this.go( this.index + direction * by );
		},

		open() {
			this.index = 0;
			dialog.showModal();
			this.render();
		},

		close() {
			if ( document.fullscreenElement === dialog ) {
				document.exitFullscreen();
			}
			dialog.close();
		},

		toggleFullscreen() {
			if ( document.fullscreenElement === dialog ) {
				document.exitFullscreen();
			} else {
				dialog.requestFullscreen().catch( () => {} );
			}
		},
	};

	/* 右から左なので、← が次、→ が前。 */
	dialog.addEventListener( 'keydown', ( event ) => {
		const keys = { ArrowLeft: () => viewer.step( 1 ), ArrowRight: () => viewer.step( -1 ), Home: () => viewer.go( 0 ), End: () => viewer.go( pages.length - 1 ) };
		if ( keys[ event.key ] ) {
			event.preventDefault();
			keys[ event.key ]();
		}
	} );
	dialog.addEventListener( 'close', () => {
		if ( document.fullscreenElement === dialog ) {
			document.exitFullscreen();
		}
		stage.replaceChildren();
	} );
	range.addEventListener( 'input', () => viewer.go( Number( range.value ) - 1 ) );

	/* 画像のクリック (左半分 = 次、右半分 = 前) とスワイプ (右へ = 次、左へ = 前)。 */
	let pointerStartX = null;
	stage.addEventListener( 'pointerdown', ( event ) => {
		pointerStartX = event.clientX;
	} );
	stage.addEventListener( 'pointerup', ( event ) => {
		if ( pointerStartX === null ) {
			return;
		}
		const dx = event.clientX - pointerStartX;
		pointerStartX = null;
		if ( Math.abs( dx ) >= SWIPE_PX ) {
			viewer.step( dx > 0 ? 1 : -1 );
		} else {
			const rect = stage.getBoundingClientRect();
			viewer.step( event.clientX < rect.left + rect.width / 2 ? 1 : -1 );
		}
	} );
	stage.addEventListener( 'pointercancel', () => {
		pointerStartX = null;
	} );

	window.addEventListener( 'resize', () => {
		if ( dialog.open ) {
			viewer.render();
		}
	} );

	viewers.set( dialog, viewer );
	return viewer;
}

async function share( button ) {
	const data = { title: button.dataset.title || document.title, url: button.dataset.url || location.href };
	if ( navigator.share ) {
		try {
			await navigator.share( data );
		} catch {
			/* 取り消し。 */
		}
		return;
	}
	if ( ! navigator.clipboard ) {
		return;
	}
	await navigator.clipboard.writeText( data.url );
	const label = button.textContent;
	button.textContent = 'URL をコピーしました';
	setTimeout( () => {
		button.textContent = label;
	}, 2000 );
}

/* 「予告」の印を現在時刻で見直す (発売日時を過ぎた HTML がキャッシュから出ても印が残らないように)。 */
function refreshUpcoming( root ) {
	root.querySelectorAll( '.doujin-profile-work-card[data-release-at]' ).forEach( ( card ) => {
		const at = Date.parse( card.dataset.releaseAt );
		const badge = card.querySelector( '.doujin-profile-work-card__upcoming' );
		if ( badge && ! Number.isNaN( at ) ) {
			badge.hidden = at <= Date.now();
		}
	} );
}

document.addEventListener( 'click', ( event ) => {
	const control = event.target.closest( '[data-doujin-viewer], [data-doujin-share]' );
	if ( ! control ) {
		return;
	}
	if ( control.hasAttribute( 'data-doujin-share' ) ) {
		share( control );
		return;
	}
	const action = control.dataset.doujinViewer;
	if ( action === 'open' ) {
		const dialog = control.closest( '.doujin-profile-work-card' )?.querySelector( '.doujin-profile-viewer' );
		if ( dialog ) {
			getViewer( dialog ).open();
		}
		return;
	}
	const dialog = control.closest( '.doujin-profile-viewer' );
	if ( ! dialog || action === 'stage' || action === 'range' ) {
		return;
	}
	const viewer = getViewer( dialog );
	( { close: () => viewer.close(), fullscreen: () => viewer.toggleFullscreen(), next: () => viewer.step( 1 ), prev: () => viewer.step( -1 ) } )[ action ]?.();
} );

refreshUpcoming( document );
document.addEventListener( 'doujin-profile:appended', ( event ) => refreshUpcoming( event.detail?.container || document ) );
