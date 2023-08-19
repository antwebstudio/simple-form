<?php

return [
    'app' => [
        'debug' => false,
        'maintenance' => false,
    ],
    'admin' => [
        'username' => 'admin',
        'password' => '',
    ],
    'path' => [
        'form' => 'register.html',
        'thankyou' => 'register.html?thankyou',
    ],
    'recaptcha' => [
        'version' => 'v2',
        'api_site_key' => '',
        'api_secret_key' => '',
    ],
    'email' => [
        'subject' => '',
        'receivers' => [
            'admin@example.com',
        ],
    ],
    'responses' => [
        // 'columns' => [],
        // 'show_raw' => true,
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => '587',
        'username' => 'admin@example.com',
        'password' => '',
    ],
    'db' => [
        // 'enabled' => false,
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'simple-form',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
        'engine' => 'InnoDB',
    ],
];