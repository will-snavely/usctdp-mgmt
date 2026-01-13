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
    if($balance_results->total_balance) {
        $outstanding_balance = $formatter->format($balance_results->total_balance);
    } else {
        $outstanding_balance = $formatter->format(0.00);
    }
} else {
    $outstanding_count = 0;
    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    $outstanding_balance = $formatter->format(0.00);
}

$active_sessions = get_posts([
    'post_type' => 'usctdp-session',
    'tag' => 'active',
    'posts_per_page' => -1
]);
$current_sessions = [];
date_default_timezone_set('America/New_York');
$today = new DateTime('now');
$threshold_start_date = new DateTime('now');
$threshold_start_date->modify('+2 weeks');
$threshold_end_date = new DateTime('now');
$threshold_end_date->modify('-1 week');

foreach ($active_sessions as $session) {
    $session_start_date = new DateTime(get_field('start_date', $session->ID));
    $session_end_date = new DateTime(get_field('end_date', $session->ID));
    if ($session_start_date < $threshold_start_date && $session_end_date > $threshold_end_date) {
        $current_sessions[] = $session->ID;
    }
}

$session_roster_query = "   
    SELECT 
        pt1.ID as id,
        pt1.post_title as title,
        rst.drive_id as drive_link
    FROM {$wpdb->prefix}posts AS pt1
    LEFT JOIN {$wpdb->prefix}usctdp_roster_link as rst ON pt1.ID = rst.entity_id
    WHERE pt1.ID = %d
    ORDER BY pt1.post_title
";
$session_rosters = [];
foreach ($current_sessions as $session_id) {
    $query = $wpdb->prepare($session_roster_query, $session_id);
    $session_roster_results = $wpdb->get_results($query);
    if ($session_roster_results) {
        foreach ($session_roster_results as $roster_result) {
            $link = "https://docs.google.com/document/d/" . $roster_result->drive_link . "/edit";
            $session_rosters[] = [
                "id" => $roster_result->id,
                "title" => $roster_result->title,
                "drive_link" => $link,
            ];
        }
    }
}

$clinic_roster_query = "   
    SELECT 
        pt1.ID as id,
        pt1.post_title as title,
        rst.drive_id as drive_link
    FROM {$wpdb->prefix}posts AS pt1
    LEFT JOIN {$wpdb->prefix}usctdp_roster_link as rst ON pt1.ID = rst.entity_id
    WHERE pt1.post_type = 'usctdp-clinic' AND pt1.post_status = 'publish'
    ORDER BY pt1.post_title
";
$clinic_roster_results = $wpdb->get_results($clinic_roster_query);
$clinic_rosters = [];
if ($clinic_roster_results) {
    foreach ($clinic_roster_results as $roster_result) {
        $link = "https://docs.google.com/document/d/" . $roster_result->drive_link . "/edit";
        $clinic_rosters[] = [
            "id" => $roster_result->id,
            "title" => $roster_result->title,
            "drive_link" => $link,
        ];
    }
} ?>

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
                <a href="<?php echo admin_url('admin.php?page=usctdp-admin-balances'); ?>" id="balances-report-link" type="button" class="button">Report</a>
            </div>
        </div>
        <div id="item-2" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Google Auth</span>
            </div>
            <div id="usctdp-google-authorization-manager" class="grid-square-content">
                <span><strong>Refresh Token Present:</strong> <?php echo get_option('usctdp_google_refresh_token') ? 'Yes' : 'No'; ?></span>
                <?php if (get_option('usctdp_google_refresh_token_timestamp')) { ?>
                    <span><strong>Refresh Token Timestamp:</strong> <?php echo get_option('usctdp_google_refresh_token_timestamp'); ?></span>
                <?php } ?>
                <span>
                    <?php $auth_url = admin_url('admin.php?page=usctdp-admin-main&usctdp_google_auth=1'); ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button">
                        Authorize Google Services
                    </a>
                </span>
            </div>
        </div>
        <div id="item-3" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Session Rosters</span>
            </div>
            <div id="usctdp-session-rosters-manager" class="grid-square-content">
                <div id="session-rosters-container">
                    <table id="session-rosters-table" class="usctdp-custom-post-table hidden">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="session-rosters-table-body">
                            <?php if ($session_rosters) : ?>
                                <?php foreach ($session_rosters as $roster) : ?>
                                    <tr>
                                        <td><?php echo $roster['title']; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="<?php echo $roster['drive_link']; ?>" class="button " target="_blank">View</a>
                                                <button class="button refresh-session-roster" data-session-id="<?php echo $roster['id']; ?>">
                                                    <span class="button-text">Refresh</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="item-4" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Clinic Rosters</span>
            </div>
            <div id="usctdp-clinic-rosters-manager" class="grid-square-content" data-current-sessions="<?php echo json_encode($current_sessions); ?>">
                <div id="clinic-rosters-container">
                    <table id="clinic-rosters-table" class="usctdp-custom-post-table hidden">
                        <thead>
                            <tr>
                                <th>Clinic</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="clinic-rosters-table-body">
                            <?php if ($clinic_rosters) : ?>
                                <?php foreach ($clinic_rosters as $roster) : ?>
                                    <tr>
                                        <td><?php echo $roster['title']; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="<?php echo $roster['drive_link']; ?>" class="button" target="_blank">View</a>
                                                <button class="button refresh-clinic-roster" data-clinic-id="<?php echo $roster['id']; ?>">
                                                    <span class="button-text">Refresh</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="item-5" class="grid-square">
            <div class="grid-square-header">
                <span class="grid-header">Active Sessions</span>
            </div>
            <div id="usctdp-active-sessions-manager" class="grid-square-content">
                <div>
                    <select id="active-sessions-select2"></select>
                    <button type="button" id="add-active-session-btn" class="button">Make Active</button>
                </div>

                <div id="active-sessions-container">
                    <table id="active-sessions-table" class="usctdp-custom-post-table">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="active-sessions-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
