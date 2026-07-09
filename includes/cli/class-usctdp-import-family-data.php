<?php

class Usctdp_Import_Family_Data
{
    public function __construct()
    {
    }

    private function import_student($student, $family_id, $family, $mock)
    {
        $query = new Usctdp_Mgmt_Student_Query([]);
        $birth_date_str = '';
        if ($student["birthday"]) {
            $birth_date = DateTime::createFromFormat('m/d/y H:i:s', $student["birthday"]);
            if($mock) {
                $birth_date->setDate($birth_date->format('Y'), 1,1);
            }
            $birth_date_str = $birth_date->format('Y-m-d');
        }
        $query->add_item([
            "title" => $student["name"] . ' ' . $family["last"],
            "family_id" => $family_id,
            "first" => $student["name"],
            "last" => $family["last"],
            "birth_date" => $birth_date_str,
            "level" => 1,
        ]);
    }

    private function import_family($family, $mock)
    {
        $query = new Usctdp_Mgmt_Family_Query([
            "title" => $family["id"]
        ]);

        if (empty($query->items)) {
            $last_name = $family["last"];
            $user_login = str_replace(' ', '', strtolower($family["id"]));

            $email = $family["email1"] ?? '';
            if(!$email) {
                $email = $user_login . '@example.com';
            }

            $userdata = array(
                'user_login' => $user_login,
                'user_pass' => bin2hex(random_bytes(32)),
                'user_email' => $mock ? $user_login . '@example.com' : $email,
                'first_name' => 'Family',
                'last_name' => $last_name,
                'display_name' => $family["id"],
                'role' => 'subscriber'
            );
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                WP_CLI::log($family["id"] . " - " . $email);
                WP_CLI::log($user_id->get_error_message());
                return;
            }
            $notes = "";
            if (isset($family["notes"])) {
                $notes = $family["notes"];
            }

            $emails = [];
            if ($mock) {
                $emails[] = "test@example.com";
            } else {
                if (isset($family["email1"]) && !empty($family["email1"])) {
                    $emails[] = $family["email1"];
                }
                if (isset($family["email2"]) && !empty($family["email2"])) {
                    $emails[] = $family["email2"];
                }
            }
            $emails_json = json_encode($emails);

            $phone_numbers = [];
            if ($mock) {
                $phone_numbers[] = "123-456-7890";
            } else {
                if (isset($family["phone"])) {
                    $phone_numbers = $family["phone"];
                }
            }
            $phone_numbers_json = json_encode($phone_numbers);

            $family_id = $query->add_item([
                "title" => $family["id"],
                "search_term" => Usctdp_Mgmt_Model::append_token_suffix($family["id"]),
                "last" => $family["last"],
                "address" => $mock ? "123 Fake St." : $family["addr_street"],
                "city" => $mock ? "Fake City" : $family["addr_city"],
                "state" => $mock ? "ZZ" : $family["addr_state"],
                "zip" => $mock ? "12345" : $family["addr_zip"],
                "phone_numbers" => $phone_numbers_json,
                "emails" => $emails_json,
                "user_id" => $user_id,
                "notes" => $notes,
            ]);
            foreach ($family["children"] as $member) {
                $this->import_student($member, $family_id, $family, $mock);
            }
        }
    }

    private function import_families($data, $mock)
    {
        foreach ($data as $family) {
            $this->import_family($family, $mock);
        }
    }

    public function import($file_path, $mock)
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
        $this->import_families($data, $mock);
    }
}
