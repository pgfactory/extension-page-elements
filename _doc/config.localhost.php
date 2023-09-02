<?php

// Defaults recommended by PageFactory plugin:
return [
    'debug'  => true,
    'email' => [
        'transport' => [
            'type' => 'smtp',
            'host' => 'localhost',
            'port' => 1025,
            'security' => false
        ]
    ],
    'auth.challenge.email.from' => 'webmaster@pagefactory.info',
];
