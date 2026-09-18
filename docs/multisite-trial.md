# Multisite 化の検証(Phase 0)— 手順・結果・戻し方

案 M(Multisite。[`spec.md`](spec.md) §4)がこのホストで成立するかを、本番 `profile.xwp.jp` で直接確かめる。

| 項目 | 値 |
|---|---|
| 実施日 | 2026-09-18 |
| 根拠 | ユーザ裁定 J7(2026-09-18):「まだ公開していないサイトなので実験していい」。ステージングではなく本番で直接試す |
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

ダンプが完結していること(末尾の `Dump completed`、標準 12 テーブルの `CREATE TABLE`)を確認してから先へ進む。

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
  `ftsf` の権限をどうするかは未裁定(spec D6)なので、この検証では変更しない。

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

# 2) DB を戻す (標準 12 テーブルが変換前の状態に戻る。CloudSecure も有効な状態に戻る)
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

## 3. 結果

(実施後に記入)
