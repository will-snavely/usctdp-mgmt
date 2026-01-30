<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

enum Session_Category: int
{
    case Junior_Beginner = 1;
    case Junior_Advanced = 2;
    case Adult = 3;
    case Cardio = 4;
}

class Usctdp_Mgmt_Session_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->name = (string) $this->name;
        $this->title = (string) str_replace(Usctdp_Mgmt_Model::$token_suffix, '', $this->title);
        $this->start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $this->end_date = DateTime::createFromFormat('Y-m-d', $this->end_date);
        $this->num_weeks = (int) $this->num_weeks;
        $this->category = Session_Category::from($this->category);
        $this->notes = (string) $this->notes;
    }
}
