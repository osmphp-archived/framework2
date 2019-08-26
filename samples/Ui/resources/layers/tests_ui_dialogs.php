<?php

use Osm\Framework\Views\View;
use Osm\Framework\Views\Views\Container;
use Osm\Ui\Buttons\Views\Button;

return [
    '@include' => ['base'],
    '#page' => [
        'modifier' => '-tests-ui-dialogs',
        'content' => Container::new([
            'id' => 'content',
            'views' => [
                'exception_button' => Button::new(['title' => m_("Show Exception Dialog")]),
                'yes_no_button' => Button::new(['title' => m_("Show Yes/No Dialog")]),
            ],
        ]),
        'footer' => View::new(['template' => 'Osm_Samples_Ui.footer']),
        'translations' => [
            "Do you really want to delete this item?" => "Do you really want to delete this item?",
        ],
    ],
];