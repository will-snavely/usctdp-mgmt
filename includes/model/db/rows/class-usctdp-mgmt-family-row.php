<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->user_id = (int) $this->user_id;
        $this->title = (string) $this->title;
        $this->search_term = (string) $this->search_term;
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

        $this->notes = (string) $this->notes;
        $this->last_modified = DateTime::createFromFormat('Y-m-d H:i:s', $this->last_modified);
        $this->last_modified_by = (int) $this->last_modified_by;
    }
}
