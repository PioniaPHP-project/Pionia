<?php

namespace Pionia\Pionia\Utils;

use Porm\Porm;

trait AppDatabaseHelper
{
    /**
     * Get the discovered database connections that are available in our environment
     * @return Arrayable
     */
    public function getDiscoveredConnections(): Arrayable
    {
        $numberDiscovered = env("DBS_CONNECTIONS_SIZE");

        if ($numberDiscovered < 1) {
            $this->logger?->info("No database connections discovered!");
            return new Arrayable();
        }
        $connectionsDiscovered = $this->env->get("DBS_CONNECTIONS");

        return new Arrayable($connectionsDiscovered);
    }

    private function attemptToConnectToAnyDbAvailable(): void
    {
        // if the developer set the default db, then we attempt to connect to that
        $defaultDb = $this->env->get('DEFAULT_DATABASE');
        if (!$defaultDb) {
            $allConnections = $this->getDiscoveredConnections();

            $defaultDb = $allConnections->first();
        }

        if (!$defaultDb) {
            $this->logger?->info("No database connections available");
            return;
        }
        $this->connectToDatabase($defaultDb);
    }

    // connects to the database and sets the connection in the context
    private function connectToDatabase(array | string $connectionString): void
    {
        $connection = null;
        if (is_string($connectionString)) {
            $connection = env($connectionString);
        }

        if ($connection) {
            // if the developer accessed the connection by its name, we will use that
            $this->context->set($connectionString, function () use ($connection) {
                return new Porm($connection);
            });
            if ($this->context->has($connectionString)) {
                $this->logger?->info("Connected to `$connectionString` as default database");
            }
        }
    }
}
