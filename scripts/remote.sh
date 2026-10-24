#!/bin/sh
# ターゲットの WordPress の設置先で、sh のコマンド列を実行する。
#
#   scripts/remote.sh 'ls -la wp-content; wp plugin list 2>&1'
set -eu
. "$(dirname "$0")/lib.sh"
dp_remote "$1"
