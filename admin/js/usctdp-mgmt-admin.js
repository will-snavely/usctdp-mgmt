/**
 * 1. Define your Global Namespace
 * This creates a single object on the 'window' so you don't 
 * pollute the global space with 100 random function names.
 */
window.USCTDP_Admin = window.USCTDP_Admin || {};

USCTDP_Admin.displayTime = function (dateObj) {
    const options = {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    };
    return new Intl.DateTimeFormat('en-US', options).format(dateObj);
}

USCTDP_Admin.select2 = function (options) {
    return {
        placeholder: options.placeholder ?? "Search...",
        allowClear: options.allowClear ?? true,
        ajax: {
            url: options.url,
            data: function (params) {
                return {
                    q: params.term,
                    action: options.action,
                    security: options.nonce,
                    ...options.filter()
                };
            },
            processResults: function (data) {
                return {
                    results: data.items
                };
            }
        }
    }
}

USCTDP_Admin.select2Options = function (options) {
    const {
        placeholder = "Search...",
        allowClear = true,
        url = usctdp_mgmt_admin.ajax_url,
        action,
        nonce,
        minimumInputLength = 0,
        filter = () => ({}),
        ...extraOptions
    } = options;

    return {
        placeholder,
        allowClear,
        minimumInputLength,
        ajax: {
            url,
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    action: action,
                    security: nonce,
                    ...filter()
                };
            },
            processResults: function (data) {
                return {
                    results: data.items || [],
                };
            },
            cache: true
        },
        ...extraOptions
    };
};