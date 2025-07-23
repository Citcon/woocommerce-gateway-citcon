const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
        'wc-payment-method-citcon-blocks': './src/index.js',
	},
	resolve: {
		extensions: [ '.js', '.jsx', '.tsx', '.ts' ],
		fallback: {
			stream: false,
			path: false,
			fs: false,
		},
	},
	output: {
		path: path.resolve( __dirname, 'build/' ),
		filename: '[name].js',
	},
    externals: {
        '@woocommerce/base-context': 'wc.baseContext',
        '@woocommerce/blocks-registry': 'wc.blocksRegistry',
        '@woocommerce/settings': 'wc.settings',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/element': 'wp.element'
    },
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};