<?php

namespace Pionia\TestSuite;

use PHPUnit\Framework\TestCase;
use Pionia\core\config\CoreKernel;
use Pionia\core\Pionia;
use Pionia\database\Connector;
use Pionia\exceptions\DatabaseException;

define('BASEPATH', __DIR__.'/../../');

define('SETTINGS', BASEPATH.'settings.ini');
/**
 * Provides a context aware test suite.
 *
 * This test suit initialises and establishes a connection to the database.
 * It also loads the settings from the application's settings.ini.
 * It should be the preferred choice if tests target context-aware modules in the application.
 *
 * @property $connection The current connection to the server
 * @property $pionia The current core app instance, use this to access settings if needed
 *
 */
class ContextAwareTestCase extends TestCase
{
    private  Pionia | null $pionia;
    private \PDO | null $connection;

    /**
     * @throws DatabaseException
     */
    protected function setUp(): void
    {
        $this->pionia = new Pionia();
        $this->connection = Connector::connect();
    }

    public function getPionia(): Pionia
    {
        return $this->pionia;
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    protected function tearDown(): void
    {
        $this->connection = null;
        $this->pionia = null;
    }
}