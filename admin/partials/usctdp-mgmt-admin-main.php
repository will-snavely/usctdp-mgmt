<?php
global $wpdb;

$balance_query =
    "   SELECT
            COUNT(id) as total_count,
            SUM(debit) - SUM(credit) as total_balance
        FROM {$wpdb->prefix}usctdp_ledger
        WHERE account in ('registration_fees', 'merchandise_fees')";

$balance_results = $wpdb->get_row($balance_query);
$formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
if ($balance_results) {
    $outstanding_count = $balance_results->total_count;
    $outstanding_raw = $balance_results->total_balance ? $balance_results->total_balance : 0.00;
} else {
    $outstanding_count = 0;
    $outstanding_raw = 0.00;
}
$outstanding_balance = $formatter->format($outstanding_raw);
$balance_status = $outstanding_raw > 0 ? 'critical' : 'good';

$google_connected = (bool) get_option('usctdp_google_refresh_token');
$google_status = $google_connected ? 'good' : 'critical';
$google_status_label = $google_connected ? 'Connected' : 'Not Connected';
$google_timestamp = get_option('usctdp_google_refresh_token_timestamp');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="usctdp-dashboard">
        <div class="dashboard-grid">
            <div id="item-0" class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Families</span>
                </div>
                <div id="usctdp-families-manager" class="dashboard-card-body">
                    <span class="card-description">Look up a family to manage their students, registrations, and
                        payments.</span>
                    <div class="card-controls">
                        <select id="families-select2"></select>
                        <button type="button" id="manage-family-btn" class="button button-primary"
                            disabled>Manage Family</button>
                    </div>
                </div>
            </div>

            <div id="item-1" class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Balances</span>
                </div>
                <div id="usctdp-oustanding-balances-manager" class="dashboard-card-body">
                    <div class="stat-tile">
                        <span class="stat-value status-<?php echo esc_attr($balance_status); ?>">
                            <?php echo esc_html($outstanding_balance); ?>
                        </span>
                        <span class="stat-label">Outstanding Balance</span>
                    </div>
                    <span class="stat-subtext"><?php echo esc_html($outstanding_count); ?> outstanding
                        purchase(s)</span>
                    <div class="card-controls">
                        <a href="<?php echo admin_url('admin.php?page=usctdp-admin-balances'); ?>"
                            id="balances-report-link" type="button" class="button">View Report</a>
                    </div>
                </div>
            </div>

            <div id="item-2" class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Google Auth</span>
                </div>
                <div id="usctdp-google-authorization-manager" class="dashboard-card-body">
                    <div class="status-badge status-<?php echo esc_attr($google_status); ?>">
                        <span class="status-dot"></span>
                        <span class="status-label"><?php echo esc_html($google_status_label); ?></span>
                    </div>
                    <?php if ($google_timestamp) { ?>
                        <span class="stat-subtext">Last authorized: <?php echo esc_html($google_timestamp); ?></span>
                    <?php } ?>
                    <div class="card-controls">
                        <?php $auth_url = admin_url('admin.php?page=usctdp-admin-main&usctdp_google_auth=1'); ?>
                        <a href="<?php echo esc_url($auth_url); ?>" class="button">
                            Authorize Google
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid dashboard-grid-split">
            <div id="item-3" class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Rosters</span>
                </div>
                <div id="usctdp-session-rosters-manager" class="dashboard-card-body">
                    <div id="session-rosters-select-container">
                        <select id="active-sessions-select2"></select>
                        <button type="button" id="add-active-session-btn" class="button">Add Session</button>
                    </div>
                    <div id="session-rosters-container" class="table-scroll-x" width="100%">
                        <table id="session-rosters-table" class="usctdp-mini-table hidden" width="100%"
                            cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="session-rosters-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="item-4" class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Recent Registrations</span>
                </div>
                <div id="usctdp-recent-registrations-manager" class="dashboard-card-body">
                    <div class="table-scroll">
                        <table id="recent-registrations-table" class="usctdp-mini-table" width="100%"
                            cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Family</th>
                                    <th>Session</th>
                                    <th>Activity</th>
                                </tr>
                            </thead>
                            <tbody id="recent-registrations-table-body">
                                <tr class="loading-row">
                                    <td colspan="4">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
