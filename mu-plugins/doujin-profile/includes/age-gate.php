<?php
/**
 * 年齢確認 (spec §9.5。T.32.8)。
 *
 * サイトの状態が「成人向け (mature)」のサイトにだけ、公開ページで「18 歳以上ですか」を出す。
 * 確認の結果は localStorage に持つ (サブドメインごとに別なので、確認はサイトごと)。「いいえ」で本文を消して離脱する。
 * 本文は HTML としてはそのまま出す (JS の無い検索エンジンには普通のページに見える。所有者の裁定「広報なんだから載せろ」)。
 * そのサイトを編集できる人 (持ち主・運営者) には出さない。文言は f_tsf の年齢確認に合わせた。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * この表示で年齢確認を出すか。
 *
 * @return bool
 */
function doujin_profile_needs_age_gate() {
	if ( ! is_multisite() || is_main_site() || is_admin() || wp_doing_ajax() || is_feed() || is_robots() ) {
		return false;
	}

	$site = get_site();
	if ( ! $site || 1 !== (int) $site->mature ) {
		return false;
	}

	return ! current_user_can( 'edit_posts' );
}

/**
 * 確認が済むまで本文を隠す (描画の前に head で判定し、ちらつきを防ぐ)。
 */
function doujin_profile_age_gate_head() {
	if ( ! doujin_profile_needs_age_gate() ) {
		return;
	}
	?>
<style id="doujin-profile-age-gate-css">
.doujin-profile-age-pending body > *:not(#doujin-profile-age-gate) { visibility: hidden; }
#doujin-profile-age-gate { max-width: min(92vw, 28rem); padding: var(--wp--preset--spacing--40, 1.5rem); border: 0; border-radius: 1rem; text-align: center; }
#doujin-profile-age-gate::backdrop { background: rgba(0, 0, 0, 0.8); }
#doujin-profile-age-gate .wp-block-buttons { flex-direction: column; align-items: stretch; margin-top: 1.25rem; }
</style>
<script id="doujin-profile-age-gate-pending">
try { if ( 'yes' !== localStorage.getItem( 'doujin_profile_age_ok_v1' ) ) { document.documentElement.classList.add( 'doujin-profile-age-pending' ); } } catch ( e ) { document.documentElement.classList.add( 'doujin-profile-age-pending' ); }
</script>
	<?php
}
add_action( 'wp_head', 'doujin_profile_age_gate_head', 1 );

/**
 * 年齢確認のダイアログ。確認済みなら何もしない。
 */
function doujin_profile_age_gate_dialog() {
	if ( ! doujin_profile_needs_age_gate() ) {
		return;
	}
	?>
<dialog id="doujin-profile-age-gate" aria-labelledby="doujin-profile-age-gate-title">
	<h2 id="doujin-profile-age-gate-title">年齢確認</h2>
	<p>このサイトには成人向け同人作品へのリンクを含みます。あなたは18歳以上ですか？</p>
	<div class="wp-block-buttons is-layout-flex">
		<div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button" data-doujin-age="yes">はい、18歳以上です</button></div>
		<div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" data-doujin-age="no">いいえ</button></div>
	</div>
</dialog>
<script id="doujin-profile-age-gate-js">
( function () {
	var root = document.documentElement;
	var gate = document.getElementById( 'doujin-profile-age-gate' );
	if ( ! gate || ! root.classList.contains( 'doujin-profile-age-pending' ) ) {
		return;
	}
	gate.addEventListener( 'cancel', function ( event ) { event.preventDefault(); } ); // Esc では閉じない。
	gate.addEventListener( 'click', function ( event ) {
		var answer = event.target.getAttribute && event.target.getAttribute( 'data-doujin-age' );
		if ( 'yes' === answer ) {
			try { localStorage.setItem( 'doujin_profile_age_ok_v1', 'yes' ); } catch ( e ) {}
			gate.close();
			root.classList.remove( 'doujin-profile-age-pending' );
		} else if ( 'no' === answer ) {
			document.body.innerHTML = '';
			if ( history.length > 1 ) { history.back(); } else { location.href = 'about:blank'; }
		}
	} );
	gate.showModal();
} )();
</script>
	<?php
}
add_action( 'wp_footer', 'doujin_profile_age_gate_dialog', 1 );
