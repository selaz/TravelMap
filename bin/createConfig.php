<?php

include_once( realpath( __DIR__ . '/../vendor/autoload.php' ) );

$options = getopt('',['dbhost:','dbuser:','dbpass:','database:','tgkey:','mapskey:','webhost:']);

$config = [
    'mysql' => [
        'host' => $options['dbhost'],
        'user' => $options['dbuser'],
        'pass' => $options['dbpass'],
        'db' => $options['database'],
    ],
    'telegram' => [
        'key' => $options['tgkey'],
    ],
    'maps' => [
        'key' => $options['mapskey'],
    ],
    'web' => [
        'host' => $options['webhost'],
    ]
];

\Selaz\Tools\Config::write_ini_file('main.ini',$config);