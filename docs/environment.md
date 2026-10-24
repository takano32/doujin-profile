# 導入先環境 (profile.xwp.jp) と作業ガード

doujin-profile を入れる WordPress について、**実際に ssh / curl して確かめた事実**だけを書いた参照文書。
仕様そのものは [`spec.md`](spec.md) が正。先行プロジェクト ts-novels の `docs/environment.md` と同じ位置づけで、
同じホストの別アカウントなので内容もよく似ている(差分は §8)。

> **2026-10-24 にデプロイ先がさくらのレンタルサーバに変わった。** 移転先の実測は直下の「§S」にまとめた。
> §0 以降は**旧ホスト `profile.xwp.jp`(Xserver for WordPress)の 2026-09-18 時点の実測**で、移転先に合わせて書き直すまでは参考として残す。

## S. 移転先: さくらのレンタルサーバ(doujin-profile.no32.tk)

2026-10-24 に ssh と curl で確かめた事実。すべて読み取りだけで、何も変えていない。

### S.1 接続

```sh
scripts/wp.sh core version             # ターゲット sakura (scripts/targets/sakura.env)。既定
scripts/remote.sh 'ls -la wp-content'  # 設置先で sh のコマンドを実行
ssh tsff@doujin-profile.no32.tk        # 直接入るとき。ポート 22、鍵認証。~/.ssh/config の設定は要らない
```

| 項目 | 実測値 |
|---|---|
| 接続先 | `tsff@doujin-profile.no32.tk`、ポート 22。鍵は ssh-agent の ED25519 で通った |
| サーバ | さくらのレンタルサーバ(`www1191.sakura.ne.jp`)。FreeBSD 13.0-RELEASE-p15 |
| ホーム / シェル | `/home/tsff` / **`/bin/csh`**。`2>&1` は csh では「Ambiguous output redirect」になるので、`sh -c '…'` で包む |
| 公開 URL | **`https://doujin-profile.no32.tk/`**。証明書は Let's Encrypt(2026-10-24 〜 2027-01-22)。`http://` は `https://` へ 302 |

### S.2 ディレクトリ構成

```
/home/tsff/
├── www/                       ← 公開ルート。別のサイトも同居している
│   ├── index.html ほか        (既存の静的サイト)
│   ├── tsket/                 (別の WordPress。触れない)
│   └── doujin-profile/        ★ WordPress の設置先。wp-config.php もこの中
│       ├── .htaccess          (さくらの圧縮・キャッシュ設定 + WordPress 標準の書き換え)
│       ├── wp-cli.local.yml   (apache_modules: mod_rewrite)
│       └── wp-content/        (index.php languages plugins themes upgrade uploads。mu-plugins/ はまだ無い)
└── (dumps/ はまだ無い)
```

- 配備先は `~/www/doujin-profile/wp-content/mu-plugins/`(`scripts/deploy.sh`)。dry-run では、このディレクトリを作って `profile-site-hardening.php` を送る、という一覧になった。
- `~/www/doujin-profile/` の外には触れない。rsync の `--delete` は使わない(宛先の 2 つ上に `wp-config.php` がある)。

### S.3 ツール

| ツール | 実測 |
|---|---|
| PHP(CLI) | 8.3.36(`/home/rs/php/8.3/bin/php`) |
| WP-CLI | 2.12.0(`/usr/local/bin/wp`) |
| DB | MySQL 8.0.40 |
| rsync / git | あり |
| node | 無い |

### S.4 WordPress の現状

| 項目 | 実測 |
|---|---|
| バージョン | 7.0.2(同日の夕方には 7.1.3 になっていた。自動更新か手動かは未確認) |
| 構成 | **単一サイト**(`wp site list` は「This is not a multisite installation」)。`wp-config.php` に Multisite の定数は無い |
| `siteurl` / `home` | `http://doujin-profile.no32.tk`(https ではない。表示はサーバ側の転送で https になる) |
| ユーザ | `takano32`(administrator)だけ |
| 有効なプラグイン | `auto-login-for-sakura-rental-server` だけ(ほかに無効のものが 11 個) |
| テーマ | Twenty Twenty-Five 1.5 |
| mu-plugins | 無い(堅牢化用の mu-plugin は未配備) |

