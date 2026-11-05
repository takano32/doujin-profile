<?php
/**
 * lib/ の失敗の値 (DoujinProfile\Failure) を WP_Error にする橋。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param DoujinProfile\Failure $failure 失敗。
 * @return WP_Error
 */
function doujin_profile_wp_error( DoujinProfile\Failure $failure ) {
	return $failure->status > 0
		? new WP_Error( $failure->code, $failure->message, array( 'status' => $failure->status ) )
		: new WP_Error( $failure->code, $failure->message );
}
