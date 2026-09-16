<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Camp_Schema extends Schema
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
        'activity_date' => [
            'name' => 'activity_date',
            'type' => 'date',
            'sortable' => true,
        ],
        'week_number' => [
            'name' => 'week_number',
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
