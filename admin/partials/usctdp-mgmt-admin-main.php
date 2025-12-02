<?php
$comparison_date = date("Ymd");
$session_query_args = array(
    'post_type'      => 'usctdp-session',
    'posts_per_page' => -1,
    'meta_query'     => array(
        array(
            'key'     => 'end_date',
            'value'   => $comparison_date,
            'compare' => '>=',
            'type'    => 'DATE',
        ),
    ),
    'orderby' => 'meta_value_num',
    'order'   => 'ASC',
);

$class_query_args = array(
    'post_type'      => 'usctdp-class',
    'posts_per_page' => -1,
    'meta_query'     => array(
        array(
            'key'     => 'end_date',
            'value'   => $comparison_date,
            'compare' => '>=',
            'type'    => 'DATE',
        ),
    ),
    'orderby' => 'meta_value_num',
    'order'   => 'ASC',
);
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="usctdp-mgmt-sections">  
        <section id="sessions-section">
            <h1>Sessions</h1>

            <h2> Active and Upcoming Sessions </h2>
            <table id="usctdp-upcoming-sessions-table" class="wp-list-table fixed widefat usctdp-custom-post-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody id="usctdp-upcoming-sessions-table-body">     
                <?php
                $session_query = new WP_Query( $session_query_args );
                if ( $session_query->have_posts() ) {
                    while (($session_query->have_posts())) {
                        $session_query->the_post();
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( get_edit_post_link() ); ?>">
                                            <?php echo get_field('field_usctdp_session_name') ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php 
                                    $start_date = get_field('field_usctdp_session_start_date'); 
                                    echo DateTime::createFromFormat('Ymd', $start_date)->format('m/d/Y');
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $end_date = get_field('field_usctdp_session_end_date'); 
                                    echo DateTime::createFromFormat('Ymd', $end_date)->format('m/d/Y');
                                    ?>
                                </td>
                            </tr>
                        <?php
                    }
                }
                wp_reset_postdata();
                ?>
                </tbody>
            </table>

            <h2> Active and Upcoming Classes </h2>
            <table id="usctdp-upcoming-classes-table" class="wp-list-table fixed widefat usctdp-custom-post-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Level</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Capacity</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody id="usctdp-upcoming-classes-table-body">     
                <?php
                $class_query = new WP_Query( $class_query_args );
                if ( $class_query->have_posts() ) {
                    while (($class_query->have_posts())) {
                        $class_query->the_post();
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( get_edit_post_link() ); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo get_field('field_usctdp_class_level'); ?>
                                </td>
                                <td>
                                    <?php 
                                    $start_date = get_field('field_usctdp_class_start_date'); 
                                    echo DateTime::createFromFormat('Ymd', $start_date)->format('m/d/Y');
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $end_date = get_field('field_usctdp_class_end_date'); 
                                    echo DateTime::createFromFormat('Ymd', $end_date)->format('m/d/Y');
                                    ?>
                                </td>
                                <td>
                                    <?php echo get_field('field_usctdp_class_capacity'); ?>
                                </td>
                                <td>
                                    <?php echo get_field('field_usctdp_class_instructors'); ?>
                                </td>
                            </tr>
                        <?php
                    }
                }
                wp_reset_postdata();
                ?>
                </tbody>
            </table>
            <h2> Actions </h2>
            <ul>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=usctdp-mgmt-create-session' ) ); ?>"> 
                        Create New Session
                    </a>
                </li>
            </ul>
        </section>

        <section id="families-section">
            <h1>Families</h1>
            <p>Content for the Families section will go here.</p>
        </section>
    </div>
</div>

