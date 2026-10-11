const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('../public/build/')
    .setPublicPath('/build')
    .addEntry('admin', './assets/js/admin.js')
    .addEntry('admin_post_form', './assets/js/admin-post-form.js')
    .addEntry('app', './assets/js/app.js')
    .copyFiles({
        from: './node_modules/bootstrap-icons/font/fonts',
        to: 'fonts/[path][name].[ext]',
    })
    .copyFiles({
        from: './node_modules/@fortawesome/fontawesome-free/webfonts',
        to: 'webfonts/[path][name].[ext]',
    })
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            config: path.resolve(__dirname, 'postcss.config.js'),
        };
    })
    .enableSassLoader()
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .enableVersioning(Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    });

module.exports = Encore.getWebpackConfig();
