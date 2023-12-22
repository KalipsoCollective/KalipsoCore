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
     *     ]
     * ];
     * 
     */
    public function setSchema(object|array $schema): object
    {
        $this->schema = is_array($schema) ? json_decode(json_encode($schema)) : $schema;
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
     * @return void
     */
    public function setupModels($importData = false)
    {
        // delete all database tables
        $sql = 'SELECT 
            CONCAT(`TABLE_NAME`) 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = "' . Helper::config('DB_NAME') . '"';
        $allTables = $this->pdo->prepare($sql);
        $allTables->execute();
        $allTables = $allTables->fetchAll(PDO::FETCH_COLUMN);

        if (is_array($allTables) and count($allTables)) {

            $dropSql = '';
            foreach ($allTables as $table) {
                $dropSql .= 'DROP TABLE IF EXISTS `' . $table . '`;' . PHP_EOL;
            }
            $this->pdo->exec($dropSql);
        }

        // create tables
        $modelClasses = Helper::getClasses('app/Model');
        foreach ($modelClasses as $modelClass) {

            $model = new $modelClass();
            $model->setupModel();

            if ($importData) {
                $model->importData();
            }
        }
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
            $query .= ' `' . $columnName . '` ' . $column->type . ' ';

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
                } else {
                    $def = '`' . $column->default . '`';
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

                $index->type = strtoupper($index->type);
                $query .= ' ' . $index->type . ($index->type === 'FULLTEXT' ? ' INDEX' : '') . (in_array($index->type, ['PRIMARY', 'UNIQUE']) ? ' KEY ' : ' ') . '`' . $indexName . '` (' . implode(', ', $index->columns) . '),' . PHP_EOL;
            }
        }

        $query = rtrim($query, PHP_EOL . ',') . PHP_EOL . ') ENGINE=' . $this->schema->engine . ' DEFAULT CHARSET=' . $this->schema->charset . ' COLLATE=' . $this->schema->collation . ';' . PHP_EOL;

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

            $query = rtrim($query, ',') . ') VALUES ';

            foreach ($this->bulkData as $data) {

                $query .= '(';

                foreach ($data as $columnValue) {
                    $query .= '\'' . $columnValue . '\',';
                }

                $query = rtrim($query, ',') . '),';
            }

            $query = rtrim($query, ',') . ';';

            try {

                $this->pdo->exec($query);
                return true;
            } catch (\PDOException $e) {

                throw new \Exception('DB seed action is not completed. ' . $e->getMessage());
            }
        }
    }

    /**
     * Sync tables
     * @return object
     */
    public function syncTables(): object
    {
        // create tables
        $modelClasses = Helper::getClasses('app/Model');
        foreach ($modelClasses as $modelClass) {

            $model = new $modelClass();
            $model->syncTable();
        }
        return $this;
    }

    /**
     * Sync table
     * @return void
     */
    public function syncTable()
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

                $sql = 'ALTER TABLE `' . $this->table . '` ';
                foreach ($diff as $columnName) {
                    $sql .= 'ADD COLUMN `' . $columnName . '` ' . $this->schema->columns->$columnName->type . ' ';

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
                        } else {
                            $def = '`' . $this->schema->columns->$columnName->default . '`';
                        }
                        $sql .= ' DEFAULT ' . $def;
                    }

                    if (isset($this->schema->columns->$columnName->auto_increment) && $this->schema->columns->$columnName->auto_increment) {
                        $sql .= ' AUTO_INCREMENT';
                    }

                    $sql .= ',' . PHP_EOL;
                }

                $sql = rtrim($sql, PHP_EOL . ',') . ';';

                try {

                    $this->pdo->exec($sql);
                } catch (\PDOException $e) {

                    throw new \Exception('DB sync action is not completed. ' . $e->getMessage());
                }
            }
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
            $data['created_by'] = isset($data['created_by']) === false ? (Helper::userData('id') ?? 0) : $data['created_by'];
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
            $data['updated_by'] = isset($data['updated_by']) === false ? (Helper::userData('id') ?? 0) : $data['updated_by'];
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
}
