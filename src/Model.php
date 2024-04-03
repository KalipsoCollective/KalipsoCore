<?php

/**
 * @package KX\Core
 * @subpackage Factory
 */

declare(strict_types=1);

namespace KX\Core;

use Buki\Pdox;
use \PDO;
use KX\Core\Helper;

class Model extends Pdox
{

    protected $table = '';
    protected $schema;
    protected $bulkData = [];
    protected $created = false;
    protected $updated = false;


    public function __construct()
    {
        parent::__construct([
            'host'      => Helper::config('DB_HOST'),
            'driver'    => Helper::config('DB_DRIVER'),
            'database'  => Helper::config('DB_NAME'),
            'username'  => Helper::config('DB_USER'),
            'password'  => Helper::config('DB_PASS'),
            'charset'   => Helper::config('DB_CHARSET'),
            'collation' => Helper::config('DB_COLLATION'),
            'prefix'    => Helper::config('DB_PREFIX'),
            'cachedir'  => Helper::path('app/Storage/db_cache/')
        ]);

        // set timezone
        $this->exec('SET GLOBAL time_zone = "' . date('e') . '";');
        $this->table($this->table);
    }

    /**
     * Set table name and schema
     * @param object|array $schema
     * @return object
     * @example
     * $schema = [
     *     'columns' => [
     *         [
     *             'name' => 'id',
     *             'type' => 'INT',
     *             'auto_increment' => true,
     *             'primary_key' => true
     *         ],
     *         [
     *             'name' => 'username',
     *             'type' => 'VARCHAR',
     *             'length' => 255,
     *             'nullable' => false,
     *             'default' => 'guest',
     *             'unique' => true
     *         ],
     *         [
     *             'name' => 'email',
     *             'type' => 'VARCHAR',
     *             'length' => 255,
     *             'nullable' => false,
     *             'unique' => true
     *         ],
     *         [
     *             'name' => 'created_at',
     *             'type' => 'DATETIME',
     *             'nullable' => false
     *         ],
     *         ...
     *     ],
     *     'indexes' => [
     *         'primary' => ['id'],
     *         'unique' => ['username', 'email'],
     *         'index_created_at' => ['created_at'],
     *         'composite_index' => ['username(255)', 'created_at'],
     *          ...
     *     ],
     * ];
     * 
     */
    public function setSchema(object|array $schema): object
    {
        $this->schema = is_array($schema) ? json_decode(json_encode($schema)) : $schema;

        if ($this->created) {
            $this->schema->columns->created_at = (object) [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ];
            $this->schema->columns->created_by = (object) [
                'type' => 'INT',
                'nullable' => false,
                'length' => '11',
                'default' => 0
            ];
        }

        if ($this->updated) {
            $this->schema->columns->updated_at = (object) [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ];
            $this->schema->columns->updated_by = (object) [
                'type' => 'INT',
                'nullable' => true,
                'length' => '11',
                'default' => 0
            ];
        }
        return $this;
    }

    /**
     * Set bulk data
     * @param array $data
     * @return object
     */
    public function setBulkData(array $data): object
    {
        $this->bulkData = $data;
        return $this;
    }

    /**
     * Set table name
     * @param string $tableName
     * @return object
     */
    public function setTable(string $tableName): object
    {
        $this->table = $tableName;
        return $this;
    }

    /**
     * Create table
     * @param bool $importData
     * @return array
     */
    public function setupModels($importData = false): array
    {
        // delete all database tables
        $sql = 'SELECT 
            CONCAT(`TABLE_NAME`) 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = "' . Helper::config('DB_NAME') . '"';
        $allTables = $this->pdo->prepare($sql);
        $allTables->execute();
        $allTables = $allTables->fetchAll(PDO::FETCH_COLUMN);

        if (is_array($allTables) && count($allTables)) {

            $dropSql = '';
            foreach ($allTables as $table) {
                $dropSql .= 'DROP TABLE IF EXISTS `' . $table . '`;' . PHP_EOL;
            }
            $this->pdo->exec($dropSql);
        }

        // create tables
        $modelClasses = Helper::getClasses('app/Model');
        $summary  = [];
        foreach ($modelClasses as $modelClass) {

            $model = new $modelClass();
            $act = $model->setupModel();

            $summary[$model->table] = [
                'status' => $act,
                'message' => $act ? 'Table created successfully.' : 'Table creation failed.'
            ];
            if ($act) {
                if ($importData) {
                    $import = $model->importData();
                    $summary[$model->table]['seed_status'] = $import;
                    $summary[$model->table]['seed_message'] = $import ? 'Data imported successfully.' : 'Data import failed.';
                }
            }
        }

        return $summary;
    }

