const pluginName = 'trackmage-wp-plugin';

const path = require('path');
const UglifyJsPlugin = require("uglifyjs-webpack-plugin");
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const WebpackSynchronizableShellPlugin = require('webpack-synchronizable-shell-plugin');
const ExtraWatchWebpackPlugin = require("extra-watch-webpack-plugin");
const EventHooksPlugin = require('event-hooks-webpack-plugin');
const fs = require('fs-extra');

const DIST_DIR = path.resolve(__dirname),
  SRC_DIR = path.resolve(__dirname, 'src');


module.exports = (env, argv) => {
  return ({

    watch: argv.mode !== 'production',

    watchOptions: {
      aggregateTimeout: 300,
      poll: 500,
      ignored: [
        'node_modules'
      ],
    },

    context: SRC_DIR,

    entry: {
      frontend: [
        './js/frontend.js',
        './scss/frontend.scss',
      ],
      admin: [
        './js/admin.js',
        './scss/admin.scss',
      ]
    },

    output: {
      path: DIST_DIR + '/assets/',
      filename: 'js/[name].min.js',
    },

    module: {
      rules: [
        {
          test: /\.js?/,
          use: [
            {
              loader: 'babel-loader',
              options: {
                presets: ['@wordpress/default'],
                plugins: [
                  [
                    "@babel/transform-react-jsx",
                    {pragma: "wp.element.createElement"}
                  ]
                ]
              }
            },
          ],
        },
        {
          test: /\.(css|scss)$/,
          use: [
            {
              loader: 'file-loader',
              options: {
                name: 'css/[name].min.css',
              }
            },
            {
              loader: 'extract-loader'
            },
            {
              loader: 'css-loader',
            },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: true
              }
            }
          ],
        },
        // fonts
        {
          test: /\.(eot|svg|ttf|woff|woff2)$/,
          loader: 'url-loader?name=fonts/[name].[ext]'
        },
        // images
        {
          test: /\.(gif|png|jpe?g|svg)$/i,
          // loader: require.resolve("file-loader") + '?name=/images/[name].[ext]',
          use: [
            {
              loader: require.resolve('file-loader') + '?name=/images/[name].[ext]'
            },
            {
              loader: 'image-webpack-loader',
              options: {
                // bypassOnDebug: true, // webpack@1.x
                // disable: true, // webpack@2.x and newer
              },
            },
          ],
        },
        {
          test: /\.json$/,
          loader: 'json-loader'
        },
      ]
    },

    plugins: [

      new WebpackSynchronizableShellPlugin({
        onBuildStart: {
          scripts: [
            'composer install --no-dev'
          ],
          blocking: true,
          parallel: false
        },
        onBuildEnd: {
          scripts: [
            'composer install'
          ],
          blocking: true,
          parallel: false
        }
      }),

      new ExtraWatchWebpackPlugin({
        // files: [ 'path/to/file', 'src/**/*.json' ],
        dirs: [
          SRC_DIR + '/php'
        ],
      }),

      new EventHooksPlugin({
        'afterEmit': (done) => {

          fs.copySync(SRC_DIR + '/php/', DIST_DIR);

          console.log('\x1b[32m%s\x1b[0m', '---------------------------------------------------------------------------------------');
          console.log('\x1b[32m%s\x1b[0m', '> Synced DIST folder `' + pluginName + '` [' + new Date() + ']');
          console.log('\x1b[32m%s\x1b[0m', '---------------------------------------------------------------------------------------');

        },
      })

    ],

    optimization: {
      minimizer: [
        new UglifyJsPlugin(),
        new OptimizeCSSAssetsPlugin()
      ]
    },

    resolve: {
      extensions: ['.json', '.js', '.jsx'],
    }

  });
};