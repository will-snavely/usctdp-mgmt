<?php

class Usctdp_Clean
{
    public function clean()
    {
        $post_types = [
            'usctdp-session',
            'usctdp-staff',
            'usctdp-class',
            'usctdp-family',
            'usctdp-student',
            'usctdp-registration'
        ];
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

        WP_CLI::log('Removing users...');
        $users = get_users([
            "role" => "subscriber"
        ]);
        foreach ($users as $user) {
            wp_delete_user($user->ID);
        }
    }
}
