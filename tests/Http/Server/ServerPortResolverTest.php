<?php

namespace Http\Server;

use Pionia\Http\Server\ServerPortResolver;
use Pionia\TestSuite\PioniaTestCase;

class ServerPortResolverTest extends PioniaTestCase
{
    public function testDefaultPortFromEnvironment(): void
    {
        $this->assertSame(8003, (new ServerPortResolver())->resolve());
        $this->assertSame(8003, serverPort());
    }

    public function testCliOverrideWins(): void
    {
        $this->assertSame(9100, (new ServerPortResolver())->resolve(9100));
        $this->assertSame(9100, serverPort(9100));
    }

    public function testEmptyEnvironmentUsesFrameworkDefault(): void
    {
        $this->assertSame(
            ServerPortResolver::DEFAULT_PORT,
            (new ServerPortResolver())->resolve(null),
        );
    }
}
