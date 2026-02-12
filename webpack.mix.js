const mix = require('laravel-mix');

mix.copy('node_modules/select2/dist/js/select2.full.min.js', 'assets/js/select2.min.js')
   .copy('node_modules/select2/dist/css/select2.min.css', 'assets/css/select2.min.css');
