#!/bin/sh
# 検収の検査 (spec §12。T.32.11)。手元から、curl と scripts/wp.sh で読み取りだけ。何も変えない。
#
#   scripts/check-site.sh https://ftsf.dp.no32.tk/
#   DP_TARGET=xserver scripts/check-site.sh https://…/
#
# 見るもの: トップと作品ページの 200 (準備中なら「準備中です」+ noindex)、http → https、description・OGP・canonical、
# 年齢確認 (成人向けの印のあるサイトだけ)、作品カードの数、リンクボタン、「さらに作品を見る」、
# 堅牢化 (REST の users がこのサイトの名前以外を出さない、XML-RPC が 405、コメント欄と X-Pingback が無い、noindex が無い)、
# 404、/wp-admin/ → ログイン画面、robots.txt、サイトマップ。結果は [OK] / [NG] / [--] (判定なし) で並べ、NG があれば 1 で終わる。
# 人手でしか見られないもの (他人のサイトの管理画面に入れないこと、見た目、メール) は spec §12 の一覧で確かめる。
set -eu
. "$(dirname "$0")/lib.sh"

URL=${1:?使い方: scripts/check-site.sh https://<名前>.dp.no32.tk/}
URL=${URL%/}
HOST=${URL#https://}; HOST=${HOST#http://}
NAME=${HOST%%.*}
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
NG=0
ok()    { echo "[OK] $1"; }
ng()    { echo "[NG] $1"; NG=$((NG + 1)); }
info()  { echo "[--] $1"; }
check() { if [ "$1" -eq 0 ]; then ok "$2"; else ng "$2"; fi; } # check <0=合格> <文>
has()   { grep -q -- "$2" "$TMP/$1.html"; }                        # has <名前> <文字列>
hdr()   { grep -i "^$2:" "$TMP/$1.h" | tr -d '\r' | sed 's/^[^:]*: *//' | head -1; }
fetch() { # fetch <名前> <URL> [curl の追加引数…] → 状態コードを出す。キャッシュバスター付き
	name=$1; url=$2; shift 2
	case "$url" in *\?*) url="$url&dp-check=$(date +%s)" ;; *) url="$url?dp-check=$(date +%s)" ;; esac
	curl -sS -o "$TMP/$name.html" -D "$TMP/$name.h" -w '%{http_code}' --max-time 30 -H 'Cache-Control: no-cache' "$@" "$url" || echo 000
}

echo "== $URL (名前 $NAME)"

