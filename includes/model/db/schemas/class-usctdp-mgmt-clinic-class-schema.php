<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Clinic_Class_Schema extends Schema
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

        'session_id' => [
            'name'       => 'session_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ],

        'clinic_id' => [
            'name'       => 'clinic_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ],

        'title' => [
            'name'       => 'title',
            'type'       => 'tinytext',
            'index'      => true,
        ],

        'day_of_week' => [
            'name'       => 'day_of_week',
            'type'       => 'tinyint',
            'unsigned'   => true,
        ],

        'start_time' => [
            'name'       => 'start_time',
            'type'       => 'time',
        ],

        'end_time' => [
            'name'       => 'end_time',
            'type'       => 'time',
        ],

        'capacity' => [
            'name'       => 'capacity',
            'type'       => 'smallint',
            'unsigned'   => true,
        ],

        'level' => [
            'name'       => 'level',
            'type'       => 'tinytext',
        ],

        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
        ],
    ];
}