### S.5 Multisite にできるか(2026-10-24、読み取りだけで確認)

結論: **サブディレクトリ型なら、技術的な障害は見つからなかった。** 実際に変換して確かめるまでは「見込み」。
同じ確認は `scripts/check-multisite.sh` でどのターゲットにもできる。

| 前提 | 実測 | 判定 |
|---|---|---|
| サブディレクトリ型を選べるか | `allow_subdirectory_install()` = true(投稿はすべて 2026-10-24 作成で、1 か月より古い公開投稿が無い) | 可。**1 か月たつと選べなくなる**(コアの判定)。WP-CLI の `wp core multisite-convert` ならこの判定に関係なく変換できる |
| `.htaccess` の書き換えが効くか | パーマリンク `/%postname%/` で `/sample-page/` が 200、存在しないパスは 404、`/wp-json/` も 200 | 可。前段は nginx(`server: nginx`)だが、`.htaccess` は効いている |
| `wp-config.php` を書けるか | `is_writable` = true | 可(定数は WP-CLI か手で足す) |
| HTTPS の判定 | ログイン画面のフォームの送信先が `https://…`(`is_ssl()` が真) | 可。ただし `siteurl` / `home` が `http://` なので、**変換の前に https へ直す**(ネットワークの URL は変換時の値で決まる) |
| ディスク | quota 300GB、使用 1.5GB | 余裕あり |
| メール(招待・パスワード再設定) | `/usr/sbin/sendmail` あり | 送れる見込み(未送信) |
| サブドメイン型 | `*.doujin-profile.no32.tk` は NXDOMAIN。DNS は value-domain(`ns1/ns2.value-domain.com`)、`doujin-profile` はさくらのサーバへの CNAME | 今のままでは不可。ワイルドカードの DNS と、さくら側でサブドメインを受けられるかの確認が要る(spec はサブディレクトリ型なので必須ではない) |
| 同居サイト | `~/www/tsket/` などは別のディレクトリ・別の WordPress | Multisite 化は `~/www/doujin-profile/` の `wp-config.php`・`.htaccess`・DB のテーブルだけを変えるので、影響は出ない見込み。DB も別(この DB のテーブルは接頭辞 `wp` の 12 個だけ。`tsket` の接頭辞は別の値) |

テーブル接頭辞は `wp`(アンダースコア無し。表は `wpposts` など)。Multisite のサイト別の表は `wp2posts` のような名前になる。

PHP の値(CLI で確認。Web 側の値は未確認): `upload_max_filesize` 5M、`post_max_size` 8M。マンガの画像を上げるには小さいかもしれない。

### S.6 未検証・気になる点

- 成人向けの扱い。2026-09-18 の調査([`hosting-research.md`](hosting-research.md) §5)では、さくらのレンタルサーバは機能比較表の「アダルトサイト」が全プランで ×。決めるのはさくらなので、使い方を決める前に再確認する。
- Multisite への変換は、実機ではまだ試していない(S.5 は読み取りでの見込み)。
- Web 側の PHP の値(アップロードの上限など)。
- `.user.ini` などの PHP の実行制限、PHP 拡張の一覧。

---

## 0. 計測の基準

