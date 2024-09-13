<?php

namespace Pionia\Utils;

use Pionia\Collections\Arrayable;
use Throwable;

trait AppDatabaseHelper
{
    /**
     * Get the discovered database connections that are available in our environment
     * @return Arrayable
     */
    public function getDiscoveredConnections(): Arrayable
    {
        $numberDiscovered = arr(env('databases'))?->get("size");

        if ($numberDiscovered < 1) {
            $this->logger?->info("No database connections discovered!");
            return new Arrayable();
        }
        $connectionsDiscovered = arr(env('databases'))?->get("connections");
        return new Arrayable($connectionsDiscovered);
    }

    /**
     * Return everything about database connections discovered
     * @return Arrayable
     */
    public function ddInfo(): Arrayable
    {
        return arr(env('databases'));
    }

    /**
     * Get a database connection from the pool of connections we have
     *
     * If a name is passed, then we try to grab that name
     * @param string|null $name
     * @return mixed
     * @throws Throwable
     */
    public function defaultOrFirst(?string $name = null): mixed
    {
        $info  = $this->ddInfo();
        if($name && $info->has($name) && $name !== 'default'){
            return $info->getOrThrow($name, 'No Porm connection found with the name '.$name);
        }

        $default = $info->get('default');

        if ($default){
            return $info->get($default);
        }

        $firstOf = $this->getDiscoveredConnections()->first();
        return $info->get($firstOf);
    }

//    private function attemptToConnectToAnyDbAvailable(): void
//    {
//        // if the developer set the default db, then we attempt to connect to that
//        $defaultDb = arr(env('databases'))?->get('default');
//        if (!$defaultDb) {
//            $allConnections = $this->getDiscoveredConnections();
//
//            // if the developer did not set the default db, we will use the first connection
//            $defaultDb = $allConnections->first();
//        }
//
//        if (!$defaultDb) {
//            $this->logger?->info("No database connections available!");
//            return;
//        }
//
//        $this->connectToDatabase($defaultDb);
//    }
//
//    // connects to the database and sets the connection in the context
//    private function connectToDatabase(array | string $connectionString): void
//    {
//        $connection = null;
//        if (is_string($connectionString)) {
//            $connection = arr(env('databases'))?->get($connectionString);
//        }
//
//        if (isset($connection['connected']) && $connection['connected'] === true) {
//            $this->logger?->info("Db already established to $connectionString");
//            return;
//        }
//
//        if ($connection) {
//            // if the developer accessed the connection by its name, we will use that
//            $this->context->set($connectionString, function () use ($connection) {
//                return new Porm($connection);
//            });
//
//            if ($this->context->has($connectionString)) {
//                $_ENV['databases'][$connectionString]['connected'] = true;
//            }
//        }
//    }
}
