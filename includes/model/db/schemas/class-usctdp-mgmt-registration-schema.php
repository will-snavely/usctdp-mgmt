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
        'order_id' => [
            'name' => 'order_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'tracking_id' => [
            'name' => 'tracking_id',
            'type' => 'tinytext',
        ],
        'student_level' => [
            'name' => 'student_level',
            'type' => 'tinytext',
        ],
        'credit' => [
            'name' => 'credit',
            'type' => 'smallint',
            'unsigned' => true,
        ],
        'debit' => [
            'name' => 'debit',
            'type' => 'smallint',
            'unsigned' => true,
        ],
        'notes' => [
            'name' => 'notes',
            'type' => 'text',
        ],
        'status' => [
            'name' => 'status',
            'type' => 'tinyint',
            'unsigned' => true,
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
        'last_modified_at' => [
            'name' => 'last_modified_at',
            'type' => 'datetime',
        ],
        'last_modified_by' => [
            'name' => 'last_modified_by',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
        ],
    ];
}
