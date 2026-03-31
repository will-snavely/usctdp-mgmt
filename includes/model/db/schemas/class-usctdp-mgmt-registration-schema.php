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
            'extra' => 'auto_increment',
            'primary' => true,
            'sortable' => true,
        ],
        'purchase_id' => [
            'name' => 'purchase_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
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
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
        ],
        'created_by' => [
            'name' => 'created_by',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
        ],
        'modified_at' => [
            'name' => 'modified_at',
            'type' => 'datetime',
        ],
        'modified_by' => [
            'name' => 'modified_by',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
        ],
    ];
}
