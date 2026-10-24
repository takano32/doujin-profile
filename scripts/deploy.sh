#!/bin/sh
# mu-plugins/ (または themes/) をターゲットの WordPress へ配備する。接続先は scripts/targets/<名前>.env。
#
#   scripts/deploy.sh                      dry-run。転送されるファイルの一覧だけを表示する
#   scripts/deploy.sh --go                 実際に転送する
#   scripts/deploy.sh --themes [--go]      mu-plugins/ の代わりに themes/ を wp-content/themes/ へ
#   DP_TARGET=xserver scripts/deploy.sh    ターゲットを指定する (既定は scripts/targets/default)
#
# 約束 (docs/environment.md §10):
#   - --delete は使わない。宛先の上に wp-config.php がある (themes/ の隣には同梱テーマがある)
#   - 宛先は WordPress の wp-content/mu-plugins/ か wp-content/themes/ だけ。同じアカウントの別のサイトには触れない
#   - 必ず dry-run の一覧を目で見てから --go する
#   - サーバ上で直接編集しない。リポジトリの mu-plugins/・themes/ が正本
#   - テーマを初めて置いたあとは、ネットワークで有効化が要る: scripts/wp.sh theme enable doujin-profile-tt5 --network
set -eu
. "$(dirname "$0")/lib.sh"
cd "$DP_ROOT"

SRC='mu-plugins'
DRY='--dry-run'
for arg in "$@"; do
	case "$arg" in
		--go) DRY='' ;;
		--themes) SRC='themes' ;;
		*) echo "使い方: scripts/deploy.sh [--themes] [--go]" >&2; exit 1 ;;
	esac
done

DEST="$SSH_TARGET:$WP_PATH/wp-content/$SRC/"

if [ -z "$DRY" ]; then
	echo "== 配備します [$DP_TARGET]: $SRC/ -> $DEST"
else
	echo "== dry-run [$DP_TARGET] (実際に送るには --go): $SRC/ -> $DEST"
fi

# -a ではなく -rlt: 所有者・グループ・パーミッションはサーバ側の既定に任せる
# 送るのは PHP と build/ だけ。JS のソースとビルドの道具 (src/・package*.json・webpack.config.js・node_modules/) は送らない (spec §12)
rsync -rltv $DRY -e "$DP_SSH" \
	--exclude='.DS_Store' --exclude='node_modules/' --exclude='*.map' \
	--exclude='src/' --exclude='package.json' --exclude='package-lock.json' --exclude='webpack.config.js' \
	"$SRC/" "$DEST"
