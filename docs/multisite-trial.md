# Multisite 化の検証(Phase 0)— 手順・結果・戻し方

案 M(Multisite。[`spec.md`](spec.md) §4)がこのホストで成立するかを、本番 `profile.xwp.jp` で直接確かめる。

| 項目 | 値 |
|---|---|
| 実施日 | 2026-09-18 |
| 根拠 | ユーザ裁定 T.23(2026-09-18):「まだ公開していないサイトなので実験していい」。ステージングではなく本番で直接試す |
| 対象 | `ssh profile` / `~/profile.xwp.jp/public_html`(WordPress 7.1.1、単一サイト、中身は初期データだけ) |
| 変わるもの | DB(Multisite 用のテーブル追加、`wp_users` に列追加)、`~/profile.xwp.jp/wp-config.php`(定数の追記)、`public_html/.htaccess`(WordPress ブロックの差し替え)、CloudSecure WP Security の無効化 |
| 利用者への影響 | **ログイン URL が標準の `/wp-login.php` に戻る**(CloudSecure のログイン URL 変更が外れるため) |

秘匿値の扱い: `wp-config.php` の中身と、`.htaccess` の CloudSecure ブロック(ログイン URL の変更値)は表示も転記もしない。バックアップはサーバのホームディレクトリ(Web から見えない場所)に置き、リポジトリには入れない。

---

## 1. 手順

### 1.1 バックアップ

```sh
TS=pre-multisite-$(date +%Y%m%d-%H%M)
mkdir -p ~/dumps/$TS && chmod 700 ~/dumps ~/dumps/$TS
cd ~/profile.xwp.jp/public_html
wp db export ~/dumps/$TS/db.sql
cp -p ~/profile.xwp.jp/wp-config.php ~/dumps/$TS/wp-config.php
cp -p .htaccess ~/dumps/$TS/htaccess
```

ダンプが完結していること(末尾の `Dump completed`、`CREATE TABLE` が 18 個 = 標準 12 + CloudSecure の 6)を確認してから先へ進む。

### 1.2 CloudSecure WP Security の無効化

Multisite 非対応(公式)なので、変換の前に外す。

```sh
wp plugin deactivate cloudsecure-wp-security
grep -n '^\s*#' .htaccess        # CloudSecure のブロックが消えたことをマーカーだけで確認
```

確認: `/wp-login.php` が 200 になる。`/` が 200 のまま。

### 1.3 Multisite へ変換(サブディレクトリ型)

```sh
wp core multisite-convert --title='プロファイル' --user=takano32
```

- Multisite 用のテーブルを作り、`wp-config.php` の `stop editing` 行の前に定数を追記する。
- `--user=takano32` を付けるのは、ネットワーク管理者の決定に「現在のユーザ」が使われるため。
- **注意(コアの仕様)**: ネットワーク作成時点の administrator は全員が特権管理者になる。`takano32` と `ftsf` の両方が該当する。
  `ftsf` の権限をどうするかは未裁定(spec T.6)なので、この検証では変更しない。

### 1.4 `.htaccess` の WordPress ブロックを Multisite 用に差し替え

`# BEGIN WordPress` 〜 `# END WordPress` の間だけを、WordPress 公式のサブディレクトリ型の規則に置き換える(他のブロックには触れない)。
Multisite では WordPress 本体はこのブロックを自動更新しない。

```apache
# BEGIN WordPress
# Multisite (subdirectory) rules. WordPress does not manage this block on multisite.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to /wp-admin
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]
RewriteRule . index.php [L]
</IfModule>
# END WordPress
```

確認: `/` と `/sample-page/` が 200 のまま。

### 1.5 `/ftsf/` サイトを作る

```sh
wp site create --slug=ftsf --title='TSFのF'            # 管理者は特権管理者 (takano32) になる
wp user set-role ftsf administrator --url=https://profile.xwp.jp/ftsf/
wp theme enable twentytwentyfour --network
wp theme activate twentytwentyfour --url=https://profile.xwp.jp/ftsf/    # サイトごとに違うテーマになることの確認用
```

### 1.6 確認項目

