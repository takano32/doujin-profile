<?php
/**
 * ログイン後の送り先の判定 (spec §7.3)。
 */

namespace DoujinProfile\Login;

final class Redirect {

	/**
	 * redirect_to がダッシュボード (管理画面のトップか index.php) を求めているか。
	 * ほかの画面 (例: 編集画面のリンクを踏んでログインした) なら、そちらを邪魔しない。
	 *
	 * @param string $requested_path redirect_to のパス。
	 * @param string $dashboard_path admin_url() のパス (末尾 / 付き。例 /wp-admin/)。
	 * @return bool
	 */
	public static function isDashboard( string $requested_path, string $dashboard_path ): bool {
		return in_array( rtrim( $requested_path, '/\\' ), array( rtrim( $dashboard_path, '/\\' ), $dashboard_path . 'index.php' ), true );
	}
}
