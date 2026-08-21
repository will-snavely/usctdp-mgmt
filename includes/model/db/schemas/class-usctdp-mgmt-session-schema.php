<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Session_Schema extends Schema
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
        'title' => [
            'name' => 'title',
            'type' => 'tinytext',
        ],
        'search_term' => [
            'name' => 'search_term',
            'type' => 'tinytext',
            'index' => true,
        ],
        // Lifecycle: 'scheduled' (part of the published season schedule,
        // admin can manage it, but not yet on sale) -> 'on_sale' (also open
        // for registration) -> 'archived' (hidden from most admin screens
        // and the public schedule, not on sale). Replaces the old is_active
        // bool - see Usctdp_Mgmt_Session_Table::migrate_is_active_to_status().
        'status' => [
            'name' => 'status',
            'type' => 'varchar',
            'length' => '20',
            'default' => 'scheduled',
            'index' => true
        ],
        'start_date' => [
            'name' => 'start_date',
            'type' => 'date',
            'index' => true
        ],
        'end_date' => [
            'name' => 'end_date',
            'type' => 'date',
        ],
        'num_weeks' => [
            'name' => 'num_weeks',
            'type' => 'tinyint',
            'unsigned' => true,
        ],
        'category' => [
            'name' => 'category',
            'type' => 'tinyint',
            'unsigned' => true,
        ],
        'season' => [
            'name' => 'season',
            'type' => 'varchar',
            'length' => 50
        ],
        'meta' => [
            'name' => 'meta',
            'type' => 'json',
            'default' => '{}'
        ]
    ];
}
