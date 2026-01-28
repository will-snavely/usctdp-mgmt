<?php

class Usctdp_Import_Family_Data
{
    public function __construct() {}

    private function import_student($student, $family_id, $family)
    {
        $query = new Usctdp_Mgmt_Student_Query([]);
        $birth_date_str = '';
        if ($student["birth_date"]) {
            $birth_date = DateTime::createFromFormat('m/d/y H:i:s', $student["birth_date"]);
            $birth_date_str = $birth_date->format('Y-m-d');
        }
        $query->add_item([
            "title" => $student["first"]  . ' ' . $family["last"],
            "family_id" => $family_id,
            "first" => $student["first"],
            "last" => $family["last"],
            "birth_date" => $birth_date_str,
            "level" => 1,
        ]);
    }

    private function import_family($family)
    {
        $query = new Usctdp_Mgmt_Family_Query([
            "title" => $family["id"]
        ]);
        if (empty($query->items)) {
            $family_id = $query->add_item([
                "title" => $family["id"],
                "last" => $family["last"],
                "address" => $family["address"],
                "city" => $family["city"],
                "state" => $family["state"],
                "zip" => $family["zip"],
                "phone_numbers" => json_encode($family["phone"]),
                "email" => $family["email"],
                "notes" => $family["notes"],
            ]);
            foreach ($family["members"] as $member) {
                $this->import_student($member, $family_id, $family);
            }
        }
    }

    private function import_families($data)
    {
        foreach ($data["families"] as $family) {
            $this->import_family($family);
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

        $this->import_families($data);
    }
}
