const webpack = require('webpack');
const { VueLoaderPlugin } = require('vue-loader');
const TerserPlugin = require('terser-webpack-plugin');
const path = require('path');

module.exports = (env, options) => {

    const isProduction = options.mode === 'production';

    const config = {
        entry: './main.js',
        output: {
            path: path.resolve(__dirname, '../amd/src'),
            publicPath: '/dist/',
            filename: 'app-lazy.js',
            chunkFilename: "[id].app-lazy.min.js?v=[hash]",
            libraryTarget: 'amd',
        },
        module: {
            rules: [
                {
                    test: /\.css$/,
                    use: ['vue-style-loader', 'css-loader'],
                },
                {
                    test: /\.vue$/,
                    loader: 'vue-loader',
                },
                {
                    // Apply ts-loader only to Vue files with <script lang="ts">
                    test: /\.ts$/,
                    loader: 'ts-loader',
                    exclude: /node_modules/,
                    options: {
                        appendTsSuffixTo: [/\.vue$/],
                        transpileOnly: true,
                    },
                },
                {
                    // Apply babel-loader to JavaScript files and Vue files with <script lang="js">
                    test: /\.js$/,
                    loader: 'babel-loader',
                    exclude: /node_modules/,
                },
                {
                    test: /\.(png|jpe?g|gif|svg)$/i,
                    use: [
                        {
                            loader: 'file-loader',
                            options: {
                                name: '[name].[ext]',
                                outputPath: 'images/',
                            },
                        },
                    ],
                }
            ]
        },
        resolve: {
            alias: {
                'vue$': 'vue/dist/vue.esm-bundler.js'
            },
            extensions: ['.js', '.ts', '.vue', '.json']
        },
        devServer: {
            historyApiFallback: true,
            noInfo: true,
            overlay: true,
            headers: {
                'Access-Control-Allow-Origin': '*'
            },
            disableHostCheck: true,
            https: true,
            public: 'https://127.0.0.1:8080',
            hot: true,
        },
        performance: {
            hints: false
        },
        devtool: 'eval',
        plugins: [
            new VueLoaderPlugin(),
            new webpack.DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify(isProduction ? 'production' : 'development')
            })
        ],
        watchOptions: {
            ignored: /node_modules/
        },
        externals: {
            'core/ajax': { amd: 'core/ajax' },
            'core/localstorage': { amd: 'core/localstorage' },
            'core/notification': { amd: 'core/notification' }
        }
    };

    if (isProduction) {
        config.devtool = false;
        config.plugins.push(
            new webpack.LoaderOptionsPlugin({
                minimize: true
            })
        );
        config.optimization = {
            minimizer: [
                new TerserPlugin({
                    parallel: true,
                }),
            ]
        };
    }

    return config;
};
