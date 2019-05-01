<?php

namespace Manadev\Data\Search;

use Illuminate\Support\Collection;
use Manadev\Core\Object_;

/**
 * @property int $count @required @part
 * @property array $facets @required @part
 * @property Collection|object[] $items @required @part
 */
class SearchResult extends Object_
{

}