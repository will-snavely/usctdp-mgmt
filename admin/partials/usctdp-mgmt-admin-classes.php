<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="usctdp-mgmt-sections">
        <section id="usctdp-classes-section">
            <h2> Actions </h2>
            <ul>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=usctdp-mgmt-create-session')); ?>">
                        Create New Session
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=usctdp-class')); ?>">
                        Create New Class
                    </a>
                </li>
            </ul>
            <h2> Active and Upcoming Classes </h2>
            <div id="usctdp-classes-container">
                <div id="session-filter-wrapper" class="dt-layout-cell dt-layout-start">
                    <label for="session-filter">Filter by Session:</label>
                    <select id="session-filter" style="margin-left: 10px;">
                    </select>
                </div>
                <table id="usctdp-upcoming-classes-table" class="usctdp-custom-post-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Session</th>
                            <th>Capacity</th>
                            <th>Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usctdp-upcoming-classes-table-body">
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>