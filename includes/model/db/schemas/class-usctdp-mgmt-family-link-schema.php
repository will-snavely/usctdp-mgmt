<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Family_Link_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name'     => 'id',
            'type'     => 'bigint',
            'length'   => '20',
            'unsigned' => true,
            'extra'    => 'auto_increment',
            'primary'  => true,
            'sortable' => true,
            'default' => 0
        ],

        'family_id' => [
            'name'       => 'family_id',
            'type'       => 'int',
            'unsigned'   => true,
            'index'      => true,
            'default' => 0,
        ],

        'student_id' => [
            'name'       => 'student_id',
            'type'       => 'int',
            'unsigned'   => true,
            'index'      => true,
            'default' => 0
        ]
    ];
}
