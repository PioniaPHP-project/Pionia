<?php

namespace Pionia\TestSuite;

use Exception;
use PHPUnit\Framework\TestCase;
use Pionia\core\Pionia;
use Pionia\exceptions\DatabaseException;
use Porm\core\Database;

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/../../');
}

if (!defined('SETTINGS')) {
    define('SETTINGS', BASEPATH . 'settings.ini');
}
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
    private ?Database $connection;

    /**
     * @throws DatabaseException|Exception
     */
    protected function setUp(): void
    {
        $this->pionia = new Pionia();
        $this->connection = Database::use();
    }

    public function getPionia(): Pionia
    {
        return $this->pionia;
    }

    public function getConnection(): Database
    {
        return $this->connection;
    }

    protected function tearDown(): void
    {
        $this->connection = null;
        $this->pionia = null;
    }
}