<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form action="options.php" method="post">
        <?php
        settings_fields('usctdp_mgmt_group');
        do_settings_sections('usctdp-admin-main');
        submit_button();
        ?>
    </form>

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