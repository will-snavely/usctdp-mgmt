<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Clinic_Schema extends Schema
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
        'day_of_week' => [
            'name' => 'day_of_week',
            'type' => 'tinyint',
            'unsigned' => true,
        ],
        'start_time' => [
            'name' => 'start_time',
            'type' => 'time',
        ],
        'end_time' => [
            'name' => 'end_time',
            'type' => 'time',
        ]
    ];
}
