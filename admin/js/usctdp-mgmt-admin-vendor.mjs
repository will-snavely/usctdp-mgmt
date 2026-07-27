import '../css/usctdp-mgmt-admin-vendor.css';

import select2 from 'select2';
// Unlike select2, datatables.net-dt attaches itself to $.fn.DataTable as a
// side effect of being imported - the `DataTable` binding itself is unused
// here. That's a property of this specific package's build, not something
// guaranteed by future versions; if a future bump ever leaves $.fn.DataTable
// undefined the way select2 did, this is the first place to look.
import DataTable from 'datatables.net-dt';
import Swal from 'sweetalert2';

import {
    QueryClient,
    QueryObserver,
    MutationObserver as TSMutationObserver
} from '@tanstack/query-core';

// select2's UMD wrapper detects Rollup's synthesized CJS `module.exports`
// and, instead of attaching itself to jQuery immediately (the plain
// browser-global branch it takes when bundled as a plain script), exports a
// lazy `(root, jQuery) => {...}` invoker meant for real CommonJS consumers -
// nothing calls it automatically, so a bare `import 'select2'` left
// `$.fn.select2` undefined. Call it so it resolves the external jQuery
// global and runs its factory.
select2();

window.QueryClient = QueryClient;
window.QueryObserver = QueryObserver;
window.TSMutationObserver = TSMutationObserver;
window.Swal = Swal;
