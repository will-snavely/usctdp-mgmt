<?php
global $wpdb;

$balance_query = "
    SELECT COUNT(id) as total_count, SUM(balance) as total_balance 
    FROM {$wpdb->prefix}usctdp_registration 
    WHERE balance > 0
";

$balance_results = $wpdb->get_row($balance_query);
if ($balance_results) {
    $outstanding_count = $balance_results->total_count;
    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    if ($balance_results->total_balance) {
        $outstanding_balance = $formatter->format($balance_results->total_balance);
    } else {
        $outstanding_balance = $formatter->format(0.00);
    }
} else {
    $outstanding_count = 0;
    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    $outstanding_balance = $formatter->format(0.00);
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="grid-layout">
        <div id="item-0" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Families</span>
            </div>

            <div id="usctdp-families-manager" class="grid-square-content">
                <select id="families-select2"></select>
                <button type="button" id="manage-family-btn" class="button" disabled>Manage Family</button>
            </div>
        </div>
        <div id="item-1" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Balances</span>
            </div>
            <div id="usctdp-oustanding-balances-manager" class="grid-square-content">
                <span><strong>Outstanding Registrations:</strong> <?php echo $outstanding_count; ?></span>
                <span><strong>Outstanding Balance:</strong> <?php echo $outstanding_balance; ?></span>
                <a href="<?php echo admin_url('admin.php?page=usctdp-admin-balances'); ?>" id="balances-report-link"
                    type="button" class="button">View Report</a>
            </div>
        </div>
        <div id="item-2" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Google Auth</span>
            </div>
            <div id="usctdp-google-authorization-manager" class="grid-square-content">
                <span><strong>Refresh Token Present:</strong>
                    <?php echo get_option('usctdp_google_refresh_token') ? 'Yes' : 'No'; ?></span>
                <?php if (get_option('usctdp_google_refresh_token_timestamp')) { ?>
                    <span><strong>Refresh Token Timestamp:</strong>
                        <?php echo get_option('usctdp_google_refresh_token_timestamp'); ?></span>
                <?php } ?>
                <span>
                    <?php $auth_url = admin_url('admin.php?page=usctdp-admin-main&usctdp_google_auth=1'); ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button">
                        Authorize Google
                    </a>
                </span>
            </div>
        </div>
        <div id="item-3" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Session Rosters</span>
            </div>
            <div id="usctdp-session-rosters-manager" class="grid-square-content">
                <div id="session-rosters-select-container">
                    <select id="active-sessions-select2"></select>
                    <button type="button" id="add-active-session-btn" class="button">Add Session</button>
                </div>
                <div id="session-rosters-container" width="100%">
                    <table id="session-rosters-table" class="hidden" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="session-rosters-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>