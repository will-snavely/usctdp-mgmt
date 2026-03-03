<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Waitlist_Schema extends Schema
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
            'default' => 0
        ],
        'registration_id' => [
            'name' => 'registration_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],
        'student_id' => [
            'name' => 'student_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],
        'priority' => [
            'name' => 'type',
            'type' => 'smallint',
            'unsigned' => true
        ],
        'status' => [
            'name' => 'type',
            'type' => 'tinyint',
            'unsigned' => true
        ],
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
        ],
        'notified_at' => [
            'name' => 'notified_at',
            'type' => 'datetime',
        ],
        'expires_at' => [
            'name' => 'expires_at',
            'type' => 'datetime',
        ],
    ];
}