| 項目 | 値 |
|---|---|
| 調査日 | 2026-09-18。午後に初回調査(単一サイトの状態)。18 時台に **Multisite 化**([`multisite-trial.md`](multisite-trial.md))を行い、その後に再測定した。19:45 ごろ堅牢化用の mu-plugin を配備した。本書は**その後の状態**を書く |
| 方法 | `ssh profile` でのコマンド(`ls` / `wp` / `php -m` など)と、外部からの `curl`。初回調査は読み取り専用。変換作業で変えたものは [`multisite-trial.md`](multisite-trial.md) にすべて記録してある |
| 読まなかったもの | `wp-config.php` の中身(Multisite 関連の定数の行だけは確認)、ユーザのメールアドレス、(変換前の)`.htaccess` の CloudSecure ブロック = ログイン URL の変更値 |
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
├── dumps/                         ← 作業前バックアップ(700。Web から見えない)
│   └── pre-multisite-20260918-1804/   db.sql / wp-config.php / htaccess ほか
└── profile.xwp.jp/                ← ドメイン単位のルート
    ├── wp-config.php              ★ 公開ルートの外側にある(rsync の --delete 禁止の理由)
    ├── public_html/               ★ 公開ルート = WordPress の設置ディレクトリ (131MB)
    │   ├── .htaccess              (41 行。§5)
    │   ├── .user.ini              (PHP 設定。§3)
    │   ├── index.php  wp-*.php  wp-admin/  wp-includes/
    │   └── wp-content/
    │       ├── plugins/  themes/  languages/  upgrade/
    │       ├── uploads/           (メインサイト用。添付 0 件)
    │       │   └── sites/         (Multisite のサイト別アップロード先。`sites/2/` が `/ftsf/` 用)
    │       └── mu-plugins/        (2026-09-18 作成。`profile-site-hardening.php` だけ。リポジトリの mu-plugins/ が正本)
    ├── autoreply/  htpasswd/  log/  script/   (いずれも空)
    ├── mail/
    └── xserver_php/               (PHP セッションの保存先など)
```

- **`wp-config.php` は `~/profile.xwp.jp/` 直下**で、`public_html/` の中ではない。
  `~/profile.xwp.jp/` を宛先に `rsync --delete` すると消える。
- `~/profile.xwp.jp/log/` は空。**アクセスログ・エラーログはユーザから読めない** → 検収は curl と WP-CLI に頼る。
- `uploads/.htaccess` に Xserver パネル管理の実行拒否ブロックがある(アップロード領域で PHP/CGI は動かない。`uploads/sites/` 以下にも継承される):

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
ブラウザ / curl ──HTTPS──▶ nginx ──▶ Apache (.htaccess 有効) ──▶ PHP 8.0.30 ──▶ WordPress 7.1.1 (Multisite) ──▶ MariaDB 10.11.18
```

- すべての応答に `server: nginx`。`.htaccess` の先頭 2 行が前段キャッシュのモードを指定している:

  ```apache
  SetEnvIf Request_URI ".*" Ngx_Cache_NoCacheMode=off
  SetEnvIf Request_URI ".*" Ngx_Cache_StaticMode
  ```

- **実測(2026-09-18): 現在の設定では、HTML はキャッシュされない。** 固定ページの内容を書き換えると、キャッシュバスター無しの URL にも即時に反映された
  (同じ URL を先に 6 回取得してからでも同じ)。削除すれば即時に 404。`StaticMode` の名のとおり、キャッシュされるのは静的ファイル。
  - 静的ファイル(CSS など)の応答は `cache-control: max-age=604800`(7 日)と `expires` 付き。
  - HTML の応答には `cache-control` も `expires` も付かない(WordPress は通常のページにこれらを付けないので、これ自体はキャッシュの証拠にならない)。
  - パネルのサーバーキャッシュ設定を変えれば挙動は変わりうる。検証 curl にキャッシュバスター(`?_cb=$RANDOM`)を付ける習慣は害が無いので続ける。
- crontab: 3 日より古い `wp-content/cache/*` を毎日 07:00 に削除(WordPress 側キャッシュの掃除)。

### PHP の実行制限 (`.user.ini` 実測)

