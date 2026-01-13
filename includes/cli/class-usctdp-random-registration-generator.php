<?php

class Usctdp_Random_Registration_Generator
{
    private function get_class_pricing($clinic_id, $session_id)
    {
        $price_query = get_posts([
            'post_type'      => 'usctdp-clinic-prices',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key' => 'clinic',
                    'value' => $clinic_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ],
                [
                    'key' => 'session',
                    'value' => $session_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ]
            ]
        ]);
        if (!empty($price_query)) {
            $price = $price_query[0];
            return [
                'one_day_price' => get_field('one_day_price', $price->ID),
                'two_day_price' => get_field('two_day_price', $price->ID)
            ];
        }

        return null;
    }

    private function generate_registrations($count, $students, $classes, $chance_unpaid)
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
            $balance = 0;
            if (rand(1, 100) <= $chance_unpaid) {
                $clinic = get_field("clinic", $class->ID);
                $session = get_field("session", $class->ID);
                $pricing = $this->get_class_pricing($clinic->ID, $session->ID);
                $balance = $pricing['one_day_price'];
            }

            $query = new Usctdp_Mgmt_Registration_Query();
            $registration_id = $query->add_item([
                'activity_id'    => $class->ID,
                'student_id'     => $student->ID,
                'starting_level' => $student_level,
                'balance'        => $balance,
                'notes'          => ''
            ]);

            $i++;
            $result[] = [
                "id" => $registration_id
            ];
        }
        return $result;
    }

    public function generate_random($num_registrations, $chance_unpaid = 0)
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
        $this->generate_registrations($num_registrations, $students, $classes, $chance_unpaid);
    }
}
