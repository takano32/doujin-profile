#!/bin/sh
# mu-plugins/ を本番 (さくらのレンタルサーバ doujin-profile.no32.tk) へ配備する。
# ~/.ssh/config の設定は要らない (接続先を直接書いている。鍵は ssh-agent か既定の鍵)。
#
#   scripts/deploy.sh        dry-run。転送されるファイルの一覧だけを表示する
#   scripts/deploy.sh --go   実際に転送する
#
# 約束 (docs/environment.md §10):
#   - --delete は使わない。宛先の 2 つ上に wp-config.php がある
#   - 宛先は www/doujin-profile/ の中だけ。同じアカウントの www/tsket/ ほかには触れない
#   - 必ず dry-run の一覧を目で見てから --go する
#   - サーバ上で直接編集しない。リポジトリの mu-plugins/ が正本
set -eu

cd "$(dirname "$0")/.."

DEST='tsff@doujin-profile.no32.tk:www/doujin-profile/wp-content/mu-plugins/'

if [ "${1:-}" = "--go" ]; then
	DRY=''
	echo "== 配備します: mu-plugins/ -> $DEST"
else
	DRY='--dry-run'
	echo "== dry-run (実際に送るには --go): mu-plugins/ -> $DEST"
fi

# -a ではなく -rlt: 所有者・グループ・パーミッションはサーバ側の既定に任せる
rsync -rltv $DRY \
	--exclude='.DS_Store' --exclude='node_modules/' --exclude='*.map' \
	mu-plugins/ "$DEST"
