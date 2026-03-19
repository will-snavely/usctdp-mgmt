<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Registration_Schema extends Schema
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
        'activity_id' => [
            'name' => 'activity_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'student_id' => [
            'name' => 'student_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'student_level' => [
            'name' => 'student_level',
            'type' => 'tinytext',
        ],
        'notes' => [
            'name' => 'notes',
            'type' => 'text',
        ],
    ];
}
