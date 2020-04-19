<?php

use Osm\Framework\Views\View;

return [
    '@include' => ['base'],
    '#page' => [
        'modifier' => '-tests-ui-containers',
    ],
    '#content' => [
        'views' => [
            'containers' => View::new([
                'template' => 'Osm_Samples_Ui.containers',
                'id_' => null,
            ]),
        ],
    ],
];