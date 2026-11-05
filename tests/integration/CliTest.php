<?php

namespace DoujinProfile\Tests\Integration;

/**
 * WP-CLI (spec §8.5): wp doujin の子コマンドの名前・引数・出力。
 */
final class CliTest extends IntegrationTestCase {

	public function test_help_lists_the_six_subcommands(): void {
		$r = self::wp( 'help doujin' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( 'サークル紹介サイト (doujin-profile) の運営の道具。', $r['out'] );
		foreach ( array( 'init-site', 'import', 'export', 'invite', 'publish', 'doctor' ) as $sub ) {
			$this->assertMatchesRegularExpression( '/^\s*' . preg_quote( $sub, '/' ) . '\s/m', $r['out'], $sub );
		}

		$r = self::wp( 'help doujin import' );
		$this->assertStringContainsString( '<file>', $r['out'] );
		$this->assertStringContainsString( '[--dry-run]', $r['out'] );
		$this->assertStringContainsString( 'wp doujin import ~/dumps/doujin-profile-import-f_tsf/f_tsf.json', $r['out'] );
	}

	public function test_doctor_passes_for_the_profile_site(): void {
		$r = self::wpSite( 'doujin doctor' );
		$this->assertStringContainsString( '== 本体 (WordPress ', $r['out'] );
		$this->assertStringContainsString( '== サイト https://ftsf.dp.test/', $r['out'] );
		foreach ( array(
			'[OK] ローダ mu-plugins/doujin-profile.php がある',
			'[OK] マイページのビルド build/my-page.js がある',
			'[OK] ブロック doujin-profile/work-card のビルドがあり、登録されている',
			'[OK] 分類 artist と作品のメタが登録されている',
			'[OK] 年齢確認と準備中の仕組みが読み込まれている',
			'[OK] home が https でサイトのドメインと一致 (https://ftsf.dp.test)',
			'[--] 公開中',
			'[--] 成人向けの印: オン',
			'[OK] 書き換え規則に分類 artist (/artist/<slug>/) が入っている',
			'[OK] トップページに作品カード (クエリーループ) がある',
			'[OK] 初期のhello-world が残っていない',
			'[OK] テーマ doujin-profile-tt5 があり、使える',
			'[OK] 作品「作品その一」',
			'[--] 作品 3 件 (公開 2・予約 0・下書き 1・予告 1・書影なし 1)',
			'[OK] このサイトの administrator: ',
			'Success: NG はありません。',
		) as $line ) {
			$this->assertStringContainsString( $line, $r['out'] );
		}
		$this->assertSame( 0, $r['code'], $r['out'] );
	}

	public function test_doctor_on_the_main_site_checks_the_network(): void {
		$r = self::wp( 'doujin doctor' );
		$this->assertStringContainsString( '== ネットワーク http://dp.test/', $r['out'] );
		$this->assertStringContainsString( '[OK] サブドメイン型の Multisite', $r['out'] );
		$this->assertStringContainsString( '[OK] 子テーマ doujin-profile-tt5 がネットワークで有効', $r['out'] );
		$this->assertStringContainsString( '[NG] コメントとピンバックを止める (Disable Comments) がネットワークで有効 → wp plugin install disable-comments --activate-network', $r['out'] );
		$this->assertStringContainsString( '== サイト https://ftsf.dp.test/ (blog_id ', $r['out'] );
		$this->assertMatchesRegularExpression( '/Warning: NG \d+ 件。直し方は各行の → のあと \(--fix で直せるものは \[FIX\] と付けた\)。/', $r['out'] );
		$this->assertSame( 1, $r['code'] );
	}

	public function test_init_site_is_idempotent_and_refuses_the_main_site(): void {
		$r = self::wpSite( 'doujin init-site' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( '- 書き換え規則を作り直した', $r['out'] );
		$this->assertStringNotContainsString( 'を https に', $r['out'] );
		$this->assertStringNotContainsString( 'を削除', $r['out'] );
		$this->assertStringNotContainsString( 'ホーム', $r['out'] );
		$this->assertStringContainsString( 'Success: https://ftsf.dp.test/ の初期設定を整えました。', $r['out'] );

		$r = self::wp( 'doujin init-site' );
		$this->assertSame( 1, $r['code'] );
		$this->assertStringContainsString( 'メインサイトには効かせません', $r['out'] );
	}

	public function test_export_writes_the_exchange_format(): void {
		$r = self::wpSite( 'doujin export' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$data = json_decode( substr( $r['out'], (int) strpos( $r['out'], '{' ) ), true );
		$this->assertIsArray( $data, $r['out'] );
		$this->assertSame( 'ftsf', $data['name'] );
		$this->assertSame( array( 'ja' => '試験サークル' ), $data['title'] );
		$this->assertTrue( $data['mature'] );
		$this->assertSame( 'doujin-profile-tt5', $data['theme'] );
		$this->assertCount( 2, $data['shops'] ); // http の 1 件は入っていない
		$this->assertSame( array( 'ja' => 'FANZA の店' ), $data['shops'][0]['label'] );
		$this->assertTrue( $data['shops'][1]['sponsored'] );

		$works = array_column( $data['works'], null, 'key' );
		$this->assertArrayHasKey( 'work-one', $works );
		$one = $works['work-one'];
		$this->assertSame( 'publish', $one['status'] );
		$this->assertSame( array( '作家A', '作家B' ), $one['artists'] );
		$this->assertSame( '2026-01-10T00:00:00+09:00', $one['release_at'] );
		$this->assertStringContainsString( 'cover1', $one['cover'] );
		$this->assertCount( 2, $one['links'] );
		$this->assertTrue( $one['links'][0]['sponsored'] );
		$this->assertCount( 3, $one['samples'] );
		$this->assertSame( 'draft', $works['%e4%b8%8b%e6%9b%b8%e3%81%8d%e3%81%ae%e4%bd%9c%e5%93%81']['status'] ); // key の無い作品はタイトルの slug
	}

	public function test_import_is_idempotent_and_dry_run_writes_nothing(): void {
		$json = self::fixtures() . '/works.json';
		$r    = self::wpSite( 'doujin import ' . escapeshellarg( $json ) . ' --dry-run' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( '== dry-run: 何も書きません ==', $r['out'] );
		$this->assertStringContainsString( 'Success: dry-run: 作品 3 件 (作成 0・更新 3・失敗 0) の予定。', $r['out'] );

		$r = self::wpSite( 'doujin import ' . escapeshellarg( $json ) );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( 'Success: 作品 3 件 (作成 0・更新 3・失敗 0)。', $r['out'] );
		$this->assertStringContainsString( '発売日時「not a date」が読めないので空にします', $r['out'] );

		$this->useSite();
		$this->assertSame( 3, (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'post' )->draft );
		$attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => self::workBySlug( 'work-one' )->ID, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$this->assertCount( 4, $attachments, '書影 1 + サンプル 3 のまま (二重にならない)' );
	}

	public function test_import_checks_the_site_name_and_reads_product_ids_in_dry_run(): void {
		$json = self::fixtures() . '/works.json';
		$tmp  = tempnam( get_temp_dir(), 'doujin-profile-other-' ) . '.json';
		file_put_contents( $tmp, str_replace( '"name": "ftsf"', '"name": "other"', (string) file_get_contents( $json ) ) );
		$r = self::wpSite( 'doujin import ' . escapeshellarg( $tmp ) . ' --dry-run' );
		$this->assertSame( 1, $r['code'] );
		$this->assertStringContainsString( 'JSON の name「other」とこのサイト「ftsf」', $r['out'] );
		$r = self::wpSite( 'doujin import ' . escapeshellarg( $tmp ) . ' --dry-run --force' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		unlink( $tmp );

		$f_tsf = dirname( self::fixtures() ) . '/src/data/f_tsf.json'; // 本番の見本 (作品番号で書影を指す)。dry-run なので取りに行かない
		$r     = self::wpSite( 'doujin import ' . escapeshellarg( $f_tsf ) . ' --dry-run' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( '書影: 作品番号 ', $r['out'] );
		$this->assertStringContainsString( 'Success: dry-run: 作品 3 件', $r['out'] );
	}

	/**
	 * 注意: invite の [--user=<login>] は WP-CLI の全体の引数 --user (動作するユーザ) に取られ、子コマンドには届かない
	 * (本体の既知の問題。T.54 の結合試験で判明。直すなら別の名前にする)。ここでは新しいユーザの道筋だけを確かめる。
	 */
	public function test_invite_validates_before_creating_anything(): void {
		$r = self::wp( 'doujin invite ab x@example.test --dry-run' );
		$this->assertSame( 1, $r['code'] );
		$this->assertStringContainsString( 'ユーザを作れません: ', $r['out'] );

		$r = self::wp( 'doujin invite ftsf other@example.test --dry-run' ); // 既にいるユーザの名前
		$this->assertSame( 1, $r['code'] );
		$this->assertStringContainsString( 'ユーザを作れません: ', $r['out'] );
		$this->assertStringContainsString( '--user=ftsf を付けてください', $r['out'] );
		$this->assertStringNotContainsString( '<strong>', $r['out'] ); // コアの HTML は外す

		$r = self::wp( 'doujin invite newcircle new@example.test --title=新しいサークル --mature --dry-run' );
		$this->assertSame( 0, $r['code'], $r['out'] );
		$this->assertStringContainsString( '- サイト: https://newcircle.dp.test/ (タイトル「新しいサークル」、成人向け オン、準備中で始める)', $r['out'] );
		$this->assertStringContainsString( '- 持ち主: 新しいユーザ newcircle (メール n***@example.test) をこのサイトの administrator に', $r['out'] );
		$this->assertStringContainsString( '- メール: パスワード設定のリンク (コアの通知。期限 24 時間)', $r['out'] );
		$this->assertStringContainsString( 'Success: dry-run: 作れます。何も作っていません。', $r['out'] );
		$this->assertSame( 0, (int) get_blog_id_from_url( 'newcircle.dp.test', '/' ) );
		$this->assertFalse( get_user_by( 'login', 'newcircle' ) );
	}
}