    /**
     * Setup model
     * @return void
     */
    public function setupModel()
    {

        $query = 'CREATE TABLE `' . $this->table . '` (' . PHP_EOL;

        // columns
        foreach ($this->schema->columns as $columnName => $column) {
            $query .= ' `' . $columnName . '` ' . mb_strtolower($column->type);

            if (isset($column->length)) {
                $query .= '(' . $column->length . ')';
            }

            if (!isset($column->nullable) || !$column->nullable) {
                $query .= ' NOT NULL';
            }

            if (isset($column->default)) {
                $def = '';
                if ($column->default === 'CURRENT_TIMESTAMP') {
                    $def = 'CURRENT_TIMESTAMP';
                } elseif (is_null($column->default)) {
                    $def = 'NULL';
                } elseif (is_numeric($column->default)) {
                    $def = $column->default;
                } else {
                    $def = '\'' . $column->default . '\'';
                }
                $query .= ' DEFAULT ' . $def;
            }

            if (isset($column->auto_increment) && $column->auto_increment) {
                $query .= ' AUTO_INCREMENT';
            }

            $query .= ',' . PHP_EOL;
        }

        // indexes
        if (isset($this->schema->indexes)) {
            foreach ($this->schema->indexes as $indexName => $index) {

                $index->type = mb_strtoupper($index->type);
                $query .= ' ' . $index->type . ($index->type === 'FULLTEXT' ? ' INDEX' : '') . (in_array($index->type, ['PRIMARY', 'UNIQUE']) ? ' KEY ' : ' ') . '`' . $indexName . '` (' . implode(', ', $index->columns) . '),' . PHP_EOL;
            }
        }

        $query = rtrim($query, PHP_EOL . ',') . PHP_EOL . ') ENGINE = ' . $this->schema->engine . ' DEFAULT CHARSET = ' . $this->schema->charset . ' COLLATE = ' . $this->schema->collation . ';' . PHP_EOL;

        try {
            $this->pdo->exec($query);
            return true;
        } catch (\PDOException $e) {

            throw new \Exception('DB migrate action is not completed. ' . $e->getMessage());
        }
    }

