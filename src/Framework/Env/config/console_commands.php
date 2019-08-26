<?php

use Osm\Framework\Env\Commands;
use Symfony\Component\Console\Input\InputArgument;

return [
    'env' => [
        'description' => m_("Gets or sets environment variables"),
        'class' => Commands\Env::class,
        'arguments' => [
            'variable' => [
                'type' => InputArgument::IS_ARRAY,
                'description' => m_("'VAR' shows variable, 'VAR=' clears variable, VAR=value sets variable")
            ],
        ],
    ],
];