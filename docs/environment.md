# 導入先環境 (profile.xwp.jp) と作業ガード

doujin-profile を入れる WordPress について、**実際に ssh / curl して確かめた事実**だけを書いた参照文書。
仕様そのものは [`spec.md`](spec.md) が正。先行プロジェクト ts-novels の `docs/environment.md` と同じ位置づけで、
同じホストの別アカウントなので内容もよく似ている(差分は §8)。

## 0. 計測の基準

| 項目 | 値 |
|---|---|
| 調査日 | 2026-09-18 |
| 方法 | `ssh profile` での**読み取り専用**コマンド(`ls` / `wp` の参照系 / `php -m` など)と、外部からの `curl`(GET / HEAD、XML-RPC のみ無害な POST 1 回)。設定変更・ファイル作成は一切していない |
| 読まなかったもの | `wp-config.php` の中身、`.htaccess` の 4〜24 行(CloudSecure ブロックに**ログイン URL の変更値**が入るため)、ユーザのメールアドレス |
| 未検証事項 | §9 |

---

## 1. 接続と身元

```sh
ssh profile           # ~/.ssh/config に設定済み。鍵認証
```

| 項目 | 実測値 |
|---|---|
| 接続先 | `profile@sv3.xwp.ne.jp` ポート `10022`、鍵 `~/.ssh/id_ed25519` |
| ログインユーザ | `profile`(uid 20147 / グループ `members`)。一般ユーザ権限 |
| OS | Rocky Linux 8.10 (Green Obsidian) / カーネル `5.15.0-186-bet02-generic` |
| ホーム / シェル | `/home/profile` / `/bin/bash`、`ulimit -u` = 2000 |
| サービス | Xserver for WordPress。公開 URL は **`https://profile.xwp.jp`** |

`ssh novels`(ts-novels の本番)と**同じホスト**の別アカウント。

---

## 2. ディレクトリ構成

```
/home/profile/
├── .bash_profile .bashrc .ssh/
├── ssl/
└── profile.xwp.jp/                ← ドメイン単位のルート
    ├── wp-config.php              ★ 公開ルートの外側にある(rsync の --delete 禁止の理由)
    ├── public_html/               ★ 公開ルート = WordPress の設置ディレクトリ (131MB)
    │   ├── .htaccess              (40 行。§5)
    │   ├── .user.ini              (PHP 設定。§3)
    │   ├── index.php  wp-*.php  wp-admin/  wp-includes/
    │   └── wp-content/
    │       ├── plugins/  themes/  languages/  upgrade/
    │       ├── uploads/           (2026/07〜09 の空ディレクトリのみ。添付 0 件)
    │       └── (mu-plugins/ は**まだ存在しない**)
    ├── autoreply/  htpasswd/  log/  script/   (いずれも空)
    ├── mail/
    └── xserver_php/               (PHP セッションの保存先など)
```

- **`wp-config.php` は `~/profile.xwp.jp/` 直下**で、`public_html/` の中ではない。
  `~/profile.xwp.jp/` を宛先に `rsync --delete` すると消える。
- `~/profile.xwp.jp/log/` は空。**アクセスログ・エラーログはユーザから読めない** → 検収は curl と WP-CLI に頼る。
- `uploads/.htaccess` に Xserver パネル管理の実行拒否ブロックがある(アップロード領域で PHP/CGI は動かない):

  ```apache
  # BEGIN XS_WPPANEL_UPLOADS_SECURITY
  <FilesMatch "\.(php([0-9])?|phtml|pht|phtm|phps|phar|cgi|pl|py|sh|shtml|shtm)(\.|/|$)">
    Require all denied
  </FilesMatch>
  # END XS_WPPANEL_UPLOADS_SECURITY
  ```

---

## 3. リクエストの通り道と PHP の設定

```
ブラウザ / curl ──HTTPS──▶ nginx (キャッシュ層) ──▶ Apache (.htaccess 有効) ──▶ PHP 8.0.30 ──▶ WordPress 7.1.1 ──▶ MariaDB 10.11.18
```

- すべての応答に `server: nginx`。`.htaccess` の先頭 2 行が前段キャッシュを有効にしている:

  ```apache
  SetEnvIf Request_URI ".*" Ngx_Cache_NoCacheMode=off
  SetEnvIf Request_URI ".*" Ngx_Cache_StaticMode
  ```

- `GET /` の 200 応答には `cache-control` / `expires` が付かない(ts-novels で確認済みの「前段キャッシュから返っている」形と同じ)。
  **検証 curl には毎回キャッシュバスターを付ける**: `curl -sI "https://profile.xwp.jp/f_tsf/?_cb=$RANDOM"`
- **「反映されない」と見えたら、まずキャッシュを疑う。** 修正を重ねるループに入らない。
- crontab: 3 日より古い `wp-content/cache/*` を毎日 07:00 に削除(WordPress 側キャッシュの掃除。nginx 側とは別)。

### PHP の実行制限 (`.user.ini` 実測)

