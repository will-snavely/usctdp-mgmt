<?php

class Usctdp_Clean
{
    public function clean($target)
    {
        $post_types = [];
        $remove_users = false;
        $remove_registrations = false;
        if ($target == "all") {
            $post_types = [
                'usctdp-session',
                'usctdp-staff',
                'usctdp-class',
                'usctdp-family',
                'usctdp-student',
                'usctdp-clinic',
                'usctdp-clinic-prices'
            ];
            $remove_users = true;
            $remove_registrations = true;
        } else if ($target == "people") {
            $post_types = [
                'usctdp-family',
                'usctdp-student',
                'usctdp-staff',
            ];
            $remove_registrations = true;
            $remove_users = true;
        } else if ($target == "classes") {
            $post_types = [
                'usctdp-session',
                'usctdp-class',
                'usctdp-clinic',
                'usctdp-clinic-prices'
            ];
            $remove_registrations = true;
            $remove_users = false;
        } else if ($target == "registrations") {
            $post_types = [
                'usctdp-registration'
            ];
            $remove_registrations = true;
            $remove_users = false;
        }
        foreach ($post_types as $post_type) {
            WP_CLI::log('Removing ' . $post_type . ' entities...');
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'tag' => 'test-data'
            ]);
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }

        if ($remove_users) {
            WP_CLI::log('Removing users...');
            $users = get_users([
                "role" => "subscriber"
            ]);
            foreach ($users as $user) {
                wp_delete_user($user->ID);
            }
        }

        if ($remove_registrations) {
            WP_CLI::log('Removing registrations...');
            $table = new Usctdp_Mgmt_Registration_Table();
            $table->delete_all();
        }
    }
}
