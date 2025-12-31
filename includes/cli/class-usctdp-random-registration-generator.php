<?php

class Usctdp_Random_Registration_Generator
{
    private function generate_registrations($count, $students, $classes)
    {
        $result = [];
        $enrolled = [];
        $i = 0;

        while ($i < $count) {
            $class = $classes[array_rand($classes)];
            $capacity = get_field("capacity", $class->ID);
            $student = $students[array_rand($students)];

            if (!isset($enrolled[$class->ID])) {
                $enrolled[$class->ID] = [
                    "roster" => []
                ];
            }
            $roster = $enrolled[$class->ID]["roster"];
            if (in_array($student->ID, $roster) || count($roster) >= $capacity) {
                continue;
            }
            $enrolled[$class->ID]["roster"][] = $student->ID;
            $student_level = get_field("level", $student->ID);

            $post_id = wp_insert_post([
                'post_title'    => "{$student->post_title} - {$class->post_title}",
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-registration'
            ]);
            update_field("student", $student->ID, $post_id);
            update_field("class", $class->ID, $post_id);
            update_field("created", date('Y-m-d H:i:s'), $post_id);
            update_field("balance", 0, $post_id);
            update_field("starting_level", $student_level, $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $i++;
            $result[] = [
                "id" => $post_id
            ];
        }
        return $result;
    }

    public function generate_random($num_registrations)
    {
        $students = get_posts([
            'post_type' => 'usctdp-student',
            'posts_per_page' => -1
        ]);
        $classes = get_posts([
            'post_type' => 'usctdp-class',
            'posts_per_page' => -1
        ]);
        WP_CLI::log('Generating registrations...');
        $this->generate_registrations($num_registrations, $students, $classes);
    }
}
