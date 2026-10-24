#!/bin/sh
# ターゲットの WordPress を Multisite (サブディレクトリ型) にできるかを調べる。読み取りだけで、何も変えない。
#
#   scripts/check-multisite.sh
#   DP_TARGET=xserver scripts/check-multisite.sh
#
# 結果の読み方は docs/environment.md §S.5 の表と同じ。
set -eu
. "$(dirname "$0")/lib.sh"

echo "== [$DP_TARGET] $SSH_TARGET:~/$WP_PATH"
dp_remote '
echo "-- 環境"
uname -sr; php -r "echo \"PHP \", PHP_VERSION, PHP_EOL;"; wp cli version 2>&1
wp core version 2>&1
echo "-- いまの構成"
if wp core is-installed --network >/dev/null 2>&1; then echo "すでに Multisite"; else echo "単一サイト"; fi
printf "siteurl: "; wp option get siteurl 2>&1
printf "home:    "; wp option get home 2>&1
printf "permalink_structure: "; wp option get permalink_structure 2>&1
echo "-- コアの判定"
wp eval "
require_once ABSPATH . \"wp-admin/includes/network.php\";
printf(\"allow_subdirectory_install: %s\n\", var_export(allow_subdirectory_install(), true));
printf(\"allow_subdomain_install:    %s\n\", var_export(allow_subdomain_install(), true));
\$c = file_exists(ABSPATH . \"wp-config.php\") ? ABSPATH . \"wp-config.php\" : dirname(ABSPATH) . \"/wp-config.php\";
printf(\"wp-config.php を書けるか:   %s (%s)\n\", var_export(is_writable(\$c), true), \$c);
printf(\".htaccess を書けるか:       %s\n\", var_export(is_writable(ABSPATH . \".htaccess\"), true));
" 2>&1
echo "-- DB"
printf "テーブル接頭辞: "; wp db prefix 2>&1
printf "テーブル数: "; wp db tables --all-tables 2>&1 | wc -l
echo "-- ディスク"
q=$(quota 2>/dev/null | tail -1); if [ -n "$q" ]; then echo "$q"; else df -h "$HOME" | tail -1; fi
printf "sendmail: "; command -v sendmail || echo なし
'

echo "-- 外から見た挙動 ($SITE_URL)"
for p in / /sample-page/ /dp-check-nonexistent-xyz/ /wp-json/; do
	printf '%s %s\n' "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$SITE_URL$p")" "$p"
done
printf 'ログイン画面の送信先: '
a=$(curl -sS --max-time 15 "$SITE_URL/wp-login.php" | grep -o 'action="[^"]*"' | head -1) || true
echo "${a:-(取れず。ログイン URL が変えてあるか、転送されている)}"
printf 'サーバ: '
curl -sSI --max-time 15 "$SITE_URL/" | grep -i '^server:' | tr -d '\r' || echo '(なし)'
host="${SITE_URL#https://}"
printf 'ワイルドカード DNS (サブドメイン型に要る): '
if host -t A "dp-check-xyz.$host" >/dev/null 2>&1; then echo あり; else echo なし; fi
