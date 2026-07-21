<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Tournament_Schema extends Schema
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
        'start_date' => [
            'name' => 'start_date',
            'type' => 'date',
        ],
        'start_date_addtl' => [
            'name' => 'start_date_addtl',
            'type' => 'date',
        ],
        'registration_deadline' => [
            'name' => 'registration_deadline',
            'type' => 'date',
        ],
        'early_registration_deadline' => [
            'name' => 'early_registration_deadline',
            'type' => 'date',
            'allow_null' => true,
        ],
        'schedule' => [
            'name' => 'schedule',
            'type' => 'json',
            'default' => '{}',
        ],
    ];
}
