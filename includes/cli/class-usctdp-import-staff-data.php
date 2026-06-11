<?php

class Usctdp_Import_Staff_Data
{
    private $image_map;

    public function __construct()
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $this->image_map = [];
    }

    private function get_or_import_image($local_file, $external_id)
    {
        global $wpdb;

        $existing_attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = '_external_source_id' 
            AND meta_value = %s 
            LIMIT 1",
            $external_id
        ));

        if ($existing_attachment) {
            return $existing_attachment;
        }

        $file_array = array(
            'name' => basename($local_file),
            'tmp_name' => $local_file
        );
        $id = media_handle_sideload($file_array, 0);
        if (is_wp_error($id)) {
            return false;
        }

        if ($external_id) {
            update_post_meta($id, '_external_source_id', $external_id);
        }
        return $id;
    }

    private function import_staff($data)
    {
        foreach ($data as $staff) {
            $name = $staff['name'];
            $staff_id = 0;
            $query = new Usctdp_Mgmt_Staff_Query([
                "title" => $name,
                "number" => 1
            ]);
            if (!empty($query->items)) {
                $staff_id = $query->items[0]->id;
                WP_CLI::log("Staff member '$name' already exists (id=$staff_id)");
                return;
            }

            $name_parts = explode(" ", $name);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1];
            $uname = strtolower($first_name[0] . $last_name);
            $userdata = array(
                'user_login' => $uname,
                'user_pass' => bin2hex(random_bytes(16)),
                'user_email' => $uname . '@example.com',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $name,
                'role' => 'Administrator'
            );
            $user = get_user_by('login', $uname);
            if ($user) {
                $user_id = $user->ID;
            } else {
                $user_id = wp_insert_user($userdata);
            }
            if (is_wp_error($user_id)) {
                WP_CLI::error($user_id->get_error_message());
                return;
            }

            $search_term = Usctdp_Mgmt_Model::append_token_suffix($name);
            $staff_id = $query->add_item([
                "title" => $name,
                "image_id" => $this->image_map[$staff["image_id"]],
                "slug" => $staff["slug"],
                "display_order" => intval($staff["order"]),
                "wp_user_id" => $user_id,
                "first_name" => $first_name,
                "last_name" => $last_name, 
                "search_term" => $search_term,
                "biography" => implode("\n\n", $staff["bio"]),
                "quote" => $staff["quote"],
                "roles" => json_encode($staff['titles']),
                "faq" => json_encode($staff['faq'])
            ]);
            if ($staff_id == 0) {
                WP_CLI::error(sprintf('Failed to add staff member: %s', $name));
                return;
            }
            WP_CLI::log("Staff member '$name' added successfully (id=$staff_id)");
        }
    }

    public function import($file_path)
    {
        if (!file_exists($file_path)) {
            WP_CLI::error(sprintf('File not found: %s', $file_path));
            return;
        }

        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            WP_CLI::error(sprintf('Could not read file: %s', $file_path));
            return;
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error(sprintf('Error decoding JSON from file %s: %s', $file_path, json_last_error_msg()));
            return;
        }

        $image_ids = [];
        foreach ($data as $staff) {
            $image_ids[] = $staff["image_id"];
        }

        $idx = 1;
        $url_pref = 'https://docs.google.com/uc?export=download&id=';
        $this->image_map = [];
        foreach ($image_ids as $image_id) {
            $url = $url_pref . $image_id;
            $path = "/tmp/staff-$idx.webp";

            $curl_cmd = "curl -L '$url' -o $path";
            WP_CLI::log($curl_cmd);
            shell_exec($curl_cmd);

            $attachment_id = $this->get_or_import_image($path, $image_id);
            $this->image_map[$image_id] = $attachment_id;
            $idx += 1;
        }

        WP_CLI::log('Importing staff...');
        $this->import_staff($data);
    }
}
