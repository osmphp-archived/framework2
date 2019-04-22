<?php

namespace Manadev\Ui\DataTables;

use Manadev\Framework\Data\CollectionRegistry;

class ColumnTypes extends CollectionRegistry
{
    public $config = 'data_table_column_types';
    public $not_found_message = "Column type ':name' not found";

    protected function get() {
        $this->modified();
        return $this->config_;
    }
}