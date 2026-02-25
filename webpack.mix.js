const mix = require('laravel-mix');

mix.webpackConfig({
   externals: {
      jquery: 'jQuery'
   }
});

mix.js('admin/js/usctdp-mgmt-admin-vendor.mjs', 'dist/js')
   .postCss('admin/css/usctdp-mgmt-admin-vendor.css', 'dist/css');