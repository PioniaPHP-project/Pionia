<?php

namespace Pionia\Porm\Core;

use Exception;
use Pionia\Porm\Database\Aggregation\AggregateTrait;
use Pionia\Porm\Database\Utils\TableLevelQueryTrait;
use Pionia\Porm\Driver\Connection;

class Porm
{
    /**
     * The CDatabase object to use
     * @var mixed
     */
    public Piql $database;

    /**
     * The CDatabase table to use. This is for interoperability with other versions of Porm
     * @var mixed
     */
    private ?string $table;

    /**
     * @var string|null The alias to use, will defualt to the table name provided.
     */
    private ?string $alias;

    /**
     * @var bool Lock out the use of filter
     */
    private bool $preventHas = false;

    /**
     * @var bool Lock out the use of any other method other than filter
     */
    private bool $allowFilterOnly = false;

    /**
     * @var mixed The result set to call asObject and asJson on.
     */
    private mixed $resultSet;

    /**
     * @var string|array|null The columns to select
     */
    private string|array|null $columns = '*';
    /**
     * @var true Lock out the use of raw queries
     */
    private bool $preventRaw = false;

    /**
     * @var array The columns to select
     */
    private array $where = [];

    use TableLevelQueryTrait, AggregateTrait;

    public function __construct(Connection $connection)
    {
        $this->database = new Piql($connection);
    }

    /**
     * This assists to perform raw sql queries
     * @throws Exception
     */
    public function raw(string $query, ?array $params = [], ?string $using = 'db'): mixed
    {
        $queryable = $this->database->raw($query, $params);
        $results = $this->database->query($queryable->value, $queryable->map)->fetchAll();
        if (count($results) === 1) {
            $this->resultSet = $results[0];
            return $this->asObject();
        }
        return arr($results);
    }

    /**
     * Logs the last query that was run
     *
     * @return string|null The last query that was run
     */
    public function lastQuery(): ?string
    {
        return $this->database->prettyQuery;
    }

    /**
     * @return Piql|null
     */
    public function getDatabase(): ?Piql
    {
        return $this->database;
    }
    /**
     * Using transactions. This is a wrapper for the action method in the Core class.
     *
     * To access data outside the transaction, Create a result variable and refer to the transaction callback with the keyword `use`, and you can get data back after when you assign it from inside.
     * @example ```php
     *      $row = null;
     *      Table::from('qa_criteria')
     *            ->inTransaction(function (Table $instance) use (&$row) {
     *                  $row = $instance->save([
     *                      'name' => 'Status 3',
     *                      'description' => 'Must be single 4',
     *                      'best_of_total' => 6,
     *                  ]);
     *              });
     *
     *      var_dump($row);
     * ```
     * @param callable $callback The callback to run. It should return a void.
     * @throws Exception
     */
    public function inTransaction(callable $callback): void
    {
        $this->database->action(function ($database) use ($callback) {
            $this->database = $database;
            return $callback($this);
        });
    }

    /**
     * Returns the details of the current db connection
     * @return array
     */
    public function info(): array
    {
        return $this->database->info();
    }

    /**
     * Returns the last saved id
     * @return string|null
     */
    public function lastSaved(): ?string
    {
     return $this->database->id();
    }

    public function setDatabase(Piql $database): void
    {
        $this->database = $database;
    }

    public function setTable(?string $table): void
    {
        $this->table = $table;
    }

    public function setAlias(?string $alias): void
    {
        $this->alias = $alias;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

}
