# 前段 Caddy + サブドメイン型 Multisite の仮運用 — 手順・結果・戻し方

`https://<名前>.dp.no32.tk/` でユーザごとのサイトを出す構成を、仮運用の環境で確かめる。
**変更の前に手順と戻し方を書き、実施後に結果を足す**(`multisite-trial.md` と同じ書き方)。

| 項目 | 値 |
|---|---|
| 根拠 | ユーザ裁定 2026-10-24:仮運用は米国の e2-micro の Caddy を前段にする。本番では国内に Caddy を立てる(Caddy と WordPress の同居が理想だが難しい見込み)。規約は判断材料にしない・当面は非公開 |
| 前段 | e2-micro(`ssh takano32@35.212.198.233`、Caddy 2.6.2。`environment.md` §C) |
| 裏側 | ターゲット `sakura`(`~/www/doujin-profile/`、WordPress 単一サイト・初期データだけ。`environment.md` §S) |
| DNS | `dp.no32.tk` と `*.dp.no32.tk` は e2-micro を向いている(追加は要らない) |
| 状態 | 段階 A・B・C は実施済み(2026-10-24、結果は §4)。ログインだけ未確認(§4 段階 C)(各段階の前に、サイト所有者の了承を得る) |

名前: サーバ上に置くファイル・環境変数・ヘッダには、なるべく `doujin-profile` を入れる(ユーザ指示 2026-10-24「あとで忘れるでしょ」)。

秘匿値の扱い: Caddy とさくらのあいだの共有の秘密(下の `DOUJIN_PROFILE_PROXY_SECRET`)は、サーバの上にだけ置く。リポジトリ・チャットには書かない。

---

## 0. 通り道

```
訪問者 ──https──> Caddy (e2-micro)                ──https──> さくらの nginx ──> Apache + PHP (WordPress)
  Host: ftsf.dp.no32.tk   on-demand TLS で証明書          Host: doujin-profile.no32.tk(さくらに登録済みの名前)
                          X-Doujin-Profile-Host: ftsf.dp.no32.tk     X-Doujin-Profile-Host / X-Doujin-Profile-Client-IP / X-Doujin-Profile-Secret を付ける
```

- さくらは登録していないホスト名に 403 を返すので(`environment.md` §C)、Caddy からは**登録済みの名前で、https で**つなぐ。
- WordPress には `wp-config.php` の数行で、秘密が一致したときだけ `X-Doujin-Profile-Host` を `HTTP_HOST` に、`X-Doujin-Profile-Client-IP` を `REMOTE_ADDR` に入れる。Multisite はその `HTTP_HOST` でサイトを見分ける(コア標準のサブドメイン型)。
- 仮運用では、経路が日本 → 米国 → 日本になるので遅い。本番では Caddy を国内に置く。

## 1. 段階 A: さくらの nginx がヘッダを通すかの確認(変更は一時ファイル 1 つ)

```sh
# 確認用の PHP を置く(名前は推測されにくいものにする)
scripts/remote.sh 'cat > doujin-profile-header-probe-XXXX.php <<"EOF"
<?php
header( "Content-Type: text/plain" );
foreach ( $_SERVER as $k => $v ) {
	if ( strpos( $k, "HTTP_X_" ) === 0 || in_array( $k, [ "HTTP_HOST", "REMOTE_ADDR", "HTTPS", "SERVER_PORT" ], true ) ) {
		echo "$k=$v\n";
	}
}
EOF'
curl -sS https://doujin-profile.no32.tk/doujin-profile-header-probe-XXXX.php -H 'X-Doujin-Profile-Host: ftsf.dp.no32.tk' -H 'X-Doujin-Profile-Client-IP: 192.0.2.1'
scripts/remote.sh 'rm -f doujin-profile-header-probe-XXXX.php'    # 確認したらすぐ消す
```

見ること: `HTTP_X_DOUJIN_PROFILE_HOST` が届くか、`REMOTE_ADDR` が何になるか(さくらの nginx の IP か、送り元の IP か)、`HTTPS` が `on` か。
戻し方: ファイルを消すだけ。

## 2. 段階 B: Caddy の設定(e2-micro)

```sh
sudo cp -p /etc/caddy/Caddyfile /etc/caddy/Caddyfile.bak-$(date +%Y%m%d-%H%M)
```

