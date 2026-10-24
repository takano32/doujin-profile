<?php
/**
 * メールの差出人をそろえる (招待メールなど)。
 *
 * コアの既定では、表の差出人 (From) は wordpress@<ネットワークのドメイン> だが、
 * 裏の差出人 (エンベロープ。受け取る側が SPF で確かめる) はさくらの既定 (<アカウント>@www…sakura.ne.jp) のまま。
 * 裏も From と同じにして、dp.no32.tk の SPF (DNS の TXT) で確かめられるようにする。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * エンベロープの差出人を From にそろえる。
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer コアのメール送信。
 */
function doujin_profile_mail_sender( $phpmailer ) {
	if ( '' === (string) $phpmailer->Sender && '' !== (string) $phpmailer->From ) {
		$phpmailer->Sender = $phpmailer->From;
	}
}
add_action( 'phpmailer_init', 'doujin_profile_mail_sender' );
