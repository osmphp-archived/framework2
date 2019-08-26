<?php

namespace Osm\Data\OptionLists;

use Osm\Core\App;
use Osm\Core\Modules\BaseModule;
use Osm\Core\Properties;

class Module extends BaseModule
{
    public $traits = [
        Properties::class => Traits\PropertiesTrait::class,
    ];
}