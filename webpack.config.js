const Encore = require("@symfony/webpack-encore");

Encore
    .addEntry("app", "./assets/js/app.js")
    .addStyleEntry("css", "./assets/css/app.scss")
    .setOutputPath("./src/Resources/public/entityfile")
    .setPublicPath("/")
    .setManifestKeyPrefix("bundles/entityfile")
    .cleanupOutputBeforeBuild()
    .enableSassLoader()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .disableSingleRuntimeChunk()
    .addRule(
        {
            test: /\.html$/,
            exclude: /node_modules/,
            use: {loader: 'html-loader'}
        });

const entityfile = Encore.getWebpackConfig();

module.exports = [entityfile];