    /**
     * Import data
     * @return void
     */
    public function importData()
    {

        if (count($this->bulkData)) {

            $query = 'INSERT INTO `' . $this->table . '` (';

            foreach ($this->bulkData[0] as $columnName => $columnValue) {
                $query .= '`' . $columnName . '`,';
            }

            // created
            if ($this->created) {
                $query .= '`created_at`,';
                $query .= '`created_by`,';
            }

            $query = rtrim($query, ',') . ') VALUES ';

            foreach ($this->bulkData as $data) {

                $query .= '(';

                foreach ($data as $columnValue) {
                    $query .= '\'' . $columnValue . '\',';
                }

                // created
                if ($this->created) {
                    $query .= '\'' . time() . '\',';
                    $query .= '\'' . (Helper::sessionData('user', 'id') ?? 0) . '\',';
                }

                $query = rtrim($query, ',') . '),';
            }

            $query = rtrim($query, ',') . ';';

            try {

                $this->pdo->exec($query);
            } catch (\PDOException $e) {

                throw new \Exception('DB seed action is not completed. ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * Sync tables
     * @return array
     */
    public function syncModels(): array
    {
        // create tables
        $modelClasses = Helper::getClasses('app/Model');
        $summary = [];
        foreach ($modelClasses as $modelClass) {

            $model = new $modelClass();
            $sync = $model->syncModel();
            $summary[$model->table] = [
                'status' => $sync,
                'message' => $sync ? 'Table synced successfully.' : 'Table sync failed.'
            ];
        }
        return $summary;
    }

    /**
     * Sync table
     * @return void
     */
    public function syncModel()
    {
        $sql = 'SELECT 
            CONCAT(`TABLE_NAME`) 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = "' . Helper::config('DB_NAME') . '" AND TABLE_NAME = "' . $this->table . '"';
        $table = $this->pdo->prepare($sql);
        $table->execute();
        $table = $table->fetch(PDO::FETCH_COLUMN);

        if (!$table) {

            $this->setupModel();
        } else {

            $sql = 'SELECT 
                CONCAT(`COLUMN_NAME`) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = "' . Helper::config('DB_NAME') . '" AND TABLE_NAME = "' . $this->table . '"';
            $columns = $this->pdo->prepare($sql);
            $columns->execute();
            $columns = $columns->fetchAll(PDO::FETCH_COLUMN);

            $schemaColumns = [];
            foreach ($this->schema->columns as $columnName => $column) {
                $schemaColumns[] = $columnName;
            }

            $diff = array_diff($schemaColumns, $columns);

            if (count($diff)) {

                $sql = 'ALTER TABLE `' . $this->table . '` ADD( ';
                foreach ($diff as $i => $columnName) {
                    $sql .= '`' . $columnName . '` ' . $this->schema->columns->$columnName->type . ' ';

                    if (isset($this->schema->columns->$columnName->length)) {
                        $sql .= '(' . $this->schema->columns->$columnName->length . ')';
                    }

                    if (!isset($this->schema->columns->$columnName->nullable) || !$this->schema->columns->$columnName->nullable) {
                        $sql .= ' NOT NULL';
                    }

                    if (isset($this->schema->columns->$columnName->default)) {
                        $def = '';
                        if ($this->schema->columns->$columnName->default === 'CURRENT_TIMESTAMP') {
                            $def = 'CURRENT_TIMESTAMP';
                        } elseif (is_null($this->schema->columns->$columnName->default)) {
                            $def = 'NULL';
                        } elseif (is_numeric($this->schema->columns->$columnName->default)) {
                            $def = $this->schema->columns->$columnName->default;
                        } else {
                            $def = '\'' . $this->schema->columns->$columnName->default . '\'';
                        }
                        $sql .= ' DEFAULT ' . $def;
                    }

                    if (isset($this->schema->columns->$columnName->auto_increment) && $this->schema->columns->$columnName->auto_increment) {
                        $sql .= ' AUTO_INCREMENT';
                    }

                    $sql .= ',' . PHP_EOL;
                }

                $sql = rtrim($sql, PHP_EOL . ',') . ');';

                try {
                    Helper::dump($sql, true);
                    $this->pdo->exec($sql);
                } catch (\PDOException $e) {

                    throw new \Exception('DB sync action is not completed. ' . $e->getMessage());
                }
            }

            return true;
        }
    }

    /**
     * insert
     * @param array $data
     * @param bool $type
     * @return null|int
     */
    public function insert(array $data, $type = false)
    {

        if ($this->created) {

            $data['created_at'] = isset($data['created_at']) === false ? time() : $data['created_at'];
            $data['created_by'] = isset($data['created_by']) === false ? (Helper::sessionData('user', 'id') ?? 0) : $data['created_by'];
        }

        return parent::insert($data, $type);
    }

    /**
     * update
     * @param array $data
     * @param bool $type
     * @return null|int
     */
    public function update(array $data, $type = false)
    {

        if ($this->updated) {

            $data['updated_at'] = isset($data['updated_at']) === false ? time() : $data['updated_at'];
            $data['updated_by'] = isset($data['updated_by']) === false ? (Helper::sessionData('user', 'id') ?? 0) : $data['updated_by'];
        }

        return parent::update($data, $type);
    }

    /**
     * reset
     * @return void
     */
    protected function reset()
    {

        parent::reset();
        $this->table($this->table);
    }

    public function fetch($type = null, $argument = null, $all = false)
    {
        if (is_null($this->query)) {
            return null;
        }

        if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
            $cache = $this->cache->getCache($this->query, $type === PDO::FETCH_ASSOC);
            if ($cache) {
                $this->cache = null;
                $this->numRows = is_array($cache) ? count($cache) : ($cache === '' ? 0 : 1);
                return $all ? $cache : (isset($cache[0]) ? $cache[0] : $cache);
            }
        } else {
            $this->cache = null;
        }

        $query = $this->pdo->query($this->query);
        if (!$query) {
            $this->error = $this->pdo->errorInfo()[2];
            $this->error();
        }

        $type = $this->getFetchType($type);
        if ($type === PDO::FETCH_CLASS) {
            $query->setFetchMode($type, $argument);
        } else {
            $query->setFetchMode($type);
        }

        $result = $all ? $query->fetchAll() : $query->fetch();
        $this->numRows = is_array($result) ? count($result) : 1;

        if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
            $this->cache->setCache($this->query, $result);
        }
        return $result;
    }

    /**
     * cache
     * @param $time
     * @return object
     */
    public function cache($time)
    {
        if (Helper::config('settings.db_cache')) {
            Helper::path('app/Storage/db_cache/', true);
            parent::cache($time);
        }
        return $this;
    }

    /** 
     * Is created column enabled
     * 
     * @param bool $status
     * @return object
     */
    public function created(bool $status = true): object
    {
        $this->created = $status;
        return $this;
    }

    /** 
     * Is updated column enabled
     * 
     * @param bool $status
     * @return object
     */
    public function updated(bool $status = true): object
    {
        $this->updated = $status;
        return $this;
    }
}
