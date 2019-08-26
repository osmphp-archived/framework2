<?php

namespace Osm\Data\OptionLists;

use Osm\Framework\Data\CollectionRegistry;

class OptionLists extends CollectionRegistry
{
    public $class_ = OptionList::class;
    public $config = 'option_lists';
    public $not_found_message = "Option list ':name' not found";
}