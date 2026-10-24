<?php
/**
 * リンクボタンの描画 (spec §6.2)。サイト設定 doujin_profile_links の shops か accounts を、
 * コアのボタンブロックの形で横に並べる (折り返し・中央寄せ)。
 *
 * @var array    $attributes 属性 (list: shops | accounts)。
 * @var string   $content    内側の内容 (無い)。
 * @var WP_Block $block      ブロック。
 */

$doujin_list  = isset( $attributes['list'] ) && 'accounts' === $attributes['list'] ? 'accounts' : 'shops';
$doujin_links = doujin_profile_sanitize_profile_links( get_option( 'doujin_profile_links', array() ) )[ $doujin_list ];

if ( ! $doujin_links ) {
	$doujin_list_name = 'accounts' === $doujin_list ? 'お知らせ・活動情報' : '販売ページ';
	echo doujin_profile_editor_notice( "リンク集の「{$doujin_list_name}」が空です。マイページの「リンク集」で足すと、ここに並びます。" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'doujin-profile-links doujin-profile-links--' . $doujin_list ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo doujin_profile_render_link_buttons( $doujin_links, 'is-content-justification-center' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
