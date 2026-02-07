<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Product_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name' => 'id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'extra' => 'auto_increment',
            'primary' => true,
            'sortable' => true,
        ],
        'woocommerce_id' => [
            'name' => 'woocommerce_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'sortable' => true,
            'index' => true,
        ],
        'title' => [
            'name' => 'title',
            'type' => 'tinytext',
        ],
        'search_term' => [
            'name' => 'search_term',
            'type' => 'tinytext',
            'index' => true,
        ],
        'type' => [
            'name' => 'type',
            'type' => 'tinyint',
        ],
        'age_group' => [
            'name' => 'age_group',
            'type' => 'tinyint',
        ],
        'session_category' => [
            'name' => 'session_category',
            'type' => 'tinyint',
        ],
    ];
}
