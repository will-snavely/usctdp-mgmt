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
        ],
        'activity_id' => [
            'name'       => 'activity_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ],
        'student_id' => [
            'name'       => 'student_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ],
        'starting_level' => [
            'name'       => 'starting_level',
            'type'       => 'tinytext',
        ],
        'credit' => [
            'name'       => 'balance',
            'type'       => 'smallint',
            'unsigned'   => true,
        ],
        'debit' => [
            'name'       => 'balance',
            'type'       => 'smallint',
            'unsigned'   => true,
        ],
        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
        ],
    ];
}