| 項目 | 値 | 効き所 |
|---|---|---|
| `memory_limit` | 1G | — |
| `max_execution_time` | **180 秒** | web 経由の重い処理は落ちる。一括投入は WP-CLI で |
| `post_max_size` / `upload_max_filesize` | 1G | 画像アップロードの上限はプラグイン側で絞る必要がある |
| `display_errors` | **On** | PHP の警告が画面に出る。**公開ページで notice/warning を出さない実装**が要る |
| `disable_functions` | `exec` `shell_exec` `proc_open` `popen` `symlink` ほか | web からの外部コマンド実行は不可 |
| `date.timezone` | Asia/Tokyo | — |
| `session.save_path` | `~/profile.xwp.jp/xserver_php/session` | — |

### PHP 拡張 (CLI で確認)

`curl` `dom` `exif` `fileinfo` `gd` `intl` `json` `mbstring` `openssl` `SimpleXML` `xml` `zip` `Zend OPcache` あり。

- **GD 2.2.5(WebP 可・AVIF 不可)、imagick なし** → 画像の縮小・変換は GD 前提。
- `PASSWORD_BCRYPT` あり / argon2id なし。

---

## 4. サーバ上で使えるツール

| ツール | バージョン | 備考 |
|---|---|---|
| WP-CLI | 2.8.1 | 検収と一括操作の主力 |
| PHP | 8.0.30 (CLI) | **mu-plugin は PHP 8.0 互換で書く**(enum・readonly プロパティ・`never` などは使えない) |
| MariaDB | サーバ 10.11.18 / クライアントあり | `wp db` 経由で使う |
| python3 | 3.6.8 | 古い。変換処理は開発機側で |
| rsync / git / curl / make / composer | 3.1.3 / 2.43.7 / あり / あり / 2.5.8 | 配備は rsync |
| sqlite3 / zip / unzip / perl | あり | — |
| node / npm | **無し** | JS/CSS のビルドは開発機で済ませ、成果物を rsync する |

WP-CLI の落とし穴(ts-novels と同じ): `--path=~/…` は展開されない。`cd ~/profile.xwp.jp/public_html && wp …` とする。

---

## 5. `.htaccess` の構造とガード

`public_html/.htaccess` は 40 行。コメント行(マーカー)だけを確認した:

| 行 | ブロック | 生成者 |
|---:|---|---|
| 1–2 | (マーカーなし)nginx キャッシュの `SetEnvIf` × 2 | Xserver |
| 4–14 | `# BEGIN CloudSecure WP Security Settings` …(内側に `rename_login_page`) | CloudSecure WP Security。**★ログイン URL の変更値がここにある** |
| 15–24 | `# BEGIN WordPress` … `# END WordPress` | WordPress |
| 26–33 | `# BEGIN XS_WPPANEL_REDIRECT_HTTPS:profile.xwp.jp` … | Xserver パネル(HTTP→HTTPS 301) |
| 35–39 | `# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI` … | Xserver パネル(`.cgi .pl .py .sh .phar .shtml .shtm` を拒否) |

ガード(ts-novels の普遍ルールをそのまま適用):

1. 触る前に `cp .htaccess .htaccess.bak-$(date +%Y%m%d)`。
2. 書くのは**自分のマーカーブロックの中だけ**。既存ブロックには手を入れない(パネル/プラグインが再生成する)。
3. **全文をリポジトリ・文書・コミット・チャットに持ち込まない**(ログイン URL 秘匿値)。構造確認は
   `ssh profile 'grep -n "^\s*#" ~/profile.xwp.jp/public_html/.htaccess'` で足りる。
4. 変更直後に「無関係な URL が 200 のまま」を curl で確認し、壊れていたら即 `.bak` を戻す。

doujin-profile 自体は `.htaccess` に規則を足さない(URL は WordPress コアが解決する)。
ただし案 M(Multisite。spec §4)を採る場合は、**`# BEGIN WordPress` ブロックを Multisite 用の規則に差し替える**必要がある
(`wp core multisite-convert` は `wp-config.php` しか書き換えない。Multisite では WordPress 本体もこのブロックを自動更新しなくなる)。
CloudSecure を無効化したあとに `rename_login_page` ブロックが残らないかも確認する。どちらも spec §13 の Phase 0 で、バックアップを取ってから行う。

---

## 6. WordPress の現状(ほぼ素の状態)

