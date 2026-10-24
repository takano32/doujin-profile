# 前段 Caddy + サブドメイン型 Multisite の仮運用 — 手順・結果・戻し方

`https://<名前>.dp.no32.tk/` でユーザごとのサイトを出す構成を、仮運用の環境で確かめる。
**変更の前に手順と戻し方を書き、実施後に結果を足す**(`multisite-trial.md` と同じ書き方)。

| 項目 | 値 |
|---|---|
| 根拠 | ユーザ裁定 2026-10-24:仮運用は米国の e2-micro の Caddy を前段にする。本番では国内に Caddy を立てる(Caddy と WordPress の同居が理想だが難しい見込み)。規約は判断材料にしない・当面は非公開 |
| 前段 | e2-micro(`ssh takano32@35.212.198.233`、Caddy 2.6.2。`environment.md` §C) |
| 裏側 | ターゲット `sakura`(`~/www/doujin-profile/`、WordPress 単一サイト・初期データだけ。`environment.md` §S) |
| DNS | `dp.no32.tk` と `*.dp.no32.tk` は e2-micro を向いている(追加は要らない) |
| 状態 | **未実施**(各段階の前に、サイト所有者の了承を得る) |

秘匿値の扱い: Caddy とさくらのあいだの共有の秘密(下の `DP_PROXY_SECRET`)は、サーバの上にだけ置く。リポジトリ・チャットには書かない。

---

## 0. 通り道

```
訪問者 ──https──> Caddy (e2-micro)                ──https──> さくらの nginx ──> Apache + PHP (WordPress)
  Host: ftsf.dp.no32.tk   on-demand TLS で証明書          Host: doujin-profile.no32.tk(さくらに登録済みの名前)
                          X-DP-Host: ftsf.dp.no32.tk     X-DP-Host / X-DP-Client-IP / X-DP-Secret を付ける
```

- さくらは登録していないホスト名に 403 を返すので(`environment.md` §C)、Caddy からは**登録済みの名前で、https で**つなぐ。
- WordPress には `wp-config.php` の数行で、秘密が一致したときだけ `X-DP-Host` を `HTTP_HOST` に、`X-DP-Client-IP` を `REMOTE_ADDR` に入れる。Multisite はその `HTTP_HOST` でサイトを見分ける(コア標準のサブドメイン型)。
- 仮運用では、経路が日本 → 米国 → 日本になるので遅い。本番では Caddy を国内に置く。

## 1. 段階 A: さくらの nginx がヘッダを通すかの確認(変更は一時ファイル 1 つ)

```sh
# 確認用の PHP を置く(名前は推測されにくいものにする)
scripts/remote.sh 'cat > dp-header-probe-XXXX.php <<"EOF"
<?php
header( "Content-Type: text/plain" );
foreach ( $_SERVER as $k => $v ) {
	if ( strpos( $k, "HTTP_X_" ) === 0 || in_array( $k, [ "HTTP_HOST", "REMOTE_ADDR", "HTTPS", "SERVER_PORT" ], true ) ) {
		echo "$k=$v\n";
	}
}
EOF'
curl -sS https://doujin-profile.no32.tk/dp-header-probe-XXXX.php -H 'X-DP-Host: ftsf.dp.no32.tk' -H 'X-DP-Client-IP: 192.0.2.1'
scripts/remote.sh 'rm -f dp-header-probe-XXXX.php'    # 確認したらすぐ消す
```

見ること: `HTTP_X_DP_HOST` が届くか、`REMOTE_ADDR` が何になるか(さくらの nginx の IP か、送り元の IP か)、`HTTPS` が `on` か。
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
		header_up X-DP-Host {host}
		header_up X-DP-Client-IP {remote_host}
		header_up X-DP-Secret {env.DP_PROXY_SECRET}
		transport http {
			tls_server_name doujin-profile.no32.tk
		}
	}
}
```

- `DP_PROXY_SECRET` は systemd の上書き設定(`sudo systemctl edit caddy` で `Environment=DP_PROXY_SECRET=…`)に置く。
- on-demand TLS は、仮運用では `ask` を付けずに始める(誰でも任意のサブドメインで証明書の発行を起こせてしまうので、Let's Encrypt のレート制限に当たりうる)。Multisite ができたら、存在するサイトだけ許可する `ask` を足す。
- 反映は `caddy validate --config /etc/caddy/Caddyfile` が通ってから `sudo systemctl reload caddy`。

戻し方: 退避した Caddyfile を戻して `sudo systemctl reload caddy`。

## 3. 段階 C: さくらの WordPress をサブドメイン型の Multisite に

1. 退避: `~/dumps/<日時>/` に DB・`wp-config.php`・`.htaccess`(700)。
2. `wp-config.php` の先頭近く(`ABSPATH` の定義より前)に、前段のヘッダを受け取る数行と `DP_PROXY_SECRET` を足す:

   ```php
   // 前段の Caddy が渡す本当のホスト名と訪問者の IP を使う。秘密が一致したときだけ信用する
   if ( isset( $_SERVER['HTTP_X_DP_SECRET'], $_SERVER['HTTP_X_DP_HOST'] )
   	&& hash_equals( DP_PROXY_SECRET, $_SERVER['HTTP_X_DP_SECRET'] )
   	&& preg_match( '/^([a-z0-9-]+\.)?dp\.no32\.tk$/', $_SERVER['HTTP_X_DP_HOST'] ) ) {
   	$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_DP_HOST'];
   	if ( ! empty( $_SERVER['HTTP_X_DP_CLIENT_IP'] ) ) {
   		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_DP_CLIENT_IP'];
   	}
   }
   ```

3. `siteurl` / `home` を `https://dp.no32.tk` にする(`wp search-replace` ではなく `wp option update`。中身は初期データだけ)。
4. `wp core multisite-convert --subdomains`、`.htaccess` の WordPress ブロックをサブドメイン型のものに替える。
5. 試しのサイトを作る → `https://<試しの名前>.dp.no32.tk/` の表示・ログイン・画像のアップロード・ログアウトを確かめる → 試しのサイトを消す。

戻し方: 退避した DB・`wp-config.php`・`.htaccess` を戻す(`wp db import`)。

## 4. 結果

(実施後に書く)
