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
            'cachedir'  => Helper::path('app/Storage/db_cache/', true)
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
     *         'composite_index' => ['username', 'created_at'],
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
                $query .= ' DEFAULT `' . $column->default . '`';
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
}
