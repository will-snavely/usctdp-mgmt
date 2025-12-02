(function ($) {
    "use strict";
    $(document).ready(function () {
        function debounce(func, wait, immediate) {
            var timeout;
            return function () {
                var context = this, args = arguments;
                var later = function () {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }

        var $searchInput = $('#usctdp-classes-search');
        var $spinner = $('#usctdp-search-spinner');
        var $tableBody = $('#usctdp-upcoming-classes-table-body');

        if ($searchInput.length) {
            $searchInput.on('keyup', debounce(function () {
                var searchTerm = $(this).val();
                $spinner.addClass('is-active');

                $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'usctdp_fetch_classes',
                        nonce: usctdp_mgmt_admin.nonce,
                        search: searchTerm
                    },
                    success: function (response) {
                        console.log(response);
                        //$tableBody.html(response);
                        $spinner.removeClass('is-active');
                    },
                    error: function () {
                        alert('Error fetching sessions.');
                        $spinner.removeClass('is-active');
                    }
                });
            }, 500));
        }
    });
})(jQuery);

