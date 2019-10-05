<?php

namespace Osm\Data\Indexing;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Osm\Core\App;
use Osm\Data\Indexing\Exceptions\CircularDependency;
use Osm\Data\Indexing\Hints\IndexerHint;
use Osm\Core\Object_;
use Osm\Framework\Cache\Cache;
use Osm\Framework\Db\Db;

/**
 * @property Db $db @required
 * @property Cache $cache @required
 * @property Targets|Target[] $targets @required
 * @property SourceGroups|string[] $source_groups @required
 * @property bool $run_async If true, indexer is expected to run in job queue instead of running after each
 *      request modifying database
 * @property bool $requires_reindex
 */
class Indexing extends Object_
{
    protected $modified_groups = [];
    protected $no_registration = []; // positive entry - skip modified source registration
    protected $no_queueing = 0; // positive - index immediately instead of queueing
    protected $requires_reindex = false;

    protected function default($property) {
        global $osm_app; /* @var App $osm_app */

        switch ($property) {
            case 'db': return $osm_app->db;
            case 'cache': return $osm_app->cache;
            case 'targets': return $this->cache->remember( 'indexers', function($data) {
                return Targets::new($data);
            });
            case 'source_groups': return $this->cache->remember('index_source_groups', function($data) {
                return SourceGroups::new($data);
            });
        }
        return parent::default($property);
    }

    /**
     * @param string $target
     * @param string $group
     * @return FluentIndexerDefinition
     */
    public function target($target, $group = null) {
        return FluentIndexerDefinition::new(compact('target', 'group'), null, $this);
    }

    public function dropTarget($target) {
        /* @var Collection|IndexerHint[] $indexers */
        $indexers = $this->db->connection->table('indexers')
            ->where('target', '=', $target)
            ->get();

        foreach ($indexers as $indexer) {
            if (!empty($indexer->events)) {
                $this->db->dropIndexingTriggers($indexer->id, $indexer->source, explode(',', $indexer->events));
            }
        }

        $this->db->connection->table('indexers')
            ->where('target', '=', $target)
            ->delete();

        $this->forgetSourceGroups();
    }

    public function registerModifiedSource($source) {
        if (!isset($this->source_groups[$source])) {
            return;
        }

        $groups = $this->source_groups[$source];

        if ($this->no_queueing > 0 &&
            ($index = array_search(null, $groups)) !== false)
        {
            $this->requires_reindex = true;
            array_splice($groups, $index, 1);
        }

        foreach ($groups as $group) {
            if (!isset($this->no_registration[$group])) {
                $this->modified_groups[$group] = true;
            }
        }
    }

    public function stopRegistering($group) {
        if (isset($this->no_registration[$group])) {
            $this->no_registration[$group]++;
        }
        else {
            $this->no_registration[$group] = 1;
        }
    }

    public function resumeRegistering($group) {
        if (!isset($this->no_registration[$group])) {
            return;
        }

        if ($this->no_registration[$group] > 1) {
            $this->no_registration--;
        }
        else {
            unset($this->no_registration[$group]);
        }
    }

    public function stopQueueing() {
        $this->no_queueing++;
    }

    public function resumeQueueing() {
        $this->no_queueing--;
    }

    public function getModifiedGroups() {
        return array_keys($this->modified_groups);
    }

    public function requiresReindex() {
        return $this->requires_reindex;
    }

    public function reindex($source) {
        $this->db->connection->table('indexers')
            ->where('source', '=', $source)
            ->update(['requires_full_reindex' => true]);

        $this->registerModifiedSource($source);

        return $this;
    }

    /**
     * @param int $mode
     * @param string $group
     * @param string $target
     * @param string $source
     * @param bool $noTransaction
     * @param OutputStyle|null $output
     */
    public function run($mode = Mode::PARTIAL, $group = null, $target = null,
        $source = null, $noTransaction = false, OutputStyle $output = null)
    {
        $this->stopRegistering($group);

        try {
            $indexers = $this->getIndexers($group, $target, $source);
            $scopes = $this->getScopes($indexers, $mode, $noTransaction);

            foreach ($scopes as $target => $scope) {
                if (!$scope->mode) {
                    continue;
                }

                $target_ = $this->targets[$target];
                $target_->scope = $scope;
                $target_->index();

                if ($mode == Mode::PARTIAL) {
                    $this->updateScopes($scopes, $scope, $indexers);
                }

                if ($output) {
                    $output->writeln((string)osm_t(":target updated",
                        ['target' => $target_->title]));
                }
            }
        }
        finally {
            $this->resumeRegistering($group);
        }
    }

