<?php

class Usctdp_Import_Session_Data
{
    private $sessions;
    private $courses;
    private $pricing;

    public function __construct()
    {
        $this->sessions = [];
        $this->courses = [];
        $this->pricing = [];
    }

    private function import_sessions($data)
    {
        foreach ($data["sessions"] as $session) {
            $start_date = new DateTime($session['start_date']);
            $end_date = new DateTime($session['end_date']);
            $title = Usctdp_Mgmt_Session::create_session_title(
                $session['session_name'],
                $session['length_weeks'],
                $start_date,
                $end_date
            );
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-session',
            ]);
            update_field('session_name', $session['session_name'], $post_id);
            update_field('start_date', $start_date->format('Y-m-d'), $post_id);
            update_field('end_date', $end_date->format('Y-m-d'), $post_id);
            update_field('length_weeks', $session['length_weeks'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $this->sessions[$session['session_name']] = $post_id;
        }
    }

    private function import_courses($data)
    {
        foreach ($data["courses"] as $course) {
            $title = Usctdp_Mgmt_Course::create_course_title($course['course_name']);
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-course',
            ]);
            update_field('name', $course['course_name'], $post_id);
            update_field('short_description', $course['short_description'], $post_id);
            update_field('age_range', $course['age_range'], $post_id);
            update_field('description', $course['description'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $this->courses[$course['course_name']] = $post_id;
        }
    }

    private function import_pricing($data)
    {
        foreach ($data["pricing"] as $pricing) {
            $session_id = $this->sessions[$pricing['session']];
            $course_id = $this->courses[$pricing['course']];
            $session_name = get_field('session_name', $session_id);
            $session_duration = get_field('length_weeks', $session_id);
            $course_name = get_field('name', $course_id);
            $title = Usctdp_Mgmt_Pricing::create_pricing_title($session_name, $session_duration, $course_name);
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-pricing',
            ]);

            update_field('session', $session_id, $post_id);
            update_field('course', $course_id, $post_id);
            update_field('one_day_price', $pricing['1_day_price'], $post_id);
            update_field('two_day_price', $pricing['2_day_price'], $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            if (!isset($this->pricing[$pricing['session']])) {
                $this->pricing[$pricing['session']] = [];
            }
            $this->pricing[$pricing['session']][$pricing['course']] = $post_id;
        }
    }

    private function import_classes($data)
    {
        foreach ($this->sessions as $session_name => $session_id) {
            $session_duration = get_field('length_weeks', $session_id);
            foreach ($data["classes"] as $class) {
                $course_id = $this->courses[$class['course']];
                $course_name = get_field('name', $course_id);

                $dow = $class['day'];
                $start_time = new DateTime($class['start_time']);
                $end_time = new DateTime($class['end_time']);
                $pricing_id = $this->pricing[$session_name][$course_name];
                $one_day_price = get_field('one_day_price', $pricing_id);
                $two_day_price = get_field('two_day_price', $pricing_id);
                $title = Usctdp_Mgmt_Class::create_class_title($course_name, $dow, $start_time, $session_duration);
                $post_id = wp_insert_post([
                    'post_title'    => $title,
                    'post_status'   => 'publish',
                    'post_type'     => 'usctdp-class',
                ]);

                update_field('session', $session_id, $post_id);
                update_field('course', $course_id, $post_id);
                update_field('day_of_week', $dow, $post_id);
                update_field('level', $class['level'], $post_id);
                update_field('start_time', $start_time->format('H:i:s'), $post_id);
                update_field('capacity', $class['capacity'], $post_id);
                update_field('end_time', $end_time->format('H:i:s'), $post_id);
                update_field('one_day_price', $one_day_price, $post_id);
                update_field('two_day_price', $two_day_price, $post_id);
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
        $this->import_sessions($data);
        WP_CLI::log('Importing courses...');
        $this->import_courses($data);
        WP_CLI::log('Importing pricing...');
        $this->import_pricing($data);
        WP_CLI::log('Importing classes...');
        $this->import_classes($data);
    }
}
