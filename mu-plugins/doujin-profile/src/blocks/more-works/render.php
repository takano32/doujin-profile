<?php
/**
 * 「さらに作品を見る」の描画 (T.31.4)。
 *
 * コアの「次のページ」ブロック (core/query-pagination-next) と同じ標準の API だけで、クエリーループの
 * 次のページの URL を求めてリンクにする。view.js がそのページを取り、投稿テンプレートの中身を下へ継ぎ足す。
 * JS が無ければ普通のリンクとして次のページへ移る。本体・テーマには手を入れない。
 *
 * 入れ物の div は、次のページが無くても出す (継ぎ足したあと、view.js が返ってきた HTML の中で同じクエリーを探す目印)。
 *
 * @var array    $attributes 属性 (label: ボタンの文言)。
 * @var string   $content    内側の内容 (無い)。
 * @var WP_Block $block      ブロック (context に queryId・query)。
 */

if ( ! isset( $block->context['queryId'] ) ) {
	echo doujin_profile_editor_notice( '「さらに作品を見る」は、クエリーループの中 (投稿テンプレートの後) に置いてください。' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$doujin_label    = isset( $attributes['label'] ) && '' !== trim( (string) $attributes['label'] ) ? (string) $attributes['label'] : 'さらに作品を見る';
$doujin_query_id = (int) $block->context['queryId'];
$doujin_page_key = 'query-' . $doujin_query_id . '-page';
$doujin_page     = empty( $_GET[ $doujin_page_key ] ) ? 1 : max( 1, (int) $_GET[ $doujin_page_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$doujin_next_url = '';

if ( ! empty( $block->context['query']['inherit'] ) ) {
	// メインのクエリー (投稿ページやアーカイブ) に乗っているとき。
	global $wp_query;
	$doujin_max_page = $wp_query instanceof WP_Query ? (int) $wp_query->max_num_pages : 0;
	if ( $doujin_max_page > max( 1, (int) get_query_var( 'paged' ) ) ) {
		$doujin_next_url = (string) next_posts( $doujin_max_page, false );
	}
} else {
	$doujin_max_page = isset( $block->context['query']['pages'] ) ? (int) $block->context['query']['pages'] : 0;
	if ( ! $doujin_max_page || $doujin_max_page > $doujin_page ) {
		$doujin_query = new WP_Query( build_query_vars_from_query_block( $block, $doujin_page ) );
		if ( (int) $doujin_query->max_num_pages > $doujin_page ) {
			$doujin_next_url = add_query_arg( $doujin_page_key, $doujin_page + 1 );
		}
		wp_reset_postdata();
	}
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wp-block-buttons is-layout-flex is-content-justification-center doujin-profile-more-works', 'data-query-id' => $doujin_query_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( '' !== $doujin_next_url ) : ?>
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $doujin_next_url ); ?>" data-doujin-more-works><?php echo esc_html( $doujin_label ); ?></a></div>
	<?php endif; ?>
</div>
