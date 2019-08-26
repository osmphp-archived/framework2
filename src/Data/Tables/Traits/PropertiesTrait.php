<?php

namespace Osm\Data\Tables\Traits;

use Osm\Core\App;
use Osm\Data\Tables\Tables;
use Osm\Framework\Db\Db;

trait PropertiesTrait
{
    public function Osm_Framework_Db_Db__tables(Db $db) {
        global $osm_app; /* @var App $osm_app */

        /* @var Tables $result */
        $cacheKey = "tables.{$db->name}";
        $result = $osm_app->cache->remember($cacheKey, function($data) {
            return Tables::new($data);
        });

        $result->parent = $db;
        return $result;
    }
}