<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Merchandise_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name' => 'id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'primary' => true,
            'sortable' => true,
        ],
        'student_id' => [
            'name' => 'student_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'product_id' => [
            'name' => 'product_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ]
    ];
}
