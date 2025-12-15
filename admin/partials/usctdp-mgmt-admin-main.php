<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p>
        <?php $auth_url = admin_url('admin.php?page=usctdp-admin-main&usctdp_google_auth=1'); ?>
        <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
            Authorize Google Drive
        </a>
    </p>
</div>