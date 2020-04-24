<?php

namespace Osm\Ui\Forms\Views;

class StringField extends InputField
{
    public $view_model = 'Osm_Ui_Forms.StringField';

    protected function default($property) {
        switch ($property) {
            case 'type': return 'text';
        }

        return parent::default($property);
    }
}