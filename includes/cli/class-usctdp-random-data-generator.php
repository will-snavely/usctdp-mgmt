<?php

class Usctdp_Random_Data_Generator
{
    // @formatter:off
    private $first_names = [
        "James", "Mary", "Robert", "Patricia", "John", "Jennifer", "Michael", "Linda",
        "David", "Elizabeth", "William", "Barbara", "Richard", "Susan", "Joseph", "Jessica",
        "Thomas", "Sarah", "Charles", "Karen", "Christopher", "Nancy", "Daniel", "Lisa",
        "Matthew", "Betty", "Anthony", "Margaret", "Mark", "Sandra", "Donald", "Ashley",
        "Steven", "Kimberly", "Paul", "Dorothy", "Andrew", "Emily", "Joshua", "Donna"
    ]; 

    private $last_names = [
        "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson",
        "Thomas", "Taylor", "Moore", "Jackson", "Martin", "Lee", "Perez", "Thompson",
        "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson", "Walker",
        "Young", "Allen", "King", "Wright", "Scott", "Torres", "Nguyen", "Hill",
        "Flores", "Green", "Adams", "Nelson", "Baker", "Hall", "Rivera", "Campbell",
        "Mitchell", "Carter", "Roberts", "Gomez", "Phillips", "Evans", "Turner", "Diaz"
    ];
    // @formatter:on
    private $sessions = [
        [
            "session_name" => "Session I",
            "start_date" => "20260825",
            "end_date" => "20261005",
        ],
        [
            "session_name" => "Session II",
            "start_date" => "20261013",
            "end_date" => "20261221",
        ]
    ];
    private $classes = [
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "mon",
            "start_time" => "3:30 PM",
            "end_time" => "4:15 PM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "tues",
            "start_time" => "10:00 AM",
            "end_time" => "10:45 AM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "tues",
            "start_time" => "1:00 PM",
            "end_time" => "1:45 PM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "fri",
            "start_time" => "3:30 PM",
            "end_time" => "4:15 PM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "fri",
            "start_time" => "6:00 PM",
            "end_time" => "6:45 PM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "sun",
            "start_time" => "11:00 AM",
            "end_time" => "11:45 AM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ],
        [
            "class_type" => "tiny-tots",
            "day_of_week" => "sun",
            "start_time" => "12:00 PM",
            "end_time" => "12:45 PM",
            "level" => "1",
            "capacity" => 10,
            "one_day_price" => 10.00,
            "two_day_price" => 20.00,
        ]
    ];

    private function generate_staff($count)
    {
        $seen = [];
        $result = [];
        $i = 0;
        while ($i < $count) {
            $first_name = $this->first_names[array_rand($this->first_names)];
            $last_name = $this->last_names[array_rand($this->last_names)];
            if (isset($seen[$first_name . " " . $last_name])) {
                continue;
            }
            $post_id = wp_insert_post([
                'post_title'    => $first_name . " " . $last_name,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-staff'
            ]);
            update_field('first_name', $first_name, $post_id);
            update_field('last_name', $last_name, $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $seen[$first_name . " " . $last_name] = true;
            $i++;
            $result[] = [
                "id" => $post_id
            ];
        }
        return $result;
    }

    private function generate_families($count, $max_students)
    {
        $result = [];
        $seen = [];
        $i = 0;
        while ($i < $count) {
            $last_name = $this->last_names[array_rand($this->last_names)];
            if (isset($seen[$last_name])) {
                continue;
            }

            // Define the user data array
            $userdata = array(
                'user_login' => $last_name,
                'user_pass' => bin2hex(random_bytes(16)),
                'user_email' => $last_name . '@example.com',
                'first_name' => 'Family',
                'last_name' => $last_name,
                'display_name' => $last_name,
                'role' => 'subscriber'
            );

            $user_id = wp_insert_user($userdata);

            $family_id = wp_insert_post([
                'post_title'    => "$last_name Family",
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-family'
            ]);
            update_field('last_name', $last_name, $family_id);
            update_field('phone_number', "555-555-5555", $family_id);
            update_field('address', "123 Main St", $family_id);
            update_field('city', "Springfield", $family_id);
            update_field('state', "IL", $family_id);
            update_field('zip', "62704", $family_id);
            update_field('assigned_user', $user_id, $family_id);
            wp_set_post_terms($family_id, ["test-data"], 'post_tag', false);
            $seen[$last_name] = true;
            $i++;
            $num_students = rand(1, $max_students);
            $result[] = [
                "id" => $family_id,
                "students" => $this->generate_students($num_students, $family_id, $last_name)
            ];
        }
        return $result;
    }

    private function generate_students($count, $family_id, $last_name)
    {
        $result = [];
        $seen = [];
        $i = 0;
        while ($i < $count) {
            $first_name = $this->first_names[array_rand($this->first_names)];
            if (isset($seen[$first_name])) {
                continue;
            }
            $name = $first_name . " " . $last_name;
            $post_id = wp_insert_post([
                'post_title'    => $name,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-student'
            ]);
            update_field('first_name', $first_name, $post_id);
            update_field('last_name', $last_name, $post_id);
            $year = mt_rand(2000, 2020);
            $month = mt_rand(1, 12);
            $day = mt_rand(1, date('t', mktime(0, 0, 0, $month, 1, $year)));
            $random_birth_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            update_field('birth_date', $random_birth_date, $post_id);
            update_field('family', $family_id, $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $seen[$first_name] = true;
            $i++;
            $result[] = [
                "id" => $post_id,
                "name" => $name
            ];
        }
        return $result;
    }

    private function generate_sessions()
    {
        $result = [];
        foreach ($this->sessions as $session) {
            $start_date = DateTime::createFromFormat("Ymd", $session['start_date']);
            $end_date = DateTime::createFromFormat("Ymd", $session['end_date']);
            $title = Usctdp_Mgmt_Session::create_session_title(
                $session['session_name'],
                $start_date,
                $end_date
            );
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-session'
            ]);
            foreach ($session as $key => $value) {
                update_field($key, $value, $post_id);
            }
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $result[] = [
                "id" => $post_id,
                "classes" => $this->generate_classes(
                    $post_id,
                    $start_date,
                    $end_date
                )
            ];
        }
        return $result;
    }