    /**
     * @param string $group
     * @param string $target
     * @param string $source
     * @return IndexerHint[]|Collection
     */
    protected function getIndexers($group = null, $target = null, $source = null) {
        $query = $this->db->connection->table('indexers');

        if ($group) {
            $query->where('group', '=', $group);
        }
        else {
            $query->whereNull('group');
        }
        if ($target) {
            $query->where('target', '=', $target);
        }

        if ($source) {
            $query->where('source', '=', $source);
        }

        return $query
            ->orderBy("target")
            ->get(["id", "target", "source", "requires_partial_reindex", "requires_full_reindex"])
            ->keyBy(function($indexer) {
                /* @var IndexerHint $indexer */
                return "{$indexer->target}<-{$indexer->source}";
            });
    }

    /**
     * @param IndexerHint[] $indexers
     * @param int $mode
     * @param bool $noTransaction
     * @return Scope[]
     */
    protected function getScopes($indexers, $mode, $noTransaction) {
        $result = [];

        $target = null;
        $scope = null;
        foreach ($indexers as $indexer) {
            if ($indexer->target != $target) {
                if ($scope) {
                    $result[$target] = $scope;
                }
                $target = $indexer->target;
                $scope = Scope::new(['no_transaction' => $noTransaction ?: false], $target);
            }

            if ($indexer->requires_full_reindex || $mode == Mode::FULL) {
                $scope->sources[$indexer->source] = $indexer->id;
                $scope->mode = Mode::FULL;
            }
            elseif ($indexer->requires_partial_reindex) {
                $scope->sources[$indexer->source] = $indexer->id;
                $scope->mode = Mode::PARTIAL;
            }
            else {
                $scope->sources[$indexer->source] = null;
            }
        }

        if ($scope) {
            $result[$target] = $scope;
        }

        return $this->sortTargetScopes($result);
    }

    protected function sortTargetScopes($scopes) {
        $count = count($scopes);
        $positions = [];

        for ($position = 0; $position < $count; $position++) {
            if (!($target = $this->findScopeWithAlreadyResolvedDependencies($scopes, $positions))) {
                throw $this->circularDependency($scopes, $positions);
            }

            $positions[$target] = $position;
        }

        $this->sortByPosition($scopes, $positions);

        return $scopes;
    }

    protected function findScopeWithAlreadyResolvedDependencies($scopes, $positions) {
        foreach ($scopes as $target => $scope) {
            if (isset($positions[$target])) {
                continue;
            }

            if ($this->scopeHasUnresolvedDependency($scope, $scopes, $positions)) {
                continue;
            }

            return $target;
        }

        return false;
    }

    /**
     * @param Scope $scope
     * @param Scope[] $scopes
     * @param int[] $positions
     * @return bool
     */
    protected function scopeHasUnresolvedDependency($scope, $scopes, $positions) {
        foreach ($scope->sources as $dependency) {
            if (!isset($scopes[$dependency])) {
                continue;
            }

            if (!isset($positions[$dependency])) {
                return true;
            }
        }

        return false;
    }

    protected function sortByPosition($scopes, array $positions) {
        uasort($scopes, function (Scope $a, Scope $b) use ($positions) {
            $a = $positions[$a->name];
            $b = $positions[$b->name];
            if ($a == $b) return 0;

            return $a < $b ? -1 : 1;
        });
    }

    protected function circularDependency($scopes, array $positions) {
        $circular = array();
        foreach (array_keys($scopes) as $target) {
            if (!isset($positions[$target])) {
                $circular[] = $target;
            }
        }
        return new CircularDependency(osm_t("Index targets with circular dependencies found: :targets",
            ['targets' => implode(', ', $circular)]));
    }

    /**
     * @param Scope[] $scopes
     * @param Scope $updated
     * @param Collection|IndexerHint[] $indexers
     */
    protected function updateScopes(array $scopes, $updated, $indexers) {
        foreach ($scopes as $scope) {
            if (!array_key_exists($updated->name, $scope->sources)) {
                continue;
            }

            $id = $indexers["{$scope->name}<-{$updated->name}"]->id ?? null;

            if ($updated->mode == Mode::FULL || $updated->mode == Mode::PARTIAL && !$id) {
                $scope->mode = Mode::FULL;
                $scope->sources[$updated->name] = true;
            }
            elseif ($updated->mode == Mode::PARTIAL) {
                if (!$scope->mode) {
                    $scope->mode = Mode::PARTIAL;
                }
                $scope->sources[$updated->name] = $id;
            }
        }
    }

    public function forgetSourceGroups() {
        $this->cache->forget('index_source_groups');
        unset($this->source_groups);
    }
}