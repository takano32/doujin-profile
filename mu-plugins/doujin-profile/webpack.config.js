/**
 * @wordpress/scripts の既定の設定に、マイページのエントリを足す。
 *
 * 既定のエントリ (src/ 以下の block.json から見つかる独自ブロック) はそのまま生かす。
 * 出力は build/<名前>.js と build/<名前>.asset.php (依存とバージョン)。
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const defaultEntry = typeof defaultConfig.entry === 'function' ? defaultConfig.entry() : defaultConfig.entry;

module.exports = {
	...defaultConfig,
	entry: {
		...defaultEntry,
		'my-page': './src/my-page/index.js',
	},
};
