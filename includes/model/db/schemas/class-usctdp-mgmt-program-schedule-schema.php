<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Program_Schedule_Schema extends Schema
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
        'product_id' => [
            'name' => 'product_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'woocommerce_id' => [
            'name' => 'woocommerce_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'type' => [
            'name' => 'type',
            'type' => 'varchar',
            'length' => '50',
            'index' => true,
        ],
        'age_group' => [
            'name' => 'age_group',
            'type' => 'varchar',
            'length' => '50',
            'index' => true,
        ],
        'level' => [
            'name' => 'level',
            'type' => 'varchar',
            'length' => '50',
            'index' => true,
        ],
        'data' => [
            'name' => 'data',
            'type' => 'json',
            'default' => '{}',
        ],
    ];
}
