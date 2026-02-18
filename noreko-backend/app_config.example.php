<?php
/**
 * Application configuration - Copy to app_config.php and edit values
 * app_config.php is excluded from version control
 */
return [
    // Registration code required for new user signups
    'registration_code' => 'CHANGE_ME',

    // OpenVPN management interface
    'vpn' => [
        'host' => '127.0.0.1',
        'port' => 7505,
        'user' => 'admin',
        'password' => 'CHANGE_ME',
    ],
];
