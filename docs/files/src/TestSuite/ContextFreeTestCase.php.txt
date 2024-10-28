<?php

namespace Pionia\TestSuite;

use PHPUnit\Framework\TestCase;
use Pionia\Core\Pionia;

define('BASEPATH', __DIR__.'/../../');

define('SETTINGS', BASEPATH.'database.ini');
/**
 * This is for testing small chunk of your codebase.
 *
 * It only has access to the settings from your database.ini, but has no access to your database
 */
class ContextFreeTestCase extends TestCase
{
    /**
     * @var Pionia|null The core app instance
     */
    private  Pionia | null $pionia;

    protected function setUp(): void
    {
        $this->pionia = new Pionia();
    }

    public function getPionia(): Pionia
    {
        return $this->pionia;
    }



    protected function tearDown(): void
    {
        $this->pionia = null;
    }
}
