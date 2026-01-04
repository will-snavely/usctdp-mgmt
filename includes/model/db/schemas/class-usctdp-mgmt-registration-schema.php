<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Registration_Schema extends Schema
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

        'activity_id' => [
            'name'       => 'activity_id',
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
        ],

        'starting_level' => [
            'name'       => 'starting_level',
            'type'       => 'int',
            'unsigned'   => true,
            'default' => 0
        ],

        'balance' => [
            'name'       => 'balance',
            'type'       => 'int',
            'unsigned'   => true,
            'default' => 0
        ],

        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
            'default' => '',
        ],
    ];
}