    private function get_dow_value($dow_label)
    {
        switch ($dow_label) {
            case 'mon':
                return 1;
            case 'tues':
                return 2;
            case 'wed':
                return 3;
            case 'thurs':
                return 4;
            case 'fri':
                return 5;
            case 'sat':
                return 6;
            case 'sun':
                return 7;
        }
    }

    private function generate_classes($session, $start_date, $end_date)
    {
        $result = [];
        foreach ($this->classes as $class) {
            $dow_label = Usctdp_Mgmt_Class::dow_value_to_label($class['day_of_week']);
            $title = "{$dow_label} at {$class['start_time']}";
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-class'
            ]);
            foreach ($class as $key => $value) {
                update_field($key, $value, $post_id);
            }
            update_field("parent_session", $session, $post_id);
            $dow_value = $this->get_dow_value($class['day_of_week']);
            $class_dates = [];

            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));
            foreach ($period as $date) {
                if ((int)$date->format('N') === $dow_value) {
                    $class_dates[] = $date->format('Y-m-d');
                }
            }
            update_field("date_list", implode(',', $class_dates), $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $result[] = [
                "id" => $post_id,
                "cap" => $class['capacity'],
                "title" => $title
            ];
        }
        return $result;
    }

    private function generate_registrations($count, $sessions, $families)
    {
        $result = [];
        $enrolled = [];
        $i = 0;
        while ($i < $count) {
            $session = $sessions[array_rand($sessions)];
            $class = $session['classes'][array_rand($session['classes'])];
            $family = $families[array_rand($families)];
            $student = $family['students'][array_rand($family['students'])];

            if (
                isset($enrolled[$class['id']]) && (
                    in_array($student['id'], $enrolled[$class['id']]["roster"]) ||
                    count($enrolled[$class['id']]["roster"]) >= $class['cap']
                )
            ) {
                continue;
            }

            $post_id = wp_insert_post([
                'post_title'    => "{$student['name']} - {$class['title']}",
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-registration'
            ]);
            update_field("student", $student['id'], $post_id);
            update_field("class", $class['id'], $post_id);
            update_field("created", date('Y-m-d H:i:s'), $post_id);
            update_field("outstanding_balance", 0, $post_id);
            update_field("payment_method", "check", $post_id);
            update_field("payment_date", date('Y-m-d H:i:s'), $post_id);
            wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            $i++;
            $result[] = [
                "id" => $post_id
            ];
        }
        return $result;
    }

    public function generate_random(
        $num_staff,
        $num_families,
        $max_students_per_family,
        $num_registrations
    ) {
        WP_CLI::log('Generating staff...');
        $staff = $this->generate_staff($num_staff);
        WP_CLI::log('Generating sessions and classes...');
        $sessions = $this->generate_sessions();
        WP_CLI::log('Generating families and students...');
        $families = $this->generate_families($num_families, $max_students_per_family);
        WP_CLI::log('Generating registrations...');
        $this->generate_registrations($num_registrations, $sessions, $families);
    }
}
