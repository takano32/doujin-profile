#!/bin/sh
# さくらの $HOME/doujin-profile-test/ で動く結合試験: 使い捨ての WordPress (本番と同じ版。DB は SQLite) に本体を入れて、
# フック・REST・WP-CLI・ブロックの描画を本物の WordPress で確かめる (PHPUnit。tests/integration/)。
# 手元からは scripts/test.sh integration (同期して、本番の WordPress の版を渡して、これを呼ぶ)。
#
#   sh tests/remote/integration.sh [<WordPress の版>]   既定は 7.1.4
#
# 約束:
#   - 触るのは $HOME/doujin-profile-test/ の中だけ。本番の WordPress・DB・wp-config.php には触れない
#   - 毎回 wp/ を作り直す (前の試験の残りを引きずらない)。取ってくるもの (本体・SQLite の drop-in・親テーマ) は
#     .wp-cli-cache/ と cache/ に残るので 2 回目から速い
#   - ネットワークに出るのは wordpress.org だけ (FANZA・DLsite は試験の中で pre_http_request で代える)。メールは送らない
#   - Web からは見えない (CLI だけ)。サイトの選択は --url / HTTP_HOST で行う (dp.test / ftsf.dp.test)
set -eu
ROOT="$HOME/doujin-profile-test"
WP="$ROOT/wp"
FIX="$ROOT/fixtures"
VERSION=${1:-7.1.4}
export WP_CLI_CACHE_DIR="$ROOT/.wp-cli-cache"      # $HOME/.wp-cli/ に書かせない
export WP_CLI_PACKAGES_DIR="$ROOT/.wp-cli-packages"
export PAGER=cat
# さくらの CLI の php.ini は memory_limit 128M (wp core download の展開に足りない) で display_errors も切ってある (致命的な誤りが黙って消える)。
# /usr/local/bin/wp は php に引数を渡さない薄い起動スクリプトなので、ここでは phar を直接 php で動かす。PHPUnit も同じ引数で動かす
PHP_ARGS="-d memory_limit=512M -d display_errors=stderr"
WP_PHAR=${WP_PHAR:-/usr/local/bin/wp-cli.phar}
if [ ! -f "$WP_PHAR" ]; then
	echo "wp-cli.phar が $WP_PHAR に無い (WP_PHAR=<道筋> で指定してください)" >&2
	exit 1
fi
wp() { php $PHP_ARGS "$WP_PHAR" "$@"; }
export DOUJIN_PROFILE_WP_CMD="php $PHP_ARGS $WP_PHAR"   # PHPUnit の中から wp を呼ぶときも同じ (tests/integration/IntegrationTestCase.php)
mkdir -p "$ROOT/cache" "$WP_CLI_CACHE_DIR"
cd "$ROOT"

