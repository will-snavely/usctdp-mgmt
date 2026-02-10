<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Row extends Row
{
    private function age_from_birthdate($birthdate)
    {
        $today = new DateTime('today');
        $age = $birthdate->diff($today)->y;
        return $age;
    }

    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->family_id = (int) $this->family_id;
        $this->first = (string) $this->first;
        $this->last = (string) $this->last;
        $this->title = (string) $this->title;
        $this->search_term = (string) $this->search_term;
        if ($this->birth_date == "0000-00-00") {
            $this->birth_date = null;
            $this->age = null;
        } else {
            $this->birth_date = DateTime::createFromFormat('Y-m-d', $this->birth_date);
            $this->age = $this->age_from_birthdate($this->birth_date);
        }

        $this->level = (string) $this->level;
    }
}
