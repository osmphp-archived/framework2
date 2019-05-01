<?php

namespace Manadev\Data\Sheets;

use Manadev\Core\Object_;

/**
 * @property string $name @required @part
 * @property array $columns @required @part
 * @property Column[] $columns_ @required @part
 */
class Sheet extends Object_
{
    protected function default($property) {
        switch ($property) {
            case 'columns_': return $this->getColumns();
        }
        return parent::default($property);
    }

    /**
     * Override this method in specialized sheet classes to inject predefined columns into the sheet
     *
     * @return array
     */
    protected function getColumnArray() {
        return $this->columns;
    }

    protected function getColumns() {
        $result = [];

        foreach ($this->getColumnArray() as $name => $data) {
            $result[$name] = Column::new($data, $name, $this);
        }

        return $result;
    }
}