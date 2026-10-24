# scripts/*.sh から読み込む共通部分。接続先は scripts/targets/<名前>.env で決まる。
#
#   DP_TARGET=xserver scripts/wp.sh core version   # ターゲットを指定する
#   scripts/wp.sh core version                     # 省略すると scripts/targets/default の名前
#
# ~/.ssh/config の設定は要らない。鍵は ssh-agent か既定の鍵。
# サーバのログインシェルが何であっても (さくらは csh) 動くように、
# リモートのコマンドは標準入力で sh に渡す。

DP_ROOT=$(cd "$(dirname "$0")/.." && pwd)
DP_TARGET=${DP_TARGET:-$(cat "$DP_ROOT/scripts/targets/default")}
DP_TARGET_FILE="$DP_ROOT/scripts/targets/$DP_TARGET.env"
[ -f "$DP_TARGET_FILE" ] || { echo "ターゲットが無い: $DP_TARGET_FILE" >&2; exit 1; }
. "$DP_TARGET_FILE"

DP_SSH="ssh -p $SSH_PORT -o BatchMode=yes"
# OpenSSH 10.1 以降の post-quantum 鍵交換の警告を黙らせる (古い ssh は知らないオプションなので調べてから)
if ssh -G -o WarnWeakCrypto=no-pq-kex localhost >/dev/null 2>&1; then
	DP_SSH="$DP_SSH -o WarnWeakCrypto=no-pq-kex"
fi

# 引数を sh 用に 1 つずつ単一引用符で囲む
dp_quote() {
	for a in "$@"; do
		printf "'%s' " "$(printf '%s' "$a" | sed "s/'/'\\\\''/g")"
	done
}

# WordPress の設置先で sh のコマンド列を実行する (標準入力で渡す)
dp_remote() {
	{ printf 'cd "$HOME/%s" || exit 1\n' "$WP_PATH"; printf '%s\n' "$1"; } \
		| $DP_SSH "$SSH_TARGET" sh -s
}