| 項目 | 値 | 効き所 |
|---|---|---|
| `memory_limit` | 1G | — |
| `max_execution_time` | **180 秒** | web 経由の重い処理は落ちる。一括投入は WP-CLI で |
| `post_max_size` / `upload_max_filesize` | 1G | 実際のアップロード上限は WordPress のネットワーク設定が決める(§6) |
| `display_errors` | **On** | PHP の警告が画面に出る。**公開ページで notice/warning を出さない実装**が要る(変換後の各ページに警告が出ていないことは確認済み) |
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
| WP-CLI | 2.8.1 | 検収と一括操作の主力。`wp site …` `wp super-admin …` `wp theme enable --network` など Multisite 用のコマンドも使える |
| PHP | 8.0.30 (CLI) | **mu-plugin は PHP 8.0 互換で書く**(enum・readonly プロパティ・`never` などは使えない) |
| MariaDB | サーバ 10.11.18 / クライアントあり | `wp db` 経由で使う |
| python3 | 3.6.8 | 古い。変換処理は開発機側で |
| rsync / git / curl / make / composer | 3.1.3 / 2.43.7 / あり / あり / 2.5.8 | 配備は rsync |
| sqlite3 / zip / unzip / perl | あり | — |
| node / npm | **無し** | JS/CSS のビルドは開発機(node 24)で済ませ、成果物を rsync する |

WP-CLI の注意:

- `--path=~/…` は展開されない。`cd ~/profile.xwp.jp/public_html && wp …` とする。
- **Multisite なので、サイトを指すときは `--url=https://profile.xwp.jp/ftsf/` を付ける**(付けないとメインサイトが対象になる)。

---

## 5. `.htaccess` の構造とガード

`public_html/.htaccess` は 41 行。

| 行 | ブロック | 状態 |
|---:|---|---|
| 1–2 | (マーカーなし)nginx キャッシュの `SetEnvIf` × 2 | Xserver が生成 |
| 4–5 | `# BEGIN CloudSecure WP Security Settings` 〜 `# END`(**中身は空**) | CloudSecure を無効化したら、プラグイン自身がログイン URL 変更のブロックを消した。外枠のマーカーだけ残っている |
| 6–25 | `# BEGIN WordPress` 〜 `# END WordPress` | **Multisite(サブディレクトリ型)用の規則に手で差し替えた**(2026-09-18)。Multisite では WordPress 本体はこのブロックを自動更新しない |
| 27–34 | `# BEGIN XS_WPPANEL_REDIRECT_HTTPS:profile.xwp.jp` … | Xserver パネル(HTTP→HTTPS 301) |
| 36–40 | `# BEGIN XS_WPPANEL_WP_ROOT_CGI_SSI` … | Xserver パネル(`.cgi .pl .py .sh .phar .shtml .shtm` を拒否) |

ガード(ts-novels の普遍ルールをそのまま適用):

1. 触る前にコピーを取る(`~/dumps/` へ)。
2. 書くのは**自分が管理するブロックの中だけ**。パネルやプラグインのブロックには手を入れない。
3. CloudSecure を再び有効にした場合、そのブロックには**ログイン URL の変更値**が入る。全文をリポジトリ・文書・コミット・チャットに持ち込まない。
   構造確認は `ssh profile 'grep -n "^\s*#" ~/profile.xwp.jp/public_html/.htaccess'` で足りる。
4. 変更直後に「無関係な URL が 200 のまま」を curl で確認し、壊れていたら即コピーを戻す。

doujin-profile 自体は `.htaccess` に規則を足さない(URL は WordPress コアが解決する)。

---

## 6. WordPress の現状

| 項目 | 実測値 |
|---|---|
| コア | **WordPress 7.1.1**(パッケージ言語 en_US、`WPLANG` = ja) |
| 構成 | **Multisite・サブディレクトリ型**(2026-09-18 に `wp core multisite-convert` で変換。`wp-config.php` の 96〜103 行に定数) |
| サイト | 1: `https://profile.xwp.jp/`(メインサイト、テーマ twentytwentyfive)/ 2: `https://profile.xwp.jp/ftsf/`(タイトル「TSFのF」、テーマ twentytwentyfour)。どちらも公開、中身はコアの初期データ(投稿 `hello-world`、固定ページ `sample-page`) |
| メインサイトのパーマリンク | `/blog/%year%/%monthnum%/%day%/%postname%/`(サブディレクトリ型では、コアがメインサイトの投稿に `/blog` を前置する) |
| ネットワークで有効なテーマ | `twentytwentyfive` `twentytwentyfour`(ほかに 2022・2023 がインストール済み) |
| プラグイン | **有効なものは無い**。`cloudsecure-wp-security` 1.4.12 は無効(Multisite 非対応。更新あり)。ほかに `akismet` / `hello` / `xserver-typesquare-webfonts`(無効) |
| `mu-plugins/` | `profile-site-hardening` 0.1.0(XML-RPC の停止、コメント・ピンバックの閉鎖。`scripts/deploy.sh` で配備)。ドロップインは無し |
| DB | `profile_wp1` @ localhost、prefix `wp_`、約 2MB、**34 テーブル**: 標準 12 + CloudSecure の 6(`wp_cloudsecurewp_*`。`wp db tables` には出ない)+ Multisite の 6(`wp_blogs` `wp_blogmeta` `wp_site` `wp_sitemeta` `wp_signups` `wp_registration_log`)+ サイト 2 の 10(`wp_2_*`) |
| 自動更新 | **コアはメジャー・マイナーとも有効**。プラグインは全て off |
| 定数 | `WP_DEBUG` あり(値は未確認)。`DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` / `FORCE_SSL_ADMIN` / `WP_CACHE` なし |