# サイトの事実を WP-CLI で 1 回だけ読む (成人向け・準備中・検索エンジン・作品数・リンク集・最初の作品の URL)
scripts_wp="$(dirname "$0")/wp.sh"
"$scripts_wp" eval '
$s = get_site();
$l = doujin_profile_sanitize_profile_links( get_option( "doujin_profile_links", array() ) );
$p = get_posts( array( "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true ) );
printf( "mature=%d\npreparing=%d\npublic=%d\nworks=%d\nshops=%d\naccounts=%d\nfirst=%s\n",
	(int) $s->mature, (int) get_option( "doujin_profile_preparing" ), (int) get_option( "blog_public" ),
	(int) wp_count_posts( "post" )->publish, count( $l["shops"] ), count( $l["accounts"] ), $p ? get_permalink( $p[0] ) : "" );
' --url="$URL" > "$TMP/wp.txt" 2>"$TMP/wp.err" || { echo "WP-CLI が失敗: $(cat "$TMP/wp.err")" >&2; exit 2; }
val() { sed -n "s/^$1=//p" "$TMP/wp.txt"; }
MATURE=$(val mature); PREPARING=$(val preparing); PUBLIC=$(val public); WORKS=$(val works)
SHOPS=$(val shops); ACCOUNTS=$(val accounts); FIRST=$(val first)
info "成人向け $MATURE / 準備中 $PREPARING / 検索エンジン blog_public=$PUBLIC / 公開の作品 $WORKS 件 / リンク集 販売 $SHOPS・お知らせ $ACCOUNTS"

# トップページ
code=$(fetch top "$URL/")
if [ "$PREPARING" = 1 ]; then
	check "$([ "$code" = 200 ] && has top '準備中です'; echo $?)" "トップ: 準備中なので「準備中です」だけ (200。いまは $code)"
	check "$(has top 'name="robots" content="noindex'; echo $?)" "トップ: 準備中なので noindex"
	check "$(! has top 'doujin-profile-work-card'; echo $?)" "トップ: 準備中なので作品が出ていない"
else
	check "$([ "$code" = 200 ]; echo $?)" "トップが 200 (いまは $code)"
	check "$(! has top 'name=.robots. content=.noindex'; echo $?)" "トップに noindex が無い (所有者の裁定「広報なんだから載せろ」)"
	check "$(has top '<meta name="description"'; echo $?)" "トップに description がある"
	check "$(has top 'property="og:title"' && has top 'property="og:url"' && has top 'property="og:type" content="website"'; echo $?)" "トップに OGP (og:title・og:url・og:type=website) がある"
	check "$(has top 'rel="canonical"'; echo $?)" "トップに canonical がある"
	if has top 'property="og:image"'; then ok "トップに og:image がある"; else info "トップに og:image が無い (マイページでアイコンを保存すると出る)"; fi
	cards=$(grep -o '<article[^>]*doujin-profile-work-card' "$TMP/top.html" | wc -l | tr -d ' ')
	expect=$WORKS; [ "$expect" -gt 6 ] && expect=6
	check "$([ "$cards" -eq "$expect" ]; echo $?)" "トップの作品カード $cards 枚 (期待 $expect = min(公開 $WORKS, 6)。ひな形を変えていれば数が違ってよい)"
	if [ "$WORKS" -gt 6 ]; then check "$(has top 'doujin-profile-more-works'; echo $?)" "「さらに作品を見る」がある (作品 $WORKS 件 > 6)"; else check "$(! has top 'data-doujin-more-works'; echo $?)" "「さらに作品を見る」のボタンが無い (作品 $WORKS 件 ≤ 6)"; fi
	for list in shops:販売ページ:$SHOPS accounts:お知らせ:$ACCOUNTS; do
		key=${list%%:*}; rest=${list#*:}; label=${rest%%:*}; n=${rest#*:}
		if [ "$n" -gt 0 ]; then check "$(has top "doujin-profile-links--$key"; echo $?)" "リンクボタン ($label $n 件) がある"; else info "リンク集の $label は空 (ボタンは出ない)"; fi
	done
fi
if [ "$MATURE" = 1 ]; then
	check "$(has top 'id="doujin-profile-age-gate"' || [ "$PREPARING" = 1 ]; echo $?)" "年齢確認が出る (成人向けの印オン)"
	check "$(has top 'name="rating" content="adult"' || [ "$PREPARING" = 1 ]; echo $?)" "rating=adult がある (成人向けの印オン)"
else
	check "$(! has top 'doujin-profile-age-gate'; echo $?)" "年齢確認が出ない (成人向けの印オフ)"
fi
check "$([ -z "$(hdr top x-pingback)" ]; echo $?)" "X-Pingback ヘッダが無い (Disable Comments)"
check "$(! has top 'comment-respond' && ! has top 'wp-block-post-comments-form'; echo $?)" "トップにコメント欄が無い"
info "Server: $(hdr top server) / Content-Type: $(hdr top content-type)"

# http → https
code=$(curl -sS -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 30 "http://$HOST/" || echo 000)
check "$(case "$code" in 30[1278]\ https://"$HOST"/*) true ;; *) false ;; esac; echo $?)" "http → https に転送 ($code)"

# 作品ページ
if [ -n "$FIRST" ] && [ "$PREPARING" != 1 ]; then
	code=$(fetch work "$FIRST")
	check "$([ "$code" = 200 ]; echo $?)" "作品ページ $FIRST が 200 (いまは $code)"
	check "$(has work 'property="og:type" content="article"' && has work '<meta name="description"'; echo $?)" "作品ページに description と og:type=article がある"
	check "$(has work 'doujin-profile-work-card'; echo $?)" "作品ページに作品カードがある"
	if has work 'doujin-profile-viewer'; then ok "作品ページにサンプルビューアがある"; else info "作品ページにサンプルビューアが無い (サンプル画像が無い作品)"; fi
	check "$(! has work 'comment-respond' && ! has work 'wp-block-post-comments-form'; echo $?)" "作品ページにコメント欄が無い"
elif [ -n "$FIRST" ]; then
	code=$(fetch work "$FIRST")
	check "$([ "$code" = 200 ] && has work '準備中です'; echo $?)" "作品ページも準備中の表示 ($code)"
else
	info "公開の作品が無いので作品ページは見ない"
fi

# 堅牢化
code=$(fetch users "$URL/wp-json/wp/v2/users")
slugs=$(grep -o '"slug":"[^"]*"' "$TMP/users.html" | sed 's/"slug":"//; s/"$//' | sort -u | tr '\n' ' ')
others=$(printf '%s' "$slugs" | tr ' ' '\n' | grep -v "^$NAME\$" | grep -v '^$' | tr '\n' ' ' || true)
check "$([ -z "$others" ]; echo $?)" "REST の users がこのサイトの名前 ($NAME) 以外を出さない ($code。出ている: ${slugs:-なし})"
code=$(fetch media "$URL/wp-json/wp/v2/media?per_page=1")
info "REST の media (未認証) は $code (spec §10.3 の一覧の停止は未実装)"
code=$(fetch xmlrpc "$URL/xmlrpc.php" -X POST -H 'Content-Type: text/xml' --data '<?xml version="1.0"?><methodCall><methodName>wp.getUsersBlogs</methodName><params><param><value>x</value></param><param><value>x</value></param></params></methodCall>')
check "$(has xmlrpc '<int>405</int>'; echo $?)" "XML-RPC の認証の要るメソッドが 405 で断られる (Disable XML-RPC。HTTP $code)"
code=$(fetch comments "$URL/wp-json/wp/v2/comments")
check "$([ "$(tr -d '[:space:]' < "$TMP/comments.html")" = '[]' ] || [ "$code" != 200 ]; echo $?)" "REST の comments が空か断られる ($code)"
code=$(fetch author "$URL/author/$NAME/")
info "著者アーカイブ /author/$NAME/ は $code (spec §10.3 ではトップへ転送の予定・未実装)"

# ふだんの URL
code=$(fetch notfound "$URL/dp-check-nonexistent-xyz/")
check "$([ "$code" = 404 ]; echo $?)" "無いページが 404 (いまは $code)"
code=$(curl -sS -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 30 "$URL/wp-admin/" || echo 000)
check "$(case "$code" in 30*wp-login.php*) true ;; *) false ;; esac; echo $?)" "/wp-admin/ はログイン画面へ ($code)"
code=$(fetch login "$URL/wp-login.php")
check "$([ "$code" = 200 ] && has login 'name="log"'; echo $?)" "ログイン画面が出る ($code)"
code=$(fetch robots "$URL/robots.txt")
check "$([ "$code" = 200 ] && { [ "$PUBLIC" != 1 ] || ! grep -qx 'Disallow: /' "$TMP/robots.html"; }; echo $?)" "robots.txt が 200 で、全体を拒んでいない ($code)"
code=$(fetch sitemap "$URL/wp-sitemap.xml")
info "サイトマップ /wp-sitemap.xml は $code"

echo "== 結果: NG $NG 件"
[ "$NG" -eq 0 ]
