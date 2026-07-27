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
  // @tanstack/query-core (and other deps) reference process.env.NODE_ENV
  // for dev-only warnings, assuming the bundler statically replaces it the
  // way Webpack's DefinePlugin did under the old Laravel Mix build. Vite's
  // lib mode doesn't do this replacement automatically, so without this the
  // literal `process.env.NODE_ENV` reaches the browser bundle and throws
  // "process is not defined" the first time that code path runs.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: 'dist',
    emptyOutDir: false,
    manifest: false,
    // Hidden: emit a .map file for real stack traces without shipping a
    // //# sourceMappingURL comment (this bundle is fetched directly by
    // browsers, not proxied through devtools-only tooling).
    sourcemap: 'hidden',
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
