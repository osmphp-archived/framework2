<?php

namespace Osm\Samples\Cache;

use Osm\Core\App;
use Osm\Core\Object_;

/**
 * @property IncrementalObject $item @required @part
 */
class Container extends Object_
{
    public function default($property) {
        global $m_app; /* @var App $m_app */

        switch ($property) {
            case 'item': return $m_app->cache->remember('test_incremental_object', function($data) {
                return IncrementalObject::new($data, null, $this);
            });
        }
        return parent::default($property);
    }
}