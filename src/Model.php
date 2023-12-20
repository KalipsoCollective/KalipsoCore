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
     * @return void
     */
    public function setupModels()
    {
        // delete all database tables
        $sql = '
        SELECT 
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

        Helper::dump($query, true);
        return;
        $sql = '';
        foreach ($this->schema['columns'] as $column => $attributes) {

            $type = '';

            switch ($attributes['type']) {
                case 'int':
                case 'bigint':
                case 'tinyint':
                case 'smallint':
                case 'mediumint':
                    if (isset($attributes['type_values']) === false) $attributes['type_values'] = 11;
                    $type = $attributes['type'] . '(' . $attributes['type_values'] . ')';
                    break;

                case 'float':
                case 'decimal':
                case 'double':
                case 'real':
                    if (isset($attributes['type_values']) !== false) $attributes['type_values'] = '(' . $attributes['type_values'] . ')';
                    $type = $attributes['type'] . $attributes['type_values'];
                    break;

                case 'char':
                case 'varchar':
                case 'tinytext':
                case 'text':
                case 'mediumtext':
                case 'longtext':
                case 'binary':
                case 'varbinary':
                case 'tinyblob':
                case 'blob':
                case 'mediumblob':
                case 'longblob':
                    $type = $attributes['type'] . '(' . $attributes['type_values'] . ') COLLATE ' .
                        (isset($attributes['collate']) !== false ? $attributes['collate'] :
                            $this->schema['table_values']['collate']);

                    break;

                case 'json':
                    $type = 'JSON ';

                    break;

                case 'enum':
                case 'set':
                    $type = $attributes['type'] . '("' . implode("', '", $attributes['type_values']) . '")';
                    break;
            }

            if (isset($attributes['index']) !== false) {

                switch ($attributes['index']) {
                    case 'PRIMARY':
                        $externalParams[] = 'PRIMARY KEY (`' . $column . '`)';
                        break;

                    case 'INDEX':
                        $externalParams[] = 'INDEX `' . $column . '` (`' . $column . '`)';
                        break;

                    case 'UNIQUE':
                        $externalParams[] = 'UNIQUE(`' . $column
                            . '`)';
                        break;

                    case 'FULLTEXT':
                        $externalParams[] = 'FULLTEXT(`' . $column . '`)';
                        break;

                    case 'FOREIGN':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`)';
                        break;

                    case 'FOREIGN_CASCADE':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE CASCADE';
                        break;

                    case 'FOREIGN_SET_NULL':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE SET NULL';
                        break;

                    case 'FOREIGN_RESTRICT':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE RESTRICT';
                        break;

                    case 'FOREIGN_NO_ACTION':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE NO ACTION';
                        break;

                    case 'FOREIGN_DEFAULT':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE SET DEFAULT';
                        break;

                    case 'FOREIGN_CHECK':
                        $externalParams[] = 'FOREIGN KEY (`' . $column . '`) REFERENCES `' .
                            $attributes['foreign_table'] . '`(`' . $attributes['foreign_column'] . '`) ON DELETE CHECK';
                        break;
                }
            }
        }

        if (
            isset($attributes['attr']) !== false
        ) {

            $type .= ' ' . $attributes['attr'];
        }

        if (isset($attributes['nullable']) === false or !$attributes['nullable']) {
            $type .= ' NOT NULL';
        }

        if (isset($attributes['default']) !== false) {
            switch ($attributes['default']) {
                case 'NULL':
                case 'CURRENT_TIMESTAMP':
                    $type .= ' DEFAULT ' . $attributes['default'];
                    break;
                default:
                    $type .= ' DEFAULT \'' . $attributes['default'] . '\'';
                    break;
            }
        }

        if (isset($attributes['auto_inc']) !== false and $attributes['auto_inc']) {
            $type .= ' AUTO_INCREMENT';
        }

        $sql .= PHP_EOL . '   `' . $column . '` ' . $type . ',';

        if (count($externalParams)) {

            foreach ($externalParams as $param) {
                $sql .= PHP_EOL . '   ' . $param . ',';
            }
        }

        $sql = rtrim($sql, ',' . PHP_EOL);

        $engine = isset($this->schema['table_values']['specific'][$table]['engine'])  !== false ?
            $this->schema['table_values']['specific'][$table]['engine']
            : $this->schema['table_values']['engine'];

        $charset = isset($this->schema['table_values']['specific'][$table]['charset'])  !== false ?

            $this->schema['table_values']['specific'][$table]['charset']
            : $this->schema['table_values']['charset'];

        $collate = isset($this->schema['table_values']['specific'][$table]['collate'])  !== false ?

            $this->schema['table_values']['specific'][$table]['collate']
            : $this->schema['table_values']['collate'];

        $sql .= PHP_EOL . ') ENGINE=' . $engine .
            ' DEFAULT CHARSET=' . $charset .
            ' COLLATE=' . $collate . ';' . PHP_EOL;

        try {

            // Helper::dump($sql, true);
            $this->pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {

            throw new \Exception('DB Init action is not completed. ' . $e->getMessage());
        }
    }
}
