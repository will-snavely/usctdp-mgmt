<?php

class Usctdp_Random_Registration_Generator
{
    private $student_count;
    private $class_count;
    public function __construct()
    {
        global $wpdb;
        $this->student_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usctdp_student");
        $this->class_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usctdp_clinic_class");
    }

    private function random_student()
    {
        global $wpdb;
        $offset = rand(0, $this->student_count - 1);
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}usctdp_student LIMIT 1 OFFSET $offset");
    }

    private function random_class()
    {
        global $wpdb;
        $offset = rand(0, $this->class_count - 1);
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}usctdp_clinic_class LIMIT 1 OFFSET $offset");
    }

    private function generate_registrations($count, $chance_unpaid)
    {
        $result = [];
        $enrolled = [];
        $i = 0;

        while ($i < $count) {
            $class = $this->random_class();
            $student = $this->random_student();
            $capacity = $class->capacity;

            if (!isset($enrolled[$class->id])) {
                $enrolled[$class->id] = [
                    "roster" => []
                ];
            }
            $roster = $enrolled[$class->id]["roster"];
            if (in_array($student->id, $roster) || count($roster) >= $capacity) {
                continue;
            }
            $enrolled[$class->id]["roster"][] = $student->id;
            $student_level = $student->level;
            $balance = 0;
            if (rand(1, 100) <= $chance_unpaid) {
                $balance = rand(20, 100);
            }
            $query = new Usctdp_Mgmt_Registration_Query();
            $registration_id = $query->add_item([
                'activity_id'    => $class->id,
                'student_id'     => $student->id,
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
        WP_CLI::log('Generating registrations...');
        $this->generate_registrations($num_registrations, $chance_unpaid);
    }
}