| 項目 | 実測値 |
|---|---|
| コア | **WordPress 7.1.1**(パッケージ言語 en_US、`WPLANG` = ja) |
| 構成 | **単一サイト**(`MULTISITE` / `WP_ALLOW_MULTISITE` 定数なし) |
| `siteurl` / `home` | `https://profile.xwp.jp` |
| `blogname` | プロファイル |
| `permalink_structure` | `/%year%/%monthnum%/%day%/%postname%/`(既定のまま) |
| `users_can_register` / `default_role` | `0` / `subscriber`(登録は閉じている) |
| `blog_public` | 1(検索エンジンに公開) |
| `show_on_front` | posts |
| `default_comment_status` | open |
| 有効テーマ | `twentytwentyfive` 1.5(ほかに 2022〜2024 が入っている) |
| 有効プラグイン | `cloudsecure-wp-security` 1.4.12(更新あり)のみ |
| 無効プラグイン | `akismet` 5.7(更新あり)/ `hello` / `xserver-typesquare-webfonts` |
| `mu-plugins/`・ドロップイン | **なし** |
| 投稿 | 投稿 1(初期投稿)・固定ページ 2(`sample-page` 公開 / `privacy-policy` 下書き)・添付 0 |
| DB | `profile_wp1` @ localhost、prefix `wp_`、約 0.9MB、標準 12 テーブル |
| 自動更新 | **コアはメジャー・マイナーとも有効**(7.1.1 へ自動で上がっている)。プラグインは全て off |
| 定数 | `WP_DEBUG` あり(値は未確認)。`DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` / `FORCE_SSL_ADMIN` / `WP_CACHE` なし |

### ユーザ

| ID | user_login | ロール |
|---:|---|---|
| 1 | `takano32` | administrator |
| 2 | `ftsf` | **administrator** |

- 公開したいプロフィールの slug は `f_tsf` だが、WordPress のログイン名は `ftsf` で**一致していない**。
- `DISALLOW_FILE_EDIT` が無いので、administrator は管理画面のテーマ/プラグインエディタから PHP を書き換えられる。
  つまり**管理者権限 = このアカウントで任意コード実行できる権限**。プロフィールの持ち主に渡してよい権限ではない(→ spec の認証・権限の章)。

---

## 7. 公開面の挙動(外部から curl。すべてキャッシュバスター付き)

| リクエスト | 応答 | 意味 |
|---|---|---|
| `/` | 200 `<title>プロファイル</title>` | 既定テーマのブログトップ |
| `/f_tsf/` `/hoge/` | **404** | まだ何も無い(ここを doujin-profile が受け持つ) |
| `/sample-page/` | 200 | ルート直下 1 セグメントは固定ページが使っている名前空間 |
| `/wp-login.php` | 404 | CloudSecure がログイン URL を変更している |
| `/wp-admin/` | 302 → `/cloudsecurewp_404` | 未ログインの管理画面アクセスを遮断 |
| `/?author=1` `/?author=2` | 302 → `/cloudsecurewp_404` | CloudSecure が author 列挙を遮断 |
| `/wp-json/wp/v2/users` | **200**(`takano32` を返す) | **REST 経由のユーザ列挙は開いている** |
| `/wp-sitemap-users-1.xml` | **200**(`/author/takano32/` を載せている) | サイトマップ経由でも分かる |
| `/author/takano32/` `/author/ftsf/` | **200** | 著者アーカイブが公開。ログイン名の実在確認に使える |
| `/xmlrpc.php` | GET 405 / POST 200 | **XML-RPC は生きている**(パスワード総当たりの経路になりうる) |
| `/wp-json/` | 200 | REST API は公開 |

ログイン URL を隠していても、**ログイン名は 3 経路で分かる**状態にある。ユーザを増やす前に塞ぐ(→ spec)。

---

## 8. ts-novels の本番 (novels.xwp.jp) との差分

| 項目 | novels | profile |
|---|---|---|
| ホスト / PHP / WP-CLI / ツール類 | — | **同じ** |
| WordPress コア | 7.1(2026-08-30 時点) | 7.1.1 |
| crontab のキャッシュ掃除 | 04:37 | 07:00 |
| mu-plugins | `ts-library` 配備済み | 未作成 |
| 管理者 | — | `takano32` と `ftsf` の 2 名 |

---

## 9. 未検証事項

- ログイン中のユーザに対して前段 nginx キャッシュがどう振る舞うか(編集直後に本人が古いページを見る可能性)。
- `WP_DEBUG` の値。
- CloudSecure の各機能(ログイン試行制限・2 段階認証・XML-RPC 無効化・REST 制限)の現在の設定値。管理画面でしか見られない → 👤。
- Xserver for WordPress が Multisite を公式にサポートするか。
- 規約の解釈(成人向けコンテンツ・第三者へのサービス提供)。spec の「ホスティング規約」の節を参照 → 👤 Xserver サポートへの確認。

---

## 10. 危険操作チェックリスト

### rsync(配備)

- [ ] **`--delete` を使っていない**(`~/profile.xwp.jp/` 直下に `wp-config.php`)
- [ ] `-n`(dry-run)を先に流し、転送一覧を目で見た
- [ ] 宛先は `public_html/wp-content/mu-plugins/`
- [ ] `node_modules` などビルド中間物が混ざっていない

### DB を変える前

- [ ] `mkdir -p ~/dumps` → `wp db export ~/dumps/pre-<用途>-$(date +%Y%m%d-%H%M).sql`(ダンプは公開リポジトリに入れない)
- [ ] `--dry-run` があるコマンドは先に dry-run。実行後にもう一度流して変更 0 件(冪等性)を確認

### 本番での実験

- [ ] `public_html/_probe/` の中だけで行い、**終わったら必ず消す**
- [ ] 秘匿値(ログイン URL・DB 認証情報・メールアドレス)を文書・コミット・チャットに転記しない
