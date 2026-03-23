<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Table extends Table
{
    public $name = 'usctdp_product';
    protected $db_version_key = 'usctdp_product_version';
    public $description = 'USCTDP Products';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public static function create_title($clinic_name, $dow, $start_time)
    {
        $time = $start_time->format('g:i A');
        return sanitize_text_field("$clinic_name, $dow at $time");
    }

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            woocommerce_id bigint(20) unsigned NOT NULL,
            code tinytext NOT NULL,
            type varchar(50),
            age_group varchar(50),
            title tinytext,
            search_term tinytext,
            session_category tinyint,
            PRIMARY KEY (id),
            KEY woocommerce_id (woocommerce_id),
            INDEX code (code),
            FULLTEXT search (search_term)
        ";
    }
}
