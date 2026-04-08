<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Purchase_Schema extends Schema
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
        'product_id' => [
            'name' => 'product_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'family_id' => [
            'name' => 'family_id',
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
        'tracking_id' => [
            'name' => 'tracking_id',
            'type' => 'varchar',
            'length' => '255',
            'nullable' => true,
        ],
        'type' => [
            'name' => 'type',
            'type' => 'varchar',
            'length' => '50',
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
        'notes' => [
            'name' => 'notes',
            'type' => 'text',
            'nullable' => true,
        ],
        'discounts' => [
            'name' => 'discounts',
            'type' => 'json',
            'nullable' => true,
            'default' => '[]',
        ],
    ];
}
