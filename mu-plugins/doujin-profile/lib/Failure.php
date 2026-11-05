<?php
/**
 * 失敗の値。lib/ が WP_Error に依存しないための入れ物で、糊 (includes/) が doujin_profile_wp_error() で WP_Error にする。
 */

namespace DoujinProfile;

final class Failure {

	/** @var string エラーコード (例 doujin_profile_not_found)。 */
	public $code;

	/** @var string 人に見せる文。 */
	public $message;

	/** @var int HTTP の状態 (REST の応答に使う。0 なら無し)。 */
	public $status;

	/**
	 * @param string $code    エラーコード。
	 * @param string $message 人に見せる文。
	 * @param int    $status  HTTP の状態 (0 なら無し)。
	 */
	public function __construct( string $code, string $message, int $status = 0 ) {
		$this->code    = $code;
		$this->message = $message;
		$this->status  = $status;
	}
}
