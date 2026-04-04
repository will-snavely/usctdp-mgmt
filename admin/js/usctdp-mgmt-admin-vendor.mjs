import 'select2';
import DataTable from 'datatables.net-dt';
import Swal from 'sweetalert2';

import {
    QueryClient,
    QueryObserver,
    MutationObserver as TSMutationObserver
} from '@tanstack/query-core';

window.QueryClient = QueryClient;
window.QueryObserver = QueryObserver;
window.TSMutationObserver = TSMutationObserver;
window.Swal = Swal;
