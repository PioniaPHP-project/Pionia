<?php

namespace Pionia\Porm\Driver;

use PDO;
use Pionia\Base\WebApplication;

interface DatabaseDriverInterface
{
    /**
     * Returns the PDO instance
     * @return PDO|null
     */
    public function getPdo(): ?PDO;

    /**
     * Returns the DSN
     * @return ?string
     */
    public function getDsn(): ?string;

    /**
     * Returns the database type we are connecting to
     * @return string
     */
    public function getType(): string;

    /**
     * Returns the database table prefix we should use
     * @return ?string
     */
    public function getPrefix(): ?string;

    /**
     * Sets whether we are in test mode
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * Tell the core if we should log queries
     */
    public function isLogging(): bool;

    /**
     * Establishes the database connection
     * @param null|string|array|PDO $connection
     * @return static
     */
    public static function connect(null|string|array|PDO $connection = 'default'): static;
}
