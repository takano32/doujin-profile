#!/bin/sh
# 試験の入口 (T.54)。JS は手元 (node)、PHP はターゲット (既定はさくら) の $HOME/doujin-profile-test/ で動かす。
# さくらは本番と同じ PHP・OS・拡張なので、PHP の試験はそこで行う (ユーザ裁定 2026-11-05「docker 使わずにリモートホストでテスト」)。
#
#   scripts/test.sh                 すべて: js → php (lint + 単体) → integration
#   scripts/test.sh js              JS の単体試験 (jest。手元の node)
#   scripts/test.sh php             さくらに同期して php -l と PHPUnit (単体。WordPress は読まない)
#   scripts/test.sh lint            php -l だけ
#   scripts/test.sh integration     さくらの使い捨ての WordPress (SQLite) に本体を入れて結合試験 (tests/integration/)
#   scripts/test.sh sync            同期だけ
#   DP_TARGET=sakura scripts/test.sh php   ターゲットの指定 (既定は scripts/targets/default)
#
# 約束 (docs/environment.md §10):
#   - ターゲットで触るのは $HOME/doujin-profile-test/ だけ。WordPress の設置先 (WP_PATH) には触れず、本番の DB も読まない
#   - 同期は rsync --delete だが、宛先は $HOME/doujin-profile-test/src/ の中だけ (配備の deploy.sh とは別物)
#   - PHPUnit は phar (tests/tools/phpunit-9.6.phar。無ければ手元で取ってくる。.gitignore 済み) を一緒に送る。composer は使わない
#   - 本番の WordPress には何も読み込ませない (配備済みの本体と二重に読まれるため)
set -eu
. "$(dirname "$0")/lib.sh"
cd "$DP_ROOT"

REMOTE_DIR='doujin-profile-test'   # ターゲットの $HOME からの相対
PHPUNIT_URL='https://phar.phpunit.de/phpunit-9.6.phar'   # PHP 8.0 と 8.3 の両方で動く版
PHPUNIT_PHAR="$DP_ROOT/tests/tools/phpunit-9.6.phar"

usage() {
	echo "使い方: scripts/test.sh [all|js|php|lint|integration|sync]" >&2
	exit 1
}

run_js() {
	echo "== JS の単体試験 (手元): mu-plugins/doujin-profile で npm test"
	( cd mu-plugins/doujin-profile && npm test )
}

sync_remote() {
	if [ ! -f "$PHPUNIT_PHAR" ]; then
		echo "== PHPUnit の phar を取ってくる: $PHPUNIT_URL"
		mkdir -p "$(dirname "$PHPUNIT_PHAR")"
		curl -fsSL -o "$PHPUNIT_PHAR" "$PHPUNIT_URL"
	fi
	echo "== 同期 [$DP_TARGET]: → $SSH_TARGET:~/$REMOTE_DIR/src/"
	dp_remote "mkdir -p \"\$HOME/$REMOTE_DIR/src\""
	# 送るのは PHP・ビルド・テーマ・試験・交換形式の見本。JS のソースとビルドの道具は送らない (サーバに node は無い)
	rsync -rlt --delete -e "$DP_SSH" \
		--exclude='.DS_Store' --exclude='node_modules/' --exclude='*.map' \
		--exclude='/mu-plugins/doujin-profile/src/' --exclude='/mu-plugins/doujin-profile/package.json' \
		--exclude='/mu-plugins/doujin-profile/package-lock.json' --exclude='/mu-plugins/doujin-profile/webpack.config.js' \
		--exclude='/tests/tools/' --exclude='/tests/integration/.wp/' \
		mu-plugins themes tests data \
		"$SSH_TARGET:$REMOTE_DIR/src/"
	rsync -lt -e "$DP_SSH" "$PHPUNIT_PHAR" "$SSH_TARGET:$REMOTE_DIR/phpunit.phar"
}

run_remote() {
	dp_remote "sh \"\$HOME/$REMOTE_DIR/src/tests/remote/$1\" ${2:-}"
}

what=${1:-all}
case "$what" in
	all)
		run_js
		sync_remote
		run_remote php.sh all
		run_remote integration.sh
		;;
	js) run_js ;;
	php) sync_remote; run_remote php.sh all ;;
	lint) sync_remote; run_remote php.sh lint ;;
	integration) sync_remote; run_remote integration.sh ;;
	sync) sync_remote ;;
	*) usage ;;
esac
