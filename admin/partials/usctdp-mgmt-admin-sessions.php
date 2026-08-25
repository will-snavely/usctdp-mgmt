<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <dl class="sessions-status-legend">
        <div class="sessions-status-legend-item status-scheduled">
            <dt>Scheduled</dt>
            <dd>Part of this season's published schedule - shown on the site's program listing, but registration isn't open yet.</dd>
        </div>
        <div class="sessions-status-legend-item status-on_sale">
            <dt>On Sale</dt>
            <dd>Shown on the site and open for registration.</dd>
        </div>
        <div class="sessions-status-legend-item status-archived">
            <dt>Archived</dt>
            <dd>No longer part of the current schedule - hidden from most admin screens and the public listing.</dd>
        </div>
    </dl>

    <div id="sessions-toolbar" class="flex-row gap-10 align-center">
        <label for="sessions-status-filter">Show:</label>
        <select id="sessions-status-filter">
            <option value="">All Sessions</option>
            <option value="not_archived" selected>Scheduled + On Sale</option>
            <option value="scheduled">Scheduled</option>
            <option value="on_sale">On Sale</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <div id="sessions-table-wrap">
        <table id="sessions-table" class="usctdp-datatable">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>

    <dialog id="session-pricing-modal">
        <div class="modal-wrap">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Pricing</h2>
                    <p id="pricing-modal-session-title" class="modal-subtitle"></p>
                </div>
                <div class="modal-body">
                    <p id="pricing-modal-empty" class="hidden">
                        This session has no pricing set up yet for any product. Pricing is
                        currently only created by the data importer, not this dialog.
                    </p>
                    <div id="pricing-modal-products" class="flex-col gap-15"></div>
                </div>
                <div class="actions-footer modal-footer">
                    <button type="button" class="button button-primary" id="save-pricing-btn">Save</button>
                    <button type="button" class="button" id="cancel-pricing-btn">Cancel</button>
                </div>
            </div>
        </div>
    </dialog>
</div>