### ネットワーク設定の既定値(変換直後)

| 設定 | 値 | 評価 |
|---|---|---|
| `registration` | `none` | ○ 登録は停止 |
| `add_new_users` | `0` | ○ サイト管理者は新規ユーザを作れない |
| `menu_items` | (空) | ○ サイト管理者にプラグインメニューは出ない |
| `upload_space_check_disabled` | `1` | **× 容量の検査が無効**(上限値 `blog_upload_space` = 100MB は入っているが効いていない) |
| `fileupload_maxk` | `1500` | 1 ファイル 1,500KB まで |
| `upload_filetypes` | 画像のほか動画・文書・`css` なども含む長い一覧 | × 画像だけに絞る |
| `illegal_names` | `www` `web` `root` `admin` `main` `invite` `administrator` `files` `blog` | 運営用の語を足す |

### ユーザ

| ID | user_login | ネットワーク | サイト 1 | サイト 2 (`/ftsf/`) |
|---:|---|---|---|---|
| 1 | `takano32` | **特権管理者** | administrator | administrator(サイトの作成者) |
| 2 | `ftsf` | **特権管理者** | administrator | administrator |

- `takano32` と `ftsf` は、どちらも運営者(spec §7.8)。ネットワーク作成時点の administrator は全員が特権管理者になる(コアの仕様)ので、変換でこの状態になった。
- 2026-09-18 19:45 ごろ、Claude が誤って `ftsf` を特権管理者とメインサイトから外したが、同日中に元へ戻した(`wp super-admin add ftsf` と、メインサイトでの `wp user set-role ftsf administrator`)。
- 実測: 特権管理者でないサイト管理者は、自分のサイトで固定ページ・投稿・メディア・テーマの切り替え・サイトエディタ・サイト設定・サイトの削除ができ、
  プラグイン/テーマのインストールと有効化・ファイル編集・`unfiltered_html`・新規ユーザ作成・ネットワーク管理はできない。所属していないサイトには何の権限も無い
  (一時ユーザで確認し、確認後に削除した)。

---

## 7. 公開面の挙動(外部から curl。すべてキャッシュバスター付き)

