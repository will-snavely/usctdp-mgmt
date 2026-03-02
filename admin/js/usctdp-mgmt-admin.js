(function($) {
    window.USCTDP_Admin = window.USCTDP_Admin || {};

    USCTDP_Admin.displayTime = function(dateObj) {
        const options = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        return new Intl.DateTimeFormat('en-US', options).format(dateObj);
    }

    USCTDP_Admin.applyReplacements = function(input, replacements) {
        return replacements.reduce((currentString, [pattern, replacement]) => {
            return currentString.replace(pattern, replacement);
        }, input);
    }

    USCTDP_Admin.formatUsd = function(amount) {
        if (amount === null) {
           amount = 0;
        }
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function select2Options(options) {
        const {
            placeholder = "Search...",
            allowClear = true,
            target = null,
            url = usctdp_mgmt_admin.ajax_url,
            action=usctdp_mgmt_admin.select2_search_action,
            nonce=usctdp_mgmt_admin.select2_search_nonce,
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
                        target: target,
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

    USCTDP_Admin.select2Options = select2Options; 

    USCTDP_Admin.CascasdingSelect = class {
        constructor(containerId, config) {
            this.container = $(`#${containerId}`);
            this.container.addClass("context-selector-group");
            this.config = config;
            this.state = {};
            this.init();
        }

        trigger(eventName, detail = {}) {
            const event = new CustomEvent(`cascade:${eventName}`, {
                detail: { ...detail, manager: this },
                bubbles: true
            });
            this.container[0].dispatchEvent(event);
        } 

        init() {
            Object.entries(this.config).forEach(([id, settings]) => {
                this.renderSection(id, settings);
                this.initSelect2(id, settings);
            });

            this.container.on('change', '.context-selector', (e) => {
                this.handleChange($(e.currentTarget));
            });

            this.trigger('ready', { state: this.state });
        }

        renderSection(id, settings) {
            const isVisible = settings.isRoot ? '' : 'hidden';
            const html = `
                <div id="${id}-section" class="context-selector-section ${isVisible}">
                    <label for="${id}" class="context-selector-label">${settings.label}</label>
                    <div class="content-selector-wrap">
                        <select id="${id}" name="${settings.name}" class="context-selector" style="width:100%">
                        </select>
                    </div>
                </div>`;
            
            this.container.append(html);
        }

        initSelect2(id, settings) {
            const $el = $(`#${id}`);
            $el.select2(
                select2Options({
                    placeholder: `Select ${settings.label}...`,
                    allowClear: true,
                    target: settings.target,
                    filter: settings.filter
                })
            );
        }

        handleChange($el) {
            const id = $el.attr('id');
            const settings = this.config[id];
            const val = $el.val();
            const text = $el.find('option:selected').text();

            // Determine the "Next" selector based on logic or static ID
            const next = settings.next;
            var nextId = typeof next === "function" ? next(val, $el) : next;
            var branches = typeof next === "string" ? [next] : settings.branches;
            if (branches) {
                branches.forEach(branchId => this.resetAndHide(branchId));
            }

            if (nextId && val) {
                $(`#${nextId}-section`).removeClass('hidden');
            }

            this.updateState();
            this.trigger('change', { 
                selectorId: id, 
                value: val, 
                text: text,
                nextId: nextId,
                complete: val && (!nextId || nextId.length === 0),
                state: this.state
            });
        }

        resetAndHide(id) {
            const $el = $(`#${id}`);
            const settings = this.config[id];

            if ($el.prop('disabled')) return;

            $el.val(null).trigger('change.select2');
            $(`#${id}-section`).addClass('hidden');

            const next = settings.next
            var branches = typeof next === "string" ? [next] : settings.branches 
            if (branches) {
                branches.forEach(branchId => this.resetAndHide(branchId));
            }
        }

        applyData(data) {
            Object.entries(this.config).forEach(([id, settings]) => {
                const entry = data[id];
                if (entry) {
                    const $el = $(`#${id}`);
                    const newOption = new Option(entry.text, entry.id, true, true);
                    $el.append(newOption).trigger('change');
                    $el.prop('disabled', entry.disable ?? true);
                    $(`#${id}-section`).removeClass('hidden');
                }
            });
        }

        updateState() {
            this.state = {};
            this.container.find('.context-selector').each((i, el) => {
                if ($(el).val()) {
                    this.state[$(el).attr('name')] = $(el).val();
                }
            });
        }
    };
})(jQuery);
