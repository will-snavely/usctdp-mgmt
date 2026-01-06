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

            $query = new Usctdp_Mgmt_Registration_Query();
            $registration_id = $query->add_item([
                'activity_id'    => $class->ID,
                'student_id'     => $student->ID,
                'starting_level' => $student_level,
                'balance'        => 0,
                'notes'          => ''
            ]);

            $i++;
            $result[] = [
                "id" => $registration_id
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
