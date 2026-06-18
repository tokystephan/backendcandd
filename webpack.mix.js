const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        //
    ]);

// Work around WebpackBar/ProgressPlugin incompatibility on newer Node/Webpack.
mix.override((webpackConfig) => {
    webpackConfig.plugins = (webpackConfig.plugins || []).filter((plugin) => {
        const pluginName = plugin?.constructor?.name || '';
        return !pluginName.includes('WebpackBar');
    });
});