| # | 確認 | 方法 |
|---|---|---|
| C1 | メインサイトが正常 | `/` `/sample-page/` が 200、`/wp-login.php` が 200 |
| C2 | サイト別 URL が前段 nginx 経由で解決される | `/ftsf/` 200、`/ftsf/wp-login.php` 200、`/ftsf/wp-admin/` がログインへ 302、`/ftsf/wp-json/` 200 |
| C3 | サイト別パスの静的ファイルが配信される | `/ftsf/wp-includes/…` の実ファイルが 200 |
| C4 | サイトごとに違うテーマで表示される | `/` は twentytwentyfive、`/ftsf/` は twentytwentyfour のスタイルを読み込む |
| C5 | 権限がサイト単位になっている | `wp super-admin list`、`wp user list --url=…/ftsf/` |
| C6 | CloudSecure の残骸が無い / 勝手に戻らない | `.htaccess` のマーカー、`wp plugin list`(作業の最後にも再確認) |
| C7 | 前段キャッシュの寿命 | 検証用ページの内容を書き換え、キャッシュバスター無しの URL に反映されるまでの時間を測る(測ったら消す) |
| — | サーバ側のログイン試行回数制限がサイト別パスに効くか | **今回は試さない**(わざと失敗を重ねると、作業元の IP が 24 時間ロックされるおそれ)。Xserver への問い合わせの第 4 問で確認する |

---

## 2. 戻し方

バックアップのディレクトリを `B=~/dumps/pre-multisite-YYYYMMDD-HHMM` とする。

```sh
cd ~/profile.xwp.jp/public_html

# 1) 設定ファイルを戻す (CloudSecure のブロックも元どおりになる)
cp -p $B/wp-config.php ~/profile.xwp.jp/wp-config.php
cp -p $B/htaccess .htaccess

# 2) DB を戻す (ダンプにある 18 テーブルが変換前の状態に戻る。CloudSecure も有効な状態に戻る)
wp db import $B/db.sql

# 3) 変換で増えたテーブルを消す (import では消えない)
wp db query "DROP TABLE IF EXISTS wp_blogs, wp_blogmeta, wp_site, wp_sitemeta, wp_signups, wp_registration_log"
wp db tables 'wp_2_*' --all-tables 2>/dev/null     # サイト 2 のテーブルを確認して
wp db query "DROP TABLE IF EXISTS wp_2_commentmeta, wp_2_comments, wp_2_links, wp_2_options, wp_2_postmeta, wp_2_posts, wp_2_term_relationships, wp_2_term_taxonomy, wp_2_termmeta, wp_2_terms"

# 4) サイト別のアップロード先ができていれば消す
rm -rf wp-content/uploads/sites

# 5) 確認
wp core is-installed --network && echo "まだ Multisite" || echo "単一サイトに戻った"
curl -s -o /dev/null -w '%{http_code}\n' "https://profile.xwp.jp/?_cb=$RANDOM"     # 200
```

戻したあと、ログイン URL は変換前(CloudSecure で変更した URL)に戻る。👤 ログインできることを確認する。

ホスト側にも自動バックアップ(過去 14 日分)がある。上の手順で戻せない場合はパネルから復元する。

---

## 3. 結果(2026-09-18 18:04〜18:10 JST に実施)

**技術的には成立した。** 手順 1.1〜1.5 は書いたとおりに通り、確認項目 C1〜C7 はすべて通過。想定外の挙動は無かった。

### 3.1 実施の記録

| 手順 | 結果 |
|---|---|
| 1.1 バックアップ | `~/dumps/pre-multisite-20260918-1804/` に `db.sql`(190KB、18 テーブル、末尾に `Dump completed`)、`wp-config.php`、`htaccess`。チェックサムが元と一致。600 / ディレクトリは 700 |
| 1.2 CloudSecure 無効化 | 成功。プラグイン自身が `.htaccess` からログイン URL 変更のブロックを消した(外枠のマーカー 2 行だけ残る)。`/wp-login.php` が 200 に戻った |
| 1.3 変換 | `Set up multisite database tables.` / `Added multisite constants to 'wp-config.php'.` 定数は 96〜103 行(`stop editing` の行と `wp-settings.php` の読み込みより前)。最古の公開投稿が 1 か月以上前でも、WP-CLI ならサブディレクトリ型で変換できた |
| 1.4 `.htaccess` | WordPress ブロックだけを差し替え(31 行 → 41 行)。差し替え直前の状態も `htaccess.after-cloudsecure-off` として保存。`/` `/sample-page/` `/wp-login.php` `/wp-json/` は 200 のまま、存在しない URL は 404 |
| 1.5 サイト作成 | `Site 2 created: https://profile.xwp.jp/ftsf/`。`ftsf` を管理者に追加。TT4 をネットワークで有効化し `/ftsf/` だけ TT4 に |

