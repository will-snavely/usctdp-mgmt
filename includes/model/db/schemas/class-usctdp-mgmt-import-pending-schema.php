<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Import_Pending_Schema extends Schema
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
        'user_id' => [
            'name' => 'user_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'external_id' => [
            'name' => 'external_id',
            'type' => 'tinytext',
        ],
        'matched_existing_user' => [
            'name' => 'matched_existing_user',
            'type' => 'tinyint',
            'length' => '1',
            'unsigned' => true,
            'default' => 0,
        ],
        'last' => [
            'name' => 'last',
            'type' => 'tinytext',
        ],
        'address' => [
            'name' => 'address',
            'type' => 'tinytext',
        ],
        'city' => [
            'name' => 'city',
            'type' => 'tinytext',
        ],
        'state' => [
            'name' => 'state',
            'type' => 'tinytext',
        ],
        'zip' => [
            'name' => 'zip',
            'type' => 'tinytext',
        ],
        'phone_numbers' => [
            'name' => 'phone_numbers',
            'type' => 'json',
        ],
        'emails' => [
            'name' => 'emails',
            'type' => 'json',
        ],
        'notes' => [
            'name' => 'notes',
            'type' => 'text',
        ],
        'students' => [
            'name' => 'students',
            'type' => 'json',
        ],
        'invited_at' => [
            'name' => 'invited_at',
            'type' => 'datetime',
        ],
        'confirmed_at' => [
            'name' => 'confirmed_at',
            'type' => 'datetime',
        ],
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
        ],
    ];
}
