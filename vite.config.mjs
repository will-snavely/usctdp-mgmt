import { defineConfig } from 'vite';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// admin/class-usctdp-mgmt-admin.php enqueues this bundle's JS and CSS from
// fixed, unhashed paths (dist/js/usctdp-mgmt-admin-vendor.js,
// dist/css/usctdp-mgmt-admin-vendor.css), and loads it as a classic script
// (no type="module"), so this builds in Vite's library mode: a single-file
// IIFE with a real extracted CSS file (plain rollupOptions.input + iife
// silently inlines the CSS into the JS instead of emitting a .css file -
// lib mode's cssFileName is what actually forces static extraction).
//
// jquery stays external: WordPress core already provides it as a global
// (`jQuery`), and the script is enqueued with 'jquery' as a WP dependency so
// it loads first - the vendor bundle should reference that global rather
// than bundling its own copy.
export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: false,
    manifest: false,
    lib: {
      entry: resolve(__dirname, 'admin/js/usctdp-mgmt-admin-vendor.mjs'),
      name: 'UsctdpMgmtAdminVendor',
      formats: ['iife'],
      fileName: () => 'js/usctdp-mgmt-admin-vendor.js',
      cssFileName: 'css/usctdp-mgmt-admin-vendor',
    },
    rollupOptions: {
      external: ['jquery'],
      output: {
        globals: { jquery: 'jQuery' },
      },
    },
  },
});
