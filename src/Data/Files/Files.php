<?php

namespace Osm\Data\Files;

use Osm\Core\App;
use Osm\Core\Object_;
use Osm\Data\Files\Exceptions\CantUploadWithoutSession;
use Osm\Data\Files\Exceptions\InvalidContentLength;
use Osm\Data\Files\Hints\FileHint;
use Osm\Data\TableQueries\TableQuery;
use Osm\Framework\Areas\Area;
use Osm\Framework\Db\Db;
use Osm\Framework\Http\Request;
use Osm\Framework\Http\Url;
use Osm\Framework\Validation\Exceptions\ValidationFailed;

/**
 * Dependencies:
 *
 * @property Request $request @required
 * @property Db|TableQuery[] $db @required
 * @property Url $url @required
 * @property Area $area @required
 *
 * Computed properties:
 *
 * @property string[] $reference_columns
 * @property string[] $data_columns
 * @property bool $can_make_queries
 */
class Files extends Object_
{
    const PUBLIC = 'public/files';
    const PRIVATE = 'data/files';

    protected function default($property) {
        global $osm_app; /* @var App $osm_app */

        switch ($property) {
            case 'request': return $osm_app->request;
            case 'db': return $osm_app->db;
            case 'url': return $osm_app->url;
            case 'area': return $osm_app->area_;
            case 'reference_columns': return $this->getColumns(true);
            case 'data_columns': return $this->getColumns(false);
            case 'can_make_queries': return $osm_app->db->connection
                ->getSchemaBuilder()->hasTable('tables');
        }

        return parent::default($property);
    }

    protected function getColumns($reference) {
        $result = [];

        foreach ($this->db->tables['files']->columns as $column) {
            if ($column->name === 'id') {
                continue;
            }

            if ($reference) {
                if ($column->pinned) {
                    $result[] = $column->name;
                }
            }
            else {
                if (!$column->pinned) {
                    $result[] = $column->name;
                }
            }
        }

        return $result;
    }

    public function validateImage() {
        static $types = ['image/jpeg', 'image/gif', 'image/png', 'image/svg'];

        if (!in_array($this->request->content_type, $types)) {
            throw new ValidationFailed(osm_t("':file' is not an image", [
                'file' => $this->request->content_name,
            ]));
        }

        return $this;
    }

    public function validateNotHidden() {
        if (mb_strpos($this->request->content_name, '.') === 0) {
            throw new ValidationFailed(osm_t("Can't upload hidden file ':file'", [
                'file' => $this->request->content_name,
            ]));
        }

        return $this;
    }

    public function validateNotExecutable() {
        $ext = mb_strtolower(pathinfo($this->request->content_name,
            PATHINFO_EXTENSION));

        if (in_array($ext, ['', 'php', 'phtml', 'php3', 'js', 'sh'])) {
            throw new ValidationFailed(osm_t("Can't upload executable file ':file'", [
                'file' => $this->request->content_name,
            ]));
        }

        return $this;
    }

    /**
     * @param $root
     * @param array $options
     * @return File
     */
    public function upload($root, $options = []) {
        $this->validateNotHidden()->validateNotExecutable();
        $file = File::new(array_merge([
            'root' => $root,
            'requested_filename' => $this->request->content_name,
        ], $options));

        file_put_contents(osm_make_dir_for($file->filename_),
            $this->request->content);

        if (filesize($file->filename_) !== $this->request->content_length) {
            throw new InvalidContentLength(osm_t(
                "The size of the uploaded file doesn't match the file size estimated by the browser."));
        }

        $file->id = $this->insert($file);

        return $file;
    }

    public function import($root, $filename, $options = []) {
        $file = File::new(array_merge([
            'root' => $root,
            'requested_filename' => basename($filename),
        ], $options));

        copy($filename, osm_make_dir_for($file->filename_));
        return $this->insert($file);
    }


    public function insert(File $file) {
        return $this->db['files']->insert($this->data($file));
    }

    /**
     * @param string|string[] $where
     * @return TableQuery
     */
    public function query($where = []) {
        $result = $this->db['files'];

        if (!is_array($where)) {
            $result->where(is_int($where)
                ? "id = {$where}"
                : "uid = {$where}");
        }
        else {
            foreach ($where as $formula) {
                $result->where($formula);
            }
        }

        return $result;
    }

    public function get($where = []) {
        return $this->query($where)
            ->select('id')
            ->get($this->data_columns);
    }

    /**
     * @param string|string[] $where
     * @return \Generator|File[]
     */
    public function each($where = []) {
        foreach ($this->get($where) as $data) {
            yield File::new((array)$data);
        }
    }

    public function first($where = []) {
        foreach ($this->each($where) as $file) {
            return $file;
        }

        return null;
    }

    public function delete($where) {
        $this->db->connection->transaction(function() use ($where) {
            foreach ($this->each($where) as $file) {
                $this->deleteFile($file);
            }
        });
    }

    public function deleteFile(File $file) {
        if (is_file($file->filename_)) {
            unlink($file->filename_);
        }

        if ($file->id) {
            $this->db['files']->where("id = {$file->id}")->delete();
        }
    }

    public function gc($options = []) {
        GarbageCollector::new($options)->collect();
    }

    public function dropAllFiles() {
        if (!$this->can_make_queries) {
            return;
        }

        foreach ($this->each() as $file) {
            unset($file->id);
            $this->deleteFile($file);
        }
    }

    /**
     * @param File $file
     * @return array
     */
    protected function data(File $file) {
        $data = [];

        foreach ($this->data_columns as $column) {
            if (($value = $file->{$column}) !== null) {
                $data[$column] = $value;
            }
        }

        $hasReference = false;
        foreach ($this->reference_columns as $column) {
            if (($value = $file->{$column}) !== null) {
                $data[$column] = $value;
                $hasReference = true;
            }
        }

        if ($hasReference) {
            $data['area'] = null;
            $data['session'] = null;
        }
        else {
            if (!$this->area->session) {
                throw new CantUploadWithoutSession(osm_t(
                    "You can upload a file in an area without a session cookie."));
            }
            $data['area'] = $this->area->name;
            $data['session'] = $this->area->session->id;
        }

        return $data;
    }

    /**
     * @param File[] $files
     *
     * @return int[]
     */
    public function ids($files) {
        return array_map(function (File $file) { return $file->id; }, $files);
    }
}