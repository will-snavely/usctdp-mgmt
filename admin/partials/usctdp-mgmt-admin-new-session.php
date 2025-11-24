<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["new_session"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];
?>

<div class="wrap" id="usctdp-admin-new-session-wrapper">
    <template id="new-session-row-template">
        <tr>
            <td class="row-index">0</td>
            <?php 
                acf_form([   
                    'post_id' => 'new_post',    
                    "form" => false,
                    "field_el" => "td",
                    'new_post' => [       
                        'post_type' => 'usctdp-class',       
                        'post_status' => 'publish'   
                    ],
                    "fields" => [
                        "class_type",
                        "day_of_week",
                        "start_time",
                        "end_time",
                        "level",
                        "capacity",
                        "one_day_price",
                        "two_day_price",
                        "instructors"
                    ]]);?>
            <td>
                <button type="button" class="button-secondary dup-row-btn">Copy</button>
            </td>
            <td>
                <button type="button" class="button-secondary remove-row-btn">Remove</button>
            </td>
       </tr>
    </template>

    <h1><?php echo esc_html( $page_title ); ?></h1>
    <form 
      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" 
      method="post" 
      id="usctdp-new-session-form">

        <div id="session-info-section">
            <h2> Session Info </h2>
            <?php 
                acf_form([   
                    'post_id' => 'new_post',    
                    "form" => false,
                    "field_el" => "div",
                    'new_post' => [       
                        'post_type' => 'usctdp-session',       
                        'post_status' => 'publish'   
                    ],
                    'fields' => [
                        'session_name', 
                        'start_date', 
                        'end_date'
                    ]]);?>
        </div>

        <div id="class-info-section">
            <h2> Classes </h2>
            <table>
                <thead>
                    <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Level</th>
                    <th>Cap</th>
                    <th>1-Day $</th>
                    <th>2-Day $</th>
                    <th>Staff</th>
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