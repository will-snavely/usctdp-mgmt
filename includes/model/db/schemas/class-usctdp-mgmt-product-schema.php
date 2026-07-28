<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Product_Schema extends Schema
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
        'woocommerce_id' => [
            'name' => 'woocommerce_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'sortable' => true,
            'index' => true,
        ],
        'code' => [
            'name' => 'code',
            'type' => 'tinytext',
            'index' => true,
        ],
        'wp_image_id' => [
            'name' => 'wp_image_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'allow_null' => true,
        ],
        'wp_flyer_id' => [
            'name' => 'wp_flyer_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'allow_null' => true,
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
        'type' => [
            'name' => 'type',
            'type' => 'varchar',
            'length' => '50',
        ],
        'level' => [
            'name' => 'level',
            'type' => 'varchar',
            'length' => '50',
        ],
        'age_group' => [
            'name' => 'age_group',
            'type' => 'varchar',
            'length' => '50',
        ],
        'age_range' => [
            'name' => 'age_range',
            'type' => 'varchar',
            'length' => '50',
        ],
        'description' => [
            'name' => 'description',
            'type' => 'text',
        ],
        'short_description' => [
            'name' => 'short_description',
            'type' => 'text',
        ],
        'session_category' => [
            'name' => 'session_category',
            'type' => 'tinyint',
        ],
        'meta' => [
            'name' => 'meta',
            'type' => 'json',
            'default' => '{}',
        ]   
    ];
}
