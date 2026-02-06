<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Row extends Row{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->woocommerce_id = (int) $this->woocommerce_id;
        $this->title = $this->title;
        $this->search_term = $this->search_term; 
        $this->session_category = Usctdp_Session_Category::from($this->session_category);
        $this->age_group = Usctdp_Age_Group::from($this->age_group);
        $this->type = Usctdp_Activity_Type::from($this->type);
    }
}
