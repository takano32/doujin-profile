#!/bin/sh
# さくらの $HOME/doujin-profile-test/ で動く: PHP の構文検査 (php -l) と PHPUnit の単体試験 (WordPress は読まない)。
# 手元からは scripts/test.sh php (同期してからこれを呼ぶ)。さくらのログインシェルは csh だが、sh で動かす。
#
#   sh tests/remote/php.sh         lint + 単体
#   sh tests/remote/php.sh lint    lint だけ
#   sh tests/remote/php.sh unit    単体だけ
#
# 触るのは $HOME/doujin-profile-test/ の中だけ (WordPress の設置先には触れない)。
set -eu
cd "$HOME/doujin-profile-test"
what=${1:-all}
status=0

echo "== PHP: $(php -v | head -1)"

if [ "$what" = all ] || [ "$what" = lint ]; then
	echo "== lint (php -l): src/mu-plugins src/themes src/tests"
	lint_out=$(mktemp -t doujin-profile-lint)
	find src/mu-plugins src/themes src/tests -name '*.php' -print0 | xargs -0 -n1 php -l > "$lint_out" 2>&1 || status=1
	files=$(grep -c '^No syntax errors' "$lint_out" || true)
	if grep -v '^No syntax errors' "$lint_out" | grep -v '^$'; then status=1; fi
	echo "== lint: $files ファイルを検査、$([ "$status" -eq 0 ] && echo '誤りなし' || echo '誤りあり')"
	rm -f "$lint_out"
fi

if [ "$what" = all ] || [ "$what" = unit ]; then
	echo "== PHPUnit (単体): src/tests/php"
	php phpunit.phar --version
	php phpunit.phar -c src/tests/php/phpunit.xml.dist || status=1
fi

exit $status
