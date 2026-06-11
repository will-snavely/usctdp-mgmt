<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Staff_Schema extends Schema
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
        'slug' => [
            'name' => 'slug',
            'type' => 'varchar',
            'length' => 255,
            'index' => true,
        ],
        'wp_user_id' => [
            'name' => 'wp_user_id',
            'type' => 'bigint',
            'index' => true
        ],
        'image_id' => [
            'name' => 'image_id',
            'type' => 'bigint',
            'index' => true
        ],
        'display_order' => [
            'name' => 'display_order',
            'type' => 'tinyint',
            'index' => true,
            'default' => 10,
        ],
        'first_name' => [
            'name' => 'first_name',
            'type' => 'varchar',
            'length' => 255
        ],
        'last_name' => [
            'name' => 'last_name',
            'type' => 'varchar',
            'length' => 255
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
        'biography' => [
            'name' => 'biography',
            'type' => 'text'
        ],
        'quote' => [
            'name' => 'quote',
            'type' => 'text'
        ],
        'roles' => [
            'name' => 'roles',
            'type' => 'json'
        ],
        'faq' => [
            'name' => 'faq',
            'type' => 'json'
        ]
    ];
}
