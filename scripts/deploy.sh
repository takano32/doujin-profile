#!/bin/sh
# mu-plugins/ をターゲットの WordPress へ配備する。接続先は scripts/targets/<名前>.env。
#
#   scripts/deploy.sh                      dry-run。転送されるファイルの一覧だけを表示する
#   scripts/deploy.sh --go                 実際に転送する
#   DP_TARGET=xserver scripts/deploy.sh    ターゲットを指定する (既定は scripts/targets/default)
#
# 約束 (docs/environment.md §10):
#   - --delete は使わない。宛先の上に wp-config.php がある
#   - 宛先は WordPress の wp-content/mu-plugins/ だけ。同じアカウントの別のサイトには触れない
#   - 必ず dry-run の一覧を目で見てから --go する
#   - サーバ上で直接編集しない。リポジトリの mu-plugins/ が正本
set -eu
. "$(dirname "$0")/lib.sh"
cd "$DP_ROOT"

DEST="$SSH_TARGET:$WP_PATH/wp-content/mu-plugins/"

if [ "${1:-}" = "--go" ]; then
	DRY=''
	echo "== 配備します [$DP_TARGET]: mu-plugins/ -> $DEST"
else
	DRY='--dry-run'
	echo "== dry-run [$DP_TARGET] (実際に送るには --go): mu-plugins/ -> $DEST"
fi

# -a ではなく -rlt: 所有者・グループ・パーミッションはサーバ側の既定に任せる
# 送るのは PHP と build/ だけ。JS のソースとビルドの道具 (src/・package*.json・webpack.config.js・node_modules/) は送らない (spec §12)
rsync -rltv $DRY -e "$DP_SSH" \
	--exclude='.DS_Store' --exclude='node_modules/' --exclude='*.map' \
	--exclude='src/' --exclude='package.json' --exclude='package-lock.json' --exclude='webpack.config.js' \
	mu-plugins/ "$DEST"
