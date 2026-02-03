<?php

class Usctdp_Random_People_Generator
{
    // @formatter:off
    private $first_names = [
        "James", "Mary", "Robert", "Patricia", "John", "Jennifer", "Michael", "Linda",
        "David", "Elizabeth", "William", "Barbara", "Richard", "Susan", "Joseph", "Jessica",
        "Thomas", "Sarah", "Charles", "Karen", "Christopher", "Nancy", "Daniel", "Lisa",
        "Matthew", "Betty", "Anthony", "Dorothy", "Mark", "Sandra", "Donald", "Ashley",
        "Steven", "Kimberly", "Paul", "Donna", "Andrew", "Carol", "Joshua", "Michelle",
        "Kenneth", "Emily", "Kevin", "Helen", "Brian", "Amanda", "George", "Margaret",
        "Timothy", "Melissa", "Ronald", "Laura", "Edward", "Sharon", "Jason", "Deborah",
        "Jeffrey", "Rebecca", "Ryan", "Stephanie", "Jacob", "Cynthia", "Gary", "Kathleen",
        "Nicholas", "Shirley", "Eric", "Amy", "Jonathan", "Anna", "Stephen", "Angela",
        "Larry", "Ruth", "Justin", "Brenda", "Scott", "Pamela", "Brandon", "Nicole",
        "Benjamin", "Katherine", "Samuel", "Christine", "Gregory", "Catherine", "Frank", "Virginia",
        "Raymond", "Debra", "Patrick", "Janet", "Alexander", "Carolyn", "Jack", "Maria",
        "Dennis", "Heather", "Jerry", "Diane", "Tyler", "Julie", "Aaron", "Joyce",
        "Jose", "Victoria", "Henry", "Rachel", "Douglas", "Kelly", "Adam", "Christina",
        "Peter", "Martha", "Zachary", "Lauren", "Walter", "Frances", "Nathan", "Ann",
        "Harold", "Alice", "Kyle", "Judith", "Carl", "Evelyn", "Arthur", "Megan",
        "Gerald", "Cheryl", "Roger", "Joan", "Keith", "Hannah", "Jeremy", "Andrea",
        "Terry", "Olivia", "Lawrence", "Jacqueline", "Sean", "Emma", "Christian", "Grace"
    ];

    private $last_names = [
        "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
        "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson",
        "Thomas", "Taylor", "Moore", "Jackson", "Martin", "Lee", "Perez", "Thompson",
        "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson", "Walker",
        "Young", "Allen", "King", "Wright", "Scott", "Torres", "Nguyen", "Hill",
        "Flores", "Green", "Adams", "Nelson", "Baker", "Hall", "Rivera", "Campbell",
        "Mitchell", "Carter", "Roberts", "Gomez", "Phillips", "Evans", "Turner", "Diaz",
        "Parker", "Edwards", "Collins", "Reyes", "Stewart", "Morris", "Morales", "Murphy",
        "Cook", "Rogers", "Gutierrez", "Ortiz", "Morgan", "Cooper", "Peterson", "Bailey",
        "Reed", "Kelly", "Howard", "Ramos", "Kim", "Cox", "Ward", "Richardson",
        "Watson", "Brooks", "Chavez", "Wood", "James", "Bennett", "Gray", "Mendoza",
        "Ruiz", "Hughes", "Price", "Alvarez", "Castillo", "Sanders", "Patel", "Myers",
        "Long", "Ross", "Foster", "Jimenez", "Powell", "Jenkins", "Perry", "Russell",
        "Sullivan", "Bell", "Coleman", "Butler", "Henderson", "Barnes", "Fisher", "Graham"
    ];

    private $street_names = [
        "Main", "Oak", "Pine", "Maple", "Elm", "Cedar", "Park", "Church", "High", "Washington"
    ];

    private $street_types = [
        "St", "Ave", "Blvd", "Ln", "Dr", "Ct", "Way", "Rd", "Pl", "Ter"
    ];
    // @formatter:on

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
                'post_title' => $first_name . " " . $last_name,
                'post_status' => 'publish',
                'post_type' => 'usctdp-staff'
            ]);
            update_field('field_usctdp_staff_first_name', $first_name, $post_id);
            update_field('field_usctdp_staff_last_name', $last_name, $post_id);
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
            $random_last_four_digits = sprintf('%04d', rand(0, 9999));
            $phone_number = "555-555-" . $random_last_four_digits;
            $family_title = $last_name . ' ' . $random_last_four_digits;
            if (isset($seen[$family_title])) {
                continue;
            }

            // Define the user data array
            $userdata = array(
                'user_login' => $last_name . $random_last_four_digits,
                'user_pass' => bin2hex(random_bytes(16)),
                'user_email' => $last_name . $random_last_four_digits . '@example.com',
                'first_name' => 'Family',
                'last_name' => $last_name,
                'display_name' => $last_name . ' ' . $random_last_four_digits,
                'role' => 'subscriber'
            );
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                WP_CLI::error($user_id->get_error_message());
                return;
            }
            $street_numbers = rand(100, 9999);
            $random_street_name = $this->street_names[array_rand($this->street_names)];
            $random_street_type = $this->street_types[array_rand($this->street_types)];
            $random_zip = sprintf('15%03d', rand(0, 999));
            $query = new Usctdp_Mgmt_Family_Query([]);
            $family_id = $query->add_item([
                "title" => $family_title,
                "last" => $last_name,
                "address" => $street_numbers . " " . $random_street_name . " " . $random_street_type,
                "city" => "Pittsburgh",
                "state" => "PA",
                "zip" => $random_zip,
                "phone_numbers" => json_encode([$phone_number]),
                "email" => $last_name . "@example.com",
                "user_id" => $user_id,
                "notes" => "",
            ]);

            $seen[$family_title] = true;
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
            $query = new Usctdp_Mgmt_Student_Query([]);
            $year = mt_rand(1980, 2020);
            $month = mt_rand(1, 12);
            $day = mt_rand(1, date('t', mktime(0, 0, 0, $month, 1, $year)));
            $random_birth_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $student_id = $query->add_item([
                "title" => $first_name . ' ' . $last_name,
                "family_id" => $family_id,
                "first" => $first_name,
                "last" => $last_name,
                "birth_date" => $random_birth_date,
                "level" => rand(1, 5),
            ]);

            $i++;
            $result[] = [
                "id" => $student_id,
                "name" => $name
            ];
        }
        return $result;
    }

    public function generate_random(
        $num_staff,
        $num_families,
        $max_students_per_family
    ) {
        WP_CLI::log('Generating staff...');
        $staff = $this->generate_staff($num_staff);
        WP_CLI::log('Generating families and students...');
        $families = $this->generate_families($num_families, $max_students_per_family);
    }
}
