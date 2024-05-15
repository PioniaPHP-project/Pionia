<?php

namespace Pioneer\database;


use PDO;
use Pioneer\exceptions\DatabaseException;

/**
 * Class QueryBuilder
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class QueryBuilder extends Connector
{
    protected string $query = "";

    public \PDO $connection;

    protected string $using = 'db';


    /**
     * @throws DatabaseException
     */
    public function __construct()
    {
        parent::__construct();
        $this->connection = Connector::connect($this->using);
    }

    /**
     * Use this to reset the connection to the database to a different one.
     * @param string $using
     */
    public function Using(string $using): static
    {
        $this->using = $using;
        $this->refreshConnection();
        return $this;
    }

    public function refreshConnection(): void
    {
        $this->connection = Connector::connect($this->using);
    }

    public function Query(string $query, $mode = 'many')
    {
        if ($mode == 'many'){
            $fetchMode = \PDO::FETCH_ASSOC;
        } else if ($mode == 'obj'){
            $fetchMode = \PDO::FETCH_OBJ;
        }
        return $this->connection->query($query, $fetchMode);
    }

    /**
     * Assists us to make bound queries
     * @example bound_query("SELECT * FROM user where username = :username", [":username" => 'JET'])
     * @param string $query
     * @param $bindings
     * @return false|\PDOStatement
     */
    private function bound_query(string $query, $bindings = []): false | \PDOStatement
    {
        $stmt = $this->connection->prepare($query);

        // bind the params here
        foreach ($bindings as $binding => $value){
            $stmt->bindValue($binding, $value);
        }
        // run the query here
        $stmt->execute();
        return $stmt;
    }

    public function one(string $query, $bindings = [])
    {
        if (str_contains($query, 'LIMIT') || str_contains($query, 'limit')){
            $query.= ' LIMIT 1 ';
        }

        $statement = $this->bound_query($query, $bindings);
        if ($statement){
            return $statement->fetch(PDO::FETCH_OBJ);
        }
        return null;
    }

    public function all(string $query, $bindings = [])
    {
        $statement = $this->bound_query($query, $bindings);
        if ($statement){
           return $statement->fetchAll(PDO::FETCH_OBJ);
        }
        return [];
    }

    protected function _count_internal(string | null $query, $bindings = []){
        if ($query) {
            $qry = 'SELECT COUNT(*) as count FROM (' . $query . ') AS baseq';
        } else {
            $qry = 'SELECT COUNT(*) as count FROM ' . $query;
        }

        $statement = $this->one($qry, $bindings);
        return $statement->count ?:null;
    }

}
