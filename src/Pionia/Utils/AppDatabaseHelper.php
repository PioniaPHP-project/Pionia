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
        $numberDiscovered = $this->env->get("DBS_CONNECTIONS_SIZE");

        $this->logger?->info("Discovering database connections", [$numberDiscovered]);

        if ($numberDiscovered < 1) {
            $this->logger?->info("No database connections discovered");
            return new Arrayable();
        }
        $connectionsDiscovered = $this->env->get("DBS_CONNECTIONS");

        $stringified = Support::arrayToString($connectionsDiscovered);
        $this->logger?->debug("Discovered database connections:- $stringified");
        return new Arrayable($connectionsDiscovered);
    }

    private function attemptToConnectToAnyDbAvailable(): void
    {
        // if the developer set the default db, then we attempt to connect to that
        $defaultDb = $this->env->get('DEFAULT_DATABASE');
        if (!$defaultDb) {
            $this->logger?->error("No connection was explicitly made default");
            $this->logger?->info("You can set the 'default' key to true in the database section to make a connection default");
            $this->logger?->debug("Attempting to connect to the first connection available.");

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
        $this->logger?->info("Attempting to connect to the database `$connectionString`");

        $connection = null;
        if (is_string($connectionString)) {
            $this->env->has($connectionString) && $connection = $this->env->get($connectionString);
        }

        if ($connection) {
            // if the developer accessed the connection by its name, we will use that
            $this->context->set($connectionString, function () use ($connection) {
                return new Porm($connection);
            });
            if ($this->context->has($connectionString)) {
                $this->logger?->info("Connected to the database `$connectionString`");
            } else {
                $this->logger?->error("Failed to connect to the database `$connectionString`");
            }
        }
    }
}
