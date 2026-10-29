<?php
return [
    'env' => 'development',
    'url' => 'http://127.0.0.1:8080',
    'db' => ['dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=wiki_ecommerce;charset=utf8mb4', 'user' => 'wiki_app', 'password' => 'CHANGE_ME'],
    'currency' => 'PKR',
    'shipping' => 25000,
    'free_shipping' => 1500000,
    'tax_basis_points' => 0,
    'mail_from' => 'store@example.com',
    'mail_transport' => 'log', // Set to mail only on a configured, trusted MTA.
];