| リクエスト | 応答 | 意味 |
|---|---|---|
| `/` | 200 `<title>プロファイル</title>` | メインサイト |
| `/ftsf/` | 200 `<title>TSFのF</title>` | サイト 2。前段 nginx 経由でもサイト別の URL が解決される |
| `/ftsf/sample-page/` `/ftsf/wp-json/` | 200 | サイトの中のページ・REST |
| `/ftsf/wp-includes/…` `/ftsf/wp-content/themes/…` `/ftsf/wp-admin/css/…` | 200 `text/css` | サイト別パスの静的ファイルも配信される |
| `/hoge/` `/ftsf/nonexistent/` | 404 | — |
| `/wp-login.php` `/ftsf/wp-login.php` | **200** | **ログイン URL は標準に戻った**(CloudSecure 無効化のため)。ログイン画面はサイト別に出る |
| `/wp-admin/` `/ftsf/wp-admin/` `/wp-admin/network/` | 302 → それぞれの `wp-login.php` | — |
| `/wp-signup.php` `/wp-activate.php` | 404 | 登録は停止 |
| `/?author=1` | 301 → `/blog/author/takano32/` | CloudSecure の遮断が外れ、コア標準の転送に戻った |
| `/wp-json/wp/v2/users` `/ftsf/wp-json/wp/v2/users` | 200(どちらも `takano32` を返す) | 初期投稿の作者として見えている |
| `/wp-sitemap-users-1.xml` `/ftsf/author/ftsf/` | 200 | — |
| `/xmlrpc.php` `/ftsf/xmlrpc.php` | 認証の要るメソッドは fault 405「このサイト上では XML-RPC サービスが無効になっています。」 | 堅牢化用の mu-plugin で停止済み(配備前は POST を受け付けていた)。ピンバックのメソッドと `X-Pingback` ヘッダも無い。コメント欄も閉じている |

ユーザ名は設計上も公開情報(spec §5.2)。防御の本体は、サーバ側のログイン試行回数制限・2 段階認証(未導入)・XML-RPC の停止(済み)(spec §10)。

---

## 8. ts-novels の本番 (novels.xwp.jp) との差分

| 項目 | novels | profile |
|---|---|---|
| ホスト / PHP / WP-CLI / ツール類 | — | **同じ** |
| 構成 | 単一サイト | **Multisite(サブディレクトリ型)** |
| CloudSecure WP Security | 有効 | 無効(Multisite 非対応) |
| 前段キャッシュ | ts-novels の文書は「HTML もキャッシュされる」前提で書かれている | 実測では HTML はキャッシュされない(§3)。設定が違うのか、あちらの推定が違うのかは未確認 |
| mu-plugins | `ts-library` 配備済み | `profile-site-hardening` だけ |

---

## 9. 未検証事項

- サーバ側の「ログイン試行回数制限」「国外 IP 制限」が、サイト別のパス(`/ftsf/wp-login.php` など)にも効くか。
  わざと失敗を重ねると作業元の IP が 24 時間ロックされるおそれがあるので試していない → Xserver への問い合わせ([`xserver-inquiry.md`](xserver-inquiry.md) 第 4 問)。
- パネルの「セキュリティ自動最適化モード」が、無効化した CloudSecure を有効に戻さないか(2026-09-18 18:10 時点では無効のまま。経過観察)。
- パネルの手動バックアップと復元が、Multisite でも正常に動くか 👤。
- 戻し方([`multisite-trial.md`](multisite-trial.md) §2)は文書化したが、**リハーサルはしていない**。
- `WP_DEBUG` の値。ログイン中のユーザに対する前段 nginx の振る舞い。
- 規約の解釈(成人向けコンテンツ・第三者へのサービス提供)→ 👤 Xserver サポートへの確認。

---

## 10. 危険操作チェックリスト

### rsync(配備)

- [ ] **`--delete` を使っていない**(`~/profile.xwp.jp/` 直下に `wp-config.php`)
- [ ] `-n`(dry-run)を先に流し、転送一覧を目で見た
- [ ] 宛先は `public_html/wp-content/mu-plugins/`(テーマは `public_html/wp-content/themes/`)
- [ ] `node_modules` などビルド中間物が混ざっていない

### DB を変える前

- [ ] `wp db export ~/dumps/pre-<用途>-$(date +%Y%m%d-%H%M).sql`(ダンプは公開リポジトリに入れない。`~/dumps` は 700)
- [ ] `--dry-run` があるコマンドは先に dry-run。実行後にもう一度流して変更 0 件(冪等性)を確認
- [ ] サイトを指すコマンドに `--url=` を付けた(付け忘れるとメインサイトを変えてしまう)

### 本番での実験

- [ ] 手順と戻し方を先に文書にした
- [ ] 一時的に作ったユーザ・ページ・ファイルは**終わったら必ず消す**
- [ ] 秘匿値(ログイン URL の変更値・DB 認証情報・メールアドレス)を文書・コミット・チャットに転記しない
