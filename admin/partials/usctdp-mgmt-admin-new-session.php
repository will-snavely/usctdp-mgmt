<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["new_session"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];

acf_add_local_field([
    'prefix'   => 'acf',
    'name'     => '_post_title',
    'key'      => '_post_title',
    'label'    => 'Session Name',
    'type'     => 'text',
    'required' => true, 
]);

$session_fields = array();
$session_field_groups = acf_get_field_groups([
    'post_type' => 'usctdp-session' 
]);

$_post_title = acf_get_field( '_post_title' );
$_post_title['value'] = '';
$session_fields[] = $_post_title;

if ( $session_field_groups ) {
    foreach ( $session_field_groups as $field_group ) {
        $_fields = acf_get_fields( $field_group );
        if ( $_fields ) {
            foreach ( $_fields as $_field ) {
                $session_fields[] = $_field;
            }
        }
    }
}

$class_fields = array();
$class_field_groups = acf_get_field_groups([
    'post_type' => 'usctdp-class' 
]);
if ( $class_field_groups ) {
    foreach ( $class_field_groups as $field_group ) {
        $_fields = acf_get_fields( $field_group );
        if ( $_fields ) {
            foreach ( $_fields as $_field ) {
                if($_field["name"] !== 'parent_session') {
                    $class_fields[] = $_field;
                }
            }
        }
    }
}
?>

<div class="wrap" id="usctdp-admin-new-session-wrapper">
    <template id="row-template">
        <tr>
            <td class="row-index">1</td>
            <td>
                <select name="level" id="level-select">
                    <option value="" disabled selected hidden>Select an option</option>
                    <option value="tinytots-1">Tiny Tots</option>
                    <option value="red-pre" >Red Pre-Rally</option>
                    <option value="red-1" >Red</option>
                    <option value="orange-pre" >Orange Pre-Rally</option>
                    <option value="orange-1" >Orange</option>
                    <option value="teen-1">Teen 1</option>
                    <option value="orange-2" >Orange 2</option>
                    <option value="green-1" >Green</option>
                    <option value="yellow-1" >Yellow Ball</option>
                    <option value="yellow-2" >Yellow Ball Open</option>
                </select>
            </td>
            <td>
                <select name="level" id="day-select">
                    <option value="mon">Monday</option>
                    <option value="tues">Tuesday</option>
                    <option value="wed">Wednesday</option>
                    <option value="thurs">Thursday</option>
                    <option value="fri" >Friday</option>
                    <option value="sat">Saturday</option>
                    <option value="sun">Sunday</option>
                </select>
            </td>
            <td>
                <input type="time" id="start-time-input">
            </td>
            <td>
                <input type="time" id="start-time-input">
            </td>
            <td>
                <input type="text" id="instructor-1-input">
            </td>
            <td>
                <input type="text" id="instructor-2-input">
            </td>
            <td>
                <button type="button" class="dup-row-btn" style="color: green;">Copy</button>
                <button type="button" class="remove-row-btn" style="color: red;">Remove</button>
            </td>
        </tr>
    </template>

    <h1><?php echo esc_html( $page_title ); ?></h1>
    <form 
      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" 
      method="post" 
      id="usctdp-new-session-form">
        <h2> Session Info </h2>
        <table>
            <tr>
                <td>
                    <label for="session-name-input">Session Name:</label>
                </td>
                <td>
                    <input type="text" id="session-name-input" name="session-name">
                </td>
            </tr>
            <tr>
                <td>
                    <label for="session-start-input">Session Start:</label>
                </td>
                <td>
                    <input type="date" id="session-start-input" name="session-start">
                </td>
            </tr>
            <tr>
                <td>
                    <label for="session-end-input">Session End:</label>
                </td>
                <td>
                    <input type="date" id="session-end-input" name="session-end">
                </td>
            </tr>
        </table>
        
        <h2> Classes </h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Level</th>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Instructor 1</th>
                    <th>Instructor 2</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="input-table-body">
            </tbody>
        </table>
        
        <div id="add-rows-section">
            <button type="button" id="add-row-btn">Add Row(s)</button>
            <input type="number" id="num-rows-field" value=5>
        </div>

        <input type="hidden" name="action" value="<?php echo esc_attr( $submit_hook ); ?>">
        <?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
        <?php submit_button('Create Session', 'primary', 'new_session_submit' ); ?>
    </form>
</div>