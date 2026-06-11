<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Staff_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->slug = (string) $this->slug;
        $this->wp_user_id = (int) $this->wp_user_id;
        $this->image_id = (int) $this->image_id;
        $this->display_order = (int) $this->display_order;
        $this->first_name = (string) $this->first_name;
        $this->last_name = (string) $this->last_name;
        $this->title = (string) $this->title;
        $this->search_term = (string) $this->search_term;
        $this->biography = (string) $this->biography;
        $this->quote = (string) $this->quote;
        $this->roles = json_decode($this->roles);
        $this->faq = json_decode($this->faq);
    }
}
