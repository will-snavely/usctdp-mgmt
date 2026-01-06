<?php

class Usctdp_Import_Clinic_Data
{
    private $sessions;
    private $clinics;
    private $pricing;
    private $sessions_by_category;

    public function __construct()
    {
        $this->sessions = [];
        $this->clinics = [];
        $this->pricing = [];
        $this->sessions_by_category = [];
    }

    private function import_clinic_sessions($data)
    {
        foreach ($data["clinic_sessions"] as $session) {
            $start_date = new DateTime($session['start_date']);
            $end_date = new DateTime($session['end_date']);
            $title = Usctdp_Mgmt_Session::create_title(
                $session['name'],
                $session['length_weeks'],
                $start_date,
                $end_date
            );
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-session',
            ]);
            update_field('name', $session['name'], $post_id);
            update_field('start_date', $start_date->format('Y-m-d'), $post_id);
            update_field('end_date', $end_date->format('Y-m-d'), $post_id);
            update_field('length_weeks', $session['length_weeks'], $post_id);
            update_field('category', $session['category'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            if (!isset($this->sessions_by_category[$session['category']])) {
                $this->sessions_by_category[$session['category']] = [];
            }
            $this->sessions_by_category[$session['category']][] = $post_id;
            $this->sessions[$session['name']] = $post_id;
        }
    }

    private function import_clinics($data)
    {
        foreach ($data["clinics"] as $clinic) {
            $title = Usctdp_Mgmt_Clinic::create_title($clinic['name']);
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-clinic',
            ]);
            update_field('name', $clinic['name'], $post_id);
            update_field('age_range', $clinic['age_range'], $post_id);
            update_field('age_group', $clinic['age_group'], $post_id);
            update_field('session_category', $clinic['session_category'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $this->clinics[$clinic['name']] = $post_id;
        }
    }

    private function import_clinic_prices($data)
    {
        foreach ($data["clinic_pricing"] as $pricing) {
            $session_id = $this->sessions[$pricing['session']];
            $clinic_id = $this->clinics[$pricing['clinic']];
            $session_name = get_field('name', $session_id);
            $session_duration = get_field('length_weeks', $session_id);
            $clinic_name = get_field('name', $clinic_id);
            $title = Usctdp_Mgmt_Clinic_Prices::create_title($session_name, $session_duration, $clinic_name);
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-clinic-prices',
            ]);

            update_field('session', $session_id, $post_id);
            update_field('clinic', $clinic_id, $post_id);
            update_field('one_day_price', $pricing['1_day_price'], $post_id);
            update_field('two_day_price', $pricing['2_day_price'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            if (!isset($this->pricing[$clinic_id])) {
                $this->pricing[$clinic_id] = [];
            }
            $this->pricing[$clinic_id][$session_id] = $post_id;
        }
    }

    private function import_clinic_classes($data)
    {
        foreach ($data["clinic_classes"] as $class) {
            $clinic_id = $this->clinics[$class['clinic']];
            $clinic_name = get_field('name', $clinic_id);
            $clinic_category = get_field('session_category', $clinic_id);
            $dow = $class['day'];
            $start_time = new DateTime($class['start_time']);
            $end_time = new DateTime($class['end_time']);
            $sessions = $this->sessions_by_category[$clinic_category];

            foreach ($sessions as $session_id) {
                $session_duration = get_field('length_weeks', $session_id);
                $title = Usctdp_Mgmt_Class::create_title($clinic_name, $dow, $start_time, $session_duration);
                $post_id = wp_insert_post([
                    'post_title'    => $title,
                    'post_status'   => 'publish',
                    'post_type'     => 'usctdp-class',
                ]);

                update_field('session', $session_id, $post_id);
                update_field('clinic', $clinic_id, $post_id);
                update_field('day_of_week', $dow, $post_id);
                update_field('level', $class['level'], $post_id);
                update_field('start_time', $start_time->format('H:i:s'), $post_id);
                update_field('capacity', $class['capacity'], $post_id);
                update_field('end_time', $end_time->format('H:i:s'), $post_id);
                wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            }
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

        WP_CLI::log('Importing sessions...');
        $this->import_clinic_sessions($data);
        WP_CLI::log('Importing clinics...');
        $this->import_clinics($data);
        WP_CLI::log('Importing clinic pricing...');
        $this->import_clinic_prices($data);
        WP_CLI::log('Importing classes...');
        $this->import_clinic_classes($data);
    }
}