### 3.2 確認項目

| # | 結果 |
|---|---|
| C1 | ✅ `/` `/sample-page/` `/wp-login.php` が 200 |
| C2 | ✅ `/ftsf/` 200、`/ftsf/sample-page/` 200、`/ftsf/wp-login.php` 200(タイトルは「ログイン ‹ TSFのF」、フォームの送信先も `/ftsf/wp-login.php`)、`/ftsf/wp-admin/` は `/ftsf/wp-login.php` へ 302、`/ftsf/wp-json/` 200、`/hoge/` 404 |
| C3 | ✅ `/ftsf/wp-includes/css/…`、`/ftsf/wp-content/themes/twentytwentyfour/style.css`、`/ftsf/wp-admin/css/login.min.css` がいずれも 200 `text/css`。前段 nginx はリクエストを Apache まで通しており、`.htaccess` の書き換えが効く |
| C4 | ✅ `/` の HTML は `themes/twentytwentyfive` だけ、`/ftsf/` は `themes/twentytwentyfour` だけを参照。タイトルも「プロファイル」と「TSFのF」で別 |
| C5 | ✅ 特権管理者でないサイト管理者(一時ユーザで確認 → 削除済み)は、自分のサイトの編集・テーマ切り替え・サイトエディタ・サイト設定・サイト削除ができ、プラグイン/テーマの導入・ファイル編集・`unfiltered_html`・新規ユーザ作成・ネットワーク管理はできない。所属していないメインサイトには `read` すら無い |
| C6 | ✅ 作業の最後(18:10)でも CloudSecure は無効のまま、`.htaccess` のマーカーも変わらず。**パネルの自動最適化が後から戻さないかは経過観察** |
| C7 | ✅ **現在の設定では HTML はキャッシュされない**。検証用ページの内容を書き換えると、キャッシュバスター無しの URL にも即時に反映された(先に同じ URL を 6 回取得してからでも同じ)。削除後は即時に 404。静的ファイルは `max-age=604800`。検証用ページは削除済み |
| — | ✅ 各ページの HTML に PHP の Warning / Notice / Deprecated は出ていない(`display_errors = On` のため確認) |

### 3.3 分かったこと

- **コアは、既存ユーザと同名のサイト名をそのユーザのために確保する。** 別のユーザとして `wpmu_validate_blog_signup('ftsf')` を呼ぶと「このサイト名は予約済みです」。
  「サイト名 = 持ち主のユーザ名」という設計と噛み合う。招待コマンドは持ち主を渡して検査する必要がある。
- `f_tsf` はコアの検査で「サイト名には半角英字の小文字 (a-z) と半角数字のみを含められます」と拒否される(裁定 T.12 のとおり)。
- **ネットワーク作成時点の administrator は全員が特権管理者になる**(`takano32` と `ftsf`)。権限の強さは変換前と同じだが、spec の原則(特権管理者は運営者だけ)には反している → T.6。
- 新しいサイトには、コアが `hello-world` と `sample-page` を入れる。サイトの作成者(`takano32`)も管理者として入る。どちらも Phase 1 の「サイトの初期設定」で整える対象。
- ネットワーク設定の既定値のうち、**アップロード容量の検査は無効**、許可形式は画像以外も含む。Phase 1 で直す(environment.md §6)。
- CloudSecure が無効になったので、`/?author=N` の遮断が外れ、XML-RPC も引き続き POST を受け付ける。ユーザ名は設計上も公開情報だが、XML-RPC は早めに止めたい(spec §10)。

### 3.4 していないこと

- サーバ側のログイン試行回数制限がサイト別のパスに効くかの確認(作業元 IP のロックを避けた)。
- 戻し方(§2)のリハーサル。手順は書いてあり、バックアップの完結性は確かめたが、実際に戻してはいない。
- パネルの手動バックアップと復元の確認 👤。

### 3.5 いまの状態と、利用者が知っておくべきこと

- 本番は **Multisite のまま**にしてある(`/` と `/ftsf/` の 2 サイト。どちらも中身はコアの初期データ)。
- **ログイン URL は `https://profile.xwp.jp/wp-login.php`(`/ftsf/` のサイトは `https://profile.xwp.jp/ftsf/wp-login.php`)。CloudSecure で変更していた URL は使えない。**
- ネットワーク管理画面は `https://profile.xwp.jp/wp-admin/network/`。
- Xserver の回答が否定的だった場合は、§2 の手順で単一サイトに戻す。
