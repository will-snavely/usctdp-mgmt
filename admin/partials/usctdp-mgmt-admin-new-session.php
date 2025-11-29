<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["new_session"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];

$class_field_names = [
    "field_usctdp_class_type",
    "field_usctdp_class_duration_weeks",
    "field_usctdp_class_dow",
    "field_usctdp_class_start_time",
    "field_usctdp_class_end_time",
    "field_usctdp_class_level",
    "field_usctdp_class_capacity",
    "field_usctdp_class_one_day_price",
    "field_usctdp_class_two_day_price",
    "field_usctdp_class_instructors"
];
$fields = array_map(function($field_name) {
    return acf_get_field($field_name);
}, $class_field_names);
?>

<div class="wrap" id="usctdp-admin-new-session-wrapper">
    <template id="new-session-row-template">
        <tr>
            <td class="row-index">0</td>
            <?php acf_render_fields($fields, 'new_post', 'td', ''); ?>
            <td>
                <input type="text" placeholder="Select" id="multi-date-picker" class="multi-date-picker">
            </td>
            <td>
                <button type="button" class="button-secondary dup-row-btn">Copy</button>
            </td>
            <td>
                <button type="button" class="button-secondary remove-row-btn">Remove</button>
            </td>
        </tr>
    </template>

    <h1><?php echo esc_html( $page_title ); ?></h1>
    <div id="form-submission-errors">
        <div class="error-message">
            <p>Please provide the required fields below and re-submit.</p>
        </div>
    </div>

    <form 
      novalidate
      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" 
      method="post" 
      id="usctdp-new-session-form">
        <div id="session-info-section">
            <h2> Session Info </h2>
            <div id="session-fields">
                <div class="form-field">
                    <label for="session_name">Session Name</label>
                    <input type="text" id="session_name" name="session[field_usctdp_session_name]" required>
                </div>
                <div class="form-field">
                    <label for="session_start_date">Start Date</label>
                    <input type="date" id="session_start_date" name="session[field_usctdp_session_start_date]" required>
                </div>
                <div class="form-field">
                    <label for="session_end_date">End Date</label>
                    <input type="date" id="session_end_date" name="session[field_usctdp_session_end_date]" required>
                </div>
            </div>
        </div>

        <div id="class-info-section">
            <h2> Classes </h2>
            <table>
                <thead>
                    <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th># Weeks</th>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Level</th>
                    <th>Cap</th>
                    <th>1-Day $</th>
                    <th>2-Day $</th>
                    <th>Staff</th>
                    <th>Class Dates</th>
                </tr>
            </thead>
            <tbody id="new-session-input-table-body">
            </tbody>
        </table>
        </div>

        <div id="new-session-add-rows-section">
            <button type="button" class="button-secondary" id="new-session-add-row-btn">Add Row(s)</button>
            <input type="number" id="new-session-num-rows-field" value=5>
        </div>

        <input type="hidden" name="action" value="<?php echo esc_attr( $submit_hook ); ?>">
        <?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
        <?php submit_button('Create Session', 'primary', 'new_session_submit' ); ?>
    </form>
</div>