NG=0
say()   { echo "== $*"; }
check() { if [ "$1" -eq 0 ]; then echo "[OK] $2"; else echo "[NG] $2"; NG=$((NG + 1)); fi; } # check <0=合格> <文>
site()  { wp "$@" --url=http://ftsf.dp.test --path="$WP"; }

say "WP-CLI $(wp cli version --skip-wordpress | sed 's/^WP-CLI //') / PHP $(php -r 'echo PHP_VERSION;') (memory_limit $(wp eval 'echo ini_get( "memory_limit" );' --skip-wordpress))"

# 1. WordPress 本体 (本番と同じ版)
say "WordPress $VERSION を wp/ に用意する (SQLite)"
rm -rf "$WP"
mkdir -p "$WP"
wp core download --path="$WP" --version="$VERSION" --quiet

# 2. SQLite の drop-in (wordpress.org の配布物 SQLite Database Integration)。有効化の処理 (activate.php) と同じ置き換えで db.php を作る
SQLITE_ZIP="$ROOT/cache/sqlite-database-integration.zip"
[ -f "$SQLITE_ZIP" ] || curl -fsSL -o "$SQLITE_ZIP" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
unzip -q -o "$SQLITE_ZIP" -d "$WP/wp-content/plugins/"
PLUGIN_DIR="$WP/wp-content/plugins/sqlite-database-integration"
sed -e "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|$PLUGIN_DIR|" -e "s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|" "$PLUGIN_DIR/db.copy" > "$WP/wp-content/db.php"
if grep -q '{SQLITE' "$WP/wp-content/db.php"; then echo "db.copy の形が変わっている (置き換えの印が残った)" >&2; exit 1; fi
mkdir -p "$WP/wp-content/database"
say "SQLite Database Integration $(sed -n 's/^ \* Version: *//p' "$PLUGIN_DIR/load.php")"

# 3. wp-config.php (DB の値はダミー。SQLite は wp-content/database/.ht.sqlite)。本体の警告は debug.log に集める
rm -f "$ROOT/debug.log"
wp config create --path="$WP" --dbname=doujin_profile_test --dbuser=unused --dbpass=unused --skip-check --quiet --extra-php <<EOF
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', '$ROOT/debug.log' );
EOF

# 4. サブドメイン型の Multisite として設置 (メールは送らない)。ワイルドカード DNS の警告は出て構わない
ADMIN_PASSWORD=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
wp core multisite-install --path="$WP" --url=http://dp.test --subdomains --title="doujin-profile の結合試験" \
	--admin_user=admin --admin_password="$ADMIN_PASSWORD" --admin_email=admin@dp.test --skip-email --quiet
check "$(wp core is-installed --network --path="$WP"; echo $?)" "サブドメイン型の Multisite として設置できた (WordPress $(wp core version --path="$WP"))"

# 5. 本体 (mu-plugins/)・子テーマ・試験用の mu-plugin (メールを送らない) を置く
mkdir -p "$WP/wp-content/mu-plugins"
cp -R "$ROOT/src/mu-plugins/." "$WP/wp-content/mu-plugins/"
cp -R "$ROOT/src/themes/doujin-profile-tt5" "$WP/wp-content/themes/"
cat > "$WP/wp-content/mu-plugins/doujin-profile-test-env.php" <<'EOF'
<?php
/** 結合試験の環境 (tests/remote/integration.sh が置く): メールは送らない (さくらから外に出さない)。 */
add_filter( 'pre_wp_mail', '__return_true' );
EOF
if ! wp theme is-installed twentytwentyfive --path="$WP"; then
	say "親テーマ twentytwentyfive を wordpress.org から入れる"
	wp theme install twentytwentyfive --path="$WP" --quiet
fi
wp theme enable doujin-profile-tt5 --network --path="$WP" --quiet
check "$(wp eval 'exit( defined( "DOUJIN_PROFILE_VERSION" ) && function_exists( "doujin_profile_sanitize_links" ) ? 0 : 1 );' --path="$WP"; echo $?)" "本体が読み込まれる (mu-plugins)"

# 6. プロフィールのサイト ftsf.dp.test (wp doujin invite。新しいユーザ ftsf を作り、init-site と「準備中」が走る。メールは pre_wp_mail で止まる) と、
#    ユーザ owner (ftsf の administrator。特権管理者ではない) と reader (購読者)。
#    注意: invite の [--user=<login>] は WP-CLI の全体の引数 --user (動作するユーザ) に取られて子コマンドに届かない (本体の既知の問題。T.54 で判明)
wp doujin invite ftsf ftsf@dp.test --title="試験サークル" --mature --path="$WP" | tee "$ROOT/invite.log"
check "$(grep -q 'ユーザ ftsf (ID' "$ROOT/invite.log" && grep -q 'を準備中で作りました。持ち主 ftsf' "$ROOT/invite.log"; echo $?)" "invite がユーザとサイトを作った"
check "$([ "$(site option get doujin_profile_preparing)" = 1 ]; echo $?)" "invite で作ったサイトは準備中で始まる"
check "$([ "$(site option get home)" = https://ftsf.dp.test ]; echo $?)" "invite で作ったサイトの home は https ($(site option get home))"
check "$([ "$(site post list --post_type=post --name=hello-world --post_status=any --format=count)" = 0 ]; echo $?)" "初期の Hello world は消えている"
check "$([ "$(site option get show_on_front)" = page ] && [ "$(site option get page_on_front)" -gt 0 ]; echo $?)" "固定ページ「ホーム」がトップ (ID $(site option get page_on_front))"
check "$([ "$(site option get timezone_string)" = Asia/Tokyo ]; echo $?)" "タイムゾーンは Asia/Tokyo"
wp user create owner owner@dp.test --role=administrator --url=http://ftsf.dp.test --path="$WP" --porcelain >/dev/null
wp user create reader reader@dp.test --role=subscriber --url=http://ftsf.dp.test --path="$WP" --porcelain >/dev/null

# 7. 見本 (交換形式の JSON と、その場で作る小さな画像) を入れる
rm -rf "$FIX"
mkdir -p "$FIX/images"
cp "$ROOT/src/tests/integration/fixtures/works.json" "$FIX/"
php "$ROOT/src/tests/integration/fixtures/make-images.php" "$FIX/images"
site doujin import "$FIX/works.json"
check "$([ "$(site post list --post_type=post --post_status=any --format=count)" = 3 ]; echo $?)" "見本の作品 3 件が入った"
check "$([ "$(site option get stylesheet)" = doujin-profile-tt5 ]; echo $?)" "テーマは子テーマ doujin-profile-tt5"
wp doujin publish ftsf --path="$WP"
check "$([ "$(site option get doujin_profile_preparing)" = "" ] || [ "$(site option get doujin_profile_preparing)" = 0 ]; echo $?)" "wp doujin publish で公開中になった"

# 8. PHPUnit (本物の WordPress を読み込んで、フック・REST・WP-CLI・ブロックの描画を確かめる)
say "PHPUnit (結合): src/tests/integration"
status=0
DOUJIN_PROFILE_WP="$WP" DOUJIN_PROFILE_FIXTURES="$FIX" DOUJIN_PROFILE_DEBUG_LOG="$ROOT/debug.log" \
	php $PHP_ARGS phpunit.phar -c src/tests/integration/phpunit.xml.dist || status=1

# 9. 本体の PHP の警告 (WP_DEBUG) が無いか。本体以外 (コア・SQLite の drop-in) の分は見るだけ
if [ -s "$ROOT/debug.log" ]; then
	if grep -q 'mu-plugins/doujin-profile' "$ROOT/debug.log"; then
		check 1 "debug.log に本体 (mu-plugins/doujin-profile) の警告がある:"
		grep 'mu-plugins/doujin-profile' "$ROOT/debug.log" | head -20
	else
		check 0 "debug.log に本体の警告は無い (本体以外: $(wc -l < "$ROOT/debug.log") 行。最初の 5 行)"
		head -5 "$ROOT/debug.log"
	fi
else
	check 0 "debug.log は空"
fi

say "結合試験の結果: 環境の検査 NG $NG 件、PHPUnit $([ "$status" -eq 0 ] && echo 'OK' || echo 'NG')"
[ "$NG" -eq 0 ] && [ "$status" -eq 0 ]
