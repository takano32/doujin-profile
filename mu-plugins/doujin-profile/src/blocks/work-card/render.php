<?php
/**
 * 作品カードの描画 (spec §6.2、並びは T.32.3.3.2)。
 *
 * 書影 → タイトル・作家 → 発売日の表記 (未来なら「予告」の印) → あらすじ (抜粋) →
 * 「サンプルを見る」「シェア」→ 販売先のボタンを縦に。データはすべて投稿から読む (属性は無い)。
 *
 * 単体ページ (作品のページ) では、テーマのテンプレートがタイトルと書影を出すので、カードはそれを重ねない。
 * サンプルビューアは <dialog> で、画像の一覧は JSON で持ち、view.js が開いたときに描く。
 *
 * @var array    $attributes ブロックの属性 (表示の指定だけ。いまは無い)。
 * @var string   $content    内側の内容 (無い)。
 * @var WP_Block $block      ブロック (context に postId)。
 */

$doujin_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$doujin_post    = $doujin_post_id ? get_post( $doujin_post_id ) : null;

if ( ! $doujin_post || 'post' !== $doujin_post->post_type ) {
	echo doujin_profile_editor_notice( '作品カードは、作品 (投稿) の本文か、クエリーループの中に置いてください。' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$doujin_in_single = is_singular() && (int) get_queried_object_id() === $doujin_post_id;
$doujin_permalink = get_permalink( $doujin_post );
$doujin_title     = get_the_title( $doujin_post );
$doujin_release   = doujin_profile_release_info( $doujin_post_id );
$doujin_shops     = doujin_profile_sanitize_links( get_post_meta( $doujin_post_id, 'doujin_shop_links', true ) );
$doujin_pages     = doujin_profile_sample_pages( $doujin_post_id );
$doujin_artists   = get_the_term_list( $doujin_post_id, 'artist', '', '、' );
$doujin_excerpt   = has_excerpt( $doujin_post ) ? get_the_excerpt( $doujin_post ) : '';

$doujin_wrapper = get_block_wrapper_attributes(
	array_filter(
		array(
			'class'           => 'doujin-profile-work-card',
			'data-release-at' => $doujin_release['release_at'], // view.js が現在時刻で「予告」の印を見直す (spec §9.5)。
		)
	)
);
?>
<article <?php echo $doujin_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $doujin_in_single ) : ?>
		<?php if ( has_post_thumbnail( $doujin_post ) ) : ?>
			<figure class="doujin-profile-work-card__cover">
				<a href="<?php echo esc_url( $doujin_permalink ); ?>"><?php echo get_the_post_thumbnail( $doujin_post, 'large' ); ?></a>
			</figure>
		<?php endif; ?>
		<h2 class="doujin-profile-work-card__title"><a href="<?php echo esc_url( $doujin_permalink ); ?>"><?php echo esc_html( $doujin_title ); ?></a></h2>
	<?php endif; ?>

	<?php if ( is_string( $doujin_artists ) && '' !== $doujin_artists ) : ?>
		<p class="doujin-profile-work-card__artists">作家: <?php echo $doujin_artists; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- コアが作るリンク ?></p>
	<?php endif; ?>

	<?php if ( '' !== $doujin_release['label'] || $doujin_release['upcoming'] ) : ?>
		<p class="doujin-profile-work-card__release">
			<span class="doujin-profile-work-card__upcoming"<?php echo $doujin_release['upcoming'] ? '' : ' hidden'; ?>>予告</span>
			<span class="doujin-profile-work-card__release-label"><?php echo esc_html( $doujin_release['label'] ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $doujin_excerpt ) : ?>
		<div class="doujin-profile-work-card__summary"><?php echo wpautop( wp_kses_post( $doujin_excerpt ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>

	<div class="wp-block-buttons is-layout-flex doujin-profile-work-card__actions">
		<?php if ( $doujin_pages ) : ?>
			<div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" data-doujin-viewer="open">サンプルを見る</button></div>
		<?php endif; ?>
		<div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" data-doujin-share data-url="<?php echo esc_url( $doujin_permalink ); ?>" data-title="<?php echo esc_attr( $doujin_title ); ?>">シェア</button></div>
	</div>

	<?php echo doujin_profile_render_link_buttons( $doujin_shops, 'is-vertical doujin-profile-work-card__shops', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( $doujin_pages ) : ?>
		<dialog class="doujin-profile-viewer" aria-label="<?php echo esc_attr( $doujin_title ); ?> のサンプル">
			<script type="application/json" class="doujin-profile-viewer__pages"><?php echo wp_json_encode( $doujin_pages, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<div class="doujin-profile-viewer__bar">
				<span class="doujin-profile-viewer__title"><?php echo esc_html( $doujin_title ); ?></span>
				<output class="doujin-profile-viewer__count" aria-live="polite"></output>
				<?php // 全画面の記号は文字 (⛶) だとスマホで崩れるので、WordPress 同梱の @wordpress/icons の fullscreen を素の SVG で。 ?>
				<button type="button" class="doujin-profile-viewer__button" data-doujin-viewer="fullscreen" aria-label="全画面" title="全画面"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M4.75 10V4.75H10M4.75 4.75L10 10M14 19.25H19.25V14M19.25 19.25L14 14" /></svg></button>
				<button type="button" class="doujin-profile-viewer__button" data-doujin-viewer="close" aria-label="閉じる" title="閉じる (Esc)">×</button>
			</div>
			<div class="doujin-profile-viewer__stage" data-doujin-viewer="stage"></div>
			<div class="doujin-profile-viewer__nav">
				<button type="button" class="doujin-profile-viewer__button" data-doujin-viewer="next" aria-label="次のページ" title="次のページ (←)">‹</button>
				<input type="range" class="doujin-profile-viewer__range" dir="rtl" min="1" max="<?php echo count( $doujin_pages ); ?>" value="1" step="1" data-doujin-viewer="range" aria-label="ページを移動">
				<button type="button" class="doujin-profile-viewer__button" data-doujin-viewer="prev" aria-label="前のページ" title="前のページ (→)">›</button>
			</div>
		</dialog>
	<?php endif; ?>
</article>
