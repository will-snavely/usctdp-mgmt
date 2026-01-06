<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <h2>Active Sessions</h2>

    <div id="usctdp-active-sessions-manager">
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

    <h2>Families</h2>

    <div id="usctdp-families-manager">
        <div>
            <select id="families-select2"></select>
            <button type="button" id="manage-family-btn" class="button" disabled>Manage Family</button>
        </div>
    </div>

    <h2>Google Authorization:</h2>
    <ul>
        <li>Refresh Token Present: <?php echo get_option('usctdp_google_refresh_token') ? 'Yes' : 'No'; ?></li>
        <?php if (get_option('usctdp_google_refresh_token_timestamp')) { ?>
            <li>Refresh Token Timestamp: <?php echo get_option('usctdp_google_refresh_token_timestamp'); ?></li>
        <?php } ?>
        <li>
            <p>
                <?php $auth_url = admin_url('admin.php?page=usctdp-admin-main&usctdp_google_auth=1'); ?>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                    Authorize Google Services
                </a>
            </p>
        </li>
    </ul>

</div>