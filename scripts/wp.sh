#!/bin/sh
# ターゲットの WordPress で WP-CLI を実行する。引数はそのまま wp に渡る。
#
#   scripts/wp.sh core version
#   scripts/wp.sh option get siteurl
#   DP_TARGET=xserver scripts/wp.sh site list --fields=blog_id,url
set -eu
. "$(dirname "$0")/lib.sh"
dp_remote "wp $(dp_quote "$@")"
