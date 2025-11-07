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

<div class="wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>
    <form 
      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" 
      method="post" 
      id="usctdp-new-session-form">
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="title_prefix">Post Title Prefix</label>
                    </th>
                    <td>
                        <!-- The value of the input should be escaped for security -->
                        <input name="title_prefix" type="text" id="title_prefix" value="Generated Post" class="regular-text" required>
                        <p class="description">Example: "Test Article 1", "Test Article 2", etc.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="post_count">Number of Posts to Generate</label>
                    </th>
                    <td>
                        <input name="post_count" type="number" id="post_count" value="5" min="1" max="50" class="small-text" required>
                        <p class="description">Maximum of 50 posts per run.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <input type="hidden" name="action" value="<?php echo esc_attr( $submit_hook ); ?>">
        <?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
        <?php submit_button('Generate Posts', 'primary', 'new_session_submit' ); ?>
    </form>
</div>