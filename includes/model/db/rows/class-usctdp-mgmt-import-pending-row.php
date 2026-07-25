<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Import_Pending_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->user_id = (int) $this->user_id;
        $this->external_id = (string) $this->external_id;
        $this->matched_existing_user = (bool) $this->matched_existing_user;
        $this->last = (string) $this->last;
        $this->address = (string) $this->address;
        $this->city = (string) $this->city;
        $this->state = (string) $this->state;
        $this->zip = (string) $this->zip;

        $phone_result = [];
        if (!empty($this->phone_numbers)) {
            $json = json_decode($this->phone_numbers);
            if (!empty($json)) {
                $phone_result = $json;
            }
        }
        $this->phone_numbers = $phone_result;

        $email_result = [];
        if (!empty($this->emails)) {
            $json = json_decode($this->emails);
            if (!empty($json)) {
                $email_result = $json;
            }
        }
        $this->emails = $email_result;

        $student_result = [];
        if (!empty($this->students)) {
            $json = json_decode($this->students);
            if (!empty($json)) {
                $student_result = $json;
            }
        }
        $this->students = $student_result;

        $this->notes = (string) $this->notes;
    }
}
