<?php

namespace Osm\Ui\Forms;

use Osm\Core\Modules\BaseModule;

class Module extends BaseModule
{
    public $hard_dependencies = [
        'Osm_Data_Search',
        'Osm_Ui_Aba',
        'Osm_Ui_SnackBars',
    ];
}