足すブロック(同居しているサービスのブロックには触れない。既存の `doujin-profile.no32.tk` のブロックは DNS が向いていないので残しても害は無いが、紛らわしいので消すかはその時に決める):

```caddyfile
dp.no32.tk, *.dp.no32.tk {
	tls {
		on_demand
	}
	reverse_proxy https://tsff.sakura.ne.jp {
		header_up Host doujin-profile.no32.tk
		header_up X-Doujin-Profile-Host {host}
		header_up X-Doujin-Profile-Client-IP {remote_host}
		header_up X-Doujin-Profile-Secret {env.DOUJIN_PROFILE_PROXY_SECRET}
		transport http {
			tls_server_name doujin-profile.no32.tk
		}
	}
}
```

- `DOUJIN_PROFILE_PROXY_SECRET` は `/etc/caddy/doujin-profile.env`(root の 600)に置き、systemd の上書き設定 `/etc/systemd/system/caddy.service.d/doujin-profile.conf` の `EnvironmentFile=` で読ませる。
- on-demand TLS は、仮運用では `ask` を付けずに始める(誰でも任意のサブドメインで証明書の発行を起こせてしまうので、Let's Encrypt のレート制限に当たりうる)。Multisite ができたら、存在するサイトだけ許可する `ask` を足す。
- 証明書は **on-demand TLS(サイトごと)** に決めた(ユーザ裁定 2026-10-24。サイト名が CT ログに載ることは構わない)。ワイルドカード証明書の案(CNAME 委任)は design-log に記録してあり、後から乗り換えられる。
- 反映は `caddy validate --config /etc/caddy/Caddyfile` が通ってから `sudo systemctl reload caddy`。

戻し方: 退避した Caddyfile を戻して `sudo systemctl reload caddy`。

## 3. 段階 C: さくらの WordPress をサブドメイン型の Multisite に

1. 退避: `~/dumps/<日時>/` に DB・`wp-config.php`・`.htaccess`(700)。
2. `wp-config.php` の先頭近く(`ABSPATH` の定義より前)に、前段のヘッダを受け取る数行と `DOUJIN_PROFILE_PROXY_SECRET` を足す:

   ```php
   // 前段の Caddy が渡す本当のホスト名と訪問者の IP を使う。秘密が一致したときだけ信用する
   if ( isset( $_SERVER['HTTP_X_DOUJIN_PROFILE_SECRET'], $_SERVER['HTTP_X_DOUJIN_PROFILE_HOST'] )
   	&& hash_equals( DOUJIN_PROFILE_PROXY_SECRET, $_SERVER['HTTP_X_DOUJIN_PROFILE_SECRET'] )
   	&& preg_match( '/^([a-z0-9-]+\.)?dp\.no32\.tk$/', $_SERVER['HTTP_X_DOUJIN_PROFILE_HOST'] ) ) {
   	$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_DOUJIN_PROFILE_HOST'];
   	if ( ! empty( $_SERVER['HTTP_X_DOUJIN_PROFILE_CLIENT_IP'] ) ) {
   		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_DOUJIN_PROFILE_CLIENT_IP'];
   	}
   }
   ```

3. `siteurl` / `home` を `https://dp.no32.tk` にする(`wp search-replace` ではなく `wp option update`。中身は初期データだけ)。
4. `wp core multisite-convert --subdomains`、`.htaccess` の WordPress ブロックをサブドメイン型のものに替える。
5. 試しのサイトを作る → `https://<試しの名前>.dp.no32.tk/` の表示・ログイン・画像のアップロード・ログアウトを確かめる → 試しのサイトを消す。

戻し方: 退避した DB・`wp-config.php`・`.htaccess` を戻す(`wp db import`)。

## 4. 結果

### 段階 A(2026-10-24 12:43 JST、ユーザ了承「A いいよ」)

確認用の PHP(推測されにくい名前)を `~/www/doujin-profile/` に置き、3 通りに送って、すぐ消した(消したあとは 404)。

| 送り方 | `HTTP_X_DP_*` | `REMOTE_ADDR` | ほか |
|---|---|---|---|
| 手元から `https://doujin-profile.no32.tk/` へ直接 | 3 つとも届いた | 手元の IP | `HTTPS=on`、`HTTP_X_REAL_IP` と `HTTP_X_SAKURA_FORWARDED_FOR` も手元の IP |
| 手元から `tsff.sakura.ne.jp` へ、SNI と Host は `doujin-profile.no32.tk`(Caddy と同じつなぎ方) | 届いた | 手元の IP | 同上 |
| **e2-micro から**同じつなぎ方 | **届いた** | **e2-micro の IP**(`35.212.198.233`) | 同上 |

分かったこと:

- **独自のヘッダは PHP まで届く。** 段階 C の `wp-config.php` の受け取り方で成り立つ。
- `REMOTE_ADDR` は、さくらの nginx に直接つないだ相手の IP になる(さくらが置き換えている)。前段を通すと Caddy の IP になるので、訪問者の IP は `X-DP-Client-IP` で受け取る必要がある。
- **送った `X-Forwarded-For` は PHP に届かなかった**(さくらの nginx が消している)。標準的な `X-Forwarded-For` には頼れず、独自のヘッダで渡すのが正しい。
- 秘密に加えて「`REMOTE_ADDR` が Caddy の IP であること」も条件にできる(ただし本番で Caddy の IP が変わると直す場所が増えるので、秘密を主にする)。

### 段階 B(2026-10-24 12:50 JST、ユーザ了承「B を進めて良い」)

e2-micro で行ったこと:

| 変更 | 内容 | 戻し方 |
|---|---|---|
| Caddyfile の退避 | `/etc/caddy/Caddyfile.bak-20261024-0350` | これを `/etc/caddy/Caddyfile` に戻す |
| 合言葉 | `/etc/caddy/dp-proxy.env`(**同日 `doujin-profile.env` に改名**。下の「名前をそろえた」)(root の 600。`DP_PROXY_SECRET=…`)。値は画面にも出していない | ファイルを消す |
| systemd の上書き | `/etc/systemd/system/caddy.service.d/dp-proxy.conf`(`EnvironmentFile=/etc/caddy/dp-proxy.env`) | ファイルを消して `sudo systemctl daemon-reload` |
| Caddyfile | 末尾に `dp.no32.tk, *.dp.no32.tk` のブロック(§2 のとおり)を足した。既存の 2 つのブロックはそのまま | 退避を戻す |
| 反映 | 環境変数を読ませるため reload ではなく `restart`(同居サービスは数秒止まった) | `sudo systemctl restart caddy` |

確かめたこと:

- `caddy validate` は通った(「整形されていない」の警告は以前から)。
- `https://dp.no32.tk/` は 200 で、`~/www/doujin-profile/` の WordPress(タイトル「同人プロフィール」)が返った。証明書は on-demand で Let's Encrypt から取れた(初回アクセスで約 4 秒)。
- 同居サービスの `e2-micro.no32.tk` は変更前と同じ応答(`/` は 404)。
- 予想どおりの警告: 「on-demand TLS is enabled, but no protections are in place」。段階 C のあとで `ask` を足して解消する。
- WordPress はまだ単一サイトで `home` が `http://doujin-profile.no32.tk` なので、ページ内のリンクは `doujin-profile.no32.tk` を指す。段階 C で直る。

### 段階 C(2026-10-24 12:54〜13:00 JST、ユーザ了承「C を進めてよい」)

| 変更 | 内容 |
|---|---|
| 退避 | `~/dumps/pre-multisite-20261024-1254/`(700): `db.sql`(12 テーブル、`Dump completed` あり)・`wp-config.php`・`htaccess`。変換直後の `wp-config.php` も `wp-config.after-stage-c.php` として同じ場所に |
| `wp-config.php` | 「編集が必要なのはここまでです」の直前に、①前段のヘッダを受け取る数行と合言葉の定数、②Multisite の定数(`SUBDOMAIN_INSTALL` true、`DOMAIN_CURRENT_SITE` `dp.no32.tk`)。合言葉は e2-micro からパイプで運び、画面には出していない。区切りが日本語なので WP-CLI の自動追記は使わず(`--skip-config`)、手で足した |
| URL | `siteurl` / `home` を `https://dp.no32.tk` に |
| 変換 | `wp core multisite-convert --subdomains --title="同人プロフィール" --skip-config` |
| `.htaccess` | WordPress のブロックをサブドメイン型の標準のものに替えた(さくらの圧縮・キャッシュのブロックはそのまま) |

確かめたこと:

| 確認 | 結果 |
|---|---|
| `https://dp.no32.tk/` | 200。ページ内のリンクはすべて `https://dp.no32.tk` |
| 試しのサイト `dptest`(blog_id 2)を作って `https://dptest.dp.no32.tk/` | 200、タイトル「試しのサイト」、リンクは `https://dptest.dp.no32.tk`。初回は証明書の取得込みで 5.7 秒 |
| 存在しないサブドメイン | 302 → `https://dp.no32.tk/wp-signup.php?new=<名前>`(コア標準の動き。WordPress が本当のホスト名を受け取れている証拠) |
| `https://doujin-profile.no32.tk/` に直接 | 302 → `wp-signup.php?new=doujin-profile.no32.tk`(ネットワークに無い名前として扱われる) |
| 画像のアップロード(REST API、一時ユーザのアプリケーションパスワード) | **201**。`/wp-content/uploads/sites/2/2026/10/` に保存された。Authorization ヘッダも Caddy・さくらを通った |
| ログイン(`wp-login.php` に POST) | **403(さくらの nginx)**。下記 |

**ログインの 403**: e2-micro(米国)から直接 `wp-login.php`・`wp-admin/` を開いても 403、`readme.html` は 200。国内の手元からは通る。さくらの「国外 IP アドレスフィルタ」(海外からの WordPress の管理系 URL を拒む機能)と見られる。仮運用のあいだはコントロールパネルでこのフィルタを切るか、本番どおり国内の Caddy を通せば解ける。訪問者の IP の受け取り(`REMOTE_ADDR` の差し替え)は、ログインのセッション記録で確かめる予定だったので、これも未確認。

気づいたこと:

- **WP-CLI で作ったサイトの URL が `http://` で登録された**(CLI には https の文脈が無いため)。サイトの `home` / `siteurl` を https に直す必要がある。spec の `wp doujin invite` では、作成直後に https にそろえる。
- 存在しないサブドメインでも on-demand で証明書が発行される(`ask` が無いため)。`ask` を足すのは残作業。
- 存在しない名前を登録ページへ送るのが嫌なら、`NOBLOGREDIRECT` で転送先を決められる(コア標準)。

片づけ: 一時ユーザ `dptmpuser` とアプリケーションパスワードを消し、試しのサイト `dptest` を消した(アップロードした画像もサイトと一緒に消える)。いまのネットワークは `https://dp.no32.tk/`(blog_id 1)だけ、ユーザは `takano32` だけ。

### 名前をそろえた(2026-10-24 13:00 JST、ユーザ指示)

| 前 | 後 |
|---|---|
| `/etc/caddy/dp-proxy.env`(`DP_PROXY_SECRET`) | `/etc/caddy/doujin-profile.env`(`DOUJIN_PROFILE_PROXY_SECRET`) |
| `/etc/systemd/system/caddy.service.d/dp-proxy.conf` | `…/caddy.service.d/doujin-profile.conf` |
| ヘッダ `X-DP-Host` / `X-DP-Client-IP` / `X-DP-Secret` | `X-Doujin-Profile-Host` / `X-Doujin-Profile-Client-IP` / `X-Doujin-Profile-Secret` |
| `wp-config.php` の定数 `DP_PROXY_SECRET` | `DOUJIN_PROFILE_PROXY_SECRET` |

Caddyfile の退避は `/etc/caddy/Caddyfile.bak-20261024-0357`(改名の直前)。改名のあとも、`dp.no32.tk` は 200、同居サービスは変わらず、存在しないサブドメインは登録ページへ(= ヘッダが届いている)。

### 戻し方(段階 B・C をまとめて)

1. さくら: `cd ~/www/doujin-profile && wp db import ~/dumps/pre-multisite-20261024-1254/db.sql`、`cp -p ~/dumps/pre-multisite-20261024-1254/wp-config.php wp-config.php`、`cp -p ~/dumps/pre-multisite-20261024-1254/htaccess .htaccess`
2. e2-micro: `sudo cp -p /etc/caddy/Caddyfile.bak-20261024-0350 /etc/caddy/Caddyfile`、`sudo rm -f /etc/caddy/doujin-profile.env /etc/systemd/system/caddy.service.d/doujin-profile.conf`、`sudo systemctl daemon-reload && sudo systemctl restart caddy`
