<?php

namespace Osm\Data\Indexing;

use Osm\Core\App;
use Osm\Core\Object_;
use Osm\Framework\Db\Db;

/**
 * @property Indexing $parent @required
 * @property string $target @required @part
 * @property string $group @part
 * @property Db $db @required
 */
class FluentIndexerDefinition extends Object_
{
    protected function default($property) {
        global $osm_app; /* @var App $osm_app */

        switch ($property) {
            case 'db': return $osm_app->db;
        }
        return parent::default($property);
    }

    public function source($source, $events = [], $columns = []) {
        if (($index = array_search(Event::SAVE, $events)) !== false) {
            array_splice($events, $index, 1,
                [Event::INSERT, Event::UPDATE]);
        }

        $id = $this->db->connection->table('indexers')->insertGetId([
            'group' => $this->group,
            'target' => $this->target,
            'source' => $source,
            'events' => implode(',', $events),
            'columns' => implode(',', $columns),
        ]);

        if (!empty($events)) {
            $this->db->createIndexingTriggers($id, $source, $events, $columns);
        }

        $this->parent->forgetSourceGroups();

        return $this;
    }
}