<?php

namespace Osm\Ui\Forms\Views;

class TextField extends Field
{
    public $field_template = 'Osm_Ui_Forms.text-field';
    public $view_model = 'Osm_Ui_Forms.TextField';

    protected function default($property) {
        switch ($property) {
            case 'modifier': return '-text';
        }

        return parent::default($property);
    }
}