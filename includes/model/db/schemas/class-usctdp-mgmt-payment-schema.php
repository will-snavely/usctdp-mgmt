<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Payment_Schema extends Schema
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
        'order_id' => [
            'name' => 'order_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],
        'amount' => [
            'name' => 'amount',
            'type' => 'decimal',
            'length' => '10,2',
            'default' => '0.00',
            'unsigned' => false,
        ],
        'house_credit_used' => [
            'name' => 'house_credit_used',
            'type' => 'decimal',
            'length' => '10,2',
            'default' => '0.00',
            'unsigned' => false,
        ],
        'method' => [
            'name' => 'method',
            'type' => 'tinytext',
        ],
        'status' => [
            'name' => 'status',
            'type' => 'tinytext',
        ],
        'created_by' => [
            'name' => 'created_by',
            'type' => 'bigint',
            'unsigned' => true,
            'default' => 0
        ],
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
        ],
        'completed_at' => [
            'name' => 'completed_at',
            'type' => 'datetime',
        ],
        'reference_number' => [
            'name' => 'reference_number',
            'type' => 'tinytext',
            'default' => ''
        ],
    ];
